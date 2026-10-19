<?php

namespace App\Models\Concerns;

use App\Support\Slugs;

/** A stable web address, made once when the item is created (re-spelling never breaks a link).
 * Public routes bind with {word:slug} / {phrase:slug}; admin routes keep using ids. */
trait HasSlug
{
    abstract protected function slugSource(): ?string;

    public static function bootHasSlug(): void
    {
        static::creating(function (self $item) {
            if (blank($item->slug)) {
                $taken = [];
                $item->slug = Slugs::unique(
                    Slugs::fromText($item->slugSource(), strtolower(class_basename($item)).'-'.uniqid()),
                    $taken,
                    fn (string $s) => static::where('slug', $s)->exists(),
                );
            }
        });
    }
}
