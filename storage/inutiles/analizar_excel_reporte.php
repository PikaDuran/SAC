<?php
// Script para analizar el archivo Excel de reporte y extraer la estructura

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $inputFile = __DIR__ . '/storage/Reporte de facturacion bl actualizado.xlsx';

    echo "Analizando archivo Excel: $inputFile\n\n";

    $spreadsheet = IOFactory::load($inputFile);
    $worksheet = $spreadsheet->getActiveSheet();

    // Obtener el rango de datos
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();

    echo "Dimensiones del archivo:\n";
    echo "- Filas: $highestRow\n";
    echo "- Columnas: $highestColumn\n\n";

    echo "=== ENCABEZADOS DEL REPORTE (Buscando en múltiples filas) ===\n";
    $headers = [];

    // Buscar encabezados en las primeras 10 filas
    for ($row = 1; $row <= min(10, $highestRow); $row++) {
        echo "Fila $row: ";
        $rowData = [];
        for ($col = 'A'; $col <= $highestColumn; ++$col) {
            $cellValue = $worksheet->getCell($col . $row)->getValue();
            if (!empty($cellValue)) {
                $rowData[$col] = $cellValue;
            }
        }

        if (count($rowData) > 5) { // Probablemente una fila de encabezados
            echo "(POSIBLES ENCABEZADOS)\n";
            foreach ($rowData as $col => $value) {
                echo "  $col: $value\n";
            }
            if (empty($headers)) {
                $headers = $rowData;
            }
        } else {
            echo implode(" | ", $rowData) . "\n";
        }
    }

    echo "\n=== MUESTRA DE DATOS (Primeras 3 filas) ===\n";
    for ($row = 1; $row <= min(4, $highestRow); $row++) {
        echo "Fila $row:\n";
        for ($col = 'A'; $col <= $highestColumn; ++$col) {
            $cellValue = $worksheet->getCell($col . $row)->getValue();
            if (!empty($cellValue)) {
                $header = isset($headers[$col]) ? $headers[$col] : "Col_$col";
                echo "  $header: $cellValue\n";
            }
        }
        echo "\n";
    }

    echo "\n=== ANÁLISIS DE CAMPOS REQUERIDOS ===\n";
    echo "Total de columnas en el reporte: " . count($headers) . "\n";
    echo "Campos identificados:\n";
    foreach ($headers as $col => $header) {
        echo "- $header\n";
    }
} catch (Exception $e) {
    echo "Error al leer el archivo Excel: " . $e->getMessage() . "\n";
}
