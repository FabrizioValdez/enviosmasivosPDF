<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'product_id',
        'supplier_code',
        'matched_code',
        'old_price',
        'new_price',
        'old_cost',
        'new_cost',
        'old_stock',
        'new_stock',
        'status',
        'confidence',
        'match_level',
        'error_message',
        'raw_data',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_cost' => 'decimal:2',
        'new_cost' => 'decimal:2',
        'old_stock' => 'integer',
        'new_stock' => 'integer',
        'confidence' => 'decimal:2',
        'raw_data' => 'array',
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_MATCHED = 'MATCHED';
    const STATUS_UPDATED = 'UPDATED';
    const STATUS_NOT_FOUND = 'NOT_FOUND';
    const STATUS_FAILED = 'FAILED';
    const STATUS_REQUIRES_REVIEW = 'REQUIRES_REVIEW';

    public function import()
    {
        return $this->belongsTo(ProductImport::class, 'import_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
