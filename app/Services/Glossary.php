<?php

namespace App\Services;

use App\Models\Phrase;
use App\Models\PhraseWord;
use App\Models\Word;
use App\Support\LugandaText;
use App\Support\Syllables;
use Illuminate\Support\Collection;

/**
 * Phase 11: tap a word. Works out what each word in a piece of Luganda means, without guessing:
 *
 *   1. an explicit link for this phrase (Admin → Word links): "ntebe" in "Tuula ku ntebe" → entebe,
 *      or "this is a name";
 *   2. otherwise a dictionary word spelled exactly the same ("amazzi" → amazzi);
 *   3. otherwise a one-word course phrase spelled the same ("bulungi" → Bulungi = Fine / Good);
 *   4. otherwise nothing: "not in the dictionary yet".
 *
 * One instance per request. Pages register the Luganda they show (or render it with
 * <x-lug-text>); the layout then puts the meanings into the page as JSON, so tapping a word
 * needs no network (works offline too) and the word sheet (<x-word-sheet>) can show it.
 */
class Glossary
{
    /** exact text => true: the Luganda shown on this page */
    private array $texts = [];

    private ?array $wordByForm = null;

    private ?array $phraseByForm = null;

    private ?array $phraseIdsByKey = null;

    /** phrase_id => [form => PhraseWord] */
    private ?array $links = null;

    /**
     * Split Luganda into words and what lies between them (spaces, punctuation).
     *
     * @return array<int, array{t: string, form: ?string}>
     */
    public static function tokens(string $text): array
    {
        $parts = preg_split("/([\p{L}\p{M}]+(?:['’ʼ][\p{L}\p{M}]+)*)/u", $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $p) => [
            't' => $p,
            'form' => preg_match('/^[\p{L}\p{M}]/u', $p) ? LugandaText::key($p) : null,
        ], $parts);
    }

    /** The phrases whose Luganda is this text (their links apply). */
    public function phraseIdsFor(string $text): array
    {
        $this->load();

        return $this->phraseIdsByKey[LugandaText::key($text)] ?? [];
    }

    /**
     * What one word means here.
     *
     * @return array{ref: string, via: string}|null ref: w12 (word), p5 (phrase) or "name"; via: link|word|phrase
     */
    public function resolve(string $form, array $phraseIds = []): ?array
    {
        $this->load();
        foreach ($phraseIds as $id) {
            if ($link = $this->links[$id][$form] ?? null) {
                return ['ref' => $link->is_name ? 'name' : 'w'.$link->word_id, 'via' => 'link'];
            }
        }
        if (isset($this->wordByForm[$form])) {
            return ['ref' => 'w'.$this->wordByForm[$form], 'via' => 'word'];
        }
        if (isset($this->phraseByForm[$form])) {
            return ['ref' => 'p'.$this->phraseByForm[$form], 'via' => 'phrase'];
        }

        return null;
    }

    /**
     * Each word of a text with its meaning reference.
     *
     * @return array<int, array{t: string, form: ?string, ref: ?string, via: ?string}>
     */
    public function analyse(string $text, ?array $phraseIds = null): array
    {
        $phraseIds ??= $this->phraseIdsFor($text);

        return array_map(function (array $tok) use ($phraseIds) {
            $hit = $tok['form'] !== null ? $this->resolve($tok['form'], $phraseIds) : null;

            return $tok + ['ref' => $hit['ref'] ?? null, 'via' => $hit['via'] ?? null];
        }, self::tokens($text));
    }

    /** Remember that this page shows this Luganda. */
    public function register(?string ...$texts): void
    {
        foreach ($texts as $text) {
            if (filled($text)) {
                $this->texts[$text] = true;
            }
        }
    }

    /** Register every "luganda" value found in lesson steps, conversation lines, review cards… */
    public function registerFrom(mixed $data): void
    {
        if (! is_array($data)) {
            return;
        }
        foreach ($data as $key => $value) {
            if ($key === 'luganda' && is_string($value)) {
                $this->register($value);
            } elseif (is_array($value)) {
                $this->registerFrom($value);
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->texts === [];
    }

    /** The text as tappable words (HTML, escaped). Also registers it for the page. */
    public function html(string $text): string
    {
        $this->register($text);
        $out = '';
        foreach ($this->analyse($text) as $tok) {
            $out .= $tok['form'] === null
                ? e($tok['t'])
                : '<button type="button" class="lug-word" data-ref="'.e($tok['ref'] ?? '').'" aria-haspopup="dialog">'.e($tok['t']).'</button>';
        }

        return $out;
    }

    /**
     * What the page needs for tapping: each registered text as tappable HTML, and the meanings.
     *
     * @return array{html: array<string, string>, entries: array<string, array<string, mixed>>}|null
     */
    public function payload(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }
        $html = [];
        $refs = [];
        foreach (array_keys($this->texts) as $text) {
            $text = (string) $text;
            $html[$text] = $this->html($text);
            foreach ($this->analyse($text) as $tok) {
                if ($tok['ref'] && $tok['ref'] !== 'name') {
                    $refs[$tok['ref']] = true;
                }
            }
        }

        return ['html' => $html, 'entries' => $this->entries(array_keys($refs))];
    }

    /** The payload for this page, then start afresh (the layout calls this once per page). */
    public function take(): ?array
    {
        $payload = $this->payload();
        $this->texts = [];

        return $payload;
    }

    /** @return array<string, array<string, mixed>> */
    public function entries(array $refs): array
    {
        $wordIds = collect($refs)->filter(fn ($r) => str_starts_with($r, 'w'))->map(fn ($r) => (int) substr($r, 1));
        $phraseIds = collect($refs)->filter(fn ($r) => str_starts_with($r, 'p'))->map(fn ($r) => (int) substr($r, 1));
        $entries = [];
        foreach (Word::whereIn('id', $wordIds)->with('currentClips')->get() as $w) {
            $entries['w'.$w->id] = self::wordEntry($w);
        }
        $public = self::publicPhraseIds($phraseIds);
        foreach (Phrase::whereIn('id', $phraseIds)->with('currentClips')->get() as $p) {
            $entries['p'.$p->id] = [
                'lg' => $p->luganda,
                'en' => $p->english,
                'audio' => $p->audio_url,
                'url' => in_array($p->id, $public, true) ? route('say.show', $p->slug) : null,
                'draft' => ! $p->is_reviewed,
                'kind' => 'phrase',
            ];
        }

        return $entries;
    }

    public static function wordEntry(Word $w): array
    {
        return [
            'lg' => $w->luganda,
            'en' => $w->english,
            'pos' => $w->part_of_speech,
            'syl' => Syllables::display($w->syllables),
            'sylDraft' => ! $w->syllablesChecked(),
            'audio' => $w->audio_url,
            'url' => route('dictionary.word', $w->slug),
            'draft' => ! $w->is_reviewed,
            'kind' => 'word',
        ];
    }

    /** Phrases that have a public page (they are in a published lesson). */
    public static function publicPhraseIds(iterable $ids): array
    {
        return Phrase::whereIn('id', collect($ids)->all())
            ->whereHas('lessons', fn ($q) => $q->where('is_published', true))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Course phrases that use this word (explicit links, or the same spelling), oldest first. */
    public function phrasesUsing(Word $word): Collection
    {
        $linked = PhraseWord::where('word_id', $word->id)->pluck('phrase_id');
        $candidates = Phrase::where(fn ($q) => $q
            ->whereIn('id', $linked)
            ->orWhereRaw('LOWER(luganda) LIKE ?', ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($word->luganda)).'%']))
            ->orderBy('id')->get();
        $ref = 'w'.$word->id;

        return $candidates->filter(fn (Phrase $p) => collect($this->analyse($p->luganda, [$p->id]))
            ->contains(fn ($t) => $t['ref'] === $ref))->values();
    }

    /**
     * Every word in the course and how it resolves, for Admin → Word links.
     *
     * @return Collection<string, array{form: string, shown: string, ref: ?string, via: ?string, phrases: Collection}>
     */
    public function courseWords(): Collection
    {
        $out = [];
        foreach (Phrase::orderBy('id')->get(['id', 'luganda', 'english']) as $p) {
            foreach ($this->analyse($p->luganda, [$p->id]) as $tok) {
                if ($tok['form'] === null) {
                    continue;
                }
                $key = $tok['form'].'|'.($tok['ref'] ?? '').'|'.($tok['via'] ?? '');
                $out[$key] ??= ['form' => $tok['form'], 'shown' => $tok['t'], 'ref' => $tok['ref'], 'via' => $tok['via'], 'phrases' => collect()];
                $out[$key]['phrases']->put($p->id, $p);
            }
        }

        return collect($out);
    }

    /** Forget what was loaded (after links or words change). */
    public function flush(): void
    {
        $this->wordByForm = $this->phraseByForm = $this->phraseIdsByKey = $this->links = null;
    }

    private function load(): void
    {
        if ($this->wordByForm !== null) {
            return;
        }
        $this->wordByForm = [];
        foreach (Word::where('kind', 'word')->orderBy('id')->get(['id', 'luganda']) as $w) {
            $this->wordByForm[LugandaText::key($w->luganda)] ??= $w->id;
        }
        $this->phraseByForm = [];
        $this->phraseIdsByKey = [];
        foreach (Phrase::orderBy('id')->get(['id', 'luganda']) as $p) {
            $key = LugandaText::key($p->luganda);
            $this->phraseIdsByKey[$key][] = $p->id;
            if ($key !== '' && ! str_contains($key, ' ')) {
                $this->phraseByForm[$key] ??= $p->id;
            }
        }
        $this->links = [];
        foreach (PhraseWord::orderBy('id')->get() as $link) {
            $this->links[$link->phrase_id][$link->form] = $link;
        }
    }
}
