<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\AudioGenerator;
use App\Services\LessonPlan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The default: learners hear Sunbird's voice straight away, before anyone checks it. */
class PlayUncheckedAudioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->learner = User::factory()->create();
        config([
            'luganda.play_unchecked' => true,
            'luganda.tts.default' => 'sunbird',
            'luganda.tts.auto' => true,
            'luganda.tts.daily_limit' => 500,
            'luganda.tts.providers.sunbird.token' => 'test-token',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'api.sunbird.ai/tasks/voice/speakers*' => Http::response(['speakers' => ['salt_lug_0001', 'waxal_lug_0002']]),
            'api.sunbird.ai/tasks/audio/speech' => Http::response(['audio_url' => 'https://storage.googleapis.com/sb/x.wav', 'duration_seconds' => 1]),
            'storage.googleapis.com/*' => Http::response('RIFF-fake', 200, ['Content-Type' => 'audio/wav']),
        ]);
    }

    private function phrase(string $lg): Phrase
    {
        return Phrase::where('luganda', $lg)->firstOrFail();
    }

    public function test_generated_audio_plays_at_once_even_for_draft_text(): void
    {
        $emu = $this->phrase('Emu');
        $this->assertFalse($emu->is_reviewed, 'seeded Luganda is still a draft');
        $clip = app(AudioGenerator::class)->generate($emu)['clip'];
        $this->assertSame('pending', $clip->status);

        $this->actingAs($this->learner);
        $this->assertSame($clip->url(), $emu->fresh()->audio_url);
        $lesson = Lesson::where('slug', 'ennamba-1-5')->first();
        $lesson->forceFill(['mode' => 'auto'])->save(); // the automatic version of the lesson (the pilot has its own listening step)
        $this->assertGreaterThan(0, collect((new LessonPlan)->build($lesson))->where('type', 'listen')->count(), 'listening questions come back');

        // The dictionary's "emu" shares it, marked as a computer voice.
        $this->actingAs($this->learner)->get('/dictionary?q=one')->assertSee(basename($clip->path), false)->assertSee('Computer voice');
    }

    public function test_flagged_never_plays_and_human_wins(): void
    {
        $p = $this->phrase('Bulungi');
        $gen = app(AudioGenerator::class)->generate($p)['clip'];
        $this->actingAs($this->learner);
        $this->assertSame($gen->url(), $p->fresh()->audio_url);

        $gen->flag($this->admin, 'Sounds wrong');
        $this->actingAs($this->learner);
        $this->assertNull($p->fresh()->audio_url);

        $this->actingAs($this->admin)->post("/studio/phrase/{$p->id}", ['audio' => UploadedFile::fake()->create('b.webm', 10, 'audio/webm')], ['Accept' => 'application/json']);
        $human = $p->fresh()->currentClips->firstWhere('source', 'human');
        $this->actingAs($this->learner);
        $this->assertSame($human->url(), $p->fresh()->audio_url, 'a human recording beats a computer voice');
    }

    public function test_new_and_respelled_words_get_a_voice_automatically(): void
    {
        $this->actingAs($this->admin)->post(route('admin.words.store'), ['kind' => 'word', 'luganda' => 'embwa', 'english' => 'dog'])->assertRedirect();
        $word = Word::where('luganda', 'embwa')->latest('id')->first();
        $this->assertSame('generated', $word->currentClips->first()?->source);

        $p = $this->phrase('Webale');
        $this->actingAs($this->admin)->postJson("/check/phrase/{$p->id}", ['action' => 'save', 'luganda' => 'Weebale', 'english' => $p->english])->assertOk();
        $this->assertTrue($p->fresh()->currentClips->contains(fn ($c) => $c->source === 'generated' && $c->text_snapshot === 'Weebale'));

        // Saving again without changes doesn't generate a second time.
        $count = $p->fresh()->audioClips()->count();
        $this->actingAs($this->admin)->postJson("/check/phrase/{$p->id}", ['action' => 'approve', 'luganda' => 'Weebale', 'english' => $p->english])->assertOk();
        $this->assertSame($count, $p->fresh()->audioClips()->count());
    }

    public function test_automatic_audio_stays_off_with_test_beeps_or_when_switched_off(): void
    {
        config(['luganda.tts.auto' => false]);
        $this->actingAs($this->admin)->post(route('admin.words.store'), ['kind' => 'word', 'luganda' => 'ente', 'english' => 'cow']);
        $this->assertTrue(Word::where('luganda', 'ente')->latest('id')->first()->currentClips->isEmpty());

        config(['luganda.tts.auto' => true, 'luganda.tts.default' => 'fake']);
        $this->actingAs($this->admin)->post(route('admin.words.store'), ['kind' => 'word', 'luganda' => 'embuzi', 'english' => 'goat']);
        $this->assertTrue(Word::where('luganda', 'embuzi')->latest('id')->first()->currentClips->isEmpty());
    }

    public function test_one_command_voices_the_whole_course(): void
    {
        $total = Phrase::count() + Word::count();
        $this->artisan('app:generate-audio', ['--all' => true, '--limit' => 1000, '--pause' => 0])
            ->expectsOutputToContain("{$total} generated.")->assertSuccessful();
        // Running it again finds nothing left to do.
        $this->artisan('app:generate-audio', ['--all' => true, '--limit' => 1000, '--pause' => 0])
            ->expectsOutputToContain('0 generated.')->assertSuccessful();
    }
}
