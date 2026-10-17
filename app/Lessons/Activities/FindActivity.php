<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Lessons\Pictures;

/**
 * find: hear or see a word, then tap the picture that shows it ("Find Bbiri" → the card with two cowries).
 * payload: { phrase, choices: [{ phrase, picture: { name, count } }, …] (2 to 4, including the answer), prompt? }
 */
class FindActivity extends BaseActivity
{
    public function types(): array
    {
        return ['find'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = array_merge($this->requirePhrase($payload, $ctx), $this->checkExtras($payload));
        $choices = $payload['choices'] ?? [];
        if (! is_array($choices) || count($choices) < 2 || count($choices) > 4) {
            return array_merge($errors, ['find needs 2 to 4 "choices"']);
        }
        foreach ($choices as $i => $choice) {
            if (! $ctx->phrase($choice['phrase'] ?? null)) {
                $errors[] = "choice {$i}: phrase does not exist";
            }
            $errors = array_merge($errors, array_map(fn ($e) => "choice {$i}: {$e}", Pictures::validate($choice['picture'] ?? null)));
        }
        if (isset($payload['phrase']) && ! collect($choices)->contains(fn ($c) => ($c['phrase'] ?? null) == $payload['phrase'])) {
            $errors[] = 'the right answer must be one of the "choices"';
        }

        return $errors;
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $target = $ctx->phrase($payload['phrase']);
        $cards = collect($payload['choices'])->map(function ($c) use ($ctx) {
            $p = $ctx->phrase($c['phrase']);

            return ['id' => $p->id, 'label' => $p->english, 'luganda' => $p->luganda, 'picture' => Pictures::normalise($c['picture'])];
        });

        return [
            'type' => 'find',
            'phrase' => $ctx->card($target),
            'cards' => $ctx->shuffle($cards)->values()->all(),
            'answer' => $target->english,
            'prompt' => "Find {$target->luganda}",
        ] + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return collect($payload['choices'] ?? [])->pluck('phrase')->push($payload['phrase'] ?? null)
            ->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
