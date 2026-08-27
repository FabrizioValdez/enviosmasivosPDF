<?php

namespace App\Services\PdfParser;

use Exception;

class PdfParserFactory
{
    public static function create(string $filePath): PdfParserInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new Exception("Unsupported file type: {$extension}");
        }

        return new NativePdfParser();
    }
}
