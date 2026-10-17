<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Models\Phrase;
use Illuminate\Support\Collection;

/**
 * choose: see Luganda, pick the English.   recall: see English, pick the Luganda.
 * listen: hear it, pick the English (left out while there is no recording to hear).
 * payload: { phrase, options?: [phrase ids for the wrong answers], prompt?, picture?, feedback? }
 * Without "options", wrong answers come from the lesson's other phrases.
 */
class ChoiceActivity extends BaseActivity
{
    public function types(): array
    {
        return ['choose', 'recall', 'listen'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = array_merge($this->requirePhrase($payload, $ctx), $this->checkExtras($payload));
        if (isset($payload['options'])) {
            $found = $ctx->phrases((array) $payload['options'])->count();
            if ($found !== count((array) $payload['options'])) {
                $errors[] = 'some "options" phrases do not exist';
            }
            if ($found < 1) {
                $errors[] = '"options" needs at least one wrong answer';
            }
        }

        return $errors;
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $answer = $ctx->phrase($payload['phrase']);
        $pool = isset($payload['options']) ? $ctx->phrases((array) $payload['options'])->push($answer) : $ctx->phrases;
        $step = $this->fromPhrase($type, $answer, $pool, $ctx);
        if ($type === 'listen' && ! $step['phrase']['audio']) {
            return null; // nothing to listen to yet
        }

        return $step + $this->extras($payload);
    }

    /** The multiple-choice step itself (also used by the automatic lessons). */
    public function fromPhrase(string $type, Phrase $answer, Collection $pool, LessonContext $ctx): array
    {
        $field = $type === 'recall' ? 'luganda' : 'english';
        $distractors = $ctx->shuffle($pool->where('id', '!=', $answer->id))
            ->pluck($field)->unique()->reject(fn ($v) => $v === $answer->{$field})->take(3);

        return [
            'type' => $type,
            'phrase' => $ctx->card($answer),
            'options' => $ctx->shuffle($distractors->push($answer->{$field}))->values()->all(),
            'answer' => $answer->{$field},
        ];
    }
}
