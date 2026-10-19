<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Word;
use App\Services\AutoAudio;
use App\Services\Glossary;
use App\Services\Recordings;
use Illuminate\Http\Request;

class WordController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.words.index', [
            'words' => Word::search($request->q)->orderBy('kind')->orderBy('luganda')->paginate(50)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.words.form', ['word' => new Word(['kind' => 'word'])]);
    }

    public function store(Request $request)
    {
        $word = Word::create($this->validated($request));
        $this->recordSyllables($request, $word);
        $this->storeAudio($request, $word);
        $this->recordApproval($request, $word);
        app(AutoAudio::class)->queue($word, $request->user());

        return redirect()->route('admin.words.index')->with('status', 'Entry created.');
    }

    public function edit(Word $word, Glossary $glossary)
    {
        return view('admin.words.form', ['word' => $word, 'usedIn' => $glossary->phrasesUsing($word)]);
    }

    public function update(Request $request, Word $word)
    {
        $word->update($this->validated($request));
        $this->recordSyllables($request, $word);
        $this->storeAudio($request, $word);
        $this->recordApproval($request, $word);
        app(AutoAudio::class)->queue($word, $request->user());

        return redirect()->route('admin.words.index')->with('status', 'Entry saved.');
    }

    public function destroy(Word $word)
    {
        $word->delete(); // its recordings are removed with it

        return redirect()->route('admin.words.index')->with('status', 'Entry deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', 'in:word,proverb'],
            'luganda' => ['required', 'string', 'max:255'],
            'english' => ['required', 'string', 'max:255'],
            'part_of_speech' => ['nullable', 'string', 'max:50'],
            'plural' => ['nullable', 'string', 'max:255'],
            'example_lg' => ['nullable', 'string', 'max:255'],
            'example_en' => ['nullable', 'string', 'max:255'],
            'syllables' => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pM]+(-[\pL\pM]+)*$/u'],
            'audio' => ['nullable', 'file', 'max:5120', 'mimes:mp3,m4a,ogg,oga,wav,webm,aac'],
        ]);
        $data['is_reviewed'] = $request->boolean('is_reviewed');
        unset($data['audio']);
        if (array_key_exists('syllables', $data) && filled($data['syllables'])) {
            $data['syllables'] = mb_strtolower($data['syllables']);
        }

        return $data;
    }

    /**
     * Phase 11: "Syllable guide checked by a native speaker", only when the box was changed.
     * (Re-spelling a word makes a fresh draft guide, which a still-ticked box must not approve.)
     */
    private function recordSyllables(Request $request, Word $word): void
    {
        $was = $request->boolean('syllables_was_checked');
        $now = $request->boolean('syllables_checked');
        if ($now && ! $was && filled($word->syllables)) {
            $word->checkSyllables($request->user());
        } elseif (! $now && $was && $word->syllables_checked_at) {
            $word->forceFill(['syllables_checked_at' => null, 'syllables_checked_by' => null])->save();
        }
    }

    /** Uploads become pending recordings, checked on the Check page like any other. */
    private function storeAudio(Request $request, Word $word): void
    {
        if ($request->hasFile('audio')) {
            app(Recordings::class)->attach($word, $request->file('audio'), 'normal', $request->user());
        }
    }

    /** Ticking "checked by a native speaker" in the admin counts as an approval. */
    private function recordApproval(Request $request, Word $word): void
    {
        if ($word->is_reviewed && ($word->wasChanged('is_reviewed') || $word->wasRecentlyCreated || $word->review_note)) {
            $word->approve($request->user());
        }
    }
}
