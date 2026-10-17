<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * learn / teach: meet a new phrase (see it, hear it, say it).
 * payload: { phrase, picture?, say?, character? }  ("say" = the teacher's line, in English)
 */
class LearnActivity extends BaseActivity
{
    public function types(): array
    {
        return ['learn', 'teach'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        return array_merge($this->requirePhrase($payload, $ctx), $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        return ['type' => 'learn', 'phrase' => $ctx->card($ctx->phrase($payload['phrase']))] + $this->extras($payload);
    }
}
