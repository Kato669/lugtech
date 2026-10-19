<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learner's day (Phase 12): practice time, what was done, and the day's three tasks.
 * `day` stays a plain Y-m-d string (no date cast), so lookups match on SQLite and PostgreSQL alike.
 */
class DailyActivity extends Model
{
    protected $fillable = ['user_id', 'day', 'active_seconds', 'lessons', 'perfect_lessons', 'reviews', 'speaks', 'games', 'tasks', 'tasks_done', 'bonus_paid', 'cowries_earned', 'goal_met_at'];

    protected $attributes = [
        'active_seconds' => 0, 'lessons' => 0, 'perfect_lessons' => 0, 'reviews' => 0, 'speaks' => 0, 'games' => 0,
        'bonus_paid' => false, 'cowries_earned' => 0,
    ];

    protected function casts(): array
    {
        return ['tasks' => 'array', 'tasks_done' => 'array', 'bonus_paid' => 'boolean', 'goal_met_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function minutes(): int
    {
        return intdiv($this->active_seconds, 60);
    }
}
