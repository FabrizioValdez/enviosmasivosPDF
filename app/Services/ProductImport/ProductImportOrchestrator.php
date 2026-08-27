<?php

namespace App\Services\ProductImport;

use App\Models\ProductImport;
use App\Models\ProductImportItem;
use App\Services\Ai\AiService;
use App\Services\PdfParser\PdfParserFactory;
use Illuminate\Support\Facades\Log;

class ProductImportOrchestrator
{
    private PdfParserFactory $parserFactory;
    private AiService $aiService;
    private ProductMatchingService $matchingService;
    private DataNormalizationService $normalizer;
    private ProductUpdateService $updateService;

    public function __construct()
    {
        $this->parserFactory = new PdfParserFactory();
        $this->aiService = new AiService();
        $this->matchingService = new ProductMatchingService();
        $this->normalizer = new DataNormalizationService();
        $this->updateService = new ProductUpdateService();
    }

    public function processImport(ProductImport $import): void
    {
        $startTime = microtime(true);

        try {
            $import->markAsProcessing();

            $filePath = storage_path('app/public/imports/' . $import->filename);

            if (!file_exists($filePath)) {
                $import->markAsFailed("File not found: {$import->filename}");
                return;
            }

            Log::info("Parsing PDF", ['import_id' => $import->id, 'file' => $filePath]);

            $parsedData = $this->parsePdf($filePath);

            Log::info("PDF parsed", [
                'import_id' => $import->id,
                'rows_found' => count($parsedData['rows'] ?? []),
                'parser' => $parsedData['parser'] ?? 'unknown',
                'raw_text_length' => strlen($parsedData['raw_text'] ?? ''),
            ]);

            $needsAi = $this->needsAiProcessing($parsedData);

            Log::info("AI check", [
                'import_id' => $import->id,
                'needs_ai' => $needsAi,
            ]);

            if ($needsAi) {
                Log::info("Processing with AI", ['import_id' => $import->id]);
                $parsedData = $this->processWithAi($filePath, $parsedData);
                Log::info("AI processing done", [
                    'import_id' => $import->id,
                    'ai_products' => count($parsedData['products'] ?? []),
                ]);
            }

            $products = $parsedData['rows'] ?? $parsedData['products'] ?? [];

            Log::info("Products to process", [
                'import_id' => $import->id,
                'total' => count($products),
                'sample' => array_slice($products, 0, 2),
            ]);

            $import->update([
                'total_products' => count($products),
                'metadata' => [
                    'parser' => $parsedData['parser'] ?? 'unknown',
                    'needs_ai' => $needsAi,
                    'pages' => $parsedData['pages'] ?? 0,
                ],
            ]);

            if (empty($products)) {
                Log::warning("No products found in PDF", ['import_id' => $import->id]);
                $import->update([
                    'error_message' => 'No products could be extracted from the PDF. The file may be scanned/image-based or have an unsupported format.',
                ]);
                $import->markAsCompleted();
                return;
            }

            $matchResults = $this->matchProducts($import, $products);

            $this->processMatchResults($import, $matchResults);

            $this->updateProducts($import, $matchResults);

            $import->update([
                'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);

            $import->markAsCompleted();

            Log::info("Import completed", [
                'import_id' => $import->id,
                'total' => $import->total_products,
                'updated' => $import->updated_products,
                'not_found' => $import->not_found_products,
                'failed' => $import->failed_products,
                'time_ms' => $import->processing_time_ms,
            ]);

        } catch (\Exception $e) {
            Log::error("Import failed", [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $import->markAsFailed($e->getMessage());
        }
    }

    private function parsePdf(string $filePath): array
    {
        $parser = $this->parserFactory->create($filePath);
        $result = $parser->parse($filePath);

        $result['raw_text'] = $this->extractRawText($filePath);

        return $result;
    }

    private function needsAiProcessing(array $parsedData): bool
    {
        $rows = $parsedData['rows'] ?? [];

        if (empty($rows)) {
            return true;
        }

        $hasCode = false;
        $hasPrice = false;

        foreach (array_slice($rows, 0, 10) as $row) {
            if (!empty($row['code'])) {
                $hasCode = true;
            }
            if (!empty($row['price'])) {
                $hasPrice = true;
            }
        }

        return !($hasCode && $hasPrice);
    }

    private function processWithAi(string $filePath, array $parsedData): array
    {
        Log::info("Enviando archivo PDF directamente a Gemini Vision (evitando texto plano corrupto)", [
            'file_path' => $filePath
        ]);

        $provider = $this->aiService->getProvider();

        // 1. FORZAR el uso de Gemini Vision enviando el PDF completo
        if (method_exists($provider, 'extractProductsFromPdf')) {
            $aiResult = $provider->extractProductsFromPdf($filePath);
        } else {
            // Fallback en caso de que el proveedor no soporte PDF nativo
            $text = $parsedData['raw_text'] ?? $this->extractRawText($filePath);
            $aiResult = $this->aiService->extractProducts($text);
        }

        Log::info("AI result received", [
            'products_count' => count($aiResult['products'] ?? []),
            'confidence'     => $aiResult['confidence'] ?? 0,
            'notes'          => $aiResult['notes'] ?? '',
        ]);

        $import = ProductImport::latest()->first();

        if ($import) {
            $import->update([
                'ai_cost'          => $provider->getCost(),
                'ai_tokens_input'  => method_exists($provider, 'getTotalInputTokens') ? $provider->getTotalInputTokens() : 0,
                'ai_tokens_output' => method_exists($provider, 'getTotalOutputTokens') ? $provider->getTotalOutputTokens() : 0,
                'ai_calls'         => 1,
            ]);
        }

        $products = $aiResult['products'] ?? [];

        return [
            'rows' => array_map(function ($product) {
                return [
                    'code'        => $product['code'] ?? null,
                    'description' => $product['description'] ?? null,
                    'price'       => $product['price'] ?? null,
                    'stock'       => $product['stock'] ?? null,
                    'unit'        => $product['unit'] ?? null,
                    'brand'       => $product['brand'] ?? null,
                    'family'      => $product['family'] ?? null,
                    'measurement' => $product['measurement'] ?? null,
                ];
            }, $products),
            'parser'     => 'ai',
            'confidence' => $aiResult['confidence'] ?? 0,
        ];
    }

    private function extractRawText(string $filePath): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (\Exception $e) {
            Log::error("Failed to extract raw text", ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function matchProducts(ProductImport $import, array $products): array
    {
        $results = [
            'matched'         => [],
            'not_found'       => [],
            'requires_review' => [],
        ];

        foreach ($products as $index => $pdfProduct) {
            $code = $pdfProduct['code'] ?? null;

            // Normalizar código si existe
            if (!empty($code)) {
                $pdfProduct['code'] = $this->normalizer->normalizeCode($code);
            }

            // 2. PERMITIR MATCHING POR DESCRIPCIÓN si el código es nulo
            $match = $this->matchingService->matchProduct($pdfProduct);

            if ($match === null) {
                $results['not_found'][] = [
                    'index'       => $index,
                    'pdf_product' => $pdfProduct,
                    'reason'      => 'Product not found by code or description',
                ];

                ProductImportItem::create([
                    'import_id'     => $import->id,
                    'supplier_code' => $pdfProduct['code'] ?? null,
                    'status'        => ProductImportItem::STATUS_NOT_FOUND,
                    'raw_data'      => $pdfProduct,
                ]);

                continue;
            }

            if ($match['match_level'] === 'DESCRIPTION_FUZZY' && $match['confidence'] < 0.9) {
                $results['requires_review'][] = [
                    'index'       => $index,
                    'pdf_product' => $pdfProduct,
                    'match'       => $match,
                ];

                ProductImportItem::create([
                    'import_id'     => $import->id,
                    'product_id'    => $match['product']->id,
                    'supplier_code' => $pdfProduct['code'] ?? null,
                    'matched_code'  => $match['product']->product_code,
                    'status'        => ProductImportItem::STATUS_REQUIRES_REVIEW,
                    'confidence'    => $match['confidence'],
                    'match_level'   => $match['match_level'],
                    'raw_data'      => $pdfProduct,
                ]);

                continue;
            }

            $results['matched'][] = [
                'index'       => $index,
                'pdf_product' => $pdfProduct,
                'match'       => $match,
            ];
        }

        return $results;
    }

    private function processMatchResults(ProductImport $import, array $matchResults): void
    {
        $import->update([
            'not_found_products' => count($matchResults['not_found']),
            'requires_review' => count($matchResults['requires_review']),
        ]);
    }

    private function updateProducts(ProductImport $import, array $matchResults): void
    {
        $itemsToUpdate = [];

        foreach ($matchResults['matched'] as $matched) {
            $product = $matched['match']['product'];
            $pdfData = $matched['pdf_product'];

            $itemsToUpdate[] = [
                'product' => $product,
                'pdf_data' => $pdfData,
                'import_id' => $import->id,
                'match' => $matched['match'],
            ];
        }

        if (empty($itemsToUpdate)) {
            return;
        }

        $results = $this->updateService->bulkUpdate($itemsToUpdate);

        $import->update([
            'updated_products' => $results['updated'],
            'failed_products' => $results['failed'],
        ]);

        foreach ($itemsToUpdate as $item) {
            $existingItem = ProductImportItem::where('import_id', $import->id)
                ->where('supplier_code', $item['pdf_data']['code'] ?? '')
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'status' => ProductImportItem::STATUS_UPDATED,
                    'old_price' => $item['match']['product']->price,
                    'new_price' => $item['pdf_data']['price'] ?? null,
                    'old_stock' => $item['match']['product']->stock,
                    'new_stock' => $item['pdf_data']['stock'] ?? null,
                ]);
            } else {
                ProductImportItem::create([
                    'import_id' => $import->id,
                    'product_id' => $item['product']->id,
                    'supplier_code' => $item['pdf_data']['code'] ?? '',
                    'matched_code' => $item['match']['product']->product_code,
                    'status' => ProductImportItem::STATUS_UPDATED,
                    'confidence' => $item['match']['confidence'],
                    'match_level' => $item['match']['match_level'],
                    'old_price' => $item['match']['product']->price,
                    'new_price' => $item['pdf_data']['price'] ?? null,
                    'old_stock' => $item['match']['product']->stock,
                    'new_stock' => $item['pdf_data']['stock'] ?? null,
                    'raw_data' => $item['pdf_data'],
                ]);
            }
        }
    }
}
