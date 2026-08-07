<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Type extends Model
{
    use SoftDeletes;

    protected $table = 'flower_types';

    protected $fillable = ['client_id', 'name'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'type_id');
    }
}
