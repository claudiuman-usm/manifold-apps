<?php

namespace App\Modules\Receipts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $table = 'receipts';

    protected $fillable = [
        'original_path', 'image_path', 'merchant', 'amount', 'currency',
        'purchased_at', 'category_id', 'client_id', 'allocation_id', 'notes', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'purchased_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class, 'allocation_id');
    }
}
