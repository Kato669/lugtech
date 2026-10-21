<?php

namespace App\Lessons\Activities;

use App\Lessons\LessonContext;
use App\Lessons\Pictures;

/**
 * pattern: a "How it works" grammar card. Phrases side by side in columns (one → many,
 * a thing → three of them), with the start of each word that carries the pattern highlighted.
 * Not graded. Phase 16 (noun classes, counting things).
 *
 * payload: {
 *   rows: [[phrase, phrase], …] (2 to 6 rows, each with the same number of phrases, 1 to 3),
 *   heads?: ["One", "Many"] (English column headings, one per column),
 *   marks?: [["omu", "omw"], ["aba"]] (per column: word starts to highlight; the longest match wins),
 *   pictures?: [[picture, picture], …] (per row, per column),
 *   title?, note? (English), say?, character?
 * }
 *
 * A highlight only ever marks letters the word really starts with, so nothing is made up.
 */
class PatternActivity extends BaseActivity
{
    public const MAX_ROWS = 6;

    public const MAX_COLUMNS = 3;

    public function types(): array
    {
        return ['pattern'];
    }

    public function validate(string $type, array $payload, LessonContext $ctx): array
    {
        $errors = $this->checkExtras($payload);
        $rows = $payload['rows'] ?? null;
        if (! is_array($rows) || count($rows) < 2 || count($rows) > self::MAX_ROWS) {
            return array_merge($errors, ['pattern needs 2 to '.self::MAX_ROWS.' "rows"']);
        }
        $columns = is_array($rows[0] ?? null) ? count($rows[0]) : 0;
        if ($columns < 1 || $columns > self::MAX_COLUMNS) {
            return array_merge($errors, ['each row needs 1 to '.self::MAX_COLUMNS.' phrases']);
        }
        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row) || count($row) !== $columns) {
                $errors[] = "row {$i}: every row needs {$columns} phrases";

                continue;
            }
            foreach ($row as $id) {
                if (! $ctx->phrase($id)) {
                    $errors[] = "row {$i}: phrase {$id} does not exist";
                }
            }
        }
        if (isset($payload['heads']) && (! is_array($payload['heads']) || count($payload['heads']) !== $columns)) {
            $errors[] = "\"heads\" needs one heading per column ({$columns})";
        }
        if (isset($payload['marks'])) {
            $marks = $payload['marks'];
            if (! is_array($marks) || count($marks) !== $columns) {
                $errors[] = "\"marks\" needs one list per column ({$columns})";
            } else {
                foreach ($marks as $list) {
                    foreach ((array) $list as $m) {
                        if (! is_string($m) || ! preg_match('/^\p{L}{1,4}$/u', $m)) {
                            $errors[] = 'a mark must be 1 to 4 letters, e.g. "omu"';
                        }
                    }
                }
            }
        }
        if (isset($payload['pictures'])) {
            foreach ((array) $payload['pictures'] as $i => $row) {
                foreach ((array) $row as $picture) {
                    if ($picture !== null) {
                        $errors = array_merge($errors, array_map(fn ($e) => "pictures {$i}: {$e}", Pictures::validate($picture)));
                    }
                }
            }
        }

        return $errors;
    }

    public function present(string $type, array $payload, LessonContext $ctx): ?array
    {
        $ctx->preload(array_merge(...array_map(fn ($r) => (array) $r, $payload['rows'])));
        $marks = $payload['marks'] ?? [];
        $rows = [];
        foreach (array_values($payload['rows']) as $i => $row) {
            $cells = [];
            foreach (array_values($row) as $c => $id) {
                $card = $ctx->card($ctx->phrase($id));
                $picture = $payload['pictures'][$i][$c] ?? null;
                $cells[] = $card + [
                    'parts' => self::highlight($card['luganda'], (array) ($marks[$c] ?? [])),
                    'picture' => $picture ? Pictures::normalise($picture) : null,
                ];
            }
            $rows[] = $cells;
        }

        return array_filter([
            'type' => 'pattern',
            'rows' => $rows,
            'heads' => $payload['heads'] ?? null,
            'title' => $payload['title'] ?? null,
            'note' => $payload['note'] ?? null,
        ], fn ($v) => $v !== null) + $this->extras($payload);
    }

    public function phraseIds(array $payload): array
    {
        return collect($payload['rows'] ?? [])->flatten()->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Split a text into parts, marking the start of each word that begins with one of $marks
     * (case-insensitive; the longest that fits, and never the whole word).
     *
     * @param  string[]  $marks
     * @return array<int, array{t:string, m:bool}>
     */
    public static function highlight(string $text, array $marks): array
    {
        usort($marks, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $parts = [];
        foreach (preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $word) {
            $hit = null;
            foreach ($marks as $m) {
                if (mb_strlen($word) > mb_strlen($m) && mb_strtolower(mb_substr($word, 0, mb_strlen($m))) === mb_strtolower($m)) {
                    $hit = mb_strlen($m);
                    break;
                }
            }
            if ($hit) {
                $parts[] = ['t' => mb_substr($word, 0, $hit), 'm' => true];
                $parts[] = ['t' => mb_substr($word, $hit), 'm' => false];
            } else {
                $parts[] = ['t' => $word, 'm' => false];
            }
        }

        // Join neighbouring plain parts, so the browser gets as few pieces as possible.
        $joined = [];
        foreach ($parts as $p) {
            $last = array_key_last($joined);
            if ($last !== null && ! $p['m'] && ! $joined[$last]['m']) {
                $joined[$last]['t'] .= $p['t'];
            } else {
                $joined[] = $p;
            }
        }

        return $joined;
    }
}
