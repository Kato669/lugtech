<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Something a learner bought in the cowrie shop (Phase 13). Bought once, worn as often as they like. */
class UserItem extends Model
{
    protected $fillable = ['user_id', 'item', 'price'];
}
