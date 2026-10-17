<?php

namespace App\Models\Concerns;

use App\Models\AudioClip;
use App\Models\User;
use App\Services\AudioResolver;
use App\Support\LugandaText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by phrases and dictionary words: a recording code (P0007, W0012)
 * used to name audio files, the native-speaker review trail for the text,
 * and the recordings (audio_clips), which are checked separately.
 */
trait Contributable
{
    abstract public static function codePrefix(): string;

    public static function bootContributable(): void
    {
        // Re-spelled? Approved recordings of the old text must be listened to again.
        static::updated(function (self $item) {
            if (! $item->wasChanged('luganda')) {
                return;
            }
            $old = $item->getOriginal('luganda');
            if (LugandaText::key($old) === LugandaText::key($item->luganda)) {
                return; // only case or punctuation changed: sounds the same
            }
            $item->audioClips()->current()->whereIn('status', ['approved', 'pending'])->update([
                'status' => 'pending',
                'review_note' => "The text changed from \"{$old}\" to \"{$item->luganda}\". Check this recording still matches.",
                'updated_at' => now(),
            ]);
            $item->unsetRelation('currentClips');
            app(AudioResolver::class)->flush();
        });

        // Deleting a phrase or word removes its recordings too.
        static::deleting(function (self $item) {
            $item->audioClips()->get()->each(function (AudioClip $clip) {
                Storage::disk($clip->disk)->delete($clip->path);
                $clip->delete();
            });
        });
    }

    /** Every recording ever made for this item, including replaced and rejected ones. */
    public function audioClips(): MorphMany
    {
        return $this->morphMany(AudioClip::class, 'owner');
    }

    /** Recordings still in use (pending, approved or flagged). */
    public function currentClips(): MorphMany
    {
        return $this->audioClips()->where('is_current', true)->orderBy('id');
    }

    /** Approve every current recording at once (used by tests and seeders). */
    public function approveRecordings(User $by): void
    {
        $this->currentClips()->get()->each(fn (AudioClip $c) => $c->approve($by));
        $this->unsetRelation('currentClips');
    }

    protected function code(): Attribute
    {
        return Attribute::get(fn () => static::codePrefix().str_pad((string) $this->id, 4, '0', STR_PAD_LEFT));
    }

    /** Suggested file name for a recording, e.g. P0007-oli-otya.mp3 / P0007-oli-otya-slow.mp3 */
    public function recordingFilename(bool $slow = false, string $ext = 'mp3'): string
    {
        $slug = str($this->luganda)->ascii()->slug()->limit(30, '')->rtrim('-');

        return $this->code.($slug->isNotEmpty() ? '-'.$slug : '').($slow ? '-slow' : '').'.'.$ext;
    }

    /**
     * The recording to play for a slot ('normal', 'slow', 'male', 'female'), or null.
     * The rules (approval, voices on/off, reviewers) live in AudioResolver.
     */
    protected function audibleUrl(string $slot = 'normal'): ?string
    {
        return app(AudioResolver::class)->url($this, $slot);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopePendingReview(Builder $q): Builder
    {
        return $q->where('is_reviewed', false)->whereNull('review_note');
    }

    public function scopeFlagged(Builder $q): Builder
    {
        return $q->where('is_reviewed', false)->whereNotNull('review_note');
    }

    public function approve(User $by): void
    {
        $this->forceFill(['is_reviewed' => true, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => null])->save();
    }

    public function flag(User $by, string $note): void
    {
        $this->forceFill(['is_reviewed' => false, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
    }

    public function isFlagged(): bool
    {
        return ! $this->is_reviewed && filled($this->review_note);
    }
}
