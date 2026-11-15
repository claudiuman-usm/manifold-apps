<?php

namespace App\Modules\PodPlan\Models;

use App\Modules\PodPlan\Support\Rules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GearType extends Model
{
    use SoftDeletes;

    protected $table = 'podplan_gear_types';

    protected $fillable = ['key', 'name', 'category', 'kind', 'symbol', 'role', 'connection', 'rules', 'sort'];

    protected $casts = ['rules' => 'array'];

    public function items(): HasMany
    {
        return $this->hasMany(GearItem::class, 'gear_type_id')->orderBy('sort')->orderBy('id');
    }

    public function isPlan(): bool
    {
        return $this->kind === 'plan';
    }

    /** Localised name for the built-in types; the stored name otherwise. */
    public function label(): string
    {
        $key = 'podplan::messages.types.'.$this->key;
        $translated = __($key);

        return $translated === $key ? $this->name : $translated;
    }

    public function categoryLabel(): string
    {
        $key = 'podplan::messages.categories.'.$this->category;
        $translated = __($key);

        return $translated === $key ? ucfirst($this->category) : $translated;
    }

    public function presetRules(): array
    {
        return Rules::normalize($this->rules);
    }
}
