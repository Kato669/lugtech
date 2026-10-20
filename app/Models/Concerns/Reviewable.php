<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 15: culture content (clans, names, story pages) is checked by a native speaker like phrases:
 * approved, or flagged with a note. Drafts show a "Draft" label and stay out of search engines.
 */
trait Reviewable
{
    public function initializeReviewable(): void
    {
        $this->mergeCasts(['is_reviewed' => 'boolean', 'reviewed_at' => 'datetime']);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeToCheck(Builder $q): Builder
    {
        return $q->where('is_reviewed', false);
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
