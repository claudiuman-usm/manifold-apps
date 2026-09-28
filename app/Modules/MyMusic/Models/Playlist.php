<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Playlist extends Model
{
    use SoftDeletes;

    protected $table = 'music_playlists';

    protected $fillable = [
        'youtube_id', 'name', 'filter_json', 'queue', 'status', 'failed_count', 'last_synced_at',
    ];

    protected $casts = [
        'filter_json' => 'array',
        'queue' => 'array',
        'failed_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id');
    }
}
