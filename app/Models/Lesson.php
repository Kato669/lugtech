<?php

namespace App\Models;

use App\Lessons\ActivityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = ['stage_id', 'position', 'slug', 'title', 'goal', 'is_published', 'mode'];

    /** Match the database default so new lessons are "auto" before saving. */
    protected $attributes = ['mode' => 'auto'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function phrases(): BelongsToMany
    {
        return $this->belongsToMany(Phrase::class)->withPivot('position')->orderByPivot('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(LessonStep::class)->orderBy('position')->orderBy('id');
    }

    public function isScripted(): bool
    {
        return $this->mode === 'scripted';
    }

    /** Every phrase the lesson teaches: its phrase list plus any its steps use. @return int[] */
    public function taughtPhraseIds(): array
    {
        $ids = $this->phrases()->pluck('phrases.id')->all();
        if ($this->isScripted()) {
            $registry = app(ActivityRegistry::class);
            foreach ($this->steps as $step) {
                if ($registry->has($step->type)) {
                    $ids = array_merge($ids, $registry->get($step->type)->phraseIds($step->payload ?? []));
                }
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
