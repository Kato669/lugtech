<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Support\Conversations;

/**
 * converse ("Your turn"): the learner plays one character and picks each of their replies.
 * payload: { conversation: "greeting" }
 *      or: { lines: [{ who, phrase }, …], you: 0|1, wrong?: { lineIndex: [phrase ids] } }
 */
class ConverseActivity extends BaseActivity
{
    public function types(): array
    {
        return ['converse'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = DialogueActivity::checkLines($payload, $ctx);
        if (! isset($payload['conversation'])) {
            if (! in_array($payload['you'] ?? null, [0, 1], true)) {
                $errors[] = '"you" must be 0 (Mukasa) or 1 (Nakato)';
            } elseif (! collect($payload['lines'] ?? [])->contains(fn ($l) => ($l['who'] ?? null) === $payload['you'])) {
                $errors[] = 'the learner has no lines to say';
            }
            foreach ($payload['wrong'] ?? [] as $i => $ids) {
                if ($ctx->phrases((array) $ids)->count() !== count((array) $ids)) {
                    $errors[] = "wrong replies for line {$i}: some phrases do not exist";
                }
            }
        }

        return array_merge($errors, $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $fallback = $ctx->phrases->pluck('luganda');
        if (isset($payload['conversation'])) {
            $lines = DialogueActivity::lines($payload, $ctx);

            return $lines ? Conversations::practice($payload['conversation'], $lines, $fallback) + $this->extras($payload) : null;
        }

        $lines = DialogueActivity::lines($payload, $ctx);
        $you = $payload['you'];
        $options = [];
        foreach ($lines as $i => $line) {
            if ($line['who'] !== $you) {
                continue;
            }
            $wrong = $ctx->phrases((array) ($payload['wrong'][$i] ?? []))->pluck('luganda')->reject(fn ($w) => $w === $line['luganda'])->values();
            $others = collect($lines)->pluck('luganda');
            $wrong = $wrong->merge($ctx->shuffle($fallback->diff($others)->diff($wrong)))->take(2);
            $options[$i] = $ctx->shuffle($wrong->push($line['luganda']))->values()->all();
        }

        return [
            'type' => 'converse',
            'you' => $you,
            'lines' => $lines,
            'options' => $options,
            'answer' => collect($lines)->where('who', $you)->pluck('luganda')->implode(' … '),
        ] + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return (new DialogueActivity)->phraseIds($payload);
    }
}
