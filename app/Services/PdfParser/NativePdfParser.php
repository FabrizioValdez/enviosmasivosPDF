<?php

namespace App\Services\PdfParser;

use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class NativePdfParser implements PdfParserInterface
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function canParse(string $filePath): bool
    {
        return file_exists($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function parse(string $filePath): array
    {
        if (!$this->canParse($filePath)) {
            throw new Exception("Cannot parse file: {$filePath}");
        }

        $pdf = $this->parser->parseFile($filePath);
        $pages = $pdf->getPages();
        $fullText = $pdf->getText();

        Log::info("PDF raw text extracted", [
            'pages' => count($pages),
            'text_length' => strlen($fullText),
            'text_preview' => substr($fullText, 0, 1000),
        ]);

        $rows = $this->parseTextToProducts($fullText);

        return [
            'headers' => [],
            'rows' => $rows,
            'total_rows' => count($rows),
            'pages' => count($pages),
            'parser' => 'native',
        ];
    }

    private function parseTextToProducts(string $text): array
    {
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($line) {
            return !empty($line) && strlen($line) > 3;
        });

        $products = [];
        $codePattern = '/^([A-Z]{1,5}[-.]?\d{1,10})\b/';
        $pricePattern = '/(\d{1,6}(?:[.,]\d{1,4})?)/';
        $skipWords = ['/^(codigo|code|cod|sku|ref|producto|description|precio|price|stock|marca|brand|familia|total|subtotal|iva|fecha|pagina|page|no\.?|nro)/i'];

        foreach ($lines as $line) {
            if (preg_match($skipWords[0], $line)) {
                continue;
            }

            $product = $this->extractProductFromLine($line, $codePattern, $pricePattern);

            if ($product !== null) {
                $products[] = $product;
            }
        }

        if (empty($products)) {
            $products = $this->tryColumnBasedParsing($lines);
        }

        if (empty($products)) {
            $products = $this->trySpaceDelimitedParsing($lines);
        }

        return $products;
    }

    private function extractProductFromLine(string $line, string $codePattern, string $pricePattern): ?array
    {
        $parts = preg_split('/\s{2,}|\t/', $line);

        if (count($parts) >= 2) {
            $code = null;
            $price = null;
            $stock = null;
            $description = null;

            foreach ($parts as $partIndex => $part) {
                $part = trim($part);

                if ($code === null && preg_match($codePattern, $part, $matches)) {
                    $code = $matches[1];
                } elseif (preg_match('/^\$?\s*(\d{1,6}(?:[.,]\d{1,2})?)$/', $part, $matches)) {
                    $val = str_replace(',', '.', $matches[1]);
                    $val = preg_replace('/\.(?=.*\.)/', '', $val);
                    if ($price === null) {
                        $price = (float) $val;
                    } elseif ($stock === null && strpos($val, '.') === false) {
                        $stock = (int) $val;
                    }
                } elseif (preg_match('/^\d+$/', $part) && $stock === null && $price !== null) {
                    $stock = (int) $part;
                } elseif ($description === null && strlen($part) > 3 && !preg_match('/^\d+$/', $part)) {
                    $description = $part;
                }
            }

            if ($code !== null && ($price !== null || $description !== null)) {
                return [
                    'code' => $code,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                ];
            }
        }

        if (preg_match($codePattern, $line, $codeMatch)) {
            preg_match_all($pricePattern, $line, $priceMatches);
            $prices = [];
            foreach ($priceMatches[1] as $p) {
                $val = str_replace(',', '.', $p);
                $val = preg_replace('/\.(?=.*\.)/', '', $val);
                if (is_numeric($val)) {
                    $prices[] = $val;
                }
            }

            if (!empty($prices) && preg_match($codePattern, $line, $cm)) {
                $descParts = preg_split('/\s+/', $line);
                $codeEnd = strpos($line, $cm[1]) + strlen($cm[1]);
                $remaining = substr($line, $codeEnd);
                $remainingParts = preg_split('/\s{2,}/', $remaining);
                $description = !empty($remainingParts) ? trim($remainingParts[0]) : null;

                if ($description === null || strlen($description) < 3) {
                    $beforeCode = substr($line, 0, strpos($line, $cm[1]));
                    $description = trim($beforeCode);
                }

                return [
                    'code' => $cm[1],
                    'description' => !empty($description) ? $description : null,
                    'price' => (float) $prices[0],
                    'stock' => count($prices) > 1 ? (int) $prices[1] : null,
                ];
            }
        }

        return null;
    }

    private function tryColumnBasedParsing(array $lines): array
    {
        $products = [];
        $codeRegex = '/\b([A-Z]{1,5}[-.]?\d{1,10})\b/';
        $priceRegex = '/\$\s*(\d{1,6}(?:[.,]\d{1,2})?)/';

        foreach ($lines as $line) {
            if (preg_match($codeRegex, $line, $codeMatch) && preg_match($priceRegex, $line, $priceMatch)) {
                $code = $codeMatch[1];
                $priceStr = str_replace(',', '.', $priceMatch[1]);
                $price = (float) $priceStr;

                $allNumbers = [];
                if (preg_match_all('/\d+(?:[.,]\d+)?/', $line, $numMatches)) {
                    foreach ($numMatches[0] as $num) {
                        $val = str_replace(',', '.', $num);
                        if (is_numeric($val) && (float) $val > 0) {
                            $allNumbers[] = $val;
                        }
                    }
                }

                $description = preg_replace($codeRegex, '', $line);
                $description = preg_replace('/\$\s*\d+(?:[.,]\d+)?/', '', $description);
                $description = preg_replace('/\s{2,}/', ' ', $description);
                $description = trim($description);

                $stock = null;
                if (count($allNumbers) > 1) {
                    $lastNum = end($allNumbers);
                    if ((float) $lastNum == (int) $lastNum && (int) $lastNum > 0 && (int) $lastNum < 100000) {
                        $stock = (int) $lastNum;
                    }
                }

                $products[] = [
                    'code' => $code,
                    'description' => !empty($description) ? $description : null,
                    'price' => $price,
                    'stock' => $stock,
                ];
            }
        }

        return $products;
    }

    private function trySpaceDelimitedParsing(array $lines): array
    {
        $products = [];
        $codeRegex = '/^([A-Z]{1,5}[-.]?\d{1,10})\s+(.+?)\s+(\d{1,6}(?:[.,]\d{1,2})?)\s+(\d+)\s*$/';

        foreach ($lines as $line) {
            if (preg_match($codeRegex, $line, $matches)) {
                $price = (float) str_replace(',', '.', $matches[3]);
                $stock = (int) $matches[4];

                $products[] = [
                    'code' => $matches[1],
                    'description' => trim($matches[2]),
                    'price' => $price,
                    'stock' => $stock,
                ];
            }
        }

        return $products;
    }
}
