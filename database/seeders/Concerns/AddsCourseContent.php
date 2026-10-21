<?php

namespace Database\Seeders\Concerns;

use App\Models\Lesson;
use App\Models\LessonStep;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\Word;
use App\Support\Slugs;
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
     * A phrase a reviewer has re-spelled on the Check page is still found: by its slug, which is made
     * once from the English and never changes (see respelled()). The step then uses the reviewer's
     * spelling, and so do the teacher's lines.
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
            $renamed = []; // script spelling => the reviewer's spelling
            $id = function (string $text) use ($newPhrases, &$created, &$renamed): int {
                $phrase = Phrase::where('luganda', $text)->first();
                if (! $phrase && ($phrase = $this->respelled($text, $newPhrases))) {
                    $renamed[$text] = $phrase->luganda;
                }
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
                $payload = self::respellLines($payload, $renamed);
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

    /**
     * The phrase a script means when its spelling isn't in the database any more: a reviewer
     * re-spelled it. Found by its slug (made once from the English, never changed), either from
     * database/seeders/data/phrase-slugs.json (every phrase as first seeded) or from the English in
     * $newPhrases. Null when there is no such phrase (then the script's text is really missing).
     */
    protected function respelled(string $text, array $newPhrases): ?Phrase
    {
        static $slugs = null;
        $slugs ??= json_decode((string) file_get_contents(database_path('seeders/data/phrase-slugs.json')), true) ?: [];

        $new = $newPhrases[$text] ?? null;
        $slug = $slugs[$text] ?? ($new !== null ? Slugs::fromText(is_array($new) ? $new[0] : $new, '') : null);

        return $slug ? Phrase::where('slug', $slug)->first() : null;
    }

    /** Use the reviewer's spelling in the teacher's lines too (whole words only). */
    protected static function respellLines(array $payload, array $renamed): array
    {
        if (! $renamed) {
            return $payload;
        }
        $fix = function (string $line) use ($renamed): string {
            foreach ($renamed as $old => $new) {
                $line = preg_replace('/(?<![\p{L}])'.preg_quote($old, '/').'(?![\p{L}])/u', $new, $line);
            }

            return $line;
        };
        foreach (['say', 'title', 'prompt', 'note'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $payload[$key] = $fix($payload[$key]);
            }
        }
        if (isset($payload['feedback']) && is_array($payload['feedback'])) {
            $payload['feedback'] = array_map(fn ($l) => is_string($l) ? $fix($l) : $l, $payload['feedback']);
        }

        return $payload;
    }
}
