<?php

namespace Tests\Feature;

use App\Models\AudioClip;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\AudioResolver;
use App\Support\LegacyAudioImport;
use App\Support\LugandaText;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['is_reviewer' => true])->save();
        $this->learner = User::factory()->create();
    }

    private function phrase(string $lg): Phrase
    {
        return Phrase::where('luganda', $lg)->firstOrFail();
    }

    /** An approved phrase or word with one approved recording. */
    private function withApprovedAudio(Phrase|Word $item, string $source = 'human', string $slot = 'normal'): AudioClip
    {
        $path = "audio/test/{$item->id}-{$slot}-{$source}.webm";
        Storage::disk('public')->put($path, 'x');
        $clip = $item->audioClips()->create([
            'slot' => $slot, 'source' => $source, 'provider' => $source === 'generated' ? 'sunbird' : null,
            'text_snapshot' => $item->luganda, 'text_key' => LugandaText::key($item->luganda), 'path' => $path,
        ]);
        $item->approve($this->reviewer);
        $clip->approve($this->reviewer);

        return $clip;
    }

    private function heardBy(User $user, Phrase|Word $item, string $slot = 'normal'): ?string
    {
        $this->actingAs($user);
        app(AudioResolver::class)->flush();

        return app(AudioResolver::class)->url($item->fresh(), $slot);
    }

    public function test_text_keys_ignore_case_and_punctuation_only(): void
    {
        $this->assertSame(LugandaText::key('Oli otya?'), LugandaText::key('  oli   OTYA '));
        $this->assertSame(LugandaText::key('Ŋŋ’aano!'), LugandaText::key("ŋŋ'aano"));
        $this->assertNotSame(LugandaText::key('Oli otya?'), LugandaText::key('Oli otyo?'));
    }

    public function test_old_recordings_are_imported_carefully(): void
    {
        $oli = $this->phrase('Oli otya?');
        $gyendi = $this->phrase('Gyendi');
        $bulungi = $this->phrase('Bulungi');
        DB::table('phrases')->where('id', $oli->id)->update(['audio_path' => 'audio/phrases/oli.webm', 'audio_male_path' => 'audio/phrases/oli-m.webm', 'is_reviewed' => true]);
        DB::table('phrases')->where('id', $bulungi->id)->update(['audio_path' => 'audio/phrases/bul.webm']);
        DB::table('phrases')->where('id', $gyendi->id)->update(['audio_path' => 'audio/phrases/gy.webm', 'audio_male_path' => 'audio/phrases/gy-m.webm', 'is_reviewed' => true]);
        $word = Word::where('luganda', 'amazzi')->first();
        DB::table('words')->where('id', $word->id)->update(['audio_path' => 'audio/words/amazzi.webm']);

        $this->assertSame(6, LegacyAudioImport::run());
        $this->assertSame(0, LegacyAudioImport::run(), 'safe to run again');

        $status = fn ($item, $slot) => $item->fresh()->currentClips->firstWhere('slot', $slot)?->status;
        $this->assertSame('approved', $status($oli, 'normal'), 'approved phrase: its recordings were approved with it');
        $this->assertSame('approved', $status($oli, 'male'));
        $this->assertSame('mukasa', $oli->fresh()->currentClips->firstWhere('slot', 'male')->voice);
        $this->assertSame('pending', $status($bulungi, 'normal'));
        $this->assertSame('pending', $status($word, 'normal'));
        // The known mix-up: flagged for a person to listen to, never silently replaced.
        $this->assertSame('flagged', $status($gyendi, 'normal'));
        $this->assertSame('flagged', $status($gyendi, 'male'));
        $this->assertStringContainsString('Gyendi', $gyendi->fresh()->currentClips->first()->review_note);
        $this->assertSame('audio/phrases/gy-m.webm', $gyendi->fresh()->audio_male_path, 'old columns untouched');

        $this->artisan('app:import-legacy-audio')->expectsOutput('Nothing new to import.')->assertSuccessful();
    }

    public function test_respelling_sends_approved_recordings_back_for_checking(): void
    {
        $oli = $this->phrase('Oli otya?');
        $clip = $this->withApprovedAudio($oli);
        $this->assertNotNull($this->heardBy($this->learner, $oli));

        $oli->fresh()->update(['luganda' => 'oli otya']); // only case and punctuation: sounds the same
        $this->assertSame('approved', $clip->fresh()->status);

        $oli->fresh()->update(['luganda' => 'Oli otyanno?']);
        $this->assertSame('pending', $clip->fresh()->status);
        $this->assertStringContainsString('The text changed', $clip->fresh()->review_note);
        $oli->fresh()->approve($this->reviewer);
        $this->assertNull($this->heardBy($this->learner, $oli));

        $this->actingAs($this->reviewer)->get('/check?show=audio')->assertSee('Recorded for “Oli otya?”', false);
        $clip->fresh()->approve($this->reviewer); // a reviewer confirms it matches
        $this->assertSame('Oli otyanno?', $clip->fresh()->text_snapshot);
        $this->assertNotNull($this->heardBy($this->learner, $oli));
    }

    public function test_the_same_text_is_recorded_once(): void
    {
        $emuPhrase = $this->phrase('Emu');
        $emuWord = Word::where('luganda', 'emu')->firstOrFail();
        $clip = $this->withApprovedAudio($emuPhrase);
        $emuWord->approve($this->reviewer);

        $this->assertSame($clip->url(), $this->heardBy($this->learner, $emuWord), 'dictionary "emu" uses the lesson recording of "Emu"');
        $this->actingAs($this->learner)->get('/dictionary?q=one')->assertSee(basename($clip->path), false);

        $emuWord->fresh()->flag($this->reviewer, 'check');
        $this->assertNull($this->heardBy($this->learner, $emuWord), 'still needs its own text approved');
    }

    public function test_human_recordings_are_preferred_over_generated(): void
    {
        $oli = $this->phrase('Oli otya?');
        $generated = $this->withApprovedAudio($oli, 'generated');
        $this->assertSame($generated->url(), $this->heardBy($this->learner, $oli));

        $human = $this->withApprovedAudio($oli, 'human');
        $this->assertSame($human->url(), $this->heardBy($this->learner, $oli));
        $this->assertTrue($generated->fresh()->is_current, 'a generated take stays available as a backup');
    }

    public function test_voices_off_silences_everyone(): void
    {
        $oli = $this->phrase('Oli otya?');
        $this->withApprovedAudio($oli);
        config(['luganda.voice' => false]);
        view()->share('voiceOn', false);

        $this->assertNull($this->heardBy($this->reviewer, $oli));
        $this->actingAs($this->reviewer)->get('/check')->assertOk()->assertDontSee('Recordings to check')->assertDontSee('no audio');
    }

    public function test_speaker_buttons_show_missing_and_pending_to_reviewers_only(): void
    {
        $word = Word::where('luganda', 'amazzi')->first();
        $this->actingAs($this->reviewer)->get('/dictionary?q=water')->assertSee('no audio');
        $this->actingAs($this->learner)->get('/dictionary?q=water')->assertDontSee('no audio')->assertDontSee('speak-btn');

        $this->withApprovedAudio($word);
        $this->actingAs($this->learner)->get('/dictionary?q=water')
            ->assertSee('speak-btn')->assertSee('Listen: amazzi')->assertDontSee('pending');
    }

    public function test_admin_uploads_become_pending_recordings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $p = $this->phrase('Bulungi');
        $this->actingAs($admin)->put(route('admin.phrases.update', $p), [
            'luganda' => 'Bulungi', 'english' => $p->english, 'is_reviewed' => '1',
            'audio' => UploadedFile::fake()->create('b.mp3', 20, 'audio/mpeg'),
        ])->assertRedirect();
        $clip = $p->fresh()->currentClips->first();
        $this->assertSame('pending', $clip->status);
        $this->assertTrue($p->fresh()->is_reviewed);
        $this->actingAs($admin)->get(route('admin.phrases.edit', $p))->assertSee('waiting to be checked');

        $this->actingAs($admin)->put(route('admin.phrases.update', $p), [
            'luganda' => 'Bulungi', 'english' => $p->english, 'is_reviewed' => '1', 'remove_audio' => '1',
        ]);
        $this->assertTrue($p->fresh()->currentClips->isEmpty());
        $this->assertNull($p->fresh()->audio_path);
        Storage::disk('public')->assertExists($clip->path); // kept as history
    }

    public function test_deleting_a_phrase_removes_its_recordings(): void
    {
        $p = $this->phrase('Bulungi');
        $clip = $this->withApprovedAudio($p);
        $p->fresh()->delete();
        $this->assertDatabaseMissing('audio_clips', ['id' => $clip->id]);
        Storage::disk('public')->assertMissing($clip->path);
    }

    public function test_audio_report_lists_every_state(): void
    {
        $this->withApprovedAudio($this->phrase('Emu'));
        $this->artisan('app:audio-report', ['--lesson' => 'ennamba-1-5'])
            ->expectsOutputToContain('Ennamba 1–5')
            ->expectsOutputToContain('Recordings in use: 1 approved (human)')
            ->assertSuccessful();
    }
}
