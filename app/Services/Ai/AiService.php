<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class AiService
{
    private AiProviderInterface $provider;

    public function __construct()
    {
        $providerName = config('services.ai.default_provider', 'gemini');

        if ($providerName === 'gemini') {
            $this->provider = new GeminiProvider();
        } else {
            $this->provider = new GeminiProvider();
        }
    }

    public function extractProducts(string $rawText): array
    {
        if (empty(trim($rawText))) {
            return [
                'products' => [],
                'confidence' => 0,
                'notes' => 'Empty text provided',
            ];
        }

        $textLength = strlen($rawText);

        if ($textLength > 100000) {
            $chunks = $this->chunkText($rawText, 80000);
            $allProducts = [];

            foreach ($chunks as $index => $chunk) {
                Log::info("Processing chunk " . ($index + 1) . " of " . count($chunks));

                $result = $this->provider->extractProducts($chunk);

                if (!empty($result['products'])) {
                    $allProducts = array_merge($allProducts, $result['products']);
                }
            }

            return [
                'products' => $allProducts,
                'confidence' => 0.8,
                'notes' => 'Processed in ' . count($chunks) . ' chunks',
            ];
        }

        return $this->provider->extractProducts($rawText);
    }

    public function getProvider(): AiProviderInterface
    {
        return $this->provider;
    }

    private function chunkText(string $text, int $maxSize): array
    {
        $lines = explode("\n", $text);
        $chunks = [];
        $currentChunk = '';
        $currentSize = 0;

        foreach ($lines as $line) {
            $lineSize = strlen($line) + 1;

            if ($currentSize + $lineSize > $maxSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = $line;
                $currentSize = $lineSize;
            } else {
                $currentChunk .= "\n" . $line;
                $currentSize += $lineSize;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }
}
