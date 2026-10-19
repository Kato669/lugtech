<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Models\PhraseWord;
use App\Models\Word;
use App\Services\AutoAudio;
use App\Services\Glossary;
use App\Support\LugandaText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Admin → Word links (Phase 11). Which dictionary word each word in a phrase belongs to.
 * Luganda joins words together, so "ntebe" in "Tuula ku ntebe" won't match the headword
 * "entebe" by itself: an admin links it. Words are never matched by guesswork.
 *
 * - The list shows course words with no meaning yet, words only known from a one-word phrase,
 *   and the links made so far.
 * - ?phrase=ID shows one phrase word by word, to set or change each link.
 * New dictionary words made here are drafts, checked on the Check page like any other.
 */
class WordLinkController extends Controller
{
    public function index(Request $request, Glossary $glossary)
    {
        if ($request->filled('phrase')) {
            return $this->phrase(Phrase::findOrFail((int) $request->query('phrase')), $glossary);
        }

        $all = $glossary->courseWords();
        $byCount = fn ($a, $b) => [$b['phrases']->count(), $a['form']] <=> [$a['phrases']->count(), $b['form']];
        $missing = $all->whereNull('ref')->sort($byCount)->values();
        $phraseOnly = $all->where('via', 'phrase')->sort($byCount)->values();
        $links = PhraseWord::with(['phrase:id,luganda,english', 'word:id,luganda,english'])->orderBy('form')->orderBy('id')->get();

        // A form already linked somewhere else is probably the same word here too: offer it.
        $suggest = $links->whereNotNull('word_id')->groupBy('form')->map(fn ($g) => $g->first()->word);
        $suggestName = $links->where('is_name', true)->pluck('form')->flip();

        return view('admin.word-links.index', [
            'missing' => $missing,
            'phraseOnly' => $phraseOnly,
            'links' => $links,
            'suggest' => $suggest,
            'suggestName' => $suggestName,
            'words' => $this->wordOptions(),
            'total' => $all->unique('form')->count(),
        ]);
    }

    private function phrase(Phrase $phrase, Glossary $glossary)
    {
        $parts = collect($glossary->analyse($phrase->luganda, [$phrase->id]))->filter(fn ($t) => $t['form'] !== null)->unique('form')->values();
        $refs = $parts->pluck('ref')->filter()->reject(fn ($r) => $r === 'name')->values()->all();

        return view('admin.word-links.phrase', [
            'phrase' => $phrase,
            'parts' => $parts,
            'entries' => $glossary->entries($refs),
            'words' => $this->wordOptions(),
        ]);
    }

    /**
     * Link one word form in the chosen phrases: to a dictionary word, as a name, to a new draft
     * dictionary word, or back to automatic (exact spelling only).
     */
    public function store(Request $request, AutoAudio $autoAudio)
    {
        $data = $request->validate([
            'form' => ['required', 'string', 'max:255'],
            'phrase_ids' => ['required', 'array', 'min:1'],
            'phrase_ids.*' => ['integer', 'exists:phrases,id'],
            'action' => ['required', 'in:link,name,new,auto'],
            'word_id' => ['required_if:action,link', 'nullable', 'integer', Rule::exists('words', 'id')->where('kind', 'word')],
            'luganda' => ['required_if:action,new', 'nullable', 'string', 'max:255'],
            'english' => ['required_if:action,new', 'nullable', 'string', 'max:255'],
            'part_of_speech' => ['nullable', 'string', 'max:50'],
            'back' => ['nullable', 'string', 'max:500'],
        ]);
        $form = LugandaText::key($data['form']);
        // Only phrases that really contain this word.
        $phrases = Phrase::whereIn('id', $data['phrase_ids'])->get()
            ->filter(fn (Phrase $p) => collect(Glossary::tokens($p->luganda))->contains('form', $form));
        abort_if($phrases->isEmpty(), 422, 'That word is not in those phrases.');

        $wordId = $data['word_id'] ?? null;
        if ($data['action'] === 'new') {
            $word = Word::create([
                'kind' => 'word',
                'luganda' => trim($data['luganda']),
                'english' => trim($data['english']),
                'part_of_speech' => $data['part_of_speech'] ?? null,
                'is_reviewed' => false, // a draft, checked on the Check page
            ]);
            $autoAudio->queue($word, $request->user());
            $wordId = $word->id;
        }

        foreach ($phrases as $phrase) {
            if ($data['action'] === 'auto') {
                PhraseWord::where('phrase_id', $phrase->id)->where('form', $form)->delete();

                continue;
            }
            // A new word spelled exactly like the form is found without a link.
            if ($data['action'] === 'new' && LugandaText::key($data['luganda']) === $form) {
                continue;
            }
            PhraseWord::updateOrCreate(
                ['phrase_id' => $phrase->id, 'form' => $form],
                ['word_id' => $data['action'] === 'name' ? null : $wordId, 'is_name' => $data['action'] === 'name', 'created_by' => $request->user()->id],
            );
        }

        $done = match ($data['action']) {
            'link' => 'Linked “'.$form.'” to '.Word::find($wordId)?->luganda,
            'name' => '“'.$form.'” marked as a name',
            'new' => 'Added “'.trim($data['luganda']).'” to the dictionary as a draft, for the Check page',
            'auto' => '“'.$form.'” is back to automatic (exact spelling only)',
        };

        return redirect($this->back($data['back'] ?? null))->with('status', $done.' ('.$phrases->count().' '.str('phrase')->plural($phrases->count()).').');
    }

    /** A one-word course phrase becomes a dictionary word (a draft), with the same Luganda and English. */
    public function fromPhrase(Request $request, Phrase $phrase, AutoAudio $autoAudio)
    {
        abort_if(str_contains(LugandaText::key($phrase->luganda), ' '), 422, 'Only one-word phrases can be added this way.');
        $word = Word::create([
            'kind' => 'word',
            'luganda' => trim(rtrim($phrase->luganda, '?!.')),
            'english' => $phrase->english,
            'is_reviewed' => false,
        ]);
        $autoAudio->queue($word, $request->user());

        return redirect($this->back($request->input('back')))->with('status', 'Added “'.$word->luganda.'” to the dictionary as a draft, for the Check page.');
    }

    public function destroy(Request $request, PhraseWord $link)
    {
        $link->delete();

        return redirect($this->back($request->input('back')))->with('status', 'Link removed: “'.$link->form.'” is back to automatic.');
    }

    /** @return Collection<int, Word> */
    private function wordOptions()
    {
        return Word::where('kind', 'word')->orderBy('luganda')->get(['id', 'luganda', 'english']);
    }

    /** Only ever back to a page of this admin section. */
    private function back(?string $to): string
    {
        $default = route('admin.word-links.index');
        if (! $to || ! str_starts_with($to, $default)) {
            return $default;
        }

        return $to;
    }
}
