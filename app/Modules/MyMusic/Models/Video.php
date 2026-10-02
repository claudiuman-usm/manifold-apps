<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use SoftDeletes;

    protected $table = 'music_videos';

    protected $fillable = [
        'video_id', 'raw_title', 'channel_title', 'video_published_at',
        'liked_at', 'thumbnail', 'liked_position', 'is_music', 'embeddable', 'fetched_at',
        'rating', 'play_count', 'last_played_at', 'unliked_at', 'tempo',
    ];

    protected $casts = [
        'video_published_at' => 'datetime',
        'liked_at' => 'datetime',
        'fetched_at' => 'datetime',
        'is_music' => 'boolean',
        'embeddable' => 'boolean',
        'rating' => 'integer',
        'play_count' => 'integer',
        'last_played_at' => 'datetime',
        'unliked_at' => 'datetime',
    ];

    public function track(): HasOne
    {
        return $this->hasOne(Track::class, 'video_id', 'video_id');
    }
}
