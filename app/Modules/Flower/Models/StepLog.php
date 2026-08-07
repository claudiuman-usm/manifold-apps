<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StepLog extends Model
{
    use SoftDeletes;

    protected $table = 'flower_step_logs';

    protected $fillable = ['run_id', 'step_id', 'started_at', 'completed_at', 'duration_seconds'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'step_id');
    }
}
