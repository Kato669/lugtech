<?php

namespace App\Models;

use App\Models\Concerns\Reviewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One page of a story: Luganda (a draft until approved, or empty: "coming soon") and English. Phase 15. */
class StoryPage extends Model
{
    use Reviewable;

    protected $fillable = ['story_id', 'position', 'luganda', 'english', 'picture', 'picture_count'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
