<?php

namespace Tests\Feature;

use App\Models\DailyActivity;
use App\Models\GamePlay;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\PushSubscription;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\DailyHabit;
use App\Services\DailyReminders;
use App\Services\WebPush;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Phase 12: the daily goal ring, three tasks a day, streak freezes and daily reminders. */
class DailyHabitTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        config(['luganda.habit.task_cowries' => 3, 'luganda.habit.all_tasks_bonus' => 5]);
        $this->learner = User::factory()->create(['daily_goal' => 5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow($utc);
        CarbonImmutable::setTestNow($utc);
    }

    /** Give today's row these tasks (instead of the day's own pick). */
    private function tasks(array $tasks, ?User $user = null): DailyActivity
    {
        $row = app(DailyHabit::class)->todayFor($user ?? $this->learner);
        $row->update(['tasks' => collect($tasks)->map(fn ($target, $key) => ['key' => $key, 'target' => $target])->values()->all(), 'tasks_done' => []]);

        return $row;
    }

    private function lesson(int $i = 0): Lesson
    {
        return Lesson::where('is_published', true)->orderBy('stage_id')->orderBy('position')->skip($i)->first();
    }

    public function test_days_follow_kampala_time(): void
    {
        config(['luganda.timezone' => 'Africa/Kampala']);
        $this->at('2026-10-19 21:30:00'); // 00:30 in Kampala
        $this->assertSame('2026-10-20', DailyHabit::today());
        $this->assertSame('2026-10-19 21:00:00', DailyHabit::startOfToday()->toDateTimeString(), 'midnight in Kampala, as UTC');
        $this->assertSame(1, DailyHabit::daysSince('2026-10-19'));
    }

    public function test_three_tasks_a_day_that_the_learner_can_do(): void
    {
        config(['luganda.voice' => false]);
        $habit = app(DailyHabit::class);
        $tasks = collect($habit->chooseTasks($this->learner, '2026-10-19'))->pluck('key');
        $this->assertCount(3, $tasks);
        $this->assertNotContains('review', $tasks, 'nothing to review yet');
        $this->assertNotContains('game', $tasks, 'games need known phrases');
        $this->assertNotContains('speak', $tasks, 'voices are off');
        $this->assertSame($tasks->all(), collect($habit->chooseTasks($this->learner, '2026-10-19'))->pluck('key')->all(), 'the same all day');

        // Chosen once: learning phrases during the day doesn't change today's tasks.
        $first = $habit->todayFor($this->learner)->tasks;
        foreach (Phrase::take(8)->pluck('id') as $id) {
            ReviewItem::create(['user_id' => $this->learner->id, 'phrase_id' => $id, 'due_at' => now()]);
        }
        $this->assertSame($first, $habit->todayFor($this->learner)->fresh()->tasks);
        $this->assertSame(1, DailyActivity::count());
    }

    public function test_finishing_a_lesson_counts_time_and_pays_tasks_once(): void
    {
        $this->tasks(['lesson' => 1, 'perfect' => 1, 'goal' => 5]);
        $res = $this->actingAs($this->learner)->postJson(route('lessons.complete', $this->lesson()), ['score' => 100, 'active_seconds' => 200])->assertOk();
        $res->assertJsonPath('habit.cowries_earned', 6)
            ->assertJsonPath('habit.tasks_done', ['Finish a lesson', 'Get every answer right in a lesson'])
            ->assertJsonPath('habit.all_done', false)
            ->assertJsonPath('habit.minutes', 3);
        $this->assertSame(10 + 5 + 6, $res->json('cowries'), 'lesson + perfect bonus + two tasks');

        // The goal (5 min) is the third task: done, and the bonus for all three.
        $res = $this->postJson(route('lessons.complete', $this->lesson(1)), ['score' => 50, 'active_seconds' => 5000])->assertOk();
        $res->assertJsonPath('habit.goal_met', true)->assertJsonPath('habit.all_done', true)
            ->assertJsonPath('habit.cowries_earned', 3 + 5)
            ->assertJsonPath('habit.minutes', 3 + 20); // one save adds at most 20 minutes

        // Nothing is paid twice.
        $this->postJson(route('lessons.complete', $this->lesson(2)), ['score' => 100])->assertJsonPath('habit.cowries_earned', 0);
        $row = DailyActivity::first();
        $this->assertSame([3, 2, 14], [$row->lessons, $row->perfect_lessons, $row->cowries_earned]);
        $this->assertTrue($row->bonus_paid);
        $this->postJson(route('lessons.complete', $this->lesson()), ['score' => 100, 'active_seconds' => -5])->assertUnprocessable();
    }

    public function test_reviews_speaking_and_games_count(): void
    {
        $this->actingAs($this->learner)->postJson(route('lessons.complete', $this->lesson()), ['score' => 80]);
        $this->tasks(['review' => 2, 'speak' => 2, 'game' => 1]);

        foreach (ReviewItem::where('user_id', $this->learner->id)->take(2)->get() as $item) {
            $res = $this->postJson(route('review.grade', $item), ['correct' => true, 'active_seconds' => 30])->assertOk();
        }
        $res->assertJsonPath('habit.tasks_done', ['Review 2 phrases']);

        $phrase = Phrase::first();
        $one = $this->postJson(route('speak.attempts'), ['phrase_id' => $phrase->id, 'self_rating' => 'again'])->json('attempt_id');
        $this->postJson(route('speak.attempts'), ['phrase_id' => $phrase->id, 'self_rating' => 'good', 'attempt_id' => $one])
            ->assertJsonPath('habit.tasks_done', []); // re-rating the same attempt isn't a new one
        $this->postJson(route('speak.attempts'), ['phrase_id' => $phrase->id, 'self_rating' => 'good'])
            ->assertJsonPath('habit.tasks_done', ['Say 2 phrases out loud']);

        // Game cowries are capped for the day; task cowries are not.
        GamePlay::create(['user_id' => $this->learner->id, 'game' => 'memory', 'score' => 100, 'cowries' => 30]);
        $before = $this->learner->fresh()->cowries;
        $this->postJson(route('games.complete', 'memory'), ['score' => 100, 'active_seconds' => 60])->assertOk()
            ->assertJsonPath('cowries_earned', 0)->assertJsonPath('habit.cowries_earned', 3 + 5);
        $this->assertSame($before + 8, $this->learner->fresh()->cowries);
        $this->assertSame([2, 2, 1, 120], array_values(DailyActivity::where('user_id', $this->learner->id)->first()->only(['reviews', 'speaks', 'games', 'active_seconds'])));
    }

    public function test_the_course_path_shows_today(): void
    {
        $this->tasks(['lesson' => 1, 'perfect' => 1, 'goal' => 5]);
        $this->actingAs($this->learner)->get('/learn')->assertOk()
            ->assertSee('Today')->assertSee('0 of 5 minutes practised')
            ->assertSee('Finish a lesson')->assertSee('Practise for 5 minutes')
            ->assertSee('Practice today: 0 of 5 minutes', false)
            ->assertSee('Buy one · 20', false);
        $this->postJson(route('lessons.complete', $this->lesson()), ['score' => 100, 'active_seconds' => 400]);
        $this->get('/learn')->assertSee('Goal reached. Kulika!')->assertSee('All done for today');
        // Practice time is counted on lesson, review and game pages only.
        $this->get(route('lessons.show', $this->lesson()))->assertSee('window.LUGANDA_PRACTICE = true', false);
        $this->get('/learn')->assertDontSee('window.LUGANDA_PRACTICE', false);
    }

    // ---- streak freezes ----------------------------------------------------------------------------

    public function test_buying_streak_freezes(): void
    {
        $this->learner->forceFill(['cowries' => 45])->save();
        $this->actingAs($this->learner)->post(route('habit.freeze'))->assertRedirect('/learn')->assertSessionHas('status', fn ($s) => str_contains($s, 'bought'));
        $this->post(route('habit.freeze'));
        $this->assertSame([5, 2], [$this->learner->fresh()->cowries, $this->learner->fresh()->streak_freezes]);
        $this->post(route('habit.freeze'))->assertSessionHas('status', fn ($s) => str_contains($s, 'most streak freezes'));

        $poor = User::factory()->create(['cowries' => 19]);
        $this->actingAs($poor)->post(route('habit.freeze'))->assertSessionHas('status', fn ($s) => str_contains($s, 'costs 20 cowries'));
        $this->assertSame([19, 0], [$poor->fresh()->cowries, $poor->fresh()->streak_freezes]);
    }

    public function test_a_freeze_keeps_the_streak_when_a_day_is_missed(): void
    {
        $this->at('2026-10-10 12:00:00');
        $this->learner->forceFill(['current_streak' => 6, 'longest_streak' => 6, 'last_active_on' => '2026-10-08', 'streak_freezes' => 1])->save();
        $this->assertSame(6, $this->learner->fresh()->liveStreak(), 'one missed day, one freeze: still alive');

        $this->actingAs($this->learner)->postJson(route('lessons.complete', $this->lesson()), ['score' => 80])->assertJsonPath('streak', 7);
        $u = $this->learner->fresh();
        $this->assertSame([7, 0, '2026-10-10'], [$u->current_streak, $u->streak_freezes, $u->freeze_used_on->toDateString()]);
        $this->get('/learn')->assertSee('A streak freeze saved your streak');

        // Two missed days and only one freeze: a fresh start, and the freeze is kept.
        $this->at('2026-10-13 12:00:00');
        $u->forceFill(['streak_freezes' => 1])->save();
        $this->assertSame(0, $u->fresh()->liveStreak());
        $this->postJson(route('lessons.complete', $this->lesson()), ['score' => 80])->assertJsonPath('streak', 1);
        $this->assertSame(1, $u->fresh()->streak_freezes);
    }

    // ---- reminders ---------------------------------------------------------------------------------

    private function pushKeys(): void
    {
        $keys = WebPush::generateKeys();
        config(['luganda.push.public_key' => $keys['public'], 'luganda.push.private_key' => $keys['private'], 'luganda.push.subject' => 'mailto:test@example.com']);
    }

    public function test_reminders_can_be_switched_on_and_off(): void
    {
        $sub = ['endpoint' => 'https://push.example.com/send/abc', 'keys' => ['p256dh' => 'x', 'auth' => 'y'], 'hour' => 18, 'timezone' => 'Africa/Kampala'];
        $this->actingAs($this->learner)->postJson(route('reminders.on'), $sub)->assertNotFound(); // no keys yet
        $this->get('/settings')->assertSee("Reminders aren't switched on for this site yet", false);

        $this->pushKeys();
        $this->get('/settings')->assertSee('Daily reminder')->assertSee('Remind me at');
        $this->postJson(route('reminders.on'), ['endpoint' => 'http://insecure.example.com/x', 'hour' => 18])->assertUnprocessable();
        $this->postJson(route('reminders.on'), ['endpoint' => $sub['endpoint'], 'hour' => 25])->assertUnprocessable();
        $this->postJson(route('reminders.on'), $sub)->assertOk()->assertJson(['hour' => 18]);
        $this->postJson(route('reminders.on'), ['hour' => 7] + $sub)->assertOk(); // same browser: updated, not doubled
        $this->assertSame(1, PushSubscription::count());
        $this->assertSame([7, 'Africa/Kampala'], [$this->learner->fresh()->reminder_hour, $this->learner->fresh()->timezone]);

        $this->postJson(route('reminders.off'), ['endpoint' => $sub['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
        $this->assertNull($this->learner->fresh()->reminder_hour);
    }

    public function test_reminders_are_signed_with_the_apps_own_key(): void
    {
        $this->pushKeys();
        $auth = app(WebPush::class)->authorization('https://fcm.googleapis.com/fcm/send/abc', 1_800_000_000);
        $this->assertMatchesRegularExpression('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=([\w-]+)$/', $auth);
        preg_match('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=/', $auth, $m);
        $claims = json_decode(base64_decode(strtr($m[2], '-_', '+/')), true);
        $this->assertSame(['aud' => 'https://fcm.googleapis.com', 'exp' => 1_800_000_000 + 43200, 'sub' => 'mailto:test@example.com'], $claims);

        // The raw ES256 signature verifies against the public key.
        $raw = base64_decode(strtr($m[3], '-_', '+/').str_repeat('=', (4 - strlen($m[3]) % 4) % 4));
        $this->assertSame(64, strlen($raw));
        $int = fn ($b) => (ord(ltrim($b, "\0")[0] ?? "\0") > 0x7F ? "\0" : '').ltrim($b, "\0");
        [$r, $s] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $public = openssl_pkey_get_details(openssl_pkey_get_private(base64_decode(config('luganda.push.private_key'))))['key'];
        $this->assertSame(1, openssl_verify($m[1].'.'.$m[2], $der, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_reminders_go_out_once_at_the_chosen_hour_and_not_after_practice(): void
    {
        $this->pushKeys();
        Http::fake(['push.example.com/*' => Http::response('', 201), 'gone.example.com/*' => Http::response('', 410)]);
        $this->learner->forceFill(['reminder_hour' => 18, 'timezone' => 'Africa/Kampala', 'last_active_on' => '2026-10-18'])->save();
        PushSubscription::create(['user_id' => $this->learner->id, 'endpoint' => 'https://push.example.com/a', 'endpoint_hash' => hash('sha256', 'a')]);
        $other = User::factory()->create(['reminder_hour' => 18, 'timezone' => 'Africa/Kampala']);
        PushSubscription::create(['user_id' => $other->id, 'endpoint' => 'https://gone.example.com/b', 'endpoint_hash' => hash('sha256', 'b')]);
        $reminders = app(DailyReminders::class);

        $this->at('2026-10-19 14:10:00'); // 17:10 in Kampala: not yet
        $this->assertSame(0, $reminders->run()['sent']);
        $this->at('2026-10-19 15:10:00'); // 18:10 in Kampala
        $this->assertSame(['sent' => 1, 'skipped' => 0, 'removed' => 1, 'failed' => 0], $reminders->run());
        Http::assertSent(fn ($req) => $req->url() === 'https://push.example.com/a' && $req->body() === ''
            && $req->hasHeader('TTL') && str_starts_with($req->header('Authorization')[0], 'vapid t='));
        $this->assertSame(0, PushSubscription::where('user_id', $other->id)->count(), 'unsubscribed browsers are removed');
        $this->assertSame(0, $reminders->run()['sent'], 'once a day');

        // Already practised today: no reminder.
        $this->at('2026-10-20 15:10:00');
        $this->learner->forceFill(['last_active_on' => '2026-10-20'])->save();
        $this->assertSame(0, $reminders->run()['sent']);
        $this->artisan('app:send-reminders --dry-run')->expectsOutputToContain('Would send: 0')->assertExitCode(0);
    }

    public function test_the_service_worker_shows_reminders(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
        $this->assertStringContainsString("const VERSION = 'v3'", $sw);
    }

    public function test_a_guest_keeps_their_day_and_freezes_when_logging_in(): void
    {
        $account = User::factory()->create(['email' => 'kato@example.com', 'password' => 'secret123', 'streak_freezes' => 1]);
        $this->get('/learn');
        $guest = auth()->user();
        $guest->forceFill(['streak_freezes' => 2])->save();
        $this->postJson(route('lessons.complete', $this->lesson()), ['score' => 80, 'active_seconds' => 120]);
        $this->post('/login', ['email' => 'kato@example.com', 'password' => 'secret123'])->assertRedirect('/learn');

        $account->refresh();
        $this->assertSame(2, $account->streak_freezes, 'up to the limit');
        $row = DailyActivity::where('user_id', $account->id)->first();
        $this->assertSame([1, 120], [$row->lessons, $row->active_seconds]);
    }
}
