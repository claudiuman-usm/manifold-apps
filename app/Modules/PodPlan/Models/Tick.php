<?php

namespace App\Modules\PodPlan\Models;

use Illuminate\Database\Eloquent\Model;

class Tick extends Model
{
    protected $table = 'podplan_ticks';

    protected $fillable = ['project_id', 'row_key'];
}
