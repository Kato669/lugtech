<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use App\Models\Word;
use App\Services\LessonPlan;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LugandaKitchenMarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_stages_two_and_three_have_lessons(): void
    {
        $count = fn (string $slug) => Stage::where('slug', $slug)->first()->lessons()->count();
        $this->assertSame(6, $count('ewaka'));       // + A first conversation (Phase 6)
        $this->assertSame(5, $count('mu-ffumbiro')); // + Actions (Phase 6)
        $this->assertSame(5, $count('mu-katale'));
        $this->assertSame(0, Phrase::where('is_reviewed', true)->count(), 'all seeded Luganda starts as a draft');
    }

    public function test_every_lesson_builds_clean_exercises(): void
    {
        foreach (Lesson::with('phrases')->get() as $lesson) {
            $phrases = $lesson->phrases;
            $this->assertGreaterThanOrEqual(4, $phrases->count(), "{$lesson->slug} needs at least 4 phrases for good multiple choice");
            $this->assertSame($phrases->count(), $phrases->pluck('english')->unique()->count(), "{$lesson->slug} repeats an English meaning");
            $this->assertSame($phrases->count(), $phrases->pluck('luganda')->unique()->count(), "{$lesson->slug} repeats a phrase");
            $this->assertSame(range(1, $phrases->count()), $phrases->pluck('pivot.position')->all(), "{$lesson->slug} positions");

            foreach ((new LessonPlan(seed: 3))->build($lesson) as $step) {
                if ($step['type'] === 'converse') {
                    foreach ($step['options'] as $i => $choices) {
                        $this->assertSame($step['you'], $step['lines'][$i]['who']);
                        $this->assertContains($step['lines'][$i]['luganda'], $choices);
                        $this->assertCount(3, $choices, "{$lesson->slug} converse needs 3 replies");
                        $this->assertSame(3, count(array_unique($choices)));
                    }

                    continue;
                }
                if (isset($step['options'])) {
                    $this->assertContains($step['answer'], $step['options']);
                    $this->assertSame(count($step['options']), count(array_unique($step['options'])));
                    $this->assertGreaterThanOrEqual(3, count($step['options']));
                }
            }
        }
    }

    public function test_reseeding_adds_only_and_keeps_reviewer_work(): void
    {
        $before = [Lesson::count(), Phrase::count(), Word::count()];

        // A reviewer corrects a phrase and an admin re-orders a lesson.
        $phrase = Phrase::where('luganda', 'Kendeezaako')->first();
        $phrase->update(['english' => 'Lower the price a little', 'notes' => 'Reviewer note kept']);
        $phrase->approve(User::factory()->create());
        $lesson = Lesson::where('slug', 'okugula')->first();
        $lesson->phrases()->updateExistingPivot($phrase->id, ['position' => 99]);
        Phrase::where('luganda', 'Oli otya?')->update(['english' => 'How are you? (corrected)']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(LugandaKitchenMarketSeeder::class);

        $this->assertSame($before, [Lesson::count(), Phrase::count(), Word::count()]);
        $phrase->refresh();
        $this->assertSame('Lower the price a little', $phrase->english);
        $this->assertSame('Reviewer note kept', $phrase->notes);
        $this->assertTrue($phrase->is_reviewed);
        $this->assertSame(99, $lesson->phrases()->where('phrases.id', $phrase->id)->first()->pivot->position);
        $this->assertSame('How are you? (corrected)', Phrase::where('luganda', 'Oli otya?')->value('english'));
    }

    public function test_new_lessons_appear_on_the_path_and_play(): void
    {
        $this->get('/welcome')->assertOk(); // a new guest: past the welcome first
        $this->post('/welcome', ['start' => 'beginning'])->assertRedirect('/learn');
        $this->get('/learn')->assertOk()
            ->assertSee('Emmere · Food')->assertSee('Ku mmeeza · At the table')
            ->assertSee('Okugula · Buying')->assertDontSee('Lessons coming soon · Mu ffumbiro');
        $this->get(route('lessons.show', Lesson::where('slug', 'okugula')->first()))->assertOk()->assertSee('Kya bbeeyi nnyo');
        $this->get('/dictionary?q=groundnut')->assertOk()->assertSee('ebinyeebwa');
    }
}
