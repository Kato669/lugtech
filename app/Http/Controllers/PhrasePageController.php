<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Stage;
use App\Models\Word;
use App\Services\Glossary;

/**
 * Phase 11: public pages for everyday phrases, e.g. /say/thank-you
 * ("How to say thank you in Luganda"), so people can find the app through search engines.
 * Only phrases in a published lesson have a page. Drafts are shown with a label and
 * kept out of search engines (noindex, not in the sitemap) unless LUGANDA_INDEX_DRAFTS=true.
 * Public: outside EnsureLearner, so visitors and search engines don't get guest accounts.
 */
class PhrasePageController extends Controller
{
    public function index()
    {
        $stages = Stage::orderBy('position')
            ->with(['lessons' => fn ($q) => $q->where('is_published', true)->with('phrases')])
            ->get()->filter(fn (Stage $s) => $s->lessons->isNotEmpty())->values();

        return view('say.index', ['stages' => $stages]);
    }

    public function show(Phrase $phrase, Glossary $glossary)
    {
        $lessons = $phrase->lessons()->where('is_published', true)->with('stage')->get()
            ->sortBy(fn (Lesson $l) => sprintf('%06d-%06d-%09d', $l->stage->position, $l->position, $l->id))->values();
        abort_if($lessons->isEmpty(), 404);
        $phrase->load('currentClips');

        // Word by word: what each word means, and where it comes from (never guessed).
        $parts = collect($glossary->analyse($phrase->luganda, [$phrase->id]))->filter(fn ($t) => $t['form'] !== null)->values();
        $wordIds = $parts->pluck('ref')->filter(fn ($r) => $r && str_starts_with($r, 'w'))->map(fn ($r) => (int) substr($r, 1));
        $phraseIds = $parts->pluck('ref')->filter(fn ($r) => $r && str_starts_with($r, 'p'))->map(fn ($r) => (int) substr($r, 1));
        $words = Word::whereIn('id', $wordIds)->get()->keyBy('id');
        $phrases = Phrase::whereIn('id', $phraseIds)->get()->keyBy('id');
        $public = Glossary::publicPhraseIds($phraseIds);
        $wordByWord = $parts->map(fn ($t) => [
            'shown' => $t['t'],
            'ref' => $t['ref'],
            'word' => $t['ref'] && $t['ref'][0] === 'w' ? $words[(int) substr($t['ref'], 1)] ?? null : null,
            'phrase' => $t['ref'] && $t['ref'][0] === 'p' ? $phrases[(int) substr($t['ref'], 1)] ?? null : null,
            'phrase_public' => $t['ref'] && $t['ref'][0] === 'p' && in_array((int) substr($t['ref'], 1), $public, true),
        ]);

        $more = $lessons->first()->phrases()->where('phrases.id', '!=', $phrase->id)->with('currentClips')->get()->take(8);

        return view('say.show', [
            'phrase' => $phrase,
            'lessons' => $lessons,
            'wordByWord' => $wordByWord,
            'more' => $more,
            'indexable' => $phrase->is_reviewed || config('luganda.index_drafts'),
        ]);
    }

    /** "How to say thank you in Luganda" (first letter lowered, except I / I'm). */
    public static function howToSay(Phrase $phrase): string
    {
        $english = trim(rtrim(trim($phrase->english), '.!?'));
        if (! preg_match("/^I(\\b|')/", $english)) {
            $english = mb_strtolower(mb_substr($english, 0, 1)).mb_substr($english, 1);
        }

        return "How to say \"{$english}\" in Luganda";
    }
}
