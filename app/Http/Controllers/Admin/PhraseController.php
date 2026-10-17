<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Services\AutoAudio;
use App\Services\Recordings;
use Illuminate\Http\Request;

class PhraseController extends Controller
{
    public function index(Request $request)
    {
        $phrases = Phrase::query()
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(luganda) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(english) LIKE ?', ['%'.mb_strtolower($term).'%'])))
            ->when($request->filter === 'unreviewed', fn ($q) => $q->where('is_reviewed', false))
            ->when($request->filter === 'no-audio', fn ($q) => $q->whereNull('audio_path'))
            ->when($request->filter === 'flagged', fn ($q) => $q->flagged())
            ->withCount('lessons')->orderBy('luganda')->paginate(50)->withQueryString();

        return view('admin.phrases.index', ['phrases' => $phrases]);
    }

    public function create()
    {
        return view('admin.phrases.form', ['phrase' => new Phrase]);
    }

    public function store(Request $request)
    {
        $phrase = Phrase::create($this->validated($request));
        $this->storeAudio($request, $phrase);
        $this->recordApproval($request, $phrase);
        app(AutoAudio::class)->queue($phrase, $request->user());

        return redirect()->route('admin.phrases.index')->with('status', 'Phrase created.');
    }

    public function edit(Phrase $phrase)
    {
        return view('admin.phrases.form', ['phrase' => $phrase]);
    }

    public function update(Request $request, Phrase $phrase)
    {
        $phrase->update($this->validated($request));
        $this->storeAudio($request, $phrase);
        $this->recordApproval($request, $phrase);
        app(AutoAudio::class)->queue($phrase, $request->user());

        return redirect()->route('admin.phrases.index')->with('status', 'Phrase saved.');
    }

    public function destroy(Phrase $phrase)
    {
        $phrase->delete(); // its recordings are removed with it

        return redirect()->route('admin.phrases.index')->with('status', 'Phrase deleted.');
    }

    private function validated(Request $request): array
    {
        $audio = ['nullable', 'file', 'max:5120', 'mimes:mp3,m4a,ogg,oga,wav,webm,aac'];
        $data = $request->validate([
            'luganda' => ['required', 'string', 'max:255'],
            'english' => ['required', 'string', 'max:255'],
            'literal' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'audio' => $audio,
            'audio_slow' => $audio,
        ]);
        $data['is_reviewed'] = $request->boolean('is_reviewed');
        unset($data['audio'], $data['audio_slow']);

        return $data;
    }

    /** Uploads become pending recordings, checked on the Check page like any other. */
    private function storeAudio(Request $request, Phrase $phrase): void
    {
        $recordings = app(Recordings::class);
        foreach (['audio' => 'normal', 'audio_slow' => 'slow'] as $input => $slot) {
            if ($request->boolean("remove_{$input}")) {
                $recordings->retire($phrase, $slot);
            }
            if ($request->hasFile($input)) {
                $recordings->attach($phrase, $request->file($input), $slot, $request->user());
            }
        }
    }

    /** Ticking "checked by a native speaker" in the admin counts as an approval. */
    private function recordApproval(Request $request, Phrase $phrase): void
    {
        if ($phrase->is_reviewed && ($phrase->wasChanged('is_reviewed') || $phrase->wasRecentlyCreated || $phrase->review_note)) {
            $phrase->approve($request->user());
        }
    }
}
