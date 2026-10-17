<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Models\Phrase;

/** build: put the words of a phrase in order. payload: { phrase } (needs two or more words) */
class BuildActivity extends BaseActivity
{
    public function types(): array
    {
        return ['build'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = $this->requirePhrase($payload, $ctx);
        if (! $errors && count(self::tokens($ctx->phrase($payload['phrase'])->luganda)) < 2) {
            $errors[] = 'build needs a phrase of two or more words';
        }

        return array_merge($errors, $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        return $this->fromPhrase($ctx->phrase($payload['phrase']), $ctx) + $this->extras($payload);
    }

    public function fromPhrase(Phrase $p, LessonContext $ctx): array
    {
        $tokens = self::tokens($p->luganda);

        return [
            'type' => 'build',
            'phrase' => $ctx->card($p),
            'tiles' => $ctx->shuffle(collect($tokens))->values()->all(),
            'answer' => implode(' ', $tokens),
        ];
    }

    public static function tokens(string $text): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($text))));
    }
}
