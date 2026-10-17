<?php

namespace App\Services;

use App\Lessons\Activities\BuildActivity;
use App\Lessons\Activities\ChoiceActivity;
use App\Lessons\ActivityRegistry;
use App\Lessons\LessonContext;
use App\Lessons\ScriptedLesson;
use App\Models\Lesson;
use App\Support\Conversations;

/**
 * Turns a lesson into the steps the player shows.
 *
 * Scripted lessons (lessons.mode = "scripted") play their own steps; see
 * app/Lessons and resources/views/lesson/steps. If a scripted lesson has no
 * usable steps, it falls back to the automatic plan below.
 *
 * Automatic lessons (every lesson so far) are generated from the ordered phrases:
 *   learn  - see and hear a new phrase, record yourself
 *   choose - pick the English meaning of a Luganda phrase
 *   recall - pick the Luganda for an English meaning
 *   listen - hear a phrase and pick what was said (only when audio exists)
 *   build  - put the words of a multi-word phrase in order
 *   dialogue - watch Mukasa and Nakato have a short conversation (not graded)
 *   converse - "Your turn": play one character and pick each of their replies
 * Content editors only order phrases; exercises are generated from them.
 */
class LessonPlan
{
    public function __construct(private ?int $seed = null) {}

    public function build(Lesson $lesson): array
    {
        $phrases = $lesson->phrases()->with('currentClips')->get();
        if ($this->seed !== null) {
            mt_srand($this->seed);
        }
        $ctx = new LessonContext($lesson, $phrases);

        if ($lesson->isScripted()) {
            $steps = app(ScriptedLesson::class)->build($lesson, $ctx);
            if ($steps) {
                return $steps;
            }
        }

        return $this->automatic($lesson, $ctx);
    }

    private function automatic(Lesson $lesson, LessonContext $ctx): array
    {
        $phrases = $ctx->phrases;
        if ($phrases->isEmpty()) {
            return [];
        }
        $registry = app(ActivityRegistry::class);
        /** @var ChoiceActivity $choice */
        $choice = $registry->get('choose');
        /** @var BuildActivity $build */
        $build = $registry->get('build');

        $steps = [];
        // Introduce phrases two at a time, each pair followed by a quick check.
        foreach ($phrases->chunk(2) as $pair) {
            foreach ($pair as $phrase) {
                $steps[] = ['type' => 'learn', 'phrase' => $ctx->card($phrase)];
            }
            foreach ($pair as $phrase) {
                $steps[] = $choice->fromPhrase('choose', $phrase, $phrases, $ctx);
            }
        }

        // Watch Mukasa and Nakato use what was just learned.
        $conversations = Conversations::forLesson($lesson);
        foreach ($conversations as $lines) {
            $steps[] = ['type' => 'dialogue', 'lines' => array_values($lines)];
        }

        // Then a mixed practice round over the whole lesson.
        foreach ($ctx->shuffle($phrases) as $phrase) {
            $steps[] = $phrase->audio_url
                ? $choice->fromPhrase('listen', $phrase, $phrases, $ctx)
                : $choice->fromPhrase('recall', $phrase, $phrases, $ctx);
        }
        foreach ($phrases as $phrase) {
            if (count(BuildActivity::tokens($phrase->luganda)) >= 2) {
                $steps[] = $build->fromPhrase($phrase, $ctx);
            }
        }

        // Finally the learner takes a part in each conversation ("Your turn").
        foreach ($conversations as $key => $lines) {
            $steps[] = Conversations::practice($key, $lines, $phrases->pluck('luganda'));
        }

        return $steps;
    }
}
