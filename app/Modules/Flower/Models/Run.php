<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Run extends Model
{
    use SoftDeletes;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    protected $table = 'flower_runs';

    protected $fillable = ['template_id', 'status', 'started_at', 'completed_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(StepLog::class, 'run_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
