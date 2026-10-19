<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Progress
{
    public const COWRIES_PER_LESSON = 10;

    public const COWRIES_PERFECT_BONUS = 5;

    public const COWRIES_PER_REVIEW = 1;

    /** @return array{cowries_earned:int, streak:int, first_time:bool} */
    public function completeLesson(User $user, Lesson $lesson, int $score): array
    {
        $score = max(0, min(100, $score));

        return DB::transaction(function () use ($user, $lesson, $score) {
            $completion = LessonCompletion::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            $firstTime = ! $completion->exists || ! $completion->times_completed; // a lesson the placement check marked still earns its full cowries
            $completion->best_score = max($completion->best_score ?? 0, $score);
            $completion->times_completed = ($completion->times_completed ?? 0) + 1;
            $completion->last_completed_at = now();
            $completion->save();

            // Every phrase in the lesson joins the learner's review queue.
            foreach ($lesson->taughtPhraseIds() as $phraseId) {
                ReviewItem::firstOrCreate(
                    ['user_id' => $user->id, 'phrase_id' => $phraseId],
                    ['due_at' => now()->addDay()],
                );
            }

            $earned = $firstTime ? self::COWRIES_PER_LESSON : (int) ceil(self::COWRIES_PER_LESSON / 2);
            if ($score === 100) {
                $earned += self::COWRIES_PERFECT_BONUS;
            }
            $user->cowries += $earned;
            $this->touchStreak($user);
            $user->save();

            return ['cowries_earned' => $earned, 'streak' => $user->current_streak, 'first_time' => $firstTime];
        });
    }

    public function gradeReview(User $user, ReviewItem $item, bool $correct): void
    {
        $item->grade($correct);
        if ($correct) {
            $user->cowries += self::COWRIES_PER_REVIEW;
        }
        $this->touchStreak($user);
        $user->save();
    }

    /**
     * Practising on consecutive days grows the streak; missing a day resets it, unless streak
     * freezes cover the days missed (Phase 12: one freeze per missed day, used automatically).
     * Days follow LUGANDA_TIMEZONE.
     */
    public function touchStreak(User $user): void
    {
        $today = DailyHabit::today();
        $last = $user->last_active_on;
        $gap = $last ? DailyHabit::daysSince($last) : null;

        if ($gap !== null && $gap <= 0) {
            return;
        }
        if ($gap === 1) {
            $user->current_streak++;
        } elseif ($gap !== null && $gap - 1 <= $user->streak_freezes && $user->current_streak > 0) {
            $user->streak_freezes -= $gap - 1;
            $user->freeze_used_on = $today;
            $user->current_streak++;
        } else {
            $user->current_streak = 1;
        }
        $user->longest_streak = max($user->longest_streak, $user->current_streak);
        $user->last_active_on = $today;
    }
}
