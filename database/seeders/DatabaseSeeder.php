<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::create([
            'name' => 'Proveedor de Prueba',
            'code' => 'PROV001',
            'notes' => 'Proveedor para pruebas del sistema de importación',
        ]);

        $products = [
            [
                'product_code' => 'A001',
                'sku' => 'SKU-A001',
                'internal_code' => 'INT-001',
                'supplier_code' => 'PROV-A001',
                'description' => 'Plancha Inox 304 2mm 4x8',
                'family' => 'Planchas',
                'line' => 'Inoxidable',
                'price' => 125.50,
                'cost' => 95.00,
                'stock' => 50,
                'unit' => 'M2',
                'measurement' => '2mm 4x8',
                'brand' => 'Aceros Inoxidables SA',
                'supplier' => 'Proveedor de Prueba',
                'currency' => 'USD',
            ],
            [
                'product_code' => 'A002',
                'sku' => 'SKU-A002',
                'internal_code' => 'INT-002',
                'supplier_code' => 'PROV-A002',
                'description' => 'Plancha Inox 316 3mm 4x8',
                'family' => 'Planchas',
                'line' => 'Inoxidable',
                'price' => 180.00,
                'cost' => 140.00,
                'stock' => 20,
                'unit' => 'M2',
                'measurement' => '3mm 4x8',
                'brand' => 'Aceros Inoxidables SA',
                'supplier' => 'Proveedor de Prueba',
                'currency' => 'USD',
            ],
            [
                'product_code' => 'A003',
                'sku' => 'SKU-A003',
                'internal_code' => 'INT-003',
                'supplier_code' => 'PROV-A003',
                'description' => 'Tubo Inox 304 1/2" x 6m',
                'family' => 'Tubos',
                'line' => 'Inoxidable',
                'price' => 95.00,
                'cost' => 70.00,
                'stock' => 35,
                'unit' => 'M',
                'measurement' => '1/2" x 6m',
                'brand' => 'Tubos y Perfiles',
                'supplier' => 'Proveedor de Prueba',
                'currency' => 'USD',
            ],
            [
                'product_code' => 'A004',
                'sku' => 'SKU-A004',
                'internal_code' => 'INT-004',
                'supplier_code' => 'PROV-A004',
                'description' => 'Tubo Inox 304 3/4" x 6m',
                'family' => 'Tubos',
                'line' => 'Inoxidable',
                'price' => 135.00,
                'cost' => 100.00,
                'stock' => 25,
                'unit' => 'M',
                'measurement' => '3/4" x 6m',
                'brand' => 'Tubos y Perfiles',
                'supplier' => 'Proveedor de Prueba',
                'currency' => 'USD',
            ],
            [
                'product_code' => 'A005',
                'sku' => 'SKU-A005',
                'internal_code' => 'INT-005',
                'supplier_code' => 'PROV-A005',
                'description' => 'Perfil U Inox 304 25x25x3mm',
                'family' => 'Perfiles',
                'line' => 'Inoxidable',
                'price' => 85.00,
                'cost' => 65.00,
                'stock' => 40,
                'unit' => 'M',
                'measurement' => '25x25x3mm',
                'brand' => 'Perfiles Industriales',
                'supplier' => 'Proveedor de Prueba',
                'currency' => 'USD',
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }
    }
}
