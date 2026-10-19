<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12: the daily habit.
 *
 * - Days follow LUGANDA_TIMEZONE (Africa/Kampala), so a day ends at midnight in Kampala.
 * - Practice time: the phone counts active seconds on lesson, review and game pages and sends
 *   them with each save (resources/js/habit.js); one save adds at most 20 minutes.
 * - Three tasks a day, picked once per learner per day from a small pool. Each pays cowries once,
 *   and all three pay a bonus. Task cowries are separate from the daily game cap.
 * - Streak freezes (Progress::touchStreak): a missed day uses one, and the streak carries on.
 */
class DailyHabit
{
    /** key => [label, target] ("{n}" becomes the target). Order is only for display. */
    public const TASKS = [
        'lesson' => ['Finish a lesson', 1],
        'review' => ['Review {n} phrases', 5],
        'speak' => ['Say {n} phrases out loud', 3],
        'game' => ['Play a game', 1],
        'perfect' => ['Get every answer right in a lesson', 1],
        'goal' => ['Practise for {n} minutes', null],
    ];

    public const TASKS_PER_DAY = 3;

    // ---- days ---------------------------------------------------------------------------------

    public static function timezone(): string
    {
        return (string) config('luganda.timezone', 'UTC');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /** Today's date (Y-m-d) in the learning time zone. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** The moment today began, in the app's own time zone (for comparing with stored timestamps). */
    public static function startOfToday(): CarbonImmutable
    {
        return self::now()->startOfDay()->setTimezone(config('app.timezone'));
    }

    /** Whole days from that date until today (0 = today, 1 = yesterday). */
    public static function daysSince(CarbonInterface|string $date): int
    {
        $from = CarbonImmutable::parse(is_string($date) ? $date : $date->toDateString(), 'UTC');
        $to = CarbonImmutable::parse(self::today(), 'UTC');

        return (int) $from->diffInDays($to, false);
    }

    // ---- today's row ----------------------------------------------------------------------------

    /** Today's row for this learner, with its three tasks chosen (once). */
    public function todayFor(User $user): DailyActivity
    {
        $row = DailyActivity::createOrFirst(['user_id' => $user->id, 'day' => self::today()]);
        if ($row->tasks === null) {
            $row->tasks = $this->chooseTasks($user, self::today());
            $row->tasks_done = [];
            $row->save();
        }

        return $row;
    }

    /**
     * Three tasks for the day, the same all day. Only tasks the learner can do: reviewing needs
     * phrases to review, games need known phrases, speaking needs voices on.
     *
     * @return array<int, array{key: string, target: int}>
     */
    public function chooseTasks(User $user, string $day): array
    {
        $known = $user->reviewItems()->count();
        $pool = collect(array_keys(self::TASKS))->filter(fn (string $key) => match ($key) {
            'review' => $known >= self::TASKS['review'][1],
            'game' => $known >= Games::MIN_PHRASES,
            'speak' => (bool) config('luganda.voice'),
            default => true,
        });
        // A lesson task is always there for someone with nothing to review yet.
        $picked = $pool->sortBy(fn (string $key) => crc32($user->id.'|'.$day.'|'.$key))->take(self::TASKS_PER_DAY)->values();

        return $picked->map(fn (string $key) => ['key' => $key, 'target' => $this->target($user, $key)])->all();
    }

    private function target(User $user, string $key): int
    {
        return $key === 'goal' ? $this->goalMinutes($user) : (int) self::TASKS[$key][1];
    }

    public function goalMinutes(User $user): int
    {
        return $user->daily_goal ?: 10;
    }

    // ---- recording what was done ------------------------------------------------------------------

    /**
     * Something was practised: count it, add the practice time, and pay any task it finished.
     * Changes $user->cowries in memory; the caller saves the user.
     *
     * @param  string|null  $what  lesson | review | speak | game (null: time only)
     * @return array{tasks_done: array<int, string>, cowries_earned: int, all_done: bool, goal_met: bool, minutes: int, goal: int}
     */
    public function record(User $user, ?string $what, int $seconds = 0, array $extra = []): array
    {
        return DB::transaction(function () use ($user, $what, $seconds, $extra) {
            $row = $this->todayFor($user);
            $row = DailyActivity::whereKey($row->id)->lockForUpdate()->first();

            $row->active_seconds += max(0, min($seconds, (int) config('luganda.habit.max_seconds_per_save', 1200)));
            match ($what) {
                'lesson' => $row->lessons++,
                'review' => $row->reviews++,
                'speak' => $row->speaks++,
                'game' => $row->games++,
                default => null,
            };
            if ($what === 'lesson' && ($extra['score'] ?? null) === 100) {
                $row->perfect_lessons++;
            }

            $goal = $this->goalMinutes($user);
            $goalMet = false;
            if (! $row->goal_met_at && $row->minutes() >= $goal) {
                $row->goal_met_at = now();
                $goalMet = true;
            }

            // Pay each task once, and the bonus once.
            $done = $row->tasks_done ?? [];
            $newlyDone = [];
            $earned = 0;
            foreach ($row->tasks ?? [] as $task) {
                if (! in_array($task['key'], $done, true) && $this->progress($row, $task['key']) >= $task['target']) {
                    $done[] = $task['key'];
                    $newlyDone[] = $this->label($task);
                    $earned += (int) config('luganda.habit.task_cowries', 3);
                }
            }
            $allDone = count($row->tasks ?? []) > 0 && count($done) >= count($row->tasks);
            if ($allDone && ! $row->bonus_paid) {
                $row->bonus_paid = true;
                $earned += (int) config('luganda.habit.all_tasks_bonus', 5);
            }
            $row->tasks_done = $done;
            $row->cowries_earned += $earned;
            $row->save();
            $user->cowries += $earned;

            return [
                'tasks_done' => $newlyDone,
                'cowries_earned' => $earned,
                'all_done' => $allDone && $newlyDone !== [],
                'goal_met' => $goalMet,
                'minutes' => $row->minutes(),
                'goal' => $goal,
            ];
        });
    }

    public function progress(DailyActivity $row, string $key): int
    {
        return match ($key) {
            'lesson' => $row->lessons,
            'review' => $row->reviews,
            'speak' => $row->speaks,
            'game' => $row->games,
            'perfect' => $row->perfect_lessons,
            'goal' => $row->minutes(),
            default => 0,
        };
    }

    /** @param array{key: string, target: int} $task */
    public function label(array $task): string
    {
        return str_replace('{n}', (string) $task['target'], self::TASKS[$task['key']][0] ?? $task['key']);
    }

    /**
     * Everything the course path shows: the goal ring, the tasks and the freezes.
     *
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        $row = $this->todayFor($user);
        $goal = $this->goalMinutes($user);
        $done = $row->tasks_done ?? [];

        return [
            'minutes' => $row->minutes(),
            'goal' => $goal,
            'percent' => min(100, (int) round($row->minutes() / max(1, $goal) * 100)),
            'goal_met' => (bool) $row->goal_met_at,
            'tasks' => collect($row->tasks ?? [])->map(fn ($t) => [
                'key' => $t['key'],
                'label' => $this->label($t),
                'progress' => min($t['target'], $this->progress($row, $t['key'])),
                'target' => $t['target'],
                'done' => in_array($t['key'], $done, true),
            ])->all(),
            'all_done' => $row->bonus_paid,
            'task_cowries' => (int) config('luganda.habit.task_cowries', 3),
            'bonus' => (int) config('luganda.habit.all_tasks_bonus', 5),
            'freezes' => $user->streak_freezes,
            'max_freezes' => (int) config('luganda.habit.max_freezes', 2),
            'freeze_price' => (int) config('luganda.habit.freeze_price', 20),
            'freeze_saved' => $user->freeze_used_on && self::daysSince($user->freeze_used_on) <= 1,
        ];
    }

    // ---- streak freezes ----------------------------------------------------------------------------

    /** @return string|null why it can't be bought, or null when bought */
    public function buyFreeze(User $user): ?string
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->first();
            $price = (int) config('luganda.habit.freeze_price', 20);
            if ($user->streak_freezes >= (int) config('luganda.habit.max_freezes', 2)) {
                return 'You already have the most streak freezes you can hold.';
            }
            if ($user->cowries < $price) {
                return "A streak freeze costs {$price} cowries. Keep practising to earn more!";
            }
            $user->cowries -= $price;
            $user->streak_freezes++;
            $user->save();

            return null;
        });
    }
}
