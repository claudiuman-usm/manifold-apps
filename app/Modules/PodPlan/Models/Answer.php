<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'podplan_answers';

    protected $fillable = ['project_id', 'placement_id', 'question', 'gear_item_id', 'value'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(GearItem::class, 'gear_item_id')->withTrashed();
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class, 'placement_id');
    }
}
