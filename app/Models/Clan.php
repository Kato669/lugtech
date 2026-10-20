<?php

namespace App\Models;

use App\Models\Concerns\Reviewable;
use Illuminate\Database\Eloquent\Model;

/** A Ganda clan (ekika) and its totem (omuziro). Phase 15. */
class Clan extends Model
{
    use Reviewable;

    protected $fillable = ['slug', 'name', 'totem_lg', 'totem_en', 'notes'];
}
