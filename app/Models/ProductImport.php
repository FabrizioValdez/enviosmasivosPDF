<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'filename',
        'file_hash',
        'status',
        'total_products',
        'processed_products',
        'updated_products',
        'failed_products',
        'not_found_products',
        'requires_review',
        'ai_cost',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_calls',
        'processing_time_ms',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_products' => 'integer',
        'processed_products' => 'integer',
        'updated_products' => 'integer',
        'failed_products' => 'integer',
        'not_found_products' => 'integer',
        'requires_review' => 'integer',
        'ai_cost' => 'decimal:6',
        'ai_tokens_input' => 'integer',
        'ai_tokens_output' => 'integer',
        'ai_calls' => 'integer',
        'processing_time_ms' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_VALIDATING = 'VALIDATING';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';
    const STATUS_FAILED = 'FAILED';
    const STATUS_REQUIRES_REVIEW = 'REQUIRES_REVIEW';

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(ProductImportItem::class, 'import_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => $this->failed_products > 0
                ? self::STATUS_COMPLETED_WITH_ERRORS
                : self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
