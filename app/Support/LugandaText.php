<?php

namespace App\Support;

/**
 * Compares Luganda text the way a listener would: case, punctuation and extra
 * spaces don't change how something sounds, so "Oli otya?" and "oli otya"
 * share a key. Any change of letters does change the key.
 */
class LugandaText
{
    public static function key(?string $text): string
    {
        $text = (string) $text;
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        $text = mb_strtolower($text);
        $text = str_replace(['’', '‘', '`', 'ʼ'], "'", $text);
        $text = preg_replace("/[^\p{L}\p{N}' ]+/u", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
