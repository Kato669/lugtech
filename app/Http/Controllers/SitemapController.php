<?php

namespace App\Http\Controllers;

use App\Models\Clan;
use App\Models\GandaName;
use App\Models\Phrase;
use App\Models\Story;
use App\Models\Word;

/**
 * /sitemap.xml (Phase 11): the public pages search engines may list. Drafts stay out
 * until a native speaker has checked them (unless LUGANDA_INDEX_DRAFTS=true).
 */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $drafts = (bool) config('luganda.index_drafts');
        $words = Word::where('kind', 'word')->when(! $drafts, fn ($q) => $q->where('is_reviewed', true))
            ->orderBy('id')->get(['slug', 'updated_at']);
        $phrases = Phrase::whereHas('lessons', fn ($q) => $q->where('is_published', true))
            ->when(! $drafts, fn ($q) => $q->where('is_reviewed', true))
            ->orderBy('id')->get(['id', 'slug', 'updated_at']);

        // Phase 15: culture pages (the hub and guide always; the rest once everything on them is checked)
        $culture = [route('culture'), route('culture.guide')];
        $checked = fn ($model) => $drafts || ! $model::where('is_reviewed', false)->exists();
        if ($checked(Clan::class) && $checked(GandaName::class)) {
            $culture[] = route('culture.clans');
        }
        if ($drafts || ! Word::where('kind', 'proverb')->where('is_reviewed', false)->exists()) {
            $culture[] = route('culture.proverbs');
        }
        foreach (Story::where('is_published', true)->get() as $story) {
            if ($drafts || ! $story->pages()->where('is_reviewed', false)->exists()) {
                $culture[] = route('culture.story', $story->slug);
            }
        }

        return response()->view('sitemap', ['words' => $words, 'phrases' => $phrases, 'culture' => $culture])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
