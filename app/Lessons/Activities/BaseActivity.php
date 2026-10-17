<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Lessons\Pictures;

/** Shared checks and extras (teacher line, picture, feedback) for activities. */
abstract class BaseActivity implements Activity
{
    public function phraseIds(array $payload): array
    {
        return isset($payload['phrase']) && is_numeric($payload['phrase']) ? [(int) $payload['phrase']] : [];
    }

    /** @return string[] */
    protected function requirePhrase(array $payload, LessonContext $ctx, string $key = 'phrase'): array
    {
        if (! isset($payload[$key])) {
            return ["needs \"{$key}\" (a phrase)"];
        }

        return $ctx->phrase($payload[$key]) ? [] : ["phrase {$payload[$key]} does not exist"];
    }

    /** @return string[] */
    protected function checkExtras(array $payload): array
    {
        $errors = [];
        if (isset($payload['picture'])) {
            $errors = array_merge($errors, Pictures::validate($payload['picture']));
        }
        if (isset($payload['character']) && ! in_array($payload['character'], ['mukasa', 'nakato', 'ngoma'], true)) {
            $errors[] = 'character must be mukasa, nakato or ngoma';
        }
        if (isset($payload['figure']) && (! is_scalar($payload['figure']) || mb_strlen((string) $payload['figure']) > 6)) {
            $errors[] = 'figure must be a short text, e.g. "3"';
        }
        if (! empty($payload['count_along']) && ! isset($payload['picture'])) {
            $errors[] = 'count_along needs a picture to count';
        }
        if (isset($payload['feedback']) && ! is_array($payload['feedback'])) {
            $errors[] = 'feedback must be { correct, wrong }';
        }

        return $errors;
    }

    /** Optional extras copied onto the step only when the script sets them. */
    protected function extras(array $payload): array
    {
        return array_filter([
            'say' => $payload['say'] ?? null,
            'character' => $payload['character'] ?? null,
            'picture' => isset($payload['picture']) ? Pictures::normalise($payload['picture']) : null,
            'prompt' => $payload['prompt'] ?? null,
            'figure' => isset($payload['figure']) ? (string) $payload['figure'] : null, // a big numeral or letter on the board
            'hide_word' => ! empty($payload['hide_word']) ? true : null,              // e.g. a counting game: picture only
            'count_along' => ! empty($payload['count_along']) ? true : null,          // tap each picture to count it
            'feedback' => isset($payload['feedback']) ? array_filter([
                'correct' => $payload['feedback']['correct'] ?? null,
                'wrong' => $payload['feedback']['wrong'] ?? null,
            ]) : null,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
