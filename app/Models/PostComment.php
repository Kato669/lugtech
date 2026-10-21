<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A comment under a blog post. Learners' comments show only once an admin approves them; admins' replies show at once. */
class PostComment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'parent_id', 'body', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->whereNotNull('approved_at');
    }

    public function scopeWaiting(Builder $q): Builder
    {
        return $q->whereNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** The name shown with the comment. */
    public function authorName(): string
    {
        return $this->user?->name ?: 'A learner';
    }
}
