<?php

namespace App\Models;

use App\Services\AudioResolver;
use App\Support\LugandaText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One recording of a phrase or dictionary word.
 *
 *   source: human (recorded or uploaded) | generated (text-to-speech, later)
 *   status: pending → approved, or flagged / rejected by a reviewer
 *   is_current: false once replaced or rejected; the file is kept as history
 */
class AudioClip extends Model
{
    public const SLOTS = ['normal', 'slow', 'male', 'female'];

    public const LABELS = ['normal' => 'Normal', 'slow' => 'Slow', 'male' => 'Mukasa', 'female' => 'Nakato'];

    protected $fillable = [
        'owner_type', 'owner_id', 'slot', 'source', 'provider', 'voice', 'text_snapshot', 'text_key',
        'disk', 'path', 'mime', 'bytes', 'duration_ms', 'status', 'is_current', 'recorded_by', 'meta', 'generation_key',
    ];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'reviewed_at' => 'datetime', 'meta' => 'array'];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(AudioResolver::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('is_current', true);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    /** Waiting for a reviewer: new, or flagged with a problem. */
    public function scopeNeedsReview(Builder $q): Builder
    {
        return $q->current()->whereIn('status', ['pending', 'flagged']);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function label(): string
    {
        return self::LABELS[$this->slot] ?? ucfirst($this->slot);
    }

    public function isGenerated(): bool
    {
        return $this->source === 'generated';
    }

    /** Was this recorded for the text the item has now? (Case and punctuation don't matter.) */
    public function matchesText(?string $text): bool
    {
        return $this->text_key === LugandaText::key($text);
    }

    /**
     * A reviewer confirms this recording says the item's current text. It becomes
     * the one learners hear, and older takes of the same kind are retired.
     */
    public function approve(User $by): void
    {
        $owner = $this->owner;
        $this->forceFill([
            'status' => 'approved', 'is_current' => true, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => null,
            'text_snapshot' => $owner?->luganda ?? $this->text_snapshot,
            'text_key' => $owner ? LugandaText::key($owner->luganda) : $this->text_key,
        ])->save();

        static::query()->where('owner_type', $this->owner_type)->where('owner_id', $this->owner_id)
            ->where('slot', $this->slot)->where('source', $this->source)->current()->whereKeyNot($this->id)
            ->update(['is_current' => false, 'updated_at' => now()]);
        app(AudioResolver::class)->flush();
    }

    public function flag(User $by, string $note): void
    {
        $this->forceFill(['status' => 'flagged', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
    }

    /**
     * Gone for good: the file and the record. Admins only, for flagged or rejected
     * recordings. Also clears the old audio_* column if it still points here, so
     * `app:import-legacy-audio` can't bring it back.
     */
    public function deletePermanently(): void
    {
        $owner = $this->owner;
        if ($owner) {
            foreach (['audio_path', 'audio_slow_path', 'audio_male_path', 'audio_female_path'] as $column) {
                if (array_key_exists($column, $owner->getAttributes()) && $owner->{$column} === $this->path) {
                    $owner->forceFill([$column => null])->saveQuietly();
                }
            }
            $owner->unsetRelation('currentClips');
        }
        $shared = static::query()->whereKeyNot($this->id)->where('disk', $this->disk)->where('path', $this->path)->exists();
        if (! $shared) {
            Storage::disk($this->disk)->delete($this->path);
        }
        $this->delete();
    }

    /** Never played again; kept on disk as history. */
    public function reject(User $by, ?string $note = null): void
    {
        $this->forceFill(['status' => 'rejected', 'is_current' => false, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
    }
}
