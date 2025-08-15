<?php
// Script para extraer texto de un PDF usando smalot/pdfparser
// Requiere: composer require smalot/pdfparser

require_once __DIR__ . '/../../../vendor/autoload.php';

use Smalot\PdfParser\Parser;

$pdfPath = __DIR__ . '/../../../temp/sat.pdf';

if (!file_exists($pdfPath)) {
    die("No se encontró el archivo PDF: $pdfPath\n");
}

$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$text = $pdf->getText();

// Guardar el texto extraído para revisión
file_put_contents(__DIR__ . '/sat_pdf_text.txt', $text);

echo "Texto extraído guardado en sat_pdf_text.txt\n";
