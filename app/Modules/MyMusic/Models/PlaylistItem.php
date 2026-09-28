<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlaylistItem extends Model
{
    use SoftDeletes;

    protected $table = 'music_playlist_items';

    protected $fillable = ['playlist_id', 'video_id', 'position'];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id');
    }
}
