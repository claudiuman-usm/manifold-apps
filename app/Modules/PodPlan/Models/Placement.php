<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Placement extends Model
{
    protected $table = 'podplan_placements';

    protected $fillable = ['project_id', 'kind', 'gear_item_id', 'label', 'x', 'y', 'rotation', 'fov', 'number', 'sort'];

    protected $casts = ['x' => 'float', 'y' => 'float', 'rotation' => 'float', 'fov' => 'float', 'number' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GearItem::class, 'gear_item_id')->withTrashed();
    }
}
