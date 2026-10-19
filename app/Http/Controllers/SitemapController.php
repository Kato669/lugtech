<?php

namespace App\Http\Controllers;

use App\Models\Phrase;
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

        return response()->view('sitemap', ['words' => $words, 'phrases' => $phrases])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
