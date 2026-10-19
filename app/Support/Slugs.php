<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Web addresses for the public dictionary and phrase pages. They never change once made. */
class Slugs
{
    public static function fromText(?string $text, string $fallback): string
    {
        $slug = (string) Str::of((string) $text)->replace(['’', "'"], '')->ascii()->slug()->limit(60, '')->rtrim('-');

        return $slug !== '' ? $slug : $fallback;
    }

    /** Adds -2, -3… when the address is taken. $taken collects what has been handed out in this run. */
    public static function unique(string $slug, array &$taken, ?callable $exists = null): string
    {
        $candidate = $slug;
        for ($n = 2; isset($taken[$candidate]) || ($exists && $exists($candidate)); $n++) {
            $candidate = $slug.'-'.$n;
        }
        $taken[$candidate] = true;

        return $candidate;
    }
}
