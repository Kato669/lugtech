<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\GamePlay;
use App\Models\LessonCompletion;
use App\Models\PushSubscription;
use App\Models\ReviewItem;
use App\Models\SpeechAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** When a guest logs into an existing account, carry their progress across. */
class GuestMerger
{
    public function merge(User $guest, User $account): void
    {
        if (! $guest->is_guest || $guest->is($account)) {
            return;
        }

        DB::transaction(function () use ($guest, $account) {
            foreach (LessonCompletion::where('user_id', $guest->id)->get() as $c) {
                $existing = LessonCompletion::firstOrNew(['user_id' => $account->id, 'lesson_id' => $c->lesson_id]);
                $existing->best_score = max($existing->best_score ?? 0, $c->best_score);
                $existing->times_completed = ($existing->times_completed ?? 0) + $c->times_completed;
                $existing->last_completed_at = max($existing->last_completed_at, $c->last_completed_at);
                $existing->placed_at ??= $c->placed_at;
                $existing->save();
            }

            $known = ReviewItem::where('user_id', $account->id)->pluck('phrase_id');
            ReviewItem::where('user_id', $guest->id)->whereNotIn('phrase_id', $known)->update(['user_id' => $account->id]);
            GamePlay::where('user_id', $guest->id)->update(['user_id' => $account->id]);
            SpeechAttempt::where('user_id', $guest->id)->update(['user_id' => $account->id]);

            // The daily habit (Phase 12): practice per day (added together; tasks already paid stay paid,
            // nothing is paid twice), reminders on this browser, and streak freezes (up to the limit).
            foreach (DailyActivity::where('user_id', $guest->id)->get() as $day) {
                $mine = DailyActivity::where('user_id', $account->id)->where('day', $day->day)->first();
                if (! $mine) {
                    $day->update(['user_id' => $account->id]);

                    continue;
                }
                foreach (['active_seconds', 'lessons', 'perfect_lessons', 'reviews', 'speaks', 'games', 'cowries_earned'] as $count) {
                    $mine->{$count} += $day->{$count};
                }
                $mine->tasks_done = array_values(array_unique(array_merge($mine->tasks_done ?? [], array_intersect($day->tasks_done ?? [], collect($mine->tasks ?? [])->pluck('key')->all()))));
                $mine->bonus_paid = $mine->bonus_paid || $day->bonus_paid;
                $mine->goal_met_at ??= $day->goal_met_at;
                $mine->save();
                $day->delete();
            }
            PushSubscription::where('user_id', $guest->id)->update(['user_id' => $account->id]);
            $account->reminder_hour ??= $guest->reminder_hour;
            $account->timezone ??= $guest->timezone;
            $account->streak_freezes = min((int) config('luganda.habit.max_freezes', 2), $account->streak_freezes + $guest->streak_freezes);

            // The welcome answers, if the account never gave its own.
            $account->learning_reason ??= $guest->learning_reason;
            $account->daily_goal ??= $guest->daily_goal;
            $account->onboarded_at ??= $guest->onboarded_at;

            $account->cowries += $guest->cowries;
            $account->longest_streak = max($account->longest_streak, $guest->longest_streak);
            if ($guest->last_active_on && (! $account->last_active_on || $guest->last_active_on->gt($account->last_active_on))) {
                $account->current_streak = max($account->current_streak, $guest->current_streak);
                $account->last_active_on = $guest->last_active_on;
            }
            $account->save();
            $guest->delete();
        });
    }
}
