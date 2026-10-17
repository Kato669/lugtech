<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a scripted lesson: an activity type and its settings (payload). */
class LessonStep extends Model
{
    protected $fillable = ['lesson_id', 'position', 'type', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
