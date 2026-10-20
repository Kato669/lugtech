<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A story told page by page (Kintu ne Nambi first). Phase 15. */
class Story extends Model
{
    protected $fillable = ['slug', 'title_lg', 'title_en', 'summary', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(StoryPage::class)->orderBy('position')->orderBy('id');
    }
}
