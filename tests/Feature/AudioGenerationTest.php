<?php

namespace Tests\Feature;

use App\Models\AudioClip;
use App\Models\AudioGenerationRequest;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\AudioGenerator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
        config([
            'luganda.tts.providers.sunbird.token' => 'test-token',
            'luganda.tts.default' => 'sunbird',
            'luganda.tts.daily_limit' => 50,
        ]);
    }

    /** Sunbird as documented: JSON with a signed audio_url, then the file itself. */
    private function fakeSunbird(int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.sunbird.ai/tasks/voice/speakers*' => Http::response(['language' => 'lug', 'speakers' => ['salt_lug_0001', 'waxal_lug_0002', 'waxal_lug_0003'], 'count' => 3]),
            'api.sunbird.ai/tasks/audio/speech' => $status === 200
                ? Http::response(['audio_url' => 'https://storage.googleapis.com/sb/tts/abc.wav?sig=1', 'duration_seconds' => 0.8, 'sample_rate' => 24000, 'request_id' => 'r1', 'model' => 'm', 'platform' => 'p', 'voice' => 'salt_lug_0001', 'usage' => ['input_characters' => 3]])
                : Http::response(['detail' => 'nope'], $status),
            'storage.googleapis.com/*' => Http::response('RIFF....WAVEfmt fake-audio', 200, ['Content-Type' => 'audio/wav']),
        ]);
    }

    private function phrase(string $lg): Phrase
    {
        return Phrase::where('luganda', $lg)->firstOrFail();
    }

    private function gen(): AudioGenerator
    {
        return app(AudioGenerator::class);
    }

    public function test_sunbird_generates_a_pending_recording_and_keeps_a_copy(): void
    {
        $this->fakeSunbird();
        $emu = $this->phrase('Emu');

        $r = $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001', 'normal', $this->admin);

        $this->assertSame('created', $r['status']);
        $clip = $r['clip'];
        $this->assertSame(['generated', 'sunbird', 'salt_lug_0001', 'pending'], [$clip->source, $clip->provider, $clip->voice, $clip->status]);
        $this->assertSame('audio/wav', $clip->mime);
        $this->assertSame(800, $clip->duration_ms);
        $this->assertStringStartsWith("audio/generated/{$emu->code}-emu-sunbird-salt-lug-0001-", $clip->path);
        Storage::disk('public')->assertExists($clip->path);
        $this->assertNull($emu->fresh()->audio_path, 'old "human" columns are not touched');

        Http::assertSent(fn (Request $req) => $req->url() === 'https://api.sunbird.ai/tasks/audio/speech'
            && $req['text'] === 'Emu' && $req['voice'] === 'salt_lug_0001' && $req['language'] === 'lug'
            && $req['response_mode'] === 'url' && $req->hasHeader('Authorization', 'Bearer test-token'));
        $this->assertDatabaseHas('audio_generation_requests', ['owner_id' => $emu->id, 'status' => 'done', 'audio_clip_id' => $clip->id, 'characters' => 3]);
    }

    public function test_learners_hear_generated_audio_only_after_approval(): void
    {
        $this->fakeSunbird();
        $emu = $this->phrase('Emu');
        $emu->approve($this->admin);
        $clip = $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001')['clip'];

        $this->actingAs(User::factory()->create());
        $this->assertNull($emu->fresh()->audio_url);

        $this->actingAs($this->admin)->postJson(route('check.audio', $clip), ['action' => 'approve'])->assertOk();
        $this->actingAs(User::factory()->create());
        $this->assertSame($clip->url(), $emu->fresh()->audio_url);
    }

    public function test_never_generates_the_same_thing_twice(): void
    {
        $this->fakeSunbird();
        $emu = $this->phrase('Emu');
        $this->assertSame('created', $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001')['status']);
        $again = $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001');
        $this->assertSame('skipped', $again['status']);
        $this->assertStringContainsString('Already generated', $again['message']);

        // Another voice is fine (that's how voices are compared) …
        $this->assertSame('created', $this->gen()->generate($emu, 'sunbird', 'waxal_lug_0002')['status']);
        // … and a rejected attempt isn't silently retried.
        $first = AudioClip::where('voice', 'salt_lug_0001')->first();
        $first->reject($this->admin, 'Wrong tone');
        $this->assertStringContainsString('rejected', $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001')['message']);
        // Unless forced.
        $this->assertSame('created', $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001', force: true)['status']);
        Http::assertSentCount(1 + 3 * 2); // voice list once + 3 generations (request + download)
    }

    public function test_skips_items_with_an_approved_human_recording(): void
    {
        $this->fakeSunbird();
        $p = $this->phrase('Bulungi');
        $reviewer = User::factory()->create(['is_admin' => true]);
        $this->actingAs($reviewer)->post("/studio/phrase/{$p->id}", ['audio' => UploadedFile::fake()->create('b.webm', 10, 'audio/webm')], ['Accept' => 'application/json']);
        $p->fresh()->approveRecordings($reviewer);

        $r = $this->gen()->generate($p->fresh(), 'sunbird');
        $this->assertSame('skipped', $r['status']);
        $this->assertStringContainsString('human', $r['message']);
        Http::assertNotSent(fn (Request $req) => str_contains($req->url(), '/tasks/audio/speech'));
    }

    public function test_approving_one_voice_retires_the_other_tries(): void
    {
        $this->fakeSunbird();
        $emu = $this->phrase('Emu');
        $a = $this->gen()->generate($emu, 'sunbird', 'salt_lug_0001')['clip'];
        $b = $this->gen()->generate($emu, 'sunbird', 'waxal_lug_0002')['clip'];
        $b->approve($this->admin);
        $this->assertFalse($a->fresh()->is_current);
        $this->assertTrue($b->fresh()->is_current);
    }

    public function test_daily_limit_and_dry_run(): void
    {
        $this->fakeSunbird();
        config(['luganda.tts.daily_limit' => 2]);
        $this->assertSame('would_create', $this->gen()->generate($this->phrase('Emu'), 'sunbird', dryRun: true)['status']);
        $this->assertSame(0, AudioGenerationRequest::count(), 'a dry run sends nothing');

        $this->gen()->generate($this->phrase('Emu'), 'sunbird');
        $this->gen()->generate($this->phrase('Bbiri'), 'sunbird');
        $r = $this->gen()->generate($this->phrase('Ssatu'), 'sunbird');
        $this->assertSame('limit', $r['status']);
        $this->assertSame(0, $this->gen()->remainingToday());
        // Test beeps don't count.
        $this->assertSame('created', $this->gen()->generate($this->phrase('Ssatu'), 'fake')['status']);
    }

    public function test_failures_are_recorded_not_hidden(): void
    {
        $this->fakeSunbird(401);
        $r = $this->gen()->generate($this->phrase('Emu'), 'sunbird', 'salt_lug_0001');
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('refused the token', $r['message']);
        $this->assertDatabaseHas('audio_generation_requests', ['status' => 'failed']);
        $this->assertSame(0, AudioClip::count());

        config(['luganda.tts.providers.sunbird.token' => null]);
        $this->assertStringContainsString('not set up', $this->gen()->generate($this->phrase('Emu'), 'sunbird')['message']);
        $this->assertSame('failed', $this->gen()->generate($this->phrase('Emu'), 'sunbird', 'no_such_voice')['status']);
    }

    public function test_test_beeps_can_never_be_approved(): void
    {
        $clip = $this->gen()->generate($this->phrase('Emu'), 'fake')['clip'];
        $this->assertSame('audio/wav', $clip->mime);
        $this->assertStringStartsWith('RIFF', Storage::disk('public')->get($clip->path));
        $this->actingAs($this->admin)->postJson(route('check.audio', $clip), ['action' => 'approve'])->assertUnprocessable();
        $this->assertSame('pending', $clip->fresh()->status);
    }

    public function test_admin_page_and_one_by_one_generation(): void
    {
        $this->fakeSunbird();
        $this->actingAs($this->admin)->get('/admin/audio')->assertOk()
            ->assertSee('Generate audio')->assertSee('Ennamba 1–5')->assertSee('waxal_lug_0002')->assertSee('50 generations left today');

        $emu = $this->phrase('Emu');
        $this->actingAs($this->admin)->postJson('/admin/audio/generate', ['type' => 'phrase', 'id' => $emu->id, 'provider' => 'sunbird', 'voice' => 'salt_lug_0001'])
            ->assertOk()->assertJson(['status' => 'created', 'voice' => 'salt_lug_0001', 'remaining' => 49]);
        $word = Word::where('luganda', 'emu')->first();
        $this->actingAs($this->admin)->postJson('/admin/audio/generate', ['type' => 'word', 'id' => $word->id, 'provider' => 'sunbird'])->assertOk();

        $this->actingAs($this->admin)->get('/check?show=audio')->assertSee('🤖 Generated · sunbird · salt_lug_0001');

        $reviewer = User::factory()->create(['is_reviewer' => true]);
        $this->actingAs($reviewer)->get('/admin/audio')->assertForbidden();
        $this->actingAs($reviewer)->postJson('/admin/audio/generate', ['type' => 'phrase', 'id' => $emu->id, 'provider' => 'sunbird'])->assertForbidden();
    }

    public function test_command_generates_a_lesson_with_a_limit(): void
    {
        $this->fakeSunbird();
        $this->artisan('app:generate-audio', ['--lesson' => ['ennamba-1-5'], '--dry-run' => true])
            ->expectsOutputToContain('[dry run] Sunbird AI · salt_lug_0001')->expectsOutputToContain('5 would be generated.')->assertSuccessful();
        $this->assertSame(0, AudioClip::count());

        $this->artisan('app:generate-audio', ['--lesson' => ['ennamba-1-5'], '--limit' => 2])
            ->expectsOutputToContain('2 generated.')->assertSuccessful();
        $this->assertSame(2, AudioClip::where('source', 'generated')->count());

        $this->artisan('app:generate-audio', ['--code' => ['P9999']])->expectsOutputToContain('No phrase or word')->assertFailed();
        $this->artisan('app:tts-voices')->expectsOutputToContain('waxal_lug_0003')->assertSuccessful();
    }
}
