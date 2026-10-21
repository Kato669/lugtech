<?php

namespace Database\Seeders\Concerns;

use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\Word;
use Illuminate\Support\Facades\DB;

/**
 * Add-only helpers for course content. Safe to run again and again: anything
 * that already exists (including reviewers' corrections) is left untouched.
 */
trait AddsCourseContent
{
    /**
     * @param  array<int, array{0:string,1:string,2:?string,3:?string}>  $phrases  [luganda, english, literal, notes]
     */
    protected function addLesson(string $stageSlug, int $position, string $slug, string $title, string $goal, array $phrases): Lesson
    {
        $stage = Stage::where('slug', $stageSlug)->firstOrFail();
        $lesson = Lesson::firstOrCreate(['slug' => $slug], [
            'stage_id' => $stage->id, 'position' => $position, 'title' => $title, 'goal' => $goal,
            'is_published' => true, // draft Luganda is published so you can try it; reviewers approve each phrase
        ]);

        // Only fill a lesson's phrases when it is new, so admin re-ordering is kept.
        if ($lesson->wasRecentlyCreated) {
            foreach ($phrases as $pos => [$lg, $en, $literal, $notes]) {
                $phrase = Phrase::firstOrCreate(['luganda' => $lg], ['english' => $en, 'literal' => $literal, 'notes' => $notes]);
                $lesson->phrases()->syncWithoutDetaching([$phrase->id => ['position' => $pos + 1]]);
            }
        }

        return $lesson;
    }

    /** @param  array<int, array{0:string,1:string,2:?string}>  $words  [luganda, english, part of speech] */
    protected function addWords(array $words): void
    {
        foreach ($words as [$lg, $en, $pos]) {
            Word::firstOrCreate(['kind' => 'word', 'luganda' => $lg], ['english' => $en, 'part_of_speech' => $pos]);
        }
    }

    /** @param  array<int, array{0:string,1:string}>  $proverbs */
    protected function addProverbs(array $proverbs): void
    {
        foreach ($proverbs as [$lg, $en]) {
            Word::firstOrCreate(['kind' => 'proverb', 'luganda' => $lg], ['english' => $en]);
        }
    }

    /**
     * Make a lesson scripted, with these steps (add-only: a lesson that already has
     * steps is left alone, so edits made later are kept).
     *
     * Steps refer to phrases by their Luganda text, e.g.
     *   ['teach', ['phrase' => 'Emu', 'picture' => ['name' => 'cowrie', 'count' => 1]]]
     * The text is looked up (or the phrase created, from $newPhrases) and stored as an id.
     * New phrases are created as drafts (for the Check page) and added to the lesson's phrase list.
     * Nothing is saved unless every step can be built.
     *
     * @param  array<int, array{0:string, 1:array}>  $steps
     * @param  array<string, string|array{0:string,1?:string}>  $newPhrases  Luganda => English (or [English, notes for reviewers]) for phrases that may not exist yet
     */
    protected function addSteps(Lesson $lesson, array $steps, array $newPhrases = []): void
    {
        if ($lesson->steps()->exists()) {
            return;
        }

        // All or nothing: if a phrase can't be found (e.g. a reviewer re-spelled it), nothing is
        // saved, so the lesson stays automatic and the seeder can simply be run again later.
        DB::transaction(function () use ($lesson, $steps, $newPhrases) {
            $created = [];
            $id = function (string $text) use ($newPhrases, &$created): int {
                $phrase = Phrase::where('luganda', $text)->first();
                if (! $phrase) {
                    $new = $newPhrases[$text] ?? throw new \InvalidArgumentException("No phrase \"{$text}\" (add it to \$newPhrases)");
                    [$english, $notes] = is_array($new) ? [$new[0], $new[1] ?? null] : [$new, null];
                    $phrase = Phrase::create(['luganda' => $text, 'english' => $english, 'notes' => $notes]); // a draft: is_reviewed = false
                    $created[] = $phrase->id;
                }

                return $phrase->id;
            };
            $ids = fn (array $texts) => array_map(fn ($t) => is_int($t) ? $t : $id($t), $texts);

            foreach (array_values($steps) as $pos => [$type, $payload]) {
                foreach (['phrase'] as $key) {
                    if (isset($payload[$key]) && is_string($payload[$key])) {
                        $payload[$key] = $id($payload[$key]);
                    }
                }
                foreach (['options', 'pairs', 'sequence'] as $key) {
                    if (isset($payload[$key])) {
                        $payload[$key] = $ids((array) $payload[$key]);
                    }
                }
                if (isset($payload['lines'])) {
                    $payload['lines'] = array_map(fn ($l) => ['who' => $l['who'], 'phrase' => is_int($l['phrase']) ? $l['phrase'] : $id($l['phrase'])], $payload['lines']);
                }
                if (isset($payload['choices'])) {
                    $payload['choices'] = array_map(fn ($c) => ['phrase' => is_int($c['phrase']) ? $c['phrase'] : $id($c['phrase'])] + $c, $payload['choices']);
                }
                foreach (['rows', 'groups'] as $key) { // Phase 16: pattern rows, sort groups
                    if (isset($payload[$key])) {
                        $payload[$key] = array_map(fn ($list) => $ids((array) $list), $payload[$key]);
                    }
                }
                if (isset($payload['wrong'])) {
                    $payload['wrong'] = array_map(fn ($list) => $ids((array) $list), $payload['wrong']);
                }
                LessonStep::create(['lesson_id' => $lesson->id, 'position' => $pos + 1, 'type' => $type, 'payload' => $payload]);
            }

            // New draft phrases join the lesson's phrase list, so the Check page shows them under
            // this lesson and the automatic version of the lesson teaches them too.
            $next = (int) DB::table('lesson_phrase')->where('lesson_id', $lesson->id)->max('position');
            foreach (array_unique($created) as $phraseId) {
                $lesson->phrases()->syncWithoutDetaching([$phraseId => ['position' => ++$next]]);
            }

            $lesson->forceFill(['mode' => 'scripted'])->save();
        });
    }
}
