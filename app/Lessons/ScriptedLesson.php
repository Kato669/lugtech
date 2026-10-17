<?php

namespace App\Lessons;

use App\Models\Lesson;
use App\Models\LessonStep;
use Illuminate\Support\Facades\Log;

/**
 * Plays a lesson written step by step (lessons.mode = "scripted").
 * A step with a problem is left out (and logged) rather than breaking the lesson;
 * `php artisan app:lesson-check` lists any problems.
 */
class ScriptedLesson
{
    public function __construct(private ActivityRegistry $registry) {}

    /** @return array<int, array> steps for the player */
    public function build(Lesson $lesson, LessonContext $ctx): array
    {
        $steps = $lesson->steps()->get();
        $ctx->preload($steps->flatMap(fn (LessonStep $s) => $this->registry->has($s->type)
            ? $this->registry->get($s->type)->phraseIds($s->payload ?? []) : [])->all());

        $out = [];
        foreach ($steps as $step) {
            $problems = $this->problems($step, $ctx);
            if ($problems) {
                Log::warning("Lesson {$lesson->slug}, step {$step->position} ({$step->type}) skipped: ".implode('; ', $problems));

                continue;
            }
            $presented = $this->registry->get($step->type)->present($step->type, $step->payload ?? [], $ctx);
            if ($presented !== null) {
                $out[] = $presented;
            }
        }

        return $out;
    }

    /** @return string[] what is wrong with a step (empty when fine) */
    public function problems(LessonStep $step, LessonContext $ctx): array
    {
        if (! $this->registry->has($step->type)) {
            return ["unknown activity \"{$step->type}\""];
        }

        return $this->registry->get($step->type)->validate($step->type, $step->payload ?? [], $ctx);
    }
}
