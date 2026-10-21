<?php

namespace App\Models;

use App\Support\Slugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A blog post, written by an admin in Markdown (App\Support\BlogMarkdown). Luganda marked
 * [[like this]] can be tapped. published_at: null = draft, in the future = scheduled.
 */
class Post extends Model
{
    public const TOPICS = ['learning' => 'Learning tips', 'culture' => 'Culture', 'news' => 'News'];

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'topic', 'cover_path', 'cover_alt', 'author_id', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /** Published and not scheduled for later. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** draft | scheduled | published */
    public function status(): string
    {
        return $this->published_at === null ? 'draft' : ($this->published_at->isFuture() ? 'scheduled' : 'published');
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? ucfirst($this->topic);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? '/storage/'.ltrim($this->cover_path, '/') : null;
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->body)) / 200));
    }

    /** The summary: the excerpt, or the start of the post. */
    public function summary(): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }
        $text = preg_replace(['/\[\[(.+?)\]\]/u', '/[#*_>`!\[\]\(\)]/u', '/\s+/u'], ['$1', '', ' '], $this->body);

        return (string) str(trim((string) $text))->limit(180);
    }

    /** A web address made from the title, unique, never changed once the post is published. */
    public static function uniqueSlug(string $title, ?int $except = null): string
    {
        $base = Slugs::fromText($title, 'post');
        $taken = [];

        return Slugs::unique($base, $taken, fn ($s) => static::where('slug', $s)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists());
    }
}
