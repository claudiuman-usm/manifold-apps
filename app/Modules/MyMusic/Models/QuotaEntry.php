<?php

namespace App\Modules\MyMusic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class QuotaEntry extends Model
{
    use SoftDeletes;

    protected $table = 'music_quota_log';

    protected $fillable = ['action', 'units', 'used_on', 'meta'];

    protected $casts = [
        'units' => 'integer',
        'used_on' => 'date',
        'meta' => 'array',
    ];

    /** YouTube's daily quota resets at midnight Pacific time. */
    public static function quotaDate(): string
    {
        return Carbon::now('America/Los_Angeles')->toDateString();
    }

    public static function record(string $action, int $units, array $meta = []): void
    {
        static::create([
            'action' => $action,
            'units' => $units,
            'used_on' => static::quotaDate(),
            'meta' => $meta ?: null,
        ]);
    }

    public static function todayUnits(): int
    {
        return (int) static::query()->whereDate('used_on', static::quotaDate())->sum('units');
    }

    public static function remainingToday(): int
    {
        return max(0, (int) config('music.daily_quota', 10000) - static::todayUnits());
    }
}
