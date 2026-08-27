<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiProvider implements AiProviderInterface
{
    private $apiKey;
    private $model;
    private $totalCost = 0;
    private $totalInputTokens = 0;
    private $totalOutputTokens = 0;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model = config('services.gemini.model', 'gemini-3.6-flash');
    }

    public function getName()
    {
        return 'gemini';
    }

    public function getCost()
    {
        return $this->totalCost;
    }

    public function getTotalInputTokens()
    {
        return $this->totalInputTokens;
    }

    public function getTotalOutputTokens()
    {
        return $this->totalOutputTokens;
    }

    public function extractProducts($rawText)
    {
        $prompt = $this->buildTextPrompt($rawText);

        return $this->callGemini([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ]);
    }

    public function extractProductsFromPdf($pdfFilePath)
    {
        if (!file_exists($pdfFilePath)) {
            throw new Exception("PDF file not found: {$pdfFilePath}");
        }

        $pdfData = base64_encode(file_get_contents($pdfFilePath));
        $prompt = $this->buildPdfPrompt();

        return $this->callGemini([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data' => $pdfData,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function buildPdfPrompt()
{
    $lines = [];
    $lines[] = 'Eres un experto en extracción de datos de reportes de inventario de aceros y metales.';
    $lines[] = '';
    $lines[] = 'REGLAS DE IDENTIFICACIÓN DE COLUMNAS:';
    $lines[] = '1. CÓDIGO (code): Es el número identificador al inicio de la fila o arriba de la descripción (ejemplo: "6352", "1849", "0025"). Si no hay un código numérico explícito, asigna null.';
    $lines[] = '2. DESCRIPCIÓN (description): El nombre textual completo del producto (ej: "BARRA DE CONSTRUCCION ASTM A615/A706 GR60 8MM X 9M").';
    $lines[] = '3. TONELADAS MÉTRICAS (T.M): Corresponde al peso/tonelaje. IGNORAR esta columna para el stock.';
    $lines[] = '4. UNIDADES (stock): Es la cantidad disponible (última columna numérica).';
    $lines[] = '';
    $lines[] = 'REGLAS NUMÉRICAS Y DE FORMATO PARA EL STOCK:';
    $lines[] = '- En la columna Unidades, la COMA (,) se usa como SEPARADOR DE MILES.';
    $lines[] = '  Ejemplo: "147,696" -> 147696';
    $lines[] = '  Ejemplo: "9,311" -> 9311';
    $lines[] = '  Ejemplo: "1,560" -> 1560';
    $lines[] = '- Si el número tiene un punto (ej: "25.760" en T.M o unidades), elimínalo o trátalo como entero según la coherencia del conteo.';
    $lines[] = '- Convierte SIEMPRE el stock a un número decimal (decimal).';
    $lines[] = '- Si la columna Unidades está VACÍA o no existe un valor, asigna stock = 0.';
    $lines[] = '';
    $lines[] = 'EXCLUSIONES:';
    $lines[] = '- Ignora filas de subtotales ("Sub. Total"), encabezados, fechas, horas y números de página ("1 / 29").';
    $lines[] = '';
    $lines[] = 'FORMATO DE SALIDA ESTRICTO (JSON):';
    $lines[] = '{"products":[{"code":"6352","description":"BARRA DE CONSTRUCCION ASTM A615/A706 GR60 8MM X 9M","price":null,"stock":147696,"unit":"UND"}],"confidence":0.98}';

    return implode("\n", $lines);
}

    private function buildTextPrompt($rawText)
    {
        $lines = [];
        $lines[] = 'Eres un asistente que extrae productos de listas de precios de aceros y metales.';
        $lines[] = '';
        $lines[] = 'MAPEO DE COLUMNAS:';
        $lines[] = 'Codigo/Codigo/SKU/Cod -> code';
        $lines[] = 'Producto/Descripcion/Articulo/Nombre -> description';
        $lines[] = 'Precio/Price/Costo -> price';
        $lines[] = 'Unidades/Stock/Cantidad/Qty/Existencia -> stock';
        $lines[] = '';
        $lines[] = 'REGLAS:';
        $lines[] = '1. Si hay columna Unidades, usa eso como stock';
        $lines[] = '2. Numero con coma decimal (9,3112159) -> 9.3112159';
        $lines[] = '3. Extrae descripcion COMPLETA del producto';
        $lines[] = '4. Si no hay precio, price = null';
        $lines[] = '5. code es obligatorio para cada producto';
        $lines[] = '';
        $lines[] = 'RESPONDE SOLO CON JSON:';
        $lines[] = '{"products":[{"code":"X","description":"Y","price":125.50,"stock":50,"unit":null}],"confidence":0.95}';
        $lines[] = '';
        $lines[] = 'TEXTO:';
        $lines[] = $rawText;

        return implode("\n", $lines);
    }

    private function callGemini($payload)
    {
        $payload['generationConfig'] = [
            'temperature' => 0.1,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
        ];

        try {
            $maxRetries = 3;
            $attempt = 0;

            while (true) {
                $attempt++;
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
                    $payload
                );

                if ($response->successful()) {
                    break;
                }

                $status = $response->status();
                $retryable = in_array($status, [429, 503]);

                Log::warning('Gemini API response', [
                    'status' => $status,
                    'attempt' => $attempt,
                    'retryable' => $retryable,
                    'body' => substr($response->body(), 0, 300),
                ]);

                if ($retryable && $attempt < $maxRetries) {
                    $delay = (int) pow(2, $attempt) * 3;
                    Log::info("Retrying in {$delay}s", ['attempt' => $attempt]);
                    sleep($delay);
                    continue;
                }

                throw new Exception("Gemini API error: {$status}");
            }

            $result = $response->json();

            if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                Log::warning('Gemini returned no content');
                return ['products' => [], 'confidence' => 0, 'notes' => 'No content'];
            }

            $content = $result['candidates'][0]['content']['parts'][0]['text'];

            if (isset($result['usageMetadata'])) {
                $this->totalInputTokens += $result['usageMetadata']['promptTokenCount'] ?? 0;
                $this->totalOutputTokens += $result['usageMetadata']['candidatesTokenCount'] ?? 0;
                $this->calculateCost(
                    $result['usageMetadata']['promptTokenCount'] ?? 0,
                    $result['usageMetadata']['candidatesTokenCount'] ?? 0
                );
            }

            $content = $this->stripCodeFences($content);

            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('JSON decode failed, trying to fix', [
                    'error' => json_last_error_msg(),
                    'raw_content' => substr($content, 0, 500),
                ]);
                $decoded = $this->fixTruncatedJson($content);
            }

            if ($decoded === null) {
                Log::error('Gemini returned unparseable content', [
                    'raw_content' => substr($content, 0, 1000),
                ]);
                return ['products' => [], 'confidence' => 0, 'notes' => 'Failed to decode'];
            }

            return $decoded;

        } catch (Exception $e) {
            Log::error('Gemini failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function calculateCost($inputTokens, $outputTokens)
    {
        $this->totalCost += ($inputTokens / 1000 * 0.000075) + ($outputTokens / 1000 * 0.0003);
    }

    private function stripCodeFences(string $content): string
    {
        $content = trim($content);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $content, $matches)) {
            return trim($matches[1]);
        }
        return $content;
    }

    private function fixTruncatedJson($content)
    {
        $content = rtrim($content);

        $lastProductEnd = strrpos($content, '},');
        if ($lastProductEnd !== false) {
            $fixed = substr($content, 0, $lastProductEnd + 1) . ']}';
            $decoded = json_decode($fixed, true);
            if ($decoded !== null && isset($decoded['products'])) {
                Log::info("Fixed truncated JSON", ['count' => count($decoded['products'])]);
                return $decoded;
            }
        }

        $lastBracket = strrpos($content, '}');
        if ($lastBracket !== false) {
            $fixed = substr($content, 0, $lastBracket + 1) . ']}';
            $decoded = json_decode($fixed, true);
            if ($decoded !== null && isset($decoded['products'])) {
                return $decoded;
            }
        }

        $decoded = json_decode($content . ']}', true);
        if ($decoded !== null && isset($decoded['products'])) {
            return $decoded;
        }

        $decoded = json_decode($content . '}]}', true);
        if ($decoded !== null && isset($decoded['products'])) {
            return $decoded;
        }

        return null;
    }
}
