<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;

class ManualItem extends Model
{
    protected $table = 'podplan_manual_items';

    protected $fillable = ['project_id', 'label', 'packed', 'sort'];

    protected $casts = ['packed' => 'boolean'];
}
