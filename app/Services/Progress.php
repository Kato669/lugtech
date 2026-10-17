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
            $firstTime = ! $completion->exists;
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

    /** Practising on consecutive days grows the streak; missing a day resets it. */
    public function touchStreak(User $user): void
    {
        $today = today();
        $last = $user->last_active_on;

        if ($last && $last->isSameDay($today)) {
            return;
        }
        $user->current_streak = ($last && $last->isSameDay($today->copy()->subDay())) ? $user->current_streak + 1 : 1;
        $user->longest_streak = max($user->longest_streak, $user->current_streak);
        $user->last_active_on = $today;
    }
}
