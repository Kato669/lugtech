<?php

namespace App\Models;

use App\Models\Concerns\Contributable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phrase extends Model
{
    use Contributable;

    protected $fillable = ['luganda', 'english', 'literal', 'notes', 'audio_path', 'audio_slow_path', 'audio_male_path', 'audio_female_path', 'is_reviewed'];

    protected $appends = ['audio_url', 'audio_slow_url'];

    /**
     * Recording slots: the lesson takes plus the two character voices.
     * (The recordings themselves live in audio_clips; these old columns are kept in step.)
     */
    public const SLOTS = [
        'normal' => 'audio_path',
        'slow' => 'audio_slow_path',
        'male' => 'audio_male_path',
        'female' => 'audio_female_path',
    ];

    protected function casts(): array
    {
        return ['is_reviewed' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public static function codePrefix(): string
    {
        return 'P';
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class)->withPivot('position');
    }

    protected function audioUrl(): Attribute
    {
        return Attribute::get(fn () => $this->audibleUrl('normal'));
    }

    protected function audioSlowUrl(): Attribute
    {
        return Attribute::get(fn () => $this->audibleUrl('slow'));
    }

    protected function audioMaleUrl(): Attribute
    {
        return Attribute::get(fn () => $this->audibleUrl('male'));
    }

    protected function audioFemaleUrl(): Attribute
    {
        return Attribute::get(fn () => $this->audibleUrl('female'));
    }

    /**
     * The voice a character speaks with: their own recording if there is one,
     * otherwise the lesson recording (still real Luganda), otherwise null.
     */
    public function voiceUrl(string $voice): ?string
    {
        return ($voice === 'male' ? $this->audio_male_url : $this->audio_female_url) ?? $this->audio_url;
    }
}
