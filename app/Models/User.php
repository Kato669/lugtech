<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Match the database defaults so new (guest) accounts show 0, not blank. */
    protected $attributes = [
        'is_admin' => false,
        'is_guest' => false,
        'is_reviewer' => false,
        'cowries' => 0,
        'current_streak' => 0,
        'longest_streak' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_guest' => 'boolean',
            'is_reviewer' => 'boolean',
            'last_active_on' => 'date',
        ];
    }

    /** Admins and native-speaker reviewers can record audio and check content. */
    public function isContributor(): bool
    {
        return $this->is_admin || $this->is_reviewer;
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    /** Streak counts as alive if the learner practised today or yesterday. */
    public function liveStreak(): int
    {
        if (! $this->last_active_on) {
            return 0;
        }

        return $this->last_active_on->greaterThanOrEqualTo(today()->subDay()) ? $this->current_streak : 0;
    }
}
