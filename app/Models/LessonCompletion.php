<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonCompletion extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'best_score', 'times_completed', 'last_completed_at'];

    protected function casts(): array
    {
        return ['last_completed_at' => 'datetime', 'placed_at' => 'datetime'];
    }

    /** Marked known by the placement check, and not played since. */
    public function isPlacedOnly(): bool
    {
        return $this->placed_at !== null && ! $this->times_completed;
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
