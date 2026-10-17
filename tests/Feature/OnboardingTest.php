<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\User;
use App\Services\GuestMerger;
use App\Services\Placement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** Phase 10: the welcome for new learners, the placement check, and settings. */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    /** The first three stages' lessons, in path order. */
    private function pathLessons(): Collection
    {
        return Stage::orderBy('position')->with(['publishedLessons' => fn ($q) => $q->orderBy('position')])->get()
            ->filter(fn ($s) => $s->publishedLessons->isNotEmpty())->take(3)->flatMap->publishedLessons->values();
    }

    public function test_a_new_learner_is_welcomed_first(): void
    {
        $new = User::factory()->newLearner()->create();
        $this->actingAs($new)->get('/learn')->assertRedirect(route('welcome'));
        $this->get('/welcome')->assertOk()->assertSee('Why are you learning Luganda?')->assertSee('My family')
            ->assertSee('How much time a day?')->assertSee('20 minutes a day')->assertSee('I already know some')->assertSee('Skip for now');

        $this->post('/welcome', ['reason' => 'nonsense', 'goal' => 7])->assertSessionHasErrors(['reason', 'goal']);
        $this->post('/welcome', ['reason' => 'family', 'goal' => 15, 'start' => 'beginning'])->assertRedirect('/learn');
        $new->refresh();
        $this->assertSame(['family', 15], [$new->learning_reason, $new->daily_goal]);
        $this->assertNotNull($new->onboarded_at);
        $this->get('/learn')->assertOk()->assertSee('Learning for my family')->assertSee('Daily goal: 15 min');

        // Skipping is fine too, and isn't asked again.
        $skipper = User::factory()->newLearner()->create();
        $this->actingAs($skipper)->post('/welcome', ['start' => 'beginning'])->assertRedirect('/learn');
        $this->get('/learn')->assertOk()->assertDontSee('Daily goal');

        // Someone already on the path (from before Phase 10) is never sent back to the welcome.
        $old = User::factory()->newLearner()->create();
        $old->completions()->create(['lesson_id' => Lesson::orderBy('id')->first()->id, 'best_score' => 80, 'times_completed' => 1]);
        $this->actingAs($old)->get('/learn')->assertOk();

        // Choosing "I already know some" goes to the check.
        $this->actingAs(User::factory()->newLearner()->create())->post('/welcome', ['start' => 'placement'])->assertRedirect(route('placement'));
    }

    public function test_the_check_uses_only_the_courses_own_luganda(): void
    {
        $questions = app(Placement::class)->questions();
        $this->assertCount(12, $questions);
        $this->assertSame([4, 4, 4], array_values(array_count_values(array_column($questions, 'stage'))));
        $path = $this->pathLessons()->pluck('id');

        $known = Phrase::pluck('luganda')->all();
        foreach ($questions as $q) {
            $this->assertContains($q['lesson_id'], $path);
            $this->assertCount(4, $q['options']);
            $this->assertCount(4, array_unique($q['options']), 'no repeated options');
            $phrase = Phrase::find($q['phrase_id']);
            $this->assertTrue(Lesson::find($q['lesson_id'])->phrases->contains($phrase), 'asked about a phrase that lesson teaches');
            if ($q['kind'] === 'meaning') {
                $this->assertSame($phrase->luganda, $q['prompt']);
                $this->assertSame($phrase->english, $q['options'][$q['answer']]);
            } else {
                $this->assertSame($phrase->english, $q['prompt']);
                $this->assertSame($phrase->luganda, $q['options'][$q['answer']]);
                $this->assertNull($q['audio'], 'hearing the answer would give it away');
                foreach ($q['options'] as $option) {
                    $this->assertContains($option, $known, 'no invented Luganda');
                }
            }
        }
        // The questions run along the path, first lesson first.
        $this->assertSame($path->first(), $questions[0]['lesson_id']);
    }

    public function test_marking_follows_the_answers(): void
    {
        $placement = app(Placement::class);
        $questions = $placement->questions();
        $right = array_column($questions, 'answer');
        $lessons = $this->pathLessons();

        // Everything right: every lesson of the three stages is known.
        $this->assertEqualsCanonicalizing($lessons->pluck('id')->all(), $placement->grade($questions, $right)['known']);

        // "I don't know" to everything: nothing is known.
        $this->assertSame([], $placement->grade($questions, [])['known']);

        // One slip in a stage is allowed.
        $oneSlip = $right;
        unset($oneSlip[1]);
        $this->assertCount($lessons->count(), $placement->grade($questions, $oneSlip)['known']);

        // Stage 1 right, then two mistakes in Stage 2: Stage 1, plus Stage 2's lessons before the first mistake.
        $answers = $right;
        unset($answers[5], $answers[6]); // Stage 2's second and third questions
        $result = $placement->grade($questions, $answers);
        $stage2 = Lesson::find($questions[4]['lesson_id'])->stage;
        $expected = $lessons->filter(fn ($l) => $l->stage_id === $questions[0]['stage_id']
            || ($l->stage_id === $stage2->id && $l->position < $questions[5]['lesson_position']))->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expected, $result['known']);
        $this->assertSame([
            Stage::find($questions[0]['stage_id'])->publishedLessons()->count(),
            count(array_filter($expected, fn ($id) => Lesson::find($id)->stage_id === $stage2->id)),
            0,
        ], array_column($result['stages'], 'known'));
        // Stage 3 isn't counted even if (somehow) answered: the check stopped at Stage 2.
        $this->assertSame(0, $result['stages'][2]['known']);
    }

    public function test_the_check_from_start_to_finish(): void
    {
        $learner = User::factory()->newLearner()->create(['cowries' => 3]);
        $this->actingAs($learner)->get('/placement')->assertOk()->assertSee('How much Luganda do you know?')->assertSee("I don't know", false);
        $questions = session('placement');
        $this->assertCount(12, $questions);

        // Right through Stage 1 and the first two of Stage 2, then "I don't know".
        $answers = array_slice(array_column($questions, 'answer'), 0, 6);
        $this->post('/placement', ['answers' => $answers])->assertRedirect(route('placement.result'));
        $stage1 = Stage::find($questions[0]['stage_id'])->publishedLessons;
        $this->get(route('placement.result'))->assertOk()->assertSee('Kulika! You already know')
            ->assertSee('All '.$stage1->count().' lessons known');

        $learner->refresh();
        $this->assertSame(3, $learner->cowries, 'no cowries for the check');
        $this->assertNotNull($learner->onboarded_at, 'the check counts as the welcome');
        $placed = $learner->completions()->whereNotNull('placed_at')->get();
        $this->assertTrue($placed->every(fn ($c) => $c->times_completed === 0 && $c->isPlacedOnly()));
        $this->assertGreaterThanOrEqual($stage1->count(), $placed->count());

        // Their phrases come back in Review over the next week, not all at once and not today.
        $due = $learner->reviewItems()->pluck('due_at');
        $this->assertNotEmpty($due);
        $this->assertTrue($due->every(fn ($d) => $d->isAfter(today()->endOfDay()) && $d->lte(today()->addDays(7))));
        $this->assertGreaterThan(1, $due->map->toDateString()->unique()->count());

        // The path: ✓ for known lessons, and "Up next" is the first one not known.
        $next = $this->pathLessons()->first(fn ($l) => ! $placed->pluck('lesson_id')->contains($l->id));
        $this->get('/learn')->assertOk()->assertSee('Known already')->assertSeeInOrder(['Up next', $next->title]);

        // Playing a known lesson for real still earns its full first-time cowries.
        $this->postJson(route('lessons.complete', $stage1->first()), ['score' => 100])->assertOk()->assertJson(['cowries_earned' => 15, 'first_time' => true]);

        // Sending the same answers twice does nothing more (the questions are used up).
        $this->post('/placement', ['answers' => $answers])->assertRedirect(route('placement'));
        $this->post('/placement', ['answers' => ['x']])->assertSessionHasErrors('answers.0');
    }

    public function test_settings_change_the_welcome_answers_and_the_placement(): void
    {
        $learner = User::factory()->create();
        $this->actingAs($learner)->get('/settings')->assertOk()->assertSee('Why you\'re learning', false)->assertSee('Take the placement check')
            ->assertDontSee('Start from the beginning');
        $this->post('/settings', ['reason' => 'travel', 'goal' => 20])->assertRedirect('/settings');
        $this->assertSame(['travel', 20], [$learner->fresh()->learning_reason, $learner->fresh()->daily_goal]);
        $this->post('/settings', ['goal' => 3])->assertSessionHasErrors('goal');

        // Place, then play one lesson and practise one phrase for real.
        $lessons = $this->pathLessons()->take(6);
        app(Placement::class)->apply($learner, $lessons->pluck('id')->all());
        $played = $lessons->first();
        $this->postJson(route('lessons.complete', $played), ['score' => 90])->assertOk();
        $practised = $learner->reviewItems()->whereNotIn('phrase_id', $played->taughtPhraseIds())->first();
        $practised->grade(true);

        $this->get('/settings')->assertSee('marked 5 lessons as known')->assertSee('Take the check again')->assertSee('Start from the beginning');
        $this->post(route('settings.start-over'))->assertRedirect('/learn')->assertSessionHas('status');
        $this->assertSame([$played->id], $learner->completions()->pluck('lesson_id')->all(), 'played lessons keep their stars');
        $this->assertTrue($learner->reviewItems()->where('id', $practised->id)->exists(), 'practised phrases stay in Review');
        $this->assertEqualsCanonicalizing(
            array_merge($played->taughtPhraseIds(), [$practised->phrase_id]),
            $learner->reviewItems()->pluck('phrase_id')->all(),
        );

        // Taking the check again replaces the last result.
        app(Placement::class)->apply($learner, $lessons->pluck('id')->all());
        $this->get('/placement');
        $this->post('/placement', ['answers' => []])->assertRedirect(route('placement.result'));
        $this->assertSame(0, $learner->completions()->whereNotNull('placed_at')->where('times_completed', 0)->count());
        $this->get(route('placement.result'))->assertSee('The beginning is the right place for you.');
    }

    public function test_a_guest_who_logs_in_keeps_their_welcome_answers(): void
    {
        $account = User::factory()->newLearner()->create();
        $guest = User::factory()->create(['learning_reason' => 'culture', 'daily_goal' => 10]);
        $guest->forceFill(['is_guest' => true])->save();
        app(Placement::class)->apply($guest, [$this->pathLessons()->first()->id]);

        app(GuestMerger::class)->merge($guest, $account);
        $account->refresh();
        $this->assertSame(['culture', 10], [$account->learning_reason, $account->daily_goal]);
        $this->assertNotNull($account->onboarded_at);
        $this->assertTrue($account->completions()->first()->isPlacedOnly());
    }

    public function test_settings_link_and_pages_for_guests(): void
    {
        $this->get('/welcome')->assertOk(); // a visitor becomes a guest here too
        $this->get('/settings')->assertOk()->assertSee('Save my progress');
        $this->get('/learn')->assertRedirect(route('welcome'));
        $this->post('/welcome', [])->assertRedirect('/learn');
        $this->get('/learn')->assertOk()->assertSee('aria-label="Settings"', false);
    }
}
