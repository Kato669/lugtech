<?php

namespace App\Models;

use App\Models\Concerns\Reviewable;
use Illuminate\Database\Eloquent\Model;

/** A Ganda name and what it says (twin names, names from the lake…). Phase 15. */
class GandaName extends Model
{
    use Reviewable;

    protected $fillable = ['name', 'gender', 'meaning', 'notes', 'position'];
}
