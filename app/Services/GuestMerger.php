<?php

namespace App\Services;

use App\Models\GamePlay;
use App\Models\LessonCompletion;
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
                $existing->save();
            }

            $known = ReviewItem::where('user_id', $account->id)->pluck('phrase_id');
            ReviewItem::where('user_id', $guest->id)->whereNotIn('phrase_id', $known)->update(['user_id' => $account->id]);
            GamePlay::where('user_id', $guest->id)->update(['user_id' => $account->id]);
            SpeechAttempt::where('user_id', $guest->id)->update(['user_id' => $account->id]);

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
