<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'flower_templates';

    protected $fillable = ['type_id', 'name'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /** Convenience: the client this template belongs to, via its type. */
    public function client(): ?Client
    {
        return $this->type?->client;
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class, 'template_id')->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class, 'template_id');
    }

    public function completedRuns(): HasMany
    {
        return $this->runs()->where('status', 'completed');
    }

    /** The latest still-in-progress run, if any — the one a user would resume. */
    public function activeRun(): HasOne
    {
        return $this->hasOne(Run::class, 'template_id')->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', Run::STATUS_IN_PROGRESS)
        );
    }
}
