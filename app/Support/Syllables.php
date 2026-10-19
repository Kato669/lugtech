<?php

namespace App\Support;

/**
 * A first guess at a syllable guide for a single Luganda word, e.g. amazzi → a-ma-zzi, ennyumba → e-nnyu-mba.
 *
 * Luganda syllables are open: any consonants (double consonants, nasal + consonant, w/y glides)
 * go with the vowel that follows, and a long vowel (aa, ee…) stays in one syllable.
 * This is only a starting point: every guide is a draft until a native speaker checks it
 * (Check → Syllable guides), because some teachers split a syllabic nasal (n-nya) differently.
 */
class Syllables
{
    private const VOWELS = 'aeiou';

    /** Hyphen-separated syllables, or null when this isn't a single plain word. */
    public static function guess(?string $word): ?string
    {
        $word = mb_strtolower(trim((string) $word));
        if ($word === '' || ! preg_match('/^[a-zŋ]+$/u', $word)) {
            return null;
        }
        $chars = mb_str_split($word);
        $syllables = [];
        $current = '';
        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $c = $chars[$i];
            $current .= $c;
            if (str_contains(self::VOWELS, $c)) {
                while ($i + 1 < $n && $chars[$i + 1] === $c) { // a long vowel stays together
                    $current .= $chars[++$i];
                }
                $syllables[] = $current;
                $current = '';
            }
        }
        if ($current !== '') { // trailing consonants (loan words): join the last syllable
            $syllables === [] ? $syllables[] = $current : $syllables[count($syllables) - 1] .= $current;
        }

        return implode('-', $syllables);
    }

    /** How a guide is shown: a·ma·zzi */
    public static function display(?string $syllables): ?string
    {
        return filled($syllables) ? str_replace('-', '·', $syllables) : null;
    }
}
