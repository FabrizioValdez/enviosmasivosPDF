<?php

namespace App\Services\ProductImport;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductMatchingService
{
    private float $confidenceThreshold;
    private array $stopWords;
    private array $unitAliases;
    private array $规格Aliases;

    public function __construct()
    {
        $this->confidenceThreshold = config('services.product_import.confidence_threshold', 0.6);

        $this->stopWords = [
            'de', 'del', 'la', 'el', 'en', 'y', 'a', 'con', 'por', 'para',
            'un', 'una', 'los', 'las', 'al', 'se', 'su', 'sus', 'que', 'como',
            'pt', 'p.t', 'p.t.', 'std', 'std.', 'especificacion', 'espec',
        ];

        $this->unitAliases = [
            'mm' => 'mm', 'milimetro' => 'mm', 'milimetros' => 'mm',
            'pulgada' => 'in', 'pulgadas' => 'in', 'in' => 'in', '"' => 'in',
            'm' => 'm', 'mt' => 'm', 'mts' => 'm', 'metro' => 'm', 'metros' => 'm',
            'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
            'lb' => 'lb', 'libra' => 'lb', 'libras' => 'lb',
            'mm2' => 'mm2', 'mm²' => 'mm2',
            'm2' => 'm2', 'm²' => 'm2',
        ];

        $this->规格Aliases = [
            ' corrugad' => ' corrugad',
            ' acero' => ' acero',
            ' fierro' => ' acero',
            ' inoxid' => ' inoxid',
            ' inox' => ' inox',
            ' astm' => ' astm',
            ' ntp' => ' ntp',
            ' norma' => ' norma',
            ' grado' => ' gr',
            ' gr ' => ' gr',
            ' grade' => ' gr',
            ' barra' => ' barra',
            ' tubo' => ' tubo',
            ' tubos' => ' tubo',
            ' plancha' => ' plancha',
            ' placas' => ' plancha',
            ' perfil' => ' perfil',
            ' perfiles' => ' perfil',
            ' angulo' => ' perfil',
            ' ángulo' => ' perfil',
            ' canal' => ' perfil',
            ' viga' => ' perfil',
            ' redondo' => ' redondo',
            ' cuadrado' => ' cuadrado',
            ' rectangular' => ' rectangular',
            ' hexagonal' => ' hexagonal',
        ];
    }

    public function matchProduct(array $pdfProduct): ?array
    {
        $match = $this->matchByCode($pdfProduct);
        if ($match) return $match;

        $match = $this->matchBySku($pdfProduct);
        if ($match) return $match;

        $match = $this->matchBySupplierCode($pdfProduct);
        if ($match) return $match;

        $match = $this->matchBySmartDescription($pdfProduct);
        if ($match) return $match;

        $match = $this->matchByKeywords($pdfProduct);
        if ($match) return $match;

        return null;
    }

    private function matchByCode(array $pdfProduct): ?array
    {
        $code = $pdfProduct['code'] ?? null;
        if (empty($code)) return null;

        $product = Product::where('product_code', $code)->first();

        if ($product) {
            return [
                'product' => $product,
                'confidence' => 1.0,
                'match_level' => 'EXACT_CODE',
            ];
        }

        return null;
    }

    private function matchBySku(array $pdfProduct): ?array
    {
        $code = $pdfProduct['code'] ?? null;
        if (empty($code)) return null;

        $product = Product::where('sku', $code)->first();

        if ($product) {
            return [
                'product' => $product,
                'confidence' => 1.0,
                'match_level' => 'EXACT_SKU',
            ];
        }

        return null;
    }

    private function matchBySupplierCode(array $pdfProduct): ?array
    {
        $code = $pdfProduct['code'] ?? null;
        if (empty($code)) return null;

        $product = Product::where('supplier_code', $code)->first();

        if ($product) {
            return [
                'product' => $product,
                'confidence' => 0.95,
                'match_level' => 'SUPPLIER_CODE',
            ];
        }

        return null;
    }

    private function matchBySmartDescription(array $pdfProduct): ?array
    {
        $description = $pdfProduct['description'] ?? null;
        if (empty($description) || strlen($description) < 5) return null;

        $pdfType = $this->extractProductType($description);
        $pdfDiameter = $this->extractDiameter($description);
        $pdfKeywords = $this->extractKeywords($description);

        if (count($pdfKeywords) < 2) return null;

        $allProducts = Product::where('active', true)
            ->whereNotNull('description')
            ->get();

        if ($allProducts->isEmpty()) return null;

        $bestMatch = null;
        $bestScore = 0;

        foreach ($allProducts as $product) {
            $candidateType = $this->extractProductType($product->description);
            if ($pdfType && $candidateType && $pdfType !== $candidateType) {
                continue;
            }

            $candidateDiameter = $this->extractDiameter($product->description);
            if ($pdfDiameter !== null && $candidateDiameter !== null) {
                if (abs($pdfDiameter - $candidateDiameter) > 1.0) {
                    continue;
                }
            }

            $score = $this->calculateKeywordScore($pdfKeywords, $product->description);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        if ($bestMatch && $bestScore >= $this->confidenceThreshold) {
            Log::info("Smart description match found", [
                'pdf' => $description,
                'matched' => $bestMatch->description,
                'score' => $bestScore,
            ]);

            return [
                'product' => $bestMatch,
                'confidence' => $bestScore,
                'match_level' => 'SMART_DESCRIPTION',
            ];
        }

        return null;
    }

    private function matchByKeywords(array $pdfProduct): ?array
    {
        $description = $pdfProduct['description'] ?? null;
        if (empty($description)) return null;

        $pdfNormalized = $this->normalizeForSearch($description);

        $products = Product::where('active', true)
            ->whereRaw("LOWER(description) LIKE ?", ["%{$this->getSearchTerm($description)}%"])
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            $words = explode(' ', $pdfNormalized);
            $shortWords = array_filter($words, function ($w) {
                return strlen($w) >= 4;
            });

            if (count($shortWords) >= 2) {
                $searchTerms = array_slice($shortWords, 0, 3);
                $query = Product::where('active', true);

                foreach ($searchTerms as $term) {
                    $query->orWhere('description', 'LIKE', "%{$term}%");
                }

                $products = $query->limit(20)->get();
            }
        }

        if ($products->isEmpty()) return null;

        $pdfType = $this->extractProductType($description);
        $pdfDiameter = $this->extractDiameter($description);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $candidateType = $this->extractProductType($product->description);
            if ($pdfType && $candidateType && $pdfType !== $candidateType) {
                continue;
            }

            $candidateDiameter = $this->extractDiameter($product->description);
            if ($pdfDiameter !== null && $candidateDiameter !== null) {
                if (abs($pdfDiameter - $candidateDiameter) > 1.0) {
                    continue;
                }
            }

            $score = $this->calculateKeywordScore(
                $this->extractKeywords($description),
                $product->description
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $product;
            }
        }

        if ($bestMatch && $bestScore >= $this->confidenceThreshold) {
            return [
                'product' => $bestMatch,
                'confidence' => $bestScore,
                'match_level' => 'KEYWORD_MATCH',
            ];
        }

        return null;
    }

    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s"\/.]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        $words = explode(' ', $text);
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word);

            if (strlen($word) < 2) continue;
            if (in_array($word, $this->stopWords)) continue;

            if (preg_match('/^\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)?$/i', $word)) {
                $keywords[] = $this->normalizeSpec($word);
                continue;
            }

            $normalized = $this->normalizeKeyword($word);
            if (!empty($normalized) && !in_array($normalized, $keywords)) {
                $keywords[] = $normalized;
            }
        }

        return $keywords;
    }

    private function normalizeKeyword(string $word): string
    {
        $word = strtolower(trim($word));

        $word = preg_replace('/corrugad[ao]/', 'corrugad', $word);
        $word = preg_replace('/acero|fierro/', 'acero', $word);
        $word = preg_replace('/inoxidable|inox/', 'inox', $word);
        $word = preg_replace('/grado|grade/', 'gr', $word);
        $word = preg_replace('/pulgada|pulgadas/', 'in', $word);
        $word = preg_replace('/metro|metros/', 'm', $word);
        $word = preg_replace('/kilo|kilos/', 'kg', $word);

        return $word;
    }

    private function normalizeSpec(string $spec): string
    {
        $spec = strtolower(trim($spec));
        $spec = str_replace('"', ' in', $spec);
        $spec = str_replace("'", ' ft', $spec);
        $spec = preg_replace('/\s+/', '', $spec);
        return $spec;
    }

    private function normalizeForSearch(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function getSearchTerm(string $description): string
    {
        $keywords = $this->extractKeywords($description);
        $significant = array_filter($keywords, function ($k) {
            return strlen($k) >= 4 && !preg_match('/^\d+/', $k);
        });

        if (empty($significant)) {
            return substr($this->normalizeForSearch($description), 0, 20);
        }

        return end($significant);
    }

    private function calculateKeywordScore(array $pdfKeywords, string $candidateDescription): float
    {
        if (empty($pdfKeywords)) return 0;

        $candidateKeywords = $this->extractKeywords($candidateDescription);

        if (empty($candidateKeywords)) return 0;

        $matches = 0;
        $totalWeight = 0;

        foreach ($pdfKeywords as $index => $pdfWord) {
            $weight = max(1, 10 - $index);
            $totalWeight += $weight;

            foreach ($candidateKeywords as $candidateWord) {
                if ($pdfWord === $candidateWord) {
                    $matches += $weight;
                    break;
                }

                if ($this->areSimilar($pdfWord, $candidateWord)) {
                    $matches += $weight * 0.8;
                    break;
                }
            }
        }

        $specsPdf = $this->extractSpecs(implode(' ', $pdfKeywords));
        $specsCandidate = $this->extractSpecs(implode(' ', $candidateKeywords));

        if (!empty($specsPdf) && !empty($specsCandidate)) {
            $specMatches = 0;
            foreach ($specsPdf as $spec) {
                if (in_array($spec, $specsCandidate)) {
                    $specMatches++;
                }
            }

            if (!empty($specsPdf)) {
                $specScore = $specMatches / count($specsPdf);
                return ($totalWeight > 0 ? ($matches / $totalWeight) : 0) * 0.6 + $specScore * 0.4;
            }
        }

        return $totalWeight > 0 ? ($matches / $totalWeight) : 0;
    }

    private function areSimilar(string $word1, string $word2): bool
    {
        if ($word1 === $word2) return true;

        if (strlen($word1) >= 5 && strlen($word2) >= 5) {
            similar_text($word1, $word2, $percent);
            if ($percent >= 80) return true;
        }

        $len1 = strlen($word1);
        $len2 = strlen($word2);
        $maxLen = max($len1, $len2);

        if ($maxLen > 0) {
            $levenshtein = levenshtein($word1, $word2);
            if ($levenshtein <= max(1, floor($maxLen * 0.3))) {
                return true;
            }
        }

        return false;
    }

    private function extractSpecs(string $text): array
    {
        $specs = [];

        if (preg_match_all('/(\d+(?:\.\d+)?)(?:x(\d+(?:\.\d+)?)?|"|mm|m|kg|lb|in)?/i', $text, $matches)) {
            foreach ($matches[0] as $match) {
                if (!empty($match) && preg_match('/\d/', $match)) {
                    $specs[] = strtolower(trim($match));
                }
            }
        }

        return array_unique($specs);
    }

    private function extractProductType(string $description): ?string
    {
        $words = explode(' ', strtolower(trim($description)));
        if (empty($words)) return null;

        $typeMap = [
            'bobina' => 'bobina',
            'barra' => 'barra',
            'tubo' => 'tubo',
            'tubos' => 'tubo',
            'placa' => 'placa',
            'plancha' => 'placa',
            'perfil' => 'perfil',
            'perfiles' => 'perfil',
            'angulo' => 'perfil',
            'ángulo' => 'perfil',
            'canal' => 'perfil',
            'viga' => 'perfil',
            'redondo' => 'redondo',
            'cuadrado' => 'cuadrado',
            'rectangular' => 'rectangular',
            'hexagonal' => 'hexagonal',
        ];

        $first = $words[0];
        return $typeMap[$first] ?? $first;
    }

    private function extractDiameter(string $description): ?float
    {
        $desc = strtolower(trim($description));

        if (preg_match('/(\d+)\/(\d+)/', $desc, $matches)) {
            $num = (float) $matches[1];
            $den = (float) $matches[2];
            if ($den > 0) {
                return ($num / $den) * 25.4;
            }
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*mm\b/i', $desc, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*x\s*\d/i', $desc, $matches)) {
            return (float) $matches[1] * 25.4;
        }

        return null;
    }
}
