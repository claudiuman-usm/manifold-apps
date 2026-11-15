<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneralRule extends Model
{
    use SoftDeletes;

    public const CONDITIONS = ['always', 'live', 'not_live'];

    protected $table = 'podplan_general_rules';

    protected $fillable = ['condition', 'label', 'gear_type_id', 'sort'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(GearType::class, 'gear_type_id')->withTrashed();
    }
}
