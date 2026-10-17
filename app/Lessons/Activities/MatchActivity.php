<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * match: tap a Luganda word, then its meaning, until every pair is matched.
 * payload: { pairs: [phrase ids] (2 to 6), prompt? }
 * Correct = every pair matched without a wrong tap.
 */
class MatchActivity extends BaseActivity
{
    public const MIN = 2;

    public const MAX = 6;

    public function types(): array
    {
        return ['match'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $ids = (array) ($payload['pairs'] ?? []);
        $errors = [];
        if (count($ids) < self::MIN || count($ids) > self::MAX) {
            $errors[] = 'match needs '.self::MIN.' to '.self::MAX.' "pairs"';
        }
        $found = $ctx->phrases($ids);
        if ($found->count() !== count($ids)) {
            $errors[] = 'some "pairs" phrases do not exist';
        }
        if ($found->pluck('english')->unique()->count() !== $found->count()) {
            $errors[] = 'two pairs have the same English meaning';
        }

        return array_merge($errors, $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $pairs = $ctx->phrases((array) $payload['pairs'])->map(fn ($p) => $ctx->card($p));

        return [
            'type' => 'match',
            'pairs' => $ctx->shuffle($pairs)->values()->all(),
            'meanings' => $ctx->shuffle($pairs->pluck('english'))->values()->all(),
            'answer' => $pairs->map(fn ($c) => "{$c['luganda']} = {$c['english']}")->implode(' · '),
        ] + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return array_map('intval', array_filter((array) ($payload['pairs'] ?? []), 'is_numeric'));
    }
}
