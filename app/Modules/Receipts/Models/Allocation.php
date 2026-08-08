<?php

namespace App\Modules\Receipts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allocation extends Model
{
    use SoftDeletes;

    protected $table = 'receipt_allocations';

    protected $fillable = ['client_id', 'title', 'period_month', 'notes'];

    protected $casts = [
        'period_month' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'allocation_id');
    }

    public function total(): float
    {
        return (float) $this->receipts()->sum('amount');
    }
}
