<?php

namespace App\Services\ProductImport;

use App\Models\Product;
use App\Models\ProductAuditLog;
use Illuminate\Support\Facades\DB;

class ProductUpdateService
{
    private DataNormalizationService $normalizer;

    public function __construct()
    {
        $this->normalizer = new DataNormalizationService();
    }

    public function bulkUpdate(array $items): array
    {
        $results = [
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $chunks = array_chunk($items, 100);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();

            try {
                foreach ($chunk as $item) {
                    $result = $this->updateSingleProduct($item);

                    if ($result['status'] === 'updated') {
                        $results['updated']++;
                    } elseif ($result['status'] === 'failed') {
                        $results['failed']++;
                    } else {
                        $results['skipped']++;
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return $results;
    }

    private function updateSingleProduct(array $item): array
    {
        $product = $item['product'] ?? null;
        $pdfData = $item['pdf_data'] ?? [];

        if (!$product) {
            return ['status' => 'failed', 'message' => 'No product provided'];
        }

        $changes = $this->detectChanges($product, $pdfData);

        if (empty($changes)) {
            return ['status' => 'skipped', 'message' => 'No changes detected'];
        }

        try {
            $oldValues = [];
            $newValues = [];

            foreach ($changes as $field => $change) {
                $oldValues[$field] = $change['old'];
                $newValues[$field] = $change['new'];
            }

            $product->update($newValues);

            $this->logAudit($product, $oldValues, $newValues, $item['import_id'] ?? null);

            return ['status' => 'updated', 'changes' => $changes];

        } catch (\Exception $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    private function detectChanges(Product $product, array $pdfData): array
    {
        $changes = [];

        if (isset($pdfData['price']) && $pdfData['price'] !== null) {
            $newPrice = $this->normalizer->normalizePrice($pdfData['price']);

            if ($newPrice !== null && $newPrice != $product->price) {
                if ($this->isPriceChangeValid($product->price, $newPrice)) {
                    $changes['price'] = [
                        'old' => $product->price,
                        'new' => $newPrice,
                    ];
                }
            }
        }

        if (isset($pdfData['cost']) && $pdfData['cost'] !== null) {
            $newCost = $this->normalizer->normalizePrice($pdfData['cost']);

            if ($newCost !== null && $newCost != $product->cost) {
                $changes['cost'] = [
                    'old' => $product->cost,
                    'new' => $newCost,
                ];
            }
        }

        if (isset($pdfData['stock']) && $pdfData['stock'] !== null) {
            $newStock = $this->normalizer->normalizeStock($pdfData['stock']);

            if ($newStock !== null && $newStock > 0 && $newStock != $product->stock) {
                if ($this->isStockChangeValid($product->stock, $newStock)) {
                    $changes['stock'] = [
                        'old' => $product->stock,
                        'new' => $newStock,
                    ];
                }
            }
        }

        return $changes;
    }

    private function isPriceChangeValid(float $oldPrice, float $newPrice): bool
    {
        if ($oldPrice == 0) {
            return true;
        }

        $changePercentage = abs(($newPrice - $oldPrice) / $oldPrice * 100);

        return $changePercentage <= 500;
    }

    private function isStockChangeValid(int $oldStock, int $newStock): bool
    {
        if ($oldStock == 0) {
            return true;
        }

        $changePercentage = abs(($newStock - $oldStock) / $oldStock * 100);

        return $changePercentage <= 1000;
    }

    private function logAudit(Product $product, array $oldValues, array $newValues, ?int $importId): void
    {
        foreach ($oldValues as $field => $oldValue) {
            ProductAuditLog::create([
                'product_id' => $product->id,
                'import_id' => $importId,
                'field' => $field,
                'old_value' => (string) $oldValue,
                'new_value' => (string) ($newValues[$field] ?? ''),
                'action' => 'UPDATE',
                'source' => 'PDF_IMPORT',
            ]);
        }
    }
}
