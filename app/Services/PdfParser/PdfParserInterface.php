<?php

namespace App\Services\PdfParser;

interface PdfParserInterface
{
    public function parse(string $filePath): array;
    public function canParse(string $filePath): bool;
}
