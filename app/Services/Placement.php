<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Phrase;
use App\Models\ReviewItem;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The placement check (Phase 10), for people who grew up hearing Luganda.
 *
 * Four questions from each of the first three stages, spread across each stage's lessons and
 * built only from the course's own phrases (no new Luganda). Three of each four are "what does
 * this mean?" (hear or read the Luganda, pick the English); the fourth goes the other way.
 *
 * Marking: a stage with at most one mistake is known, and the check moves on to the next stage.
 * In the first stage that isn't, the lessons before the first mistake are known, and the check
 * stops there (the page also stops as soon as a stage has two mistakes).
 * Known lessons are marked on the path (no cowries) and their phrases join Review over a week.
 */
class Placement
{
    public const STAGES = 3;

    public const PER_STAGE = 4;

    public const MISTAKES_ALLOWED = 1;

    /** @return list<array<string, mixed>> */
    public function questions(): array
    {
        $questions = [];
        foreach ($this->stages() as $s => $stage) {
            $lessons = $stage->publishedLessons->filter(fn (Lesson $l) => $l->phrases->isNotEmpty())->values();
            $stagePhrases = $lessons->flatMap->phrases->unique('id');
            foreach ($this->spread($lessons) as $q => $lesson) {
                $kind = $q === self::PER_STAGE - 1 ? 'say' : 'meaning';
                if ($question = $this->question($stage, $s, $lesson, $kind, $stagePhrases)) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }

    /**
     * @param  list<array<string, mixed>>  $questions  as made by questions() (kept on the server)
     * @param  array<int|string, mixed>  $answers  question number => chosen option number (missing or null: "I don't know")
     * @return array{known: list<int>, stages: list<array<string, mixed>>}
     */
    public function grade(array $questions, array $answers): array
    {
        $known = [];
        $report = [];
        $stopped = false;
        foreach (collect($questions)->groupBy('stage', preserveKeys: true) as $s => $group) {
            $stage = Stage::with('publishedLessons')->find($group->first()['stage_id']);
            $right = $group->filter(fn ($q, $i) => isset($answers[$i]) && (int) $answers[$i] === $q['answer']);
            $firstMistake = $group->first(fn ($q, $i) => ! $right->has($i));
            $passed = ! $stopped && $group->count() - $right->count() <= self::MISTAKES_ALLOWED;

            $lessonsKnown = collect();
            if ($passed) {
                $lessonsKnown = $stage->publishedLessons;
            } elseif (! $stopped) {
                $lessonsKnown = $stage->publishedLessons->filter(fn (Lesson $l) => $l->position < $firstMistake['lesson_position']);
                $stopped = true;
            }
            $known = array_merge($known, $lessonsKnown->pluck('id')->all());
            $report[] = [
                'title' => $stage->title_lg.' · '.$stage->title_en,
                'asked' => $group->count(),
                'right' => $right->count(),
                'known' => $lessonsKnown->count(),
                'of' => $stage->publishedLessons->count(),
            ];
        }

        return ['known' => $known, 'stages' => $report];
    }

    /** Mark lessons as known, and put their phrases into Review, a few each day over the coming week. */
    public function apply(User $user, array $lessonIds): int
    {
        return DB::transaction(function () use ($user, $lessonIds) {
            $marked = 0;
            $phraseIds = [];
            foreach (Lesson::whereIn('id', $lessonIds)->get() as $lesson) {
                $completion = LessonCompletion::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
                if ($completion->exists) {
                    continue; // already played for real (or already placed)
                }
                $completion->forceFill(['best_score' => 0, 'times_completed' => 0, 'placed_at' => now()])->save();
                $phraseIds = array_merge($phraseIds, $lesson->taughtPhraseIds());
                $marked++;
            }
            foreach (array_values(array_unique($phraseIds)) as $i => $phraseId) {
                ReviewItem::firstOrCreate(['user_id' => $user->id, 'phrase_id' => $phraseId], ['due_at' => now()->startOfDay()->addDays(1 + $i % 7)]);
            }

            return $marked;
        });
    }

    /** Start again from the beginning: forget lessons marked by the check (lessons really played stay). */
    public function undo(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $placed = $user->completions()->whereNotNull('placed_at')->where('times_completed', 0)->get();
            if ($placed->isEmpty()) {
                return 0;
            }
            $stillTaught = $user->completions()->where('times_completed', '>', 0)->with('lesson')->get()
                ->flatMap(fn ($c) => $c->lesson?->taughtPhraseIds() ?? [])->all();
            $placedPhrases = $placed->load('lesson')->flatMap(fn ($c) => $c->lesson?->taughtPhraseIds() ?? [])->all();
            // Phrases only the check brought in, and never practised since (grading always sets an interval).
            $user->reviewItems()->whereIn('phrase_id', array_diff($placedPhrases, $stillTaught))
                ->where('repetitions', 0)->where('interval_days', 0)->delete();
            LessonCompletion::whereIn('id', $placed->pluck('id'))->delete();

            return $placed->count();
        });
    }

    /** @return Collection<int, Stage> the first stages that have lessons */
    private function stages(): Collection
    {
        return Stage::with(['publishedLessons' => fn ($q) => $q->orderBy('position')->with('phrases')])
            ->orderBy('position')->get()
            ->filter(fn (Stage $s) => $s->publishedLessons->isNotEmpty())
            ->take(self::STAGES)->values();
    }

    /** Up to four lessons, evenly spread from the first to the last. */
    private function spread(Collection $lessons): Collection
    {
        $n = $lessons->count();
        if ($n <= self::PER_STAGE) {
            return $lessons;
        }

        return collect(range(0, self::PER_STAGE - 1))
            ->map(fn ($i) => $lessons[(int) round($i * ($n - 1) / (self::PER_STAGE - 1))])
            ->unique('id')->values();
    }

    private function question(Stage $stage, int $s, Lesson $lesson, string $kind, Collection $stagePhrases): ?array
    {
        $candidates = $lesson->phrases->where('is_reviewed', true);
        $phrase = ($candidates->isNotEmpty() ? $candidates : $lesson->phrases)->shuffle()->first();
        $field = $kind === 'meaning' ? 'english' : 'luganda';

        // Wrong answers: other lessons' phrases from this stage, clearly different from the answer.
        $norm = fn (?string $t) => mb_strtolower(trim((string) $t, ' ?!.,'));
        $answer = $norm($phrase->{$field});
        $different = fn (Phrase $p) => $p->{$field} && ! str_contains($norm($p->{$field}), $answer) && ! str_contains($answer, $norm($p->{$field}));
        $others = $lesson->phrases->pluck('id');
        $wrong = $stagePhrases->reject(fn (Phrase $p) => $others->contains($p->id))->filter($different);
        if ($wrong->unique(fn ($p) => $norm($p->{$field}))->count() < 3) {
            $wrong = Phrase::whereNotIn('id', $others)->get()->filter($different); // a small stage: any other phrase
        }
        $wrong = $wrong->unique(fn ($p) => $norm($p->{$field}))->shuffle()->take(3);
        if ($wrong->count() < 3) {
            return null;
        }
        $options = $wrong->pluck($field)->push($phrase->{$field})->shuffle()->values();

        return [
            'stage' => $s,
            'stage_id' => $stage->id,
            'lesson_id' => $lesson->id,
            'lesson_position' => $lesson->position,
            'phrase_id' => $phrase->id,
            'kind' => $kind,
            'prompt' => $kind === 'meaning' ? $phrase->luganda : $phrase->english,
            'audio' => $kind === 'meaning' ? $phrase->audio_url : null, // the other way, hearing it would give it away
            'options' => $options->all(),
            'answer' => $options->search($phrase->{$field}),
        ];
    }
}
