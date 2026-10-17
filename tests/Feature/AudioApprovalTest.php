<?php

namespace Tests\Feature;

use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use App\Services\LessonPlan;
use App\Support\LegacyAudioImport;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Learners hear a recording only when the Luganda text AND that recording are
 * both approved. The text and each recording are checked separately.
 */
class AudioApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private User $reviewer;

    private Phrase $phrase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(LugandaCourseSeeder::class);
        $this->learner = User::factory()->create();
        $this->reviewer = User::factory()->create();
        $this->reviewer->forceFill(['is_reviewer' => true])->save();

        $this->phrase = Phrase::where('luganda', 'Oli otya?')->first();
        $this->record($this->phrase, 'a.webm');
    }

    private function record(Phrase|Word $item, string $name = 'take.webm'): void
    {
        $type = $item instanceof Word ? 'word' : 'phrase';
        $this->actingAs($this->reviewer)->post("/studio/{$type}/{$item->id}",
            ['audio' => UploadedFile::fake()->create($name, 20, 'audio/webm')], ['Accept' => 'application/json'])->assertOk();
        $item->refresh();
    }

    private function listenSteps(User $as): int
    {
        $this->actingAs($as);

        return collect((new LessonPlan)->build(Lesson::where('slug', 'okulamusa')->first()))->where('type', 'listen')->count();
    }

    private function clip(): AudioClip
    {
        return $this->phrase->fresh()->currentClips->sortByDesc('id')->first();
    }

    private function reviewClip(AudioClip $clip, string $action, ?string $note = null)
    {
        return $this->actingAs($this->reviewer)->postJson(route('check.audio', $clip), ['action' => $action, 'note' => $note]);
    }

    public function test_learners_do_not_hear_unapproved_recordings(): void
    {
        $this->phrase->approve($this->reviewer); // the text is fine, the recording isn't checked yet
        $file = basename($this->phrase->audio_path);
        $this->assertSame('pending', $this->clip()->status);

        $lessonUrl = route('lessons.show', Lesson::where('slug', 'okulamusa')->first());
        $this->actingAs($this->reviewer)->get($lessonUrl)->assertSee($file, false); // proves the check below can see it
        $this->actingAs($this->learner)->get($lessonUrl)->assertOk()->assertDontSee($file, false);
        $this->assertSame(0, $this->listenSteps($this->learner));

        // Reviewers and admins still hear it so they can check it.
        $this->actingAs($this->reviewer)->get('/check?show=audio')->assertSee($file, false)->assertSee('Waiting to be checked');
        $this->assertSame(1, $this->listenSteps($this->reviewer));
    }

    public function test_approving_text_and_recording_makes_it_audible_everywhere(): void
    {
        $this->actingAs($this->reviewer)->postJson("/check/phrase/{$this->phrase->id}", [
            'action' => 'approve', 'luganda' => 'Oli otya?', 'english' => 'How are you?',
        ])->assertOk();
        $this->assertSame(0, $this->listenSteps($this->learner), 'text approved, recording not yet');

        $this->reviewClip($this->clip(), 'approve')->assertOk()->assertJson(['status' => 'approved']);
        $file = basename($this->phrase->fresh()->audio_path);

        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $this->actingAs($this->learner)->get(route('lessons.show', $lesson))->assertSee($file, false);
        $this->assertSame(1, $this->listenSteps($this->learner));

        $this->actingAs($this->learner)->postJson(route('lessons.complete', $lesson), ['score' => 100]);
        $this->actingAs($this->learner)->get('/phrasebook')->assertSee($file, false);
        $this->actingAs($this->learner)->get('/games/memory')->assertSee($file, false);
        $this->actingAs($this->learner)->get('/')->assertSee($file, false);
    }

    public function test_an_approved_recording_is_silent_while_the_text_is_unapproved(): void
    {
        $this->reviewClip($this->clip(), 'approve')->assertOk();
        $this->phrase->flag($this->reviewer, 'Spelling?');
        $this->assertSame(0, $this->listenSteps($this->learner));
    }

    public function test_a_new_take_waits_for_its_own_check_and_the_old_one_keeps_playing(): void
    {
        $this->phrase->approve($this->reviewer);
        $first = $this->clip();
        $this->reviewClip($first, 'approve');
        $this->actingAs($this->learner);
        $this->assertStringContainsString(basename($first->path), $this->phrase->fresh()->audio_url);

        $this->actingAs($this->reviewer)->post('/studio/upload',
            ['file' => UploadedFile::fake()->create("{$this->phrase->code}.mp3", 20, 'audio/mpeg')], ['Accept' => 'application/json'])->assertOk();

        $fresh = $this->phrase->fresh();
        $this->assertTrue($fresh->is_reviewed, 'a new recording no longer un-approves the text');
        $second = $this->clip();
        $this->assertSame('pending', $second->status);
        $this->actingAs($this->learner);
        $this->assertStringContainsString(basename($first->path), $fresh->audio_url, 'learners still hear the approved take');

        $this->reviewClip($second, 'approve');
        $this->actingAs($this->learner);
        $this->assertStringContainsString(basename($second->path), $this->phrase->fresh()->audio_url);
        $this->assertFalse($first->fresh()->is_current, 'the old take is retired but kept');
        Storage::disk('public')->assertExists($first->path);
    }

    public function test_flagged_and_rejected_recordings(): void
    {
        $this->phrase->approve($this->reviewer);
        $clip = $this->clip();

        $this->reviewClip($clip, 'flag')->assertUnprocessable(); // a flag needs a reason
        $this->reviewClip($clip, 'flag', 'Background noise')->assertOk()->assertJson(['status' => 'flagged']);
        $this->actingAs($this->learner);
        $this->assertNull($this->phrase->fresh()->audio_url);
        $this->actingAs($this->reviewer)->get('/check?show=audio')->assertSee('Background noise');

        $this->reviewClip($clip, 'reject', 'Wrong phrase')->assertOk()->assertJson(['status' => 'rejected', 'is_current' => false]);
        $this->actingAs($this->reviewer);
        $this->assertNull($this->phrase->fresh()->audio_url, 'nobody hears a rejected recording');
        $this->reviewClip($clip, 'approve')->assertNotFound();
        $this->actingAs($this->learner)->postJson(route('check.audio', $clip), ['action' => 'approve'])->assertForbidden();
    }

    public function test_admins_can_delete_flagged_recordings_for_good(): void
    {
        $clip = $this->clip();
        $path = $clip->path;
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->deleteJson(route('check.audio.destroy', $clip))->assertUnprocessable(); // not flagged yet
        $this->reviewClip($clip, 'flag', 'Says the wrong word');
        $this->actingAs($this->reviewer)->deleteJson(route('check.audio.destroy', $clip))->assertForbidden(); // reviewers can't
        $this->actingAs($admin)->get('/check?show=audio')->assertSee('🗑 Delete');
        $this->actingAs($this->reviewer)->get('/check?show=audio')->assertDontSee('🗑 Delete');

        $this->actingAs($admin)->deleteJson(route('check.audio.destroy', $clip))->assertOk()->assertJson(['deleted' => true]);
        $this->assertDatabaseMissing('audio_clips', ['id' => $clip->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->phrase->fresh()->audio_path, 'old column cleared, so a re-import cannot bring it back');
        $this->assertSame(0, LegacyAudioImport::run());
    }

    public function test_dictionary_audio_follows_the_same_rule(): void
    {
        $word = Word::where('luganda', 'amazzi')->first();
        $this->record($word, 'w.webm');
        $file = basename($word->fresh()->audio_path);

        $this->actingAs($this->reviewer)->get('/dictionary?q=water')->assertSee($file, false)->assertSee('pending');
        $this->actingAs($this->learner)->get('/dictionary?q=water')->assertSee('amazzi')->assertDontSee($file, false);
        $word->fresh()->approve($this->reviewer);
        $this->actingAs($this->learner)->get('/dictionary?q=water')->assertDontSee($file, false);
        $word->fresh()->approveRecordings($this->reviewer);
        $this->actingAs($this->learner)->get('/dictionary?q=water')->assertSee($file, false)->assertDontSee('pending');
        $this->post('/logout');
        $this->get('/dictionary?q=water')->assertSee($file, false); // visitors too, once approved
    }
}
