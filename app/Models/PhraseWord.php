<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11: an explicit link from a word inside a phrase to its dictionary entry
 * ("ntebe" in "Tuula ku ntebe" → entebe), or a note that it's a person's name.
 * `form` is the word as written in the phrase, normalised with LugandaText::key().
 */
class PhraseWord extends Model
{
    protected $table = 'phrase_word';

    protected $fillable = ['phrase_id', 'form', 'word_id', 'is_name', 'created_by'];

    protected function casts(): array
    {
        return ['is_name' => 'boolean'];
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
