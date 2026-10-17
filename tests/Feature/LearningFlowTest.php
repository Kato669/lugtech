<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Services\LessonPlan;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(LugandaCourseSeeder::class);
    }

    public function test_public_pages_load(): void
    {
        $this->get('/')->assertOk()->assertSee('Learn Luganda')->assertSee('Tap Ngoma')->assertSee('Engalabi');
        $this->get('/dictionary')->assertOk()->assertSee('matooke');
        $this->get('/dictionary?q=water')->assertOk()->assertSee('amazzi')->assertDontSee('matooke');
    }

    public function test_register_and_see_course_path(): void
    {
        $this->post('/register', [
            'name' => 'Nakato', 'email' => 'nakato@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect('/learn');

        $this->get('/learn')->assertRedirect(route('welcome'));
        $this->post('/welcome', ['reason' => 'family', 'goal' => 10, 'start' => 'beginning'])->assertRedirect('/learn');
        $this->get('/learn')->assertOk()->assertSee('Daily goal: 10 min')->assertSee('Ewaka')->assertSee('Up next')->assertSee('Okulamusa')->assertSee('Oli otya, Nakato!');
    }

    public function test_lesson_plan_generates_all_exercise_types(): void
    {
        $lesson = Lesson::where('slug', 'amannya')->first();
        $steps = (new LessonPlan(seed: 1))->build($lesson);
        $types = collect($steps)->pluck('type')->unique()->sort()->values()->all();

        $this->assertSame(['build', 'choose', 'learn', 'recall'], $types);
        $this->assertCount(5, collect($steps)->where('type', 'learn'));
        foreach (collect($steps)->whereIn('type', ['choose', 'recall']) as $step) {
            $this->assertContains($step['answer'], $step['options']);
            $this->assertSame(count($step['options']), count(array_unique($step['options'])));
        }
        $build = collect($steps)->firstWhere('type', 'build');
        $this->assertEqualsCanonicalizing(explode(' ', $build['answer']), $build['tiles']);
    }

    public function test_completing_a_lesson_awards_cowries_streak_and_review_items(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::where('slug', 'okulamusa')->first();

        $this->actingAs($user)->get(route('lessons.show', $lesson))->assertOk()->assertSee('lessonPlayer');

        $this->actingAs($user)->postJson(route('lessons.complete', $lesson), ['score' => 100])
            ->assertOk()->assertJson(['cowries_earned' => 15, 'streak' => 1, 'first_time' => true]);

        $user->refresh();
        $this->assertSame(15, $user->cowries);
        $this->assertSame(6, $user->reviewItems()->count());

        // Repeat on the same day: half cowries, streak unchanged.
        $this->actingAs($user)->postJson(route('lessons.complete', $lesson), ['score' => 80])
            ->assertJson(['cowries_earned' => 5, 'streak' => 1, 'first_time' => false]);
        $this->assertSame(100, $user->completions()->first()->best_score);
    }

    public function test_streak_grows_on_consecutive_days_and_resets_after_gap(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::orderBy('id')->first();
        $complete = fn () => $this->actingAs($user)->postJson(route('lessons.complete', $lesson), ['score' => 50]);

        $this->travelTo(now()->setDate(2026, 10, 1));
        $complete();
        $this->travelTo(now()->setDate(2026, 10, 2));
        $complete();
        $this->assertSame(2, $user->fresh()->current_streak);
        $this->travelTo(now()->setDate(2026, 10, 5));
        $complete();
        $this->assertSame(1, $user->fresh()->current_streak);
        $this->assertSame(2, $user->fresh()->longest_streak);
    }

    public function test_review_shows_due_items_and_grading_reschedules(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 90]);

        $this->actingAs($user)->get('/review')->assertOk()->assertSee('Nothing due right now');

        $this->travel(2)->days();
        $item = $user->reviewItems()->first();
        $this->actingAs($user)->get('/review')->assertOk()->assertSee($item->phrase->luganda);

        $this->actingAs($user)->postJson(route('review.grade', $item), ['correct' => true])->assertOk();
        $item->refresh();
        $this->assertSame(1, $item->repetitions);
        $this->assertTrue($item->due_at->isFuture());

        $this->actingAs($user)->postJson(route('review.grade', $item), ['correct' => false]);
        $this->assertSame(0, $item->fresh()->repetitions);

        $other = User::factory()->create();
        $this->actingAs($other)->postJson(route('review.grade', $item), ['correct' => true])->assertForbidden();
    }

    public function test_phrasebook_lists_learned_phrases(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 90]);
        $this->actingAs($user)->get('/phrasebook')->assertOk()->assertSee('Oli otya?')->assertDontSee('Nnyabo');
    }

    public function test_unpublished_lessons_are_hidden_from_learners(): void
    {
        $lesson = Lesson::orderBy('id')->first();
        $lesson->update(['is_published' => false]);
        $this->actingAs(User::factory()->create())->get(route('lessons.show', $lesson))->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('lessons.show', $lesson))->assertOk();
    }
}
