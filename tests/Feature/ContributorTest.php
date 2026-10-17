<?php

namespace Tests\Feature;

use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\Recordings;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContributorTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(LugandaCourseSeeder::class);
        Storage::fake('public');
        $this->reviewer = User::factory()->create(['name' => 'Nalongo']);
        $this->reviewer->forceFill(['is_reviewer' => true])->save();
    }

    public function test_only_contributors_reach_the_tools(): void
    {
        $learner = User::factory()->create();
        foreach (['/studio', '/studio/script', '/studio/upload', '/check'] as $url) {
            $this->actingAs($learner)->get($url)->assertForbidden();
            $this->actingAs($this->reviewer)->get($url)->assertOk();
        }
        $this->actingAs($this->reviewer)->get('/admin')->assertForbidden();
        $this->post('/logout');
        $this->get('/studio')->assertRedirect('/login');
    }

    public function test_studio_lists_phrases_in_course_order(): void
    {
        $res = $this->actingAs($this->reviewer)->get('/studio')->assertOk();
        $res->assertSeeInOrder(['Oli otya?', 'Bulungi', 'Wasuze otya?', 'Nnyabo']);
        $p = Phrase::where('luganda', 'Oli otya?')->first();
        $this->assertSame('P'.str_pad((string) $p->id, 4, '0', STR_PAD_LEFT), $p->code);
    }

    public function test_studio_saves_normal_and_slow_recordings(): void
    {
        $phrase = Phrase::where('luganda', 'Oli otya?')->first();
        $this->actingAs($this->reviewer)
            ->post("/studio/phrase/{$phrase->id}", ['audio' => UploadedFile::fake()->create('take.webm', 30, 'audio/webm')], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['saved' => 'audio_path']);
        $this->actingAs($this->reviewer)
            ->post("/studio/phrase/{$phrase->id}", ['audio' => UploadedFile::fake()->create('take.m4a', 30, 'audio/mp4'), 'slow' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['saved' => 'audio_slow_path']);

        $phrase->refresh();
        Storage::disk('public')->assertExists($phrase->audio_path);
        Storage::disk('public')->assertExists($phrase->audio_slow_path);
        $this->assertStringStartsWith("audio/phrases/{$phrase->code}-oli-otya-", $phrase->audio_path);
        $this->assertStringContainsString('-slow-', $phrase->audio_slow_path);
        $this->assertSame($this->reviewer->id, $phrase->recorded_by);

        // Re-recording replaces the take in use, but the old file is kept as history.
        $old = $phrase->audio_path;
        $this->actingAs($this->reviewer)->post("/studio/phrase/{$phrase->id}", ['audio' => UploadedFile::fake()->create('again.webm', 30, 'audio/webm')], ['Accept' => 'application/json']);
        Storage::disk('public')->assertExists($old);
        $this->assertNotSame($old, $phrase->fresh()->audio_path);
        $this->assertSame(1, $phrase->fresh()->currentClips->where('slot', 'normal')->count());

        $word = Word::first();
        $this->actingAs($this->reviewer)->post("/studio/word/{$word->id}", ['audio' => UploadedFile::fake()->create('w.webm', 10, 'audio/webm')], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull($word->fresh()->audio_path);

        $this->actingAs($this->reviewer)->post("/studio/phrase/{$phrase->id}", ['audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_file_names_are_matched_by_code(): void
    {
        $r = app(Recordings::class);
        $p7 = Phrase::where('luganda', 'Wasuze otya?')->first();
        $id = $p7->id;
        $this->assertTrue($r->matchFilename("{$p7->code}-wasuze-otya.mp3")['model']->is($p7));
        $this->assertFalse($r->matchFilename("{$p7->code}-wasuze-otya.mp3")['slow']);
        $this->assertTrue($r->matchFilename("p{$id} slow.m4a")['model']->is($p7));
        $this->assertTrue($r->matchFilename("p{$id} slow.m4a")['slow']);
        $this->assertTrue($r->matchFilename("P_{$id}-mpola-pole.ogg")['slow']);
        $this->assertInstanceOf(Word::class, $r->matchFilename(Word::first()->code.'.wav')['model']);
        $this->assertNull($r->matchFilename('P99999.mp3'));
        $this->assertNull($r->matchFilename('voice-note-12.mp3'));
        $this->assertNull($r->matchFilename('P12345abc.mp3') ?? null);
    }

    public function test_bulk_upload_attaches_files_and_reports_misses(): void
    {
        $send = fn (string $name, string $mime = 'audio/mpeg') => $this->actingAs($this->reviewer)
            ->post('/studio/upload', ['file' => UploadedFile::fake()->create($name, 20, $mime)], ['Accept' => 'application/json']);

        $p = Phrase::where('luganda', 'Oli otya?')->first();
        $w = Word::where('luganda', 'amazzi')->first();
        $send("{$p->code}-oli-otya.mp3")->assertOk()->assertJson(['code' => $p->code, 'luganda' => 'Oli otya?', 'slow' => false]);
        $send("{$p->code}-oli-otya-slow.mp3")->assertOk()->assertJson(['slow' => true]);
        $send("w{$w->id}.m4a", 'audio/mp4')->assertOk()->assertJson(['code' => $w->code]);
        $send('hello.mp3')->assertUnprocessable()->assertJsonFragment(['message' => 'No phrase or word matches this file name. Start the name with its code, e.g. P0007-oli-otya.mp3.']);
        $send("{$p->code}.txt", 'text/plain')->assertUnprocessable();

        $p->refresh();
        $this->assertNotNull($p->audio_path);
        $this->assertNotNull($p->audio_slow_path);
    }

    public function test_script_page_and_csv(): void
    {
        $first = Phrase::where('luganda', 'Oli otya?')->first();
        $second = Phrase::where('luganda', 'Bulungi')->first();
        $this->actingAs($this->reviewer)->get('/studio/script')->assertOk()
            ->assertSee($first->code)->assertSee('Oli otya?')->assertSee('Stage 1 · Okulamusa · Greetings')->assertSee('Proverbs');

        $first->update(['audio_path' => 'audio/phrases/x.mp3']);
        $this->actingAs($this->reviewer)->get('/studio/script?missing=1')->assertOk()
            ->assertDontSee('font-mono font-bold">'.$first->code.'<', false)
            ->assertSee('font-mono font-bold">'.$second->code.'<', false);

        $csv = $this->actingAs($this->reviewer)->get('/studio/script?format=csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFcode,group,luganda", $csv);
        $this->assertStringContainsString("{$second->code},\"Stage 1 · Okulamusa · Greetings\",Bulungi,\"Fine / Good\"", $csv);
        $this->assertStringContainsString("{$second->code}-bulungi.mp3,{$second->code}-bulungi-slow.mp3", $csv);
    }

    public function test_reviewers_approve_correct_and_flag(): void
    {
        $phrase = Phrase::where('luganda', 'Ki kati?')->first();
        $this->actingAs($this->reviewer)->get('/check')->assertOk()->assertSee('Ki kati?');

        // Approve with a correction.
        $this->actingAs($this->reviewer)->postJson("/check/phrase/{$phrase->id}", [
            'action' => 'approve', 'luganda' => 'Kiki kati?', 'english' => "What's up?", 'literal' => 'What now?', 'notes' => 'Only with friends.',
        ])->assertOk()->assertJson(['is_reviewed' => true]);
        $phrase->refresh();
        $this->assertSame('Kiki kati?', $phrase->luganda);
        $this->assertSame('Only with friends.', $phrase->notes);
        $this->assertSame($this->reviewer->id, $phrase->reviewed_by);
        $this->actingAs($this->reviewer)->get('/check')->assertDontSee('Kiki kati?');
        $this->actingAs($this->reviewer)->get('/check?show=approved')->assertSee('Kiki kati?');

        // Flagging needs a note and shows up for the admin.
        $other = Phrase::where('luganda', 'Gyendi')->first();
        $payload = ['action' => 'flag', 'luganda' => 'Gyendi', 'english' => "I'm fine"];
        $this->actingAs($this->reviewer)->postJson("/check/phrase/{$other->id}", $payload)->assertUnprocessable();
        $this->actingAs($this->reviewer)->postJson("/check/phrase/{$other->id}", $payload + ['note' => 'Usually a reply to "Ki kati?", not "Oli otya?"'])
            ->assertOk()->assertJson(['is_reviewed' => false]);
        $this->assertTrue($other->fresh()->isFlagged());
        $this->actingAs($this->reviewer)->get('/check?show=flagged')->assertSee('Usually a reply to');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/phrases?filter=flagged')->assertSee('Gyendi')->assertDontSee('Bulungi');
        $this->actingAs($admin)->get("/admin/phrases/{$other->id}/edit")->assertSee('Flagged by Nalongo');

        // Admin ticking "checked" clears the flag and records the approval.
        $this->actingAs($admin)->put("/admin/phrases/{$other->id}", ['luganda' => 'Gyendi', 'english' => "I'm fine", 'is_reviewed' => '1']);
        $other->refresh();
        $this->assertTrue($other->is_reviewed);
        $this->assertNull($other->review_note);
        $this->assertSame($admin->id, $other->reviewed_by);

        // Word entries too.
        $word = Word::where('luganda', 'amazzi')->first();
        $this->actingAs($this->reviewer)->postJson("/check/word/{$word->id}", ['action' => 'approve', 'luganda' => 'amazzi', 'english' => 'water'])->assertOk();
        $this->assertTrue($word->fresh()->is_reviewed);
    }

    public function test_admin_manages_reviewers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $speaker = User::factory()->create(['name' => 'Ssekandi']);
        $guest = User::factory()->create();
        $guest->forceFill(['is_guest' => true])->save();

        $this->actingAs($admin)->get('/admin/people')->assertOk()->assertSee('Ssekandi')->assertSee('Nalongo');
        $this->actingAs($admin)->patch("/admin/people/{$speaker->id}", ['is_reviewer' => 1])->assertRedirect();
        $this->assertTrue($speaker->fresh()->is_reviewer);
        $this->actingAs($speaker->fresh())->get('/studio')->assertOk();
        $this->actingAs($admin)->patch("/admin/people/{$guest->id}", ['is_reviewer' => 1])->assertStatus(422);

        $this->artisan('app:make-reviewer', ['email' => $admin->email])->assertSuccessful();
        $this->artisan('app:make-reviewer', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
