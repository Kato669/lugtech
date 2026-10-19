<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Word;
use App\Services\Glossary;
use App\Support\LugandaText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The dictionary: the list (/dictionary) and, from Phase 11, a public page per word
 * (/dictionary/amazzi) with its syllable guide, course phrases that use it and related words.
 * Public: outside EnsureLearner, so visitors and search engines don't get guest accounts.
 */
class DictionaryController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = $request->string('q')->trim()->toString();

        return view('dictionary', [
            'q' => $q,
            'words' => Word::where('kind', 'word')->with('currentClips')->search($q)->orderBy('luganda')->paginate(40)->withQueryString(),
            'proverbs' => $q ? collect() : Word::where('kind', 'proverb')->with('currentClips')->orderBy('luganda')->get(),
        ]);
    }

    public function show(Word $word, Glossary $glossary)
    {
        abort_unless($word->kind === 'word', 404);
        $word->load('currentClips');

        $public = Glossary::publicPhraseIds($glossary->phrasesUsing($word)->pluck('id'));
        $examples = Phrase::whereIn('id', $public)->with('currentClips')->orderBy('id')->get();

        return view('dictionary.word', [
            'word' => $word,
            'examples' => $examples,
            'related' => $this->related($word, $examples, $glossary),
            'indexable' => $word->is_reviewed || config('luganda.index_drafts'),
        ]);
    }

    /**
     * Words that go with this one, taken only from the course: its plural or singular,
     * then the other dictionary words used in the same lessons.
     */
    private function related(Word $word, Collection $examples, Glossary $glossary): Collection
    {
        $ids = collect();
        $plural = filled($word->plural) ? LugandaText::key($word->plural) : null;
        foreach (Word::where('kind', 'word')->where('id', '!=', $word->id)->orderBy('id')->get(['id', 'luganda', 'plural']) as $w) {
            if (($plural && LugandaText::key($w->luganda) === $plural)
                || (filled($w->plural) && LugandaText::key($w->plural) === LugandaText::key($word->luganda))) {
                $ids->push($w->id);
            }
        }

        $lessons = Lesson::where('is_published', true)
            ->whereHas('phrases', fn ($q) => $q->whereIn('phrases.id', $examples->pluck('id')))
            ->with(['stage', 'phrases'])->get()
            ->sortBy(fn (Lesson $l) => sprintf('%06d-%06d-%09d', $l->stage->position, $l->position, $l->id));
        foreach ($lessons as $lesson) {
            foreach ($lesson->phrases as $p) {
                foreach ($glossary->analyse($p->luganda, [$p->id]) as $tok) {
                    if ($tok['ref'] && str_starts_with($tok['ref'], 'w') && $tok['ref'] !== 'w'.$word->id) {
                        $ids->push((int) substr($tok['ref'], 1));
                    }
                }
            }
        }
        $ids = $ids->unique()->take(8)->values();
        $words = Word::whereIn('id', $ids)->where('kind', 'word')->get()->keyBy('id');

        return $ids->map(fn ($id) => $words[$id] ?? null)->filter()->values();
    }
}
