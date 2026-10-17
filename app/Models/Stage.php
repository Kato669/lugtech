<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = ['position', 'slug', 'title_lg', 'title_en', 'description', 'greeting'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function publishedLessons(): HasMany
    {
        return $this->lessons()->where('is_published', true);
    }
}
