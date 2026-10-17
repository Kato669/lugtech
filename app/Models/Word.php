<?php

namespace App\Models;

use App\Models\Concerns\Contributable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    use Contributable;

    protected $fillable = ['kind', 'luganda', 'english', 'part_of_speech', 'plural', 'example_lg', 'example_en', 'audio_path', 'is_reviewed'];

    protected function casts(): array
    {
        return ['is_reviewed' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public static function codePrefix(): string
    {
        return 'W';
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
