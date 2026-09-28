<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoogleToken extends Model
{
    use SoftDeletes;

    protected $table = 'music_google_tokens';

    protected $fillable = [
        'access_token', 'refresh_token', 'expires_at', 'scope', 'account_name',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
