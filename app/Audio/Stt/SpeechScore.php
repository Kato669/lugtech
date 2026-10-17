<?php

namespace App\Audio\Stt;

use App\Support\LugandaText;

/**
 * How close what the checker heard is to the phrase, 0–100. Case, punctuation and
 * spaces are ignored (as LugandaText::key does); every letter counts.
 * Speech recognition for Luganda is far from perfect, so this is a hint for the
 * learner, never a grade: lessons are not marked on it.
 */
class SpeechScore
{
    public const GREAT = 90;

    public const CLOSE = 60;

    public static function score(string $target, string $heard): int
    {
        $a = self::letters($target);
        $b = self::letters($heard);
        if ($a === [] || $b === []) {
            return 0;
        }
        $distance = self::distance($a, $b);

        return (int) max(0, round(100 * (1 - $distance / max(count($a), count($b)))));
    }

    /** great | close | again */
    public static function verdict(int $score): string
    {
        return $score >= self::GREAT ? 'great' : ($score >= self::CLOSE ? 'close' : 'again');
    }

    /** @return string[] */
    private static function letters(string $text): array
    {
        return mb_str_split(str_replace([' ', "'"], '', LugandaText::key($text)));
    }

    /** Levenshtein distance over letters (works for ŋ and other non-ASCII letters). */
    private static function distance(array $a, array $b): int
    {
        $prev = range(0, count($b));
        foreach ($a as $i => $ca) {
            $cur = [$i + 1];
            foreach ($b as $j => $cb) {
                $cur[$j + 1] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }
            $prev = $cur;
        }

        return $prev[count($b)];
    }
}
