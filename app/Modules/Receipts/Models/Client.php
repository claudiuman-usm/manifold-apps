<?php

namespace App\Modules\Receipts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'receipt_clients';

    protected $fillable = ['name', 'email', 'notes'];

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'client_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'client_id');
    }
}
