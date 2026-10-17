<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Support\Conversations;

/**
 * dialogue: watch Mukasa (0) and Nakato (1) talk.
 * payload: { conversation: "greeting" }  (a script in app/Support/Conversations.php)
 *      or: { lines: [{ who: 0|1, phrase }, …] }
 */
class DialogueActivity extends BaseActivity
{
    public function types(): array
    {
        return ['dialogue'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        return array_merge(self::checkLines($payload, $ctx), $this->checkExtras($payload));
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $lines = self::lines($payload, $ctx);

        return $lines ? ['type' => 'dialogue', 'lines' => $lines] + $this->extras($payload) : null;
    }

    public function phraseIds(array $payload): array
    {
        return collect($payload['lines'] ?? [])->pluck('phrase')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all();
    }

    /** @return string[] */
    public static function checkLines(array $payload, LessonContext $ctx): array
    {
        if (isset($payload['conversation'])) {
            return isset(Conversations::SCRIPTS[$payload['conversation']]) ? [] : ["no conversation called \"{$payload['conversation']}\""];
        }
        $lines = $payload['lines'] ?? null;
        if (! is_array($lines) || count($lines) < 2) {
            return ['needs "conversation" or at least two "lines"'];
        }
        $errors = [];
        foreach ($lines as $i => $line) {
            if (! in_array($line['who'] ?? null, [0, 1], true)) {
                $errors[] = "line {$i}: who must be 0 (Mukasa) or 1 (Nakato)";
            }
            if (! $ctx->phrase($line['phrase'] ?? null)) {
                $errors[] = "line {$i}: phrase does not exist";
            }
        }

        return $errors;
    }

    /** Lines in the shape the talking scene uses. */
    public static function lines(array $payload, LessonContext $ctx): array
    {
        if (isset($payload['conversation'])) {
            return array_values(Conversations::all()[$payload['conversation']] ?? []);
        }

        return collect($payload['lines'])->map(function ($line) use ($ctx) {
            $p = $ctx->phrase($line['phrase']);

            return [
                'who' => $line['who'],
                'luganda' => $p->luganda,
                'english' => $p->english,
                'audio' => $p->voiceUrl($line['who'] === Conversations::MUKASA ? 'male' : 'female'),
                'phrase_id' => $p->id,
            ];
        })->all();
    }
}
