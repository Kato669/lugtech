<?php

namespace App\Support;

use App\Services\Glossary;
use Illuminate\Support\Str;

/**
 * Blog posts are written in Markdown. Raw HTML is shown as text (never run) and unsafe links
 * (javascript:…) are dropped. Luganda marked [[like this]] becomes tappable words, like in
 * lessons (App\Services\Glossary: meanings are never guessed).
 */
class BlogMarkdown
{
    public static function render(string $markdown): string
    {
        $luganda = [];
        $markdown = preg_replace_callback('/\[\[([^\[\]\n]{1,200})\]\]/u', function ($m) use (&$luganda) {
            $luganda[] = trim($m[1]);

            return 'LUGTEXT'.(count($luganda) - 1).'END';
        }, $markdown);

        $html = (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        $glossary = app(Glossary::class);
        $html = preg_replace_callback('/LUGTEXT(\d+)END/', fn ($m) => isset($luganda[(int) $m[1]])
            ? '<span class="lug-words blog-lug" lang="lg">'.$glossary->html($luganda[(int) $m[1]]).'</span>'
            : $m[0], $html);

        // Pictures load as the reader scrolls; links to other sites open safely.
        $html = str_replace('<img ', '<img loading="lazy" ', $html);

        return preg_replace_callback('/<a href="(https?:\/\/[^"]+)"/', function ($m) {
            $host = parse_url($m[1], PHP_URL_HOST);

            return $host && $host !== request()->getHost() ? $m[0].' rel="noopener" target="_blank"' : $m[0];
        }, $html);
    }

    /** Every piece of Luganda marked [[…]] in a post. @return string[] */
    public static function luganda(string $markdown): array
    {
        preg_match_all('/\[\[([^\[\]\n]{1,200})\]\]/u', $markdown, $m);

        return array_values(array_unique(array_map('trim', $m[1])));
    }
}
