<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'sku',
        'internal_code',
        'supplier_code',
        'description',
        'family',
        'line',
        'price',
        'cost',
        'stock',
        'unit',
        'measurement',
        'brand',
        'supplier',
        'currency',
        'active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock' => 'integer',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    public function imports()
    {
        return $this->hasMany(ProductImportItem::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(ProductAuditLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('product_code', $code);
    }

    public function scopeBySku($query, string $sku)
    {
        return $query->where('sku', $sku);
    }

    public function scopeBySupplierCode($query, string $code)
    {
        return $query->where('supplier_code', $code);
    }
}
