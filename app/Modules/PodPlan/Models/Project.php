<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    public const SHOOT_TYPES = ['podcast', 'interview', 'event', 'other'];

    protected $table = 'podplan_projects';

    protected $fillable = [
        'name', 'shoot_type', 'shoot_date', 'location', 'people_count',
        'live_mixing', 'description', 'share_token', 'plan_undo', 'ai_result',
    ];

    protected $casts = [
        'shoot_date' => 'date',
        'live_mixing' => 'boolean',
        'people_count' => 'integer',
        'plan_undo' => 'array',
        'ai_result' => 'array',
    ];

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'project_id')->orderBy('sort')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'project_id');
    }

    public function ticks(): HasMany
    {
        return $this->hasMany(Tick::class, 'project_id');
    }

    public function manualItems(): HasMany
    {
        return $this->hasMany(ManualItem::class, 'project_id')->orderBy('sort')->orderBy('id');
    }
}
