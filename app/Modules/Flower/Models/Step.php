<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Step extends Model
{
    use SoftDeletes;

    protected $table = 'flower_steps';

    protected $fillable = ['template_id', 'name', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(StepLog::class, 'step_id');
    }

    /**
     * Average duration (seconds) of this step across all COMPLETED runs of its
     * template. Null when there is no history yet. Drives the nudge threshold.
     */
    public function averageDuration(): ?float
    {
        return $this->averageDurationExcludingRun(null);
    }

    /**
     * Same average but excluding one run — used by a run's summary so it is
     * compared against the average of the runs that preceded it.
     */
    public function averageDurationExcludingRun(?int $runId): ?float
    {
        $query = $this->averageDurationQuery();

        if ($runId !== null) {
            $query->where('flower_step_logs.run_id', '!=', $runId);
        }

        $avg = $query->avg('flower_step_logs.duration_seconds');

        return $avg !== null ? (float) $avg : null;
    }

    protected function averageDurationQuery()
    {
        return StepLog::query()
            ->where('flower_step_logs.step_id', $this->id)
            ->whereNotNull('duration_seconds')
            ->join('flower_runs', 'flower_runs.id', '=', 'flower_step_logs.run_id')
            ->where('flower_runs.status', 'completed')
            ->whereNull('flower_runs.deleted_at');
    }
}
