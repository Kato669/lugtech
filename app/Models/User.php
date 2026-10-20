<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\DailyHabit;
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

    /** Why people learn (asked in the welcome, Phase 10): key => [icon, label]. */
    public const REASONS = [
        'family' => ['👪', 'My family'],
        'culture' => ['🥁', 'Ganda culture and heritage'],
        'conversation' => ['💬', 'Talking with friends and colleagues'],
        'travel' => ['🧭', 'Travelling in Uganda'],
        'curious' => ['✨', 'Just curious'],
    ];

    /** Daily goals in minutes => name. */
    public const GOALS = [5 => 'Casual', 10 => 'Regular', 15 => 'Serious', 20 => 'Intense'];

    /** Match the database defaults so new (guest) accounts show 0, not blank. */
    protected $attributes = [
        'is_admin' => false,
        'is_guest' => false,
        'is_reviewer' => false,
        'cowries' => 0,
        'current_streak' => 0,
        'longest_streak' => 0,
        'streak_freezes' => 0,
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
            'onboarded_at' => 'datetime',
            'daily_goal' => 'integer',
            'streak_freezes' => 'integer',
            'freeze_used_on' => 'date',
            'reminder_hour' => 'integer',
            'outfit' => 'array',
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

    /** A new learner who hasn't seen the welcome yet (and hasn't started on the path either). */
    public function needsWelcome(): bool
    {
        return ! $this->onboarded_at && ! $this->completions()->exists();
    }

    /** One row per day of practice (Phase 12). */
    public function dailyActivities(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }

    /** Bought in the cowrie shop (Phase 13). */
    public function items(): HasMany
    {
        return $this->hasMany(UserItem::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Streak counts as alive if the learner practised today or yesterday (days in LUGANDA_TIMEZONE),
     * or if their streak freezes cover the days missed since (Phase 12).
     */
    public function liveStreak(): int
    {
        if (! $this->last_active_on) {
            return 0;
        }
        $gap = DailyHabit::daysSince($this->last_active_on);

        return $gap <= 1 || $gap - 1 <= $this->streak_freezes ? $this->current_streak : 0;
    }
}
