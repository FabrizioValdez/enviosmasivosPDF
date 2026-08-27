<?php

require __DIR__ . '/vendor/autoload.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);

$pdf->Cell(0, 10, 'Lista de Precios - Proveedor de Prueba', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 8, 'Codigo', 1, 0, 'C');
$pdf->Cell(70, 8, 'Descripcion', 1, 0, 'C');
$pdf->Cell(30, 8, 'Precio', 1, 0, 'C');
$pdf->Cell(20, 8, 'Stock', 1, 0, 'C');
$pdf->Cell(20, 8, 'Unidad', 1, 1, 'C');

$pdf->SetFont('Arial', '', 10);

$products = [
    ['A001', 'Plancha Inox 304 2mm 4x8', '130.00', '45', 'M2'],
    ['A002', 'Plancha Inox 316 3mm 4x8', '185.00', '18', 'M2'],
    ['A003', 'Tubo Inox 304 1/2 x 6m', '98.00', '32', 'M'],
    ['A004', 'Tubo Inox 304 3/4 x 6m', '140.00', '22', 'M'],
    ['A005', 'Perfil U Inox 304 25x25x3mm', '88.00', '38', 'M'],
    ['B001', 'Plancha Acero CS 3/16 4x8', '85.00', '60', 'M2'],
    ['B002', 'Plancha Acero CS 1/4 4x8', '110.00', '40', 'M2'],
    ['B003', 'Tubo Redondo Acero 1/2 x 6m', '45.00', '80', 'M'],
    ['B004', 'Tubo Cuadrado Acero 1x1 16ga', '55.00', '45', 'M'],
    ['B005', 'Perfil L Acero 2x2x1/4', '35.00', '70', 'M'],
];

foreach ($products as $product) {
    $pdf->Cell(30, 8, $product[0], 1, 0, 'C');
    $pdf->Cell(70, 8, $product[1], 1, 0, 'L');
    $pdf->Cell(30, 8, $product[2], 1, 0, 'R');
    $pdf->Cell(20, 8, $product[3], 1, 0, 'C');
    $pdf->Cell(20, 8, $product[4], 1, 1, 'C');
}

$outputPath = __DIR__ . '/storage/app/public/test_products.pdf';
$pdf->Output('F', $outputPath);

echo "PDF created successfully at: {$outputPath}\n";
echo "Products: " . count($products) . "\n";
