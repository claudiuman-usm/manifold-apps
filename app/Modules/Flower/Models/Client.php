<?php

namespace App\Modules\Flower\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'flower_clients';

    protected $fillable = ['name'];

    public function types(): HasMany
    {
        return $this->hasMany(Type::class, 'client_id');
    }
}
