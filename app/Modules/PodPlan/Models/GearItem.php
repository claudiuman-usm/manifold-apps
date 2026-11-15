<?php

namespace App\Modules\PodPlan\Models;

use App\Modules\PodPlan\Support\Rules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GearItem extends Model
{
    use SoftDeletes;

    protected $table = 'podplan_gear_items';

    protected $fillable = ['gear_type_id', 'name', 'short_name', 'provider', 'quantity', 'rule_overrides', 'last_answers', 'sort'];

    protected $casts = ['rule_overrides' => 'array', 'last_answers' => 'array', 'quantity' => 'integer'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(GearType::class, 'gear_type_id')->withTrashed();
    }

    public function isMine(): bool
    {
        return trim((string) $this->provider) === '';
    }

    /** Name used where space is tight (the diagram). */
    public function shortLabel(): string
    {
        return trim((string) $this->short_name) !== '' ? $this->short_name : $this->name;
    }

    public function hasCustomRules(): bool
    {
        return $this->rule_overrides !== null;
    }

    /** The item's own rules when it overrides, the type preset otherwise. */
    public function effectiveRules(): array
    {
        return $this->hasCustomRules()
            ? Rules::normalize($this->rule_overrides)
            : $this->type->presetRules();
    }
}
