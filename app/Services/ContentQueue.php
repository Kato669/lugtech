<?php

namespace App\Services;

use App\Models\AudioClip;
use App\Models\Lesson;
use App\Models\Phrase;
use App\Models\Word;
use App\Support\Conversations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lists phrases in the order learners meet them (stage → lesson → position),
 * then phrases not yet in a lesson, then dictionary entries. Each item knows
 * which lesson it belongs to, so speakers and reviewers see helpful context.
 */
class ContentQueue
{
    /** How many items one page of the Check page shows (the Studio shows more: one at a time). */
    public const PER_PAGE = 25;

    /**
     * Every item in course order, without loading the items themselves: cheap even for
     * thousands of phrases. Each entry: [type, id, group].
     *
     * @return Collection<int, array{type: string, id: int, group: string}>
     */
    public function index(): Collection
    {
        return once(function () {
            $entries = collect();
            $seen = [];
            $rows = DB::table('lesson_phrase')
                ->join('lessons', 'lessons.id', '=', 'lesson_phrase.lesson_id')
                ->join('stages', 'stages.id', '=', 'lessons.stage_id')
                ->orderBy('stages.position')->orderBy('lessons.position')->orderBy('lessons.id')->orderBy('lesson_phrase.position')
                ->get(['lesson_phrase.phrase_id', 'stages.position as stage', 'lessons.title']);
            foreach ($rows as $r) {
                if (! isset($seen[$r->phrase_id])) {
                    $seen[$r->phrase_id] = true;
                    $entries->push(['type' => 'phrase', 'id' => (int) $r->phrase_id, 'group' => "Stage {$r->stage} · {$r->title}"]);
                }
            }
            foreach (Phrase::orderBy('id')->pluck('id') as $id) {
                if (! isset($seen[$id])) {
                    $entries->push(['type' => 'phrase', 'id' => (int) $id, 'group' => 'Not in a lesson yet']);
                }
            }
            foreach (Word::orderBy('kind')->orderBy('luganda')->get(['id', 'kind']) as $w) {
                $entries->push(['type' => 'word', 'id' => $w->id, 'group' => $w->kind === 'proverb' ? 'Proverbs' : 'Dictionary']);
            }

            return $entries;
        });
    }

    /**
     * The index narrowed to one tab and an optional search (Luganda, English or a code like P0008).
     *
     * show: pending | flagged | approved | audio (recordings to check) | all              (Check page)
     *       missing (no human recording yet) | conversations | phrases | all              (Studio)
     */
    public function filtered(string $show = 'all', ?string $search = null): Collection
    {
        $keep = [];
        foreach (['phrase' => Phrase::class, 'word' => Word::class] as $type => $class) {
            $ids = $this->idsFor($class, $show, $search);
            $keep[$type] = $ids === null ? null : array_flip($ids);
        }

        return $this->index()->filter(fn ($e) => $keep[$e['type']] === null || isset($keep[$e['type']][$e['id']]))->values();
    }

    /** How many items each Check tab holds (no search). @return array<string, int> */
    public function counts(array $tabs): array
    {
        return collect($tabs)->mapWithKeys(fn ($t) => [$t => $this->filtered($t)->count()])->all();
    }

    /**
     * Load the items for some index entries (with their recordings), keeping the order.
     *
     * @param  Collection<int, array{type: string, id: int, group: string}>  $entries
     * @return Collection<int, array{model: Phrase|Word, type: string, group: string}>
     */
    public function load(Collection $entries): Collection
    {
        $models = [
            'phrase' => Phrase::with('currentClips.reviewer')->whereIn('id', $entries->where('type', 'phrase')->pluck('id'))->get()->keyBy('id'),
            'word' => Word::with('currentClips.reviewer')->whereIn('id', $entries->where('type', 'word')->pluck('id'))->get()->keyBy('id'),
        ];

        return $entries->map(fn ($e) => isset($models[$e['type']][$e['id']])
            ? ['model' => $models[$e['type']][$e['id']], 'type' => $e['type'], 'group' => $e['group']] : null)->filter()->values();
    }

    /** Ids of one kind (phrase or word) that belong in a tab, or null for "no limit". @return int[]|null */
    private function idsFor(string $class, string $show, ?string $search): ?array
    {
        $query = $class::query();
        $limited = false;
        $empty = fn ($q) => $q->whereNull('review_note')->orWhere('review_note', '');

        switch ($show) {
            case 'pending':
                $query->where('is_reviewed', false)->where($empty);
                $limited = true;
                break;
            case 'flagged':
                $query->where('is_reviewed', false)->whereNot($empty);
                $limited = true;
                break;
            case 'approved':
                $query->where('is_reviewed', true);
                $limited = true;
                break;
            case 'audio':
                $query->whereIn('id', AudioClip::where('owner_type', (new $class)->getMorphClass())->where('is_current', true)
                    ->whereIn('status', ['pending', 'flagged'])->select('owner_id'));
                $limited = true;
                break;
            case 'missing':
                $query->where(fn ($q) => $q->whereNull('audio_path')->orWhere('audio_path', ''));
                $limited = true;
                break;
            case 'phrases':
                if ($class === Word::class) {
                    return [];
                }
                break;
            case 'conversations':
                if ($class === Word::class) {
                    return [];
                }
                $query->whereIn('luganda', collect(Conversations::SCRIPTS)->flatten(1)->pluck(1)->unique()->all());
                $limited = true;
                break;
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $limited = true;
            if (preg_match('/^([PW])0*(\d+)$/i', $search, $m)) {
                if (strtoupper($m[1]) !== $class::codePrefix()) {
                    return [];
                }
                $query->where('id', (int) $m[2]);
            } else {
                $like = '%'.mb_strtolower(addcslashes($search, '%_\\')).'%';
                $query->where(fn ($q) => $q->whereRaw('LOWER(luganda) LIKE ?', [$like])->orWhereRaw('LOWER(english) LIKE ?', [$like]));
            }
        }

        return $limited ? $query->pluck('id')->map(fn ($id) => (int) $id)->all() : null;
    }

    /** @return Collection<int, array{model: Phrase|Word, type: string, group: string}> */
    public function all(): Collection
    {
        $items = collect();
        $seen = [];

        $lessons = Lesson::with(['stage', 'phrases.currentClips.reviewer'])->get()
            ->sortBy(fn ($l) => [$l->stage->position, $l->position]);
        foreach ($lessons as $lesson) {
            foreach ($lesson->phrases as $phrase) {
                if (isset($seen[$phrase->id])) {
                    continue;
                }
                $seen[$phrase->id] = true;
                $items->push(['model' => $phrase, 'type' => 'phrase', 'group' => "Stage {$lesson->stage->position} · {$lesson->title}"]);
            }
        }
        Phrase::whereNotIn('id', array_keys($seen) ?: [0])->with('currentClips.reviewer')->orderBy('id')->get()
            ->each(fn ($p) => $items->push(['model' => $p, 'type' => 'phrase', 'group' => 'Not in a lesson yet']));
        Word::with('currentClips.reviewer')->orderBy('kind')->orderBy('luganda')->get()
            ->each(fn ($w) => $items->push(['model' => $w, 'type' => 'word', 'group' => $w->kind === 'proverb' ? 'Proverbs' : 'Dictionary']));

        return $items;
    }

    public function toArray(array $item): array
    {
        $m = $item['model'];

        return [
            'type' => $item['type'],
            'id' => $m->id,
            'code' => $m->code,
            'group' => $item['group'],
            'luganda' => $m->luganda,
            'english' => $m->english,
            'literal' => $m->literal ?? null,
            'notes' => $item['type'] === 'phrase' ? $m->notes : trim(($m->part_of_speech ?? '').' '.($m->example_lg ? "· e.g. {$m->example_lg}" : '')),
            'audio' => $m->audio_url,
            'audio_slow' => $item['type'] === 'phrase' ? $m->audio_slow_url : null,
            'has_slow' => $item['type'] === 'phrase',
            // Conversation lines also need a character voice: Mukasa (man) or Nakato (woman).
            'voice' => $item['type'] === 'phrase' ? Conversations::voiceFor($m->id) : null,
            'audio_male' => $item['type'] === 'phrase' ? $m->audio_male_url : null,
            'audio_female' => $item['type'] === 'phrase' ? $m->audio_female_url : null,
            'is_reviewed' => $m->is_reviewed,
            'review_note' => $m->review_note,
            // Each recording, checked on its own (only listed while voices are on, so reviewers can play them).
            'clips' => config('luganda.voice') ? $this->clips($m) : [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function clips(Phrase|Word $m): array
    {
        $order = array_flip(AudioClip::SLOTS);

        return $m->currentClips->where('status', '!=', 'rejected')
            ->sortBy(fn (AudioClip $c) => [$order[$c->slot] ?? 9, $c->id])
            ->map(fn (AudioClip $c) => [
                'id' => $c->id,
                'slot' => $c->slot,
                'label' => $c->label(),
                'source' => $c->source,
                'provider' => $c->provider,
                'voice' => $c->voice,
                'status' => $c->status,
                'note' => $c->review_note,
                'url' => $c->url(),
                'recorded_for' => $c->text_snapshot,
                'matches_text' => $c->matchesText($m->luganda),
                'reviewed_by' => $c->reviewer?->name,
                'review_url' => route('check.audio', $c),
            ])->values()->all();
    }

    /** Is any recording of this item waiting for a reviewer? */
    public static function audioToCheck(Phrase|Word $m): bool
    {
        return $m->currentClips->contains(fn (AudioClip $c) => in_array($c->status, ['pending', 'flagged'], true));
    }
}
