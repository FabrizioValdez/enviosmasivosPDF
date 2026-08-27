<?php

namespace App\Services\ProductImport;

class DataNormalizationService
{
    public function normalizePrice($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        $value = preg_replace('/[^\d.,\-]/', '', $value);

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{1,2})$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}(,\d{3})*(\.\d{1,2})$/', $value)) {
            $value = str_replace(',', '', $value);
        } elseif (preg_match('/^\d+(,\d{1,2})$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    public function normalizeStock($value)
    {
        if (is_numeric($value)) {
            $float = (float) $value;
            if ($float == (int) $float) {
                return (int) $float;
            }
            return (int) round($float);
        }

        $value = (string) $value;
        $value = trim($value);

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d+(,\d+)$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^\d.\-]/', '', $value);

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    public function normalizeUnit($value)
    {
        $value = strtoupper(trim($value));

        $unitMap = [
            'UND' => 'UND',
            'UN' => 'UND',
            'UNID' => 'UND',
            'UNIDADES' => 'UND',
            'UNIT' => 'UND',
            'KG' => 'KG',
            'KILO' => 'KG',
            'KILOS' => 'KG',
            'KGS' => 'KG',
            'LT' => 'LT',
            'LTS' => 'LT',
            'LITRO' => 'LT',
            'LITROS' => 'LT',
            'M' => 'M',
            'MT' => 'M',
            'MTR' => 'M',
            'METRO' => 'M',
            'METROS' => 'M',
            'ML' => 'ML',
            'MILILITRO' => 'ML',
            'M2' => 'M2',
            'M3' => 'M3',
            'PAR' => 'PAR',
            'PARES' => 'PAR',
            'JUEGO' => 'JUEGO',
            'JUEGOS' => 'JUEGO',
            'JGO' => 'JUEGO',
            'SET' => 'SET',
            'SETS' => 'SET',
            'ROLLO' => 'ROLLO',
            'ROLLOS' => 'ROLLO',
            'ROL' => 'ROLLO',
            'PIEZA' => 'PIEZA',
            'PIEZAS' => 'PIEZA',
            'PZA' => 'PIEZA',
            'PZAS' => 'PIEZA',
            'CAJA' => 'CAJA',
            'CAJAS' => 'CAJA',
            'CJ' => 'CAJA',
            'FARDO' => 'FARDO',
            'FARDOS' => 'FARDO',
        ];

        return isset($unitMap[$value]) ? $unitMap[$value] : 'UND';
    }

    public function normalizeCode($value)
    {
        return strtoupper(trim($value));
    }

    public function normalizeCurrency($value)
    {
        $value = strtoupper(trim($value));

        $validCurrencies = ['USD', 'EUR', 'PEN', 'GBP', 'JPY'];

        return in_array($value, $validCurrencies) ? $value : 'USD';
    }
}
