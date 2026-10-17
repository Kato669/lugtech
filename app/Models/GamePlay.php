<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamePlay extends Model
{
    protected $fillable = ['user_id', 'game', 'score', 'cowries'];
}
