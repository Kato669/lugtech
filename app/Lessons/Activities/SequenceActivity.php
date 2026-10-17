<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * sequence: "What comes after Emu?" for anything with an order (numbers, days, steps).
 * payload: { sequence: [phrase ids, in order], ask: index of the one shown, prompt?, picture?, feedback? }
 * The answer is the next one; wrong answers come from the rest of the sequence.
 */
class SequenceActivity extends BaseActivity
{
    public function types(): array
    {
        return ['sequence'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $ids = (array) ($payload['sequence'] ?? []);
        $errors = [];
        if (count($ids) < 3) {
            $errors[] = 'sequence needs at least three phrases, in order';
        } elseif ($ctx->phrases($ids)->count() !== count($ids)) {
            $errors[] = 'some "sequence" phrases do not exist';
        }
        $ask = $payload['ask'] ?? null;
        if (! is_int($ask) || $ask < 0 || $ask >= count($ids) - 1) {
            $errors[] = '"ask" must point at a phrase that has one after it (0 to '.max(0, count($ids) - 2).')';
        }

        return array_merge($errors, $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $seq = $ctx->phrases((array) $payload['sequence']);
        $shown = $seq[$payload['ask']];
        $next = $seq[$payload['ask'] + 1];
        $wrong = $ctx->shuffle($seq->reject(fn ($p) => $p->id === $next->id || $p->id === $shown->id))->pluck('luganda')->take(2);

        return [
            'type' => 'sequence',
            'phrase' => $ctx->card($shown),
            'next' => $ctx->card($next),
            'options' => $ctx->shuffle($wrong->push($next->luganda))->values()->all(),
            'answer' => $next->luganda,
            'prompt' => "What comes after {$shown->luganda}?",
        ] + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return array_map('intval', array_filter((array) ($payload['sequence'] ?? []), 'is_numeric'));
    }
}
