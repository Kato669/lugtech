<?php

namespace App\Models;

use App\Models\Concerns\Contributable;
use App\Models\Concerns\HasSlug;
use App\Support\Syllables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Word extends Model
{
    use Contributable, HasSlug;

    protected $fillable = ['kind', 'luganda', 'english', 'part_of_speech', 'plural', 'example_lg', 'example_en', 'audio_path', 'is_reviewed', 'syllables'];

    protected function casts(): array
    {
        return ['is_reviewed' => 'boolean', 'reviewed_at' => 'datetime', 'syllables_checked_at' => 'datetime'];
    }

    public static function codePrefix(): string
    {
        return 'W';
    }

    protected static function booted(): void
    {
        // Phase 11: every word gets a draft syllable guide. Re-spelling makes a fresh draft,
        // unless a new guide was typed in at the same time.
        static::saving(function (self $word) {
            $respelled = $word->exists && $word->isDirty('luganda');
            if ((! $word->exists && blank($word->syllables)) || ($respelled && ! $word->isDirty('syllables'))) {
                $word->syllables = $word->kind === 'proverb' ? null : Syllables::guess($word->luganda);
            }
            if ($respelled || $word->isDirty('syllables')) {
                if (! $word->isDirty('syllables_checked_at')) {
                    $word->syllables_checked_at = null;
                    $word->syllables_checked_by = null;
                }
            }
        });
    }

    protected function slugSource(): ?string
    {
        return $this->luganda;
    }

    /** Explicit links from words inside phrases to this entry (Admin → Word links). */
    public function phraseLinks(): HasMany
    {
        return $this->hasMany(PhraseWord::class);
    }

    public function syllablesChecked(): bool
    {
        return filled($this->syllables) && $this->syllables_checked_at !== null;
    }

    /** A native speaker says the syllable guide is right (optionally correcting it). */
    public function checkSyllables(User $by, ?string $syllables = null): void
    {
        $this->forceFill([
            'syllables' => $syllables ?? $this->syllables,
            'syllables_checked_at' => now(),
            'syllables_checked_by' => $by->id,
        ])->save();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }
        $like = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(fn ($q) => $q
            ->whereRaw('LOWER(luganda) LIKE ?', [$like])
            ->orWhereRaw('LOWER(english) LIKE ?', [$like]));
    }

    /** Same entry for everyone on a given day. */
    public static function ofTheDay(string $kind = 'word', ?string $date = null): ?self
    {
        $ids = static::where('kind', $kind)->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }
        $seed = crc32($kind.($date ?? now()->toDateString()));

        return static::find($ids[$seed % $ids->count()]);
    }

    protected function audioUrl(): Attribute
    {
        return Attribute::get(fn () => $this->audibleUrl('normal'));
    }
}
