<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;

/**
 * sort: tap a word, then the basket it belongs in ("One" or "Many", "omu-" or "aba-").
 * Phase 16. Self-graded like match: correct = everything sorted without a wrong basket.
 *
 * payload: { bins: ["One", "Many"] (2 or 3 English labels), groups: [[phrases for bin 1], [phrases for bin 2], …],
 *            say?, prompt?, feedback? }  3 to 8 phrases in all, each in one group only.
 */
class SortActivity extends BaseActivity
{
    public const MIN = 3;

    public const MAX = 8;

    public function types(): array
    {
        return ['sort'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = $this->checkExtras($payload);
        $bins = $payload['bins'] ?? null;
        $groups = $payload['groups'] ?? null;
        if (! is_array($bins) || count($bins) < 2 || count($bins) > 3 || array_filter($bins, fn ($b) => ! is_string($b) || $b === '')) {
            return array_merge($errors, ['sort needs 2 or 3 "bins" (English labels)']);
        }
        if (! is_array($groups) || count($groups) !== count($bins)) {
            return array_merge($errors, ['sort needs one list in "groups" per bin']);
        }
        $ids = collect($groups)->flatten()->all();
        if (count($ids) < self::MIN || count($ids) > self::MAX) {
            $errors[] = 'sort needs '.self::MIN.' to '.self::MAX.' phrases in all';
        }
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'a phrase can only be in one group';
        }
        foreach ($groups as $i => $group) {
            if (! is_array($group) || $group === []) {
                $errors[] = "group {$i} is empty";
            }
        }
        if ($ctx->phrases($ids)->count() !== count($ids)) {
            $errors[] = 'some "groups" phrases do not exist';
        }

        return $errors;
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $items = collect();
        $answer = [];
        foreach (array_values($payload['groups']) as $bin => $group) {
            $phrases = $ctx->phrases((array) $group);
            foreach ($phrases as $p) {
                $items->push($ctx->card($p) + ['bin' => $bin]);
            }
            $answer[] = $payload['bins'][$bin].': '.$phrases->pluck('luganda')->implode(', ');
        }

        return [
            'type' => 'sort',
            'bins' => array_values($payload['bins']),
            'items' => $ctx->shuffle($items)->values()->all(),
            'answer' => implode(' · ', $answer),
        ] + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return collect($payload['groups'] ?? [])->flatten()->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
