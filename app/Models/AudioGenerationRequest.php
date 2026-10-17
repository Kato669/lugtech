<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One attempt to generate a recording (kept for the daily limit and history). */
class AudioGenerationRequest extends Model
{
    protected $fillable = [
        'owner_type', 'owner_id', 'slot', 'provider', 'voice', 'text', 'generation_key',
        'status', 'error', 'audio_clip_id', 'requested_by', 'characters', 'took_ms',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(AudioClip::class, 'audio_clip_id');
    }

    /** Requests that count towards today's limit (real attempts, not test beeps). */
    public function scopeCountedToday(Builder $q): Builder
    {
        return $q->where('created_at', '>=', today())->where('provider', '!=', 'fake');
    }
}
