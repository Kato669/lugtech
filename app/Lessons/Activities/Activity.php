<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * One kind of lesson step (learn, choose, match…). A scripted lesson stores
 * { type, payload } rows; the activity checks the payload and turns it into the
 * step data the lesson player shows.
 */
interface Activity
{
    /** Types this activity handles (e.g. ['choose', 'recall', 'listen']). @return string[] */
    public function types(): array;

    /** Problems with a payload, in plain words; empty when it's fine. @return string[] */
    public function validate(string $type, array $payload, LessonContext $ctx): array;

    /** The step for the player, or null to leave it out (e.g. a listening step with no audio yet). */
    public function present(string $type, array $payload, LessonContext $ctx): ?array;

    /** Phrases this step teaches or practises (they join the learner's review queue). @return int[] */
    public function phraseIds(array $payload): array;
}
