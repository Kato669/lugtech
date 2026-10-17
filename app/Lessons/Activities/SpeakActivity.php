<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * speak: the learner's turn to say a phrase: hear it, record yourself, compare, and say
 * whether it sounded the same. Not graded (speaking is practice, and a microphone may not
 * be available). Left out while voices are off or the phrase has no recording to compare with.
 * payload: { phrase, say?, character?, picture?, figure? }
 */
class SpeakActivity extends BaseActivity
{
    public function types(): array
    {
        return ['speak'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        return array_merge($this->requirePhrase($payload, $ctx), $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $card = $ctx->card($ctx->phrase($payload['phrase']));
        if (! config('luganda.voice') || ! $card['audio']) {
            return null;
        }

        return ['type' => 'speak', 'phrase' => $card] + $this->extras($payload);
    }
}
