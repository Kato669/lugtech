<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * reward: a celebration in the middle of a lesson ("Well done, you can count to five!").
 * Not graded; cowries are still given when the lesson is finished.
 * payload: { say?, character? }
 */
class RewardActivity extends BaseActivity
{
    public function types(): array
    {
        return ['reward'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        return $this->checkExtras($payload);
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        return ['type' => 'reward'] + $this->extras($payload) + ['say' => 'Kulika! Well done!', 'character' => 'ngoma'];
    }

    public function phraseIds(array $payload): array
    {
        return [];
    }
}
