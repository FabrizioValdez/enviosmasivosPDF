<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'import_id',
        'field',
        'old_value',
        'new_value',
        'action',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function import()
    {
        return $this->belongsTo(ProductImport::class, 'import_id');
    }
}
