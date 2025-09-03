<?php
// Verificar resultados del procesamiento

try {
    $pdo = new PDO('mysql:host=localhost;dbname=sac_db', 'root', '');

    echo "🔍 Verificando resultados del procesamiento:" . PHP_EOL;

    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pagos');
    $pagos = $stmt->fetch()['total'];
    echo "📊 Registros en cfdi_pagos: $pagos" . PHP_EOL;

    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pago_documentos_relacionados');
    $docs = $stmt->fetch()['total'];
    echo "📊 Registros en cfdi_pago_documentos_relacionados: $docs" . PHP_EOL;

    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pago_impuestos_dr');
    $impuestos = $stmt->fetch()['total'];
    echo "📊 Registros en cfdi_pago_impuestos_dr: $impuestos" . PHP_EOL;

    $stmt = $pdo->query('SELECT COUNT(*) as total FROM cfdi_pago_totales');
    $totales = $stmt->fetch()['total'];
    echo "📊 Registros en cfdi_pago_totales: $totales" . PHP_EOL;

    // Analizar uno de los XMLs para verificar qué está pasando
    echo PHP_EOL . "🔍 Analizando un CFDI de pago específico:" . PHP_EOL;

    $stmt = $pdo->query("
        SELECT id, uuid, archivo_xml 
        FROM cfdi 
        WHERE tipo = 'P' 
        LIMIT 1
    ");

    $cfdi = $stmt->fetch();
    if ($cfdi) {
        echo "UUID: {$cfdi['uuid']}" . PHP_EOL;
        echo "Archivo: {$cfdi['archivo_xml']}" . PHP_EOL;

        if (file_exists($cfdi['archivo_xml'])) {
            $content = file_get_contents($cfdi['archivo_xml']);
            $xml = simplexml_load_string($content);

            if ($xml) {
                $namespaces = $xml->getNamespaces(true);
                echo "Namespaces encontrados:" . PHP_EOL;
                foreach ($namespaces as $prefix => $uri) {
                    echo "  $prefix: $uri" . PHP_EOL;
                    if (strpos($uri, 'Pagos') !== false) {
                        echo "  ⭐ NAMESPACE DE PAGOS DETECTADO!" . PHP_EOL;
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
