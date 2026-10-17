<?php

namespace Database\Seeders\Lessons;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use Database\Seeders\Concerns\AddsCourseContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One interactive classroom lesson, built like the Numbers 1–5 pilot
 * (LugandaNumbersPilotSeeder): an add-only script of steps, teacher lines in English.
 *
 * Safe to run again and again:
 *  - a lesson that already has steps is left exactly as it is;
 *  - if a phrase the script needs can't be found (for example a reviewer re-spelled it),
 *    nothing is saved, the lesson stays automatic and a warning says which phrase;
 *  - new Luganda only ever comes from newPhrases(), and arrives as a draft for the Check page.
 */
abstract class ClassroomLesson extends Seeder
{
    use AddsCourseContent;

    /** The lesson this script belongs to. */
    protected string $slug;

    /** @return array<int, array{0:string, 1:array}> the steps, as for addSteps() */
    abstract protected function steps(): array;

    /** @return array<string, string|array{0:string,1?:string}> new draft phrases: Luganda => English or [English, notes] */
    protected function newPhrases(): array
    {
        return [];
    }

    /** For a lesson that doesn't exist yet: create it here (see lessonOfExistingPhrases()). */
    protected function createLesson(): ?Lesson
    {
        return null;
    }

    public function run(): void
    {
        try {
            DB::transaction(function () {
                $lesson = Lesson::where('slug', $this->slug)->first() ?? $this->createLesson();
                if (! $lesson) {
                    $this->command?->warn("Lesson {$this->slug} not found: run the course seeders first.");

                    return;
                }
                if ($lesson->steps()->exists()) {
                    $this->command?->line("  {$lesson->title}: already interactive, left as it is.");

                    return;
                }
                $this->addSteps($lesson, $this->steps(), $this->newPhrases());
                $this->command?->info("  {$lesson->title} is now an interactive lesson ({$lesson->steps()->count()} steps).");
            });
        } catch (\InvalidArgumentException $e) {
            $this->command?->warn("  {$this->slug}: not converted, nothing was changed. {$e->getMessage()}"
                .' (Was it re-spelled on the Check page? Update the script to the new spelling.)');
        }
    }

    /**
     * A new lesson made only of phrases that already exist (so no new Luganda sneaks in
     * through the lesson's phrase list). Throws if one is missing.
     *
     * @param  string[]  $luganda
     */
    protected function lessonOfExistingPhrases(string $stageSlug, int $position, string $title, string $goal, array $luganda): Lesson
    {
        $phrases = Phrase::whereIn('luganda', $luganda)->get()->keyBy('luganda');
        foreach ($luganda as $text) {
            if (! $phrases->has($text)) {
                throw new \InvalidArgumentException("No phrase \"{$text}\".");
            }
        }
        $stage = Stage::where('slug', $stageSlug)->first() ?? throw new \InvalidArgumentException("No stage \"{$stageSlug}\".");
        $lesson = Lesson::create([
            'stage_id' => $stage->id, 'position' => $position, 'slug' => $this->slug,
            'title' => $title, 'goal' => $goal, 'is_published' => true,
        ]);
        foreach (array_values($luganda) as $i => $text) {
            $lesson->phrases()->attach($phrases[$text]->id, ['position' => $i + 1]);
        }

        return $lesson;
    }

    /** A picture for a step: ['name' => 'cowrie', 'count' => 3]. */
    protected function pic(string $name, int $count = 1): array
    {
        return ['name' => $name, 'count' => $count];
    }
}
