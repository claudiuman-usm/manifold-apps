<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Play extends Model
{
    use SoftDeletes;

    protected $table = 'music_plays';

    protected $fillable = ['video_id', 'played_at'];

    protected $casts = ['played_at' => 'datetime'];
}
