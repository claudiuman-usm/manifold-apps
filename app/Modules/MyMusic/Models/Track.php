<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Track extends Model
{
    use SoftDeletes;

    protected $table = 'music_tracks';

    protected $fillable = [
        'video_id', 'artist', 'title', 'album', 'year', 'genres',
        'enrich_status', 'enriched_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'genres' => 'array',
        'enriched_at' => 'datetime',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id', 'video_id');
    }
}
