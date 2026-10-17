<?php

namespace App\Lessons;

use App\Models\Lesson;
use App\Models\Phrase;
use Illuminate\Support\Collection;

/**
 * What an activity needs while a lesson is being put together: the lesson, its
 * phrases (the pool wrong answers come from), phrase lookups and the shared shuffle.
 */
class LessonContext
{
    /** @var array<int, Phrase|null> */
    private array $byId = [];

    /** @param  Collection<int, Phrase>  $phrases  the lesson's phrases, in order */
    public function __construct(public readonly Lesson $lesson, public readonly Collection $phrases)
    {
        foreach ($phrases as $p) {
            $this->byId[$p->id] = $p;
        }
    }

    /** Load several phrases at once (with their recordings). */
    public function preload(array $ids): void
    {
        $missing = array_diff(array_unique(array_map('intval', $ids)), array_keys($this->byId));
        if ($missing) {
            foreach (Phrase::with('currentClips')->whereIn('id', $missing)->get() as $p) {
                $this->byId[$p->id] = $p;
            }
            foreach ($missing as $id) {
                $this->byId[$id] ??= null;
            }
        }
    }

    public function phrase(mixed $id): ?Phrase
    {
        if (! is_numeric($id)) {
            return null;
        }
        $this->preload([(int) $id]);

        return $this->byId[(int) $id];
    }

    /** @return Collection<int, Phrase> phrases for these ids, in the given order, skipping unknown ones */
    public function phrases(array $ids): Collection
    {
        $this->preload($ids);

        return collect($ids)->map(fn ($id) => $this->phrase($id))->filter()->values();
    }

    /** The phrase card every step uses to show and play a phrase. */
    public function card(Phrase $p): array
    {
        return [
            'id' => $p->id,
            'luganda' => $p->luganda,
            'english' => $p->english,
            'literal' => $p->literal,
            'notes' => $p->notes,
            'audio' => $p->audio_url,
            'audio_slow' => $p->audio_slow_url,
        ];
    }

    /** Fisher–Yates with mt_rand, so a seeded LessonPlan always comes out the same. */
    public function shuffle(Collection $items): Collection
    {
        $array = $items->values()->all();
        for ($i = count($array) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }

        return collect($array);
    }
}
