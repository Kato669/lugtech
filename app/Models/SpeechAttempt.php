<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A learner said a phrase and checked it. Their audio itself is never kept. */
class SpeechAttempt extends Model
{
    public const SOURCES = ['learn', 'speak', 'game'];

    public const RATINGS = ['good', 'again'];

    protected $fillable = ['user_id', 'phrase_id', 'lesson_id', 'source', 'self_rating', 'provider', 'transcript', 'score', 'error', 'duration_ms'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'duration_ms' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
