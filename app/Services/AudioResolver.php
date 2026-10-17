<?php

namespace App\Services;

use App\Models\AudioClip;
use App\Models\User;
use App\Support\LugandaText;
use Illuminate\Database\Eloquent\Model;

/**
 * Decides which recording (if any) is heard for a phrase or word.
 *
 * Learners hear a recording only when voices are on (LUGANDA_VOICE) and it was
 * made for the item's current text. Then:
 *   - LUGANDA_PLAY_UNCHECKED=true (default): any recording that isn't flagged or
 *     rejected plays straight away; checked ones first, then human, then generated;
 *   - LUGANDA_PLAY_UNCHECKED=false: only approved recordings of approved text. If the item has none,
 * an approved recording of exactly the same text on another item is used
 * (so "Emu" in a lesson and "emu" in the dictionary need recording only once).
 *
 * Reviewers and admins hear the newest recording that isn't rejected, so they
 * can check it.
 */
class AudioResolver
{
    /** @var array<string, ?AudioClip> shared recordings found this request, by text|slot */
    private array $shared = [];

    public function flush(): void
    {
        $this->shared = [];
    }

    public function clip(Model $item, string $slot = 'normal', ?User $viewer = null): ?AudioClip
    {
        if (! config('luganda.voice') || ! $item->exists) {
            return null;
        }
        $viewer ??= auth()->user();
        $reviewer = (bool) $viewer?->isContributor();
        $unchecked = (bool) config('luganda.play_unchecked');
        if (! $reviewer && ! $item->is_reviewed && ! $unchecked) {
            return null;
        }
        $key = LugandaText::key($item->luganda);

        $own = $item->currentClips->where('slot', $slot)
            ->filter(fn (AudioClip $c) => $reviewer
                ? $c->status !== 'rejected'
                : $c->text_key === $key && $this->playable($c, $item->is_reviewed, $unchecked));

        $best = $reviewer
            ? $own->sortByDesc('id')->first()                                                   // newest take, to check it
            : $own->sortBy(fn ($c) => [$c->status === 'approved' ? 0 : 1, $c->isGenerated() ? 1 : 0, -$c->id])->first();

        return $best ?? $this->sharedClip($key, $slot, $unchecked);
    }

    /** Can a learner hear this recording? (Flagged and rejected ones: never.) */
    private function playable(AudioClip $c, bool $textApproved, bool $unchecked): bool
    {
        return match ($c->status) {
            'approved' => $textApproved || $unchecked,
            'pending' => $unchecked,
            default => false,
        };
    }

    public function url(Model $item, string $slot = 'normal', ?User $viewer = null): ?string
    {
        return $this->clip($item, $slot, $viewer)?->url();
    }

    /**
     * Everything the speaker button needs: the URL, plus (for reviewers) whether
     * it is still waiting for approval.
     *
     * @return array{url: ?string, slow: ?string, status: ?string, source: ?string}
     */
    public function info(Model $item, ?User $viewer = null): array
    {
        $clip = $this->clip($item, 'normal', $viewer);
        $slow = $this->clip($item, 'slow', $viewer);

        return ['url' => $clip?->url(), 'slow' => $slow?->url(), 'status' => $clip?->status, 'source' => $clip?->source];
    }

    private function sharedClip(string $key, string $slot, bool $unchecked = false): ?AudioClip
    {
        if ($key === '') {
            return null;
        }

        $cacheKey = "{$key}|{$slot}|".(int) $unchecked;
        if (! array_key_exists($cacheKey, $this->shared)) {
            $this->shared[$cacheKey] = AudioClip::query()->current()
                ->whereIn('status', $unchecked ? ['approved', 'pending'] : ['approved'])
                ->where('text_key', $key)->where('slot', $slot)
                ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN source = 'human' THEN 0 ELSE 1 END")->orderByDesc('id')
                ->first();
        }

        return $this->shared[$cacheKey];
    }
}
