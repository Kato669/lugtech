<?php

namespace Tests\Feature;

use App\Lessons\ActivityRegistry;
use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\User;
use App\Services\LessonPlan;
use App\Support\LugandaText;
use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScriptedLessonTest extends TestCase
{
    use AddsCourseContent, RefreshDatabase;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        $this->seedAutomaticCourse(); // these lessons as automatic lessons (Phase 6 makes them scripted)
        $this->lesson = Lesson::where('slug', 'ennamba-1-5')->firstOrFail();
        // These tests check the engine on their own steps, so start from a plain lesson (not the pilot).
        $this->lesson->steps()->delete();
        $this->lesson->forceFill(['mode' => 'auto'])->save();
    }

    private function script(array $steps, array $newPhrases = []): array
    {
        $this->addSteps($this->lesson, $steps, $newPhrases);
        $this->lesson->refresh();
        $this->actingAs(User::factory()->create());

        return (new LessonPlan(7))->build($this->lesson);
    }

    private function id(string $lg): int
    {
        return Phrase::where('luganda', $lg)->firstOrFail()->id;
    }

    public function test_every_activity_type_plays_from_a_script(): void
    {
        $steps = $this->script([
            ['intro', ['say' => 'Today we are learning numbers!', 'character' => 'mukasa']],
            ['teach', ['phrase' => 'Emu', 'picture' => ['name' => 'cowrie', 'count' => 1], 'say' => 'One cowrie: Emu.']],
            ['teach', ['phrase' => 'Bbiri', 'picture' => ['name' => 'cowrie', 'count' => 2]]],
            ['choose', ['phrase' => 'Bbiri', 'feedback' => ['correct' => 'Yes! Bbiri is two.']]],
            ['recall', ['phrase' => 'Emu', 'options' => ['Bbiri', 'Ssatu']]],
            ['listen', ['phrase' => 'Ssatu']], // no recording yet: left out
            ['sequence', ['sequence' => ['Emu', 'Bbiri', 'Ssatu', 'Nnya', 'Ttaano'], 'ask' => 0]],
            ['match', ['pairs' => ['Emu', 'Bbiri', 'Ssatu']]],
            ['dialogue', ['lines' => [['who' => 0, 'phrase' => 'Emu'], ['who' => 1, 'phrase' => 'Bbiri']]]],
            ['converse', ['lines' => [['who' => 0, 'phrase' => 'Emu'], ['who' => 1, 'phrase' => 'Bbiri']], 'you' => 1, 'wrong' => [1 => ['Ttaano']]]],
            ['build', ['phrase' => 'Oli otya?']],
            ['reward', ['say' => 'You can count!']],
        ]);

        $this->assertTrue($this->lesson->isScripted());
        $this->assertSame(['intro', 'learn', 'learn', 'choose', 'recall', 'sequence', 'match', 'dialogue', 'converse', 'build', 'reward'], array_column($steps, 'type'));

        [$intro, $teach, , $choose, $recall, $sequence, $match, $dialogue, $converse] = $steps;
        $this->assertSame(['Today we are learning numbers!', 'mukasa'], [$intro['say'], $intro['character']]);
        $this->assertSame(['name' => 'cowrie', 'count' => 1], $teach['picture']);
        $this->assertSame('One cowrie: Emu.', $teach['say']);
        $this->assertSame('Yes! Bbiri is two.', $choose['feedback']['correct']);
        $this->assertEqualsCanonicalizing(['Emu', 'Bbiri', 'Ssatu'], $recall['options'], 'hand-picked wrong answers');
        $this->assertSame(['What comes after Emu?', 'Bbiri'], [$sequence['prompt'], $sequence['answer']]);
        $this->assertContains('Bbiri', $sequence['options']);
        $this->assertCount(3, $sequence['options']);
        $this->assertEqualsCanonicalizing(['One', 'Two', 'Three'], $match['meanings']);
        $this->assertSame([0, 1], array_column($dialogue['lines'], 'who'));
        $this->assertSame(1, $converse['you']);
        $this->assertContains('Ttaano', $converse['options'][1]);
        $this->assertContains('Bbiri', $converse['options'][1]);
    }

    public function test_listen_appears_once_there_is_audio(): void
    {
        $ssatu = Phrase::where('luganda', 'Ssatu')->first();
        Storage::disk('public')->put('a/s.wav', 'x');
        $ssatu->audioClips()->create(['slot' => 'normal', 'source' => 'generated', 'provider' => 'sunbird', 'text_snapshot' => 'Ssatu', 'text_key' => LugandaText::key('Ssatu'), 'path' => 'a/s.wav']);
        config(['luganda.play_unchecked' => true]);

        $steps = $this->script([['teach', ['phrase' => 'Ssatu']], ['listen', ['phrase' => 'Ssatu']]]);
        $this->assertSame(['learn', 'listen'], array_column($steps, 'type'));
    }

    public function test_broken_steps_are_skipped_and_reported(): void
    {
        $this->addSteps($this->lesson, [
            ['intro', ['say' => 'Hello']],
            ['teach', ['phrase' => 'Emu']],
        ]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 3, 'type' => 'dance', 'payload' => []]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 4, 'type' => 'match', 'payload' => ['pairs' => [$this->id('Emu')]]]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 5, 'type' => 'teach', 'payload' => ['phrase' => 999999]]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 7, 'type' => 'teach', 'payload' => ['phrase' => $this->id('Emu'), 'picture' => 'unicorn']]);
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 6, 'type' => 'sequence', 'payload' => ['sequence' => [$this->id('Emu'), $this->id('Bbiri'), $this->id('Ssatu')], 'ask' => 2]]);

        $steps = (new LessonPlan)->build($this->lesson->fresh());
        $this->assertSame(['intro', 'learn'], array_column($steps, 'type'));

        $this->artisan('app:lesson-check', ['slug' => 'ennamba-1-5'])
            ->expectsOutputToContain('unknown activity "dance"')
            ->expectsOutputToContain('match needs 2 to 6 "pairs"')
            ->expectsOutputToContain('phrase 999999 does not exist')
            ->expectsOutputToContain('picture must be one of')
            ->expectsOutputToContain('"ask" must point at a phrase that has one after it')
            ->expectsOutputToContain('Plays as 2 steps')
            ->assertFailed();
    }

    public function test_a_scripted_lesson_with_nothing_playable_falls_back_to_automatic(): void
    {
        $this->lesson->forceFill(['mode' => 'scripted'])->save();
        LessonStep::create(['lesson_id' => $this->lesson->id, 'position' => 1, 'type' => 'dance', 'payload' => []]);
        $steps = (new LessonPlan)->build($this->lesson->fresh());
        $this->assertContains('learn', array_column($steps, 'type'));
        $this->assertContains('choose', array_column($steps, 'type'));
    }

    public function test_adding_steps_is_add_only_and_creates_draft_phrases(): void
    {
        $this->addSteps($this->lesson, [['intro', ['say' => 'Hi', 'phrase' => 'Gyebale ko']]], ['Gyebale ko' => 'Well done (greeting)']);
        $new = Phrase::where('luganda', 'Gyebale ko')->first();
        $this->assertNotNull($new);
        $this->assertFalse($new->is_reviewed, 'new Luganda is a draft for the Check page');

        $this->addSteps($this->lesson, [['reward', []]]); // already has steps: left alone
        $this->assertSame(1, $this->lesson->steps()->count());

        $this->expectException(\InvalidArgumentException::class);
        $this->addSteps(Lesson::where('slug', 'ennamba-6-10')->first(), [['teach', ['phrase' => 'Not a phrase yet']]]);
    }

    public function test_finishing_a_scripted_lesson_reviews_every_phrase_it_used(): void
    {
        $this->script([['teach', ['phrase' => 'Emu']], ['build', ['phrase' => 'Oli otya?']]]);
        $learner = User::factory()->create();
        $this->actingAs($learner)->postJson(route('lessons.complete', $this->lesson), ['score' => 100])->assertOk();
        $ids = $learner->reviewItems()->pluck('phrase_id');
        $this->assertContains($this->id('Oli otya?'), $ids, 'a phrase from another lesson, used in a step');
        $this->assertContains($this->id('Ttaano'), $ids, 'the lesson\'s own phrases still count');
    }

    public function test_the_player_page_has_a_view_for_every_activity(): void
    {
        $this->script([
            ['intro', ['say' => 'Today we are learning numbers!']],
            ['match', ['pairs' => ['Emu', 'Bbiri']]],
            ['reward', ['say' => 'Kulika!']],
        ]);
        $page = $this->get(route('lessons.show', $this->lesson))->assertOk();
        foreach (["step.type === 'intro'", "step.type === 'match'", "step.type === 'reward'", "'sequence'].includes(step.type)", 'matchStep(step)', 'Today we are learning numbers!'] as $marker) {
            $page->assertSee($marker, false);
        }
        $this->assertSame(['intro', 'learn', 'teach', 'speak', 'choose', 'recall', 'listen', 'build', 'find', 'match', 'sequence', 'dialogue', 'converse', 'pattern', 'sort', 'reward'], app(ActivityRegistry::class)->types());
    }

    public function test_automatic_lessons_are_unchanged(): void
    {
        $lesson = Lesson::where('slug', 'okulamusa')->first();
        $this->assertSame('auto', $lesson->mode);
        $this->actingAs(User::factory()->create());
        $types = array_column((new LessonPlan(1))->build($lesson), 'type');
        $this->assertSame(['learn', 'learn', 'choose', 'choose'], array_slice($types, 0, 4));
        $this->assertContains('dialogue', $types);
        $this->assertSame('converse', end($types));
        $this->artisan('app:lesson-check')->expectsOutputToContain('No scripted lessons yet')->assertSuccessful();
    }
}
