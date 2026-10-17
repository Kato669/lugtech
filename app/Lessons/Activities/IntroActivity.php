<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * intro: the teacher opens the lesson. Not graded.
 * payload: { title?, say: "Today we are learning numbers!", character?, phrase?, picture? }
 * ("phrase" = an optional Luganda greeting shown and spoken with it, e.g. "Gyebale ko")
 */
class IntroActivity extends BaseActivity
{
    public function types(): array
    {
        return ['intro'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = filled($payload['say'] ?? null) ? [] : ['intro needs "say" (what the teacher says)'];
        if (isset($payload['phrase'])) {
            $errors = array_merge($errors, $this->requirePhrase($payload, $ctx));
        }

        return array_merge($errors, $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        return array_filter([
            'type' => 'intro',
            'title' => $payload['title'] ?? $ctx->lesson->title,
            'phrase' => isset($payload['phrase']) ? $ctx->card($ctx->phrase($payload['phrase'])) : null,
        ]) + $this->extras($payload) + ['character' => 'mukasa'];
    }
}
