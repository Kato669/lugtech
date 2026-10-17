<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewItem extends Model
{
    protected $fillable = ['user_id', 'phrase_id', 'ease', 'interval_days', 'repetitions', 'due_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'ease' => 'float'];
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }

    /**
     * Simplified SM-2: a correct answer pushes the phrase further out,
     * a wrong one brings it back tomorrow.
     */
    public function grade(bool $correct): void
    {
        if ($correct) {
            $this->repetitions++;
            $this->interval_days = match ($this->repetitions) {
                1 => 1,
                2 => 3,
                default => (int) round(max(1, $this->interval_days) * $this->ease),
            };
            $this->ease = min(3.0, $this->ease + 0.1);
        } else {
            $this->repetitions = 0;
            $this->interval_days = 1;
            $this->ease = max(1.3, $this->ease - 0.2);
        }
        $this->due_at = now()->addDays($this->interval_days);
        $this->save();
    }
}
