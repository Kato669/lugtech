<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A real recording of one of a guide's English lines (App\Support\TeacherLines). */
class TeacherRecording extends Model
{
    protected $fillable = ['character', 'text', 'text_key', 'path', 'recorded_by'];

    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }
}
