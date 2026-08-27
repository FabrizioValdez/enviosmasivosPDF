<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'notes',
        'active',
        'config',
    ];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];

    public function imports()
    {
        return $this->hasMany(ProductImport::class);
    }
}
