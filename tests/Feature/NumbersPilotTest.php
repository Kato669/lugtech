<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\User;
use App\Services\LessonPlan;
use App\Support\LugandaText;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LugandaNumbersPilotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 5: Ennamba 1–5 as the first interactive classroom lesson. */
class NumbersPilotTest extends TestCase
{
    use RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class); // includes the pilot
        $this->lesson = Lesson::where('slug', 'ennamba-1-5')->firstOrFail();
    }

    private function steps(): array
    {
        $this->actingAs(User::factory()->create());

        return (new LessonPlan(3))->build($this->lesson->fresh());
    }

    public function test_the_pilot_is_a_complete_classroom_lesson(): void
    {
        $this->assertTrue($this->lesson->isScripted());
        $this->artisan('app:lesson-check', ['slug' => 'ennamba-1-5'])->expectsOutputToContain('Plays as 18 steps')->assertSuccessful();

        $types = array_column($this->steps(), 'type'); // "listen" waits for audio
        $this->assertSame(['intro', 'learn', 'learn', 'find', 'learn', 'choose', 'learn', 'learn', 'reward',
            'find', 'sequence', 'recall', 'sequence', 'match', 'dialogue', 'converse', 'recall', 'reward'], $types);
    }

    public function test_each_number_is_taught_with_cowries_a_numeral_and_counting(): void
    {
        $teach = array_values(array_filter($this->steps(), fn ($s) => $s['type'] === 'learn'));
        $this->assertSame(['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano'], array_map(fn ($s) => $s['phrase']['luganda'], $teach));
        foreach ($teach as $n => $step) {
            $this->assertSame((string) ($n + 1), $step['figure']);
            $this->assertSame(['name' => 'cowrie', 'count' => $n + 1], $step['picture']);
            $this->assertTrue($step['count_along']);
            $this->assertNotEmpty($step['say']);
        }
    }

    public function test_questions_feedback_and_the_counting_game(): void
    {
        $steps = $this->steps();
        $find = collect($steps)->firstWhere('type', 'find');
        $this->assertSame(['Find Bbiri', 'Two'], [$find['prompt'], $find['answer']]);
        $this->assertEqualsCanonicalizing([1, 2], array_column(array_column($find['cards'], 'picture'), 'count'));

        $sequence = collect($steps)->firstWhere('type', 'sequence');
        $this->assertSame(['Bbiri', 'Yes! Emu, Bbiri!'], [$sequence['answer'], $sequence['feedback']['correct']]);

        $game = collect($steps)->where('type', 'recall')->first();
        $this->assertTrue($game['hide_word'], 'counting game: picture only, no English to give it away');
        $this->assertSame(['Nnya', 4], [$game['answer'], $game['picture']['count']]);

        $converse = collect($steps)->firstWhere('type', 'converse');
        $this->assertSame(1, $converse['you']);
        $this->assertSame('Bbiri … Nnya', $converse['answer']);
    }

    public function test_listening_appears_once_the_numbers_have_audio(): void
    {
        config(['luganda.play_unchecked' => true]);
        $ssatu = Phrase::where('luganda', 'Ssatu')->first();
        Storage::disk('public')->put('a/ssatu.wav', 'x');
        $ssatu->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => 'Ssatu', 'text_key' => LugandaText::key('Ssatu'), 'path' => 'a/ssatu.wav']);
        $this->assertContains('listen', array_column($this->steps(), 'type'));
    }

    public function test_no_new_luganda_was_invented_and_seeding_is_add_only(): void
    {
        $phrases = Phrase::count();
        $steps = LessonStep::count();
        $this->seed(LugandaNumbersPilotSeeder::class);
        $this->assertSame($phrases, Phrase::count(), 'only existing, reviewable phrases are used');
        $this->assertSame($steps, LessonStep::count(), 're-running changes nothing');
    }

    public function test_finishing_rewards_and_queues_all_five_numbers(): void
    {
        $learner = User::factory()->create();
        $this->actingAs($learner)->get(route('lessons.show', $this->lesson))->assertOk()
            ->assertSee("step.type === 'find'", false)->assertSee('countTap($event)', false)->assertSee('lesson-figure', false);
        $this->actingAs($learner)->postJson(route('lessons.complete', $this->lesson), ['score' => 100])->assertOk()->assertJson(['cowries_earned' => 15]);
        $queued = Phrase::whereIn('id', $learner->reviewItems()->pluck('phrase_id'))->pluck('luganda');
        foreach (['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano', 'Oli otya?'] as $lg) {
            $this->assertContains($lg, $queued);
        }
    }

    public function test_the_course_marks_it_interactive_and_it_can_be_switched_back(): void
    {
        $this->actingAs(User::factory()->create())->get('/learn')->assertSee('🎬 Interactive');

        $this->artisan('app:lesson-mode', ['slug' => 'ennamba-1-5', 'mode' => 'auto'])->assertSuccessful();
        $this->assertSame('auto', $this->lesson->fresh()->mode);
        $this->assertSame(['learn', 'learn', 'choose', 'choose'], array_slice(array_column($this->steps(), 'type'), 0, 4), 'automatic again');
        $this->assertSame(19, $this->lesson->steps()->count(), 'its steps are kept');
        $this->artisan('app:lesson-mode', ['slug' => 'ennamba-1-5', 'mode' => 'scripted'])->assertSuccessful();
        $this->artisan('app:lesson-mode', ['slug' => 'ssente', 'mode' => 'scripted'])->expectsOutputToContain('no steps yet')->assertFailed();
    }

    public function test_find_activity_checks_its_choices(): void
    {
        $emu = Phrase::where('luganda', 'Emu')->first()->id;
        $bbiri = Phrase::where('luganda', 'Bbiri')->first()->id;
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 50, 'type' => 'find', 'payload' => ['phrase' => $emu, 'choices' => [['phrase' => $bbiri, 'picture' => ['name' => 'cowrie', 'count' => 2]]]]]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 51, 'type' => 'find', 'payload' => ['phrase' => $emu, 'choices' => [['phrase' => $bbiri, 'picture' => ['name' => 'cowrie', 'count' => 2]], ['phrase' => $emu, 'picture' => ['name' => 'dragon']]]]]);
        $this->artisan('app:lesson-check', ['slug' => 'ennamba-1-5'])
            ->expectsOutputToContain('find needs 2 to 4 "choices"')
            ->expectsOutputToContain('choice 1: picture must be one of')
            ->assertFailed();
    }
}
