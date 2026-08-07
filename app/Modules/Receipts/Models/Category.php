<?php

namespace App\Modules\Receipts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'receipt_categories';

    protected $fillable = ['name'];

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'category_id');
    }
}
