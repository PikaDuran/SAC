<?php
// Análisis de muestras de XMLs de diferentes años para identificar campos faltantes

require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

// Obtener muestras de diferentes años
$stmt = $pdo->query("
    SELECT YEAR(fecha) as anio, COUNT(*) as cantidad, MIN(fecha) as primera, MAX(fecha) as ultima
    FROM cfdi 
    GROUP BY YEAR(fecha) 
    ORDER BY anio
");

echo "=== DISTRIBUCIÓN DE CFDIs POR AÑO ===\n";
$anios = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $anios[] = $row['anio'];
    printf(
        "Año %s: %d CFDIs (desde %s hasta %s)\n",
        $row['anio'],
        $row['cantidad'],
        $row['primera'],
        $row['ultima']
    );
}

echo "\n=== ANALIZANDO MUESTRAS DE XMLs ===\n";

// Función para analizar XML y extraer campos específicos
function analizarXmlDetallado($xmlFile)
{
    if (!file_exists($xmlFile)) return null;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xmlFile);
    if (!$xml) return null;

    $namespaces = $xml->getNamespaces(true);
    $cfdiNs = isset($namespaces['cfdi']) ? $namespaces['cfdi'] : null;

    $data = [];

    // Campos del comprobante
    foreach ($xml->attributes($cfdiNs) as $k => $v) {
        $data['comprobante'][$k] = (string)$v;
    }

    // TimbreFiscalDigital
    $complemento = null;
    if ($cfdiNs) {
        $complemento = $xml->children($cfdiNs)->Complemento;
    }
    if (!$complemento && isset($xml->Complemento)) {
        $complemento = $xml->Complemento;
    }

    if ($complemento) {
        foreach ($complemento->children() as $comp) {
            if (strpos($comp->getName(), 'TimbreFiscalDigital') !== false) {
                foreach ($comp->attributes() as $k => $v) {
                    $data['timbre'][$k] = (string)$v;
                }
            }
        }
    }

    // CFDIs Relacionados
    if ($cfdiNs) {
        $relacionados = $xml->children($cfdiNs)->CfdiRelacionados;
    }
    if (!$relacionados && isset($xml->CfdiRelacionados)) {
        $relacionados = $xml->CfdiRelacionados;
    }

    if ($relacionados) {
        foreach ($relacionados->attributes() as $k => $v) {
            $data['relacionados'][$k] = (string)$v;
        }
        foreach ($relacionados->children() as $rel) {
            $data['relacionados']['uuids'][] = (string)$rel->attributes()['UUID'];
        }
    }

    return $data;
}

// Obtener muestras de cada año
foreach ($anios as $anio) {
    echo "\n--- ANALIZANDO AÑO $anio ---\n";

    $stmt = $pdo->prepare("
        SELECT archivo_xml, uuid, fecha 
        FROM cfdi 
        WHERE YEAR(fecha) = ? AND archivo_xml IS NOT NULL 
        LIMIT 2
    ");
    $stmt->execute([$anio]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Archivo: " . basename($row['archivo_xml']) . " (" . $row['fecha'] . ")\n";

        $analisis = analizarXmlDetallado($row['archivo_xml']);
        if ($analisis) {
            if (isset($analisis['comprobante']['Version'])) {
                echo "  - Versión CFDI: " . $analisis['comprobante']['Version'] . "\n";
            }

            if (isset($analisis['timbre'])) {
                echo "  - TimbreFiscalDigital:\n";
                foreach ($analisis['timbre'] as $k => $v) {
                    echo "    * $k: $v\n";
                }
            }

            if (isset($analisis['relacionados'])) {
                echo "  - CFDIs Relacionados:\n";
                foreach ($analisis['relacionados'] as $k => $v) {
                    if ($k !== 'uuids') {
                        echo "    * $k: $v\n";
                    }
                }
                if (isset($analisis['relacionados']['uuids'])) {
                    echo "    * UUIDs: " . implode(', ', $analisis['relacionados']['uuids']) . "\n";
                }
            }
        }
    }
}

echo "\n=== CAMPOS ADICIONALES IDENTIFICADOS ===\n";
echo "1. Version (del comprobante) - para campo 'Ver.'\n";
echo "2. FechaTimbrado (TimbreFiscalDigital) - ya extraído\n";
echo "3. SelloCFD (TimbreFiscalDigital) - para campo 'Sello'\n";
echo "4. SelloSAT (TimbreFiscalDigital) - para campo 'SAT'\n";
echo "5. NoCertificadoSAT (TimbreFiscalDigital)\n";
echo "6. RfcProvCertif (TimbreFiscalDigital)\n";
echo "7. TipoRelacion (CfdiRelacionados) - para campo 'CFDI Relacionado'\n";
echo "8. UUID (CfdiRelacionados) - para campo 'CFDI Relacionado'\n";

echo "\n=== CAMPOS QUE NECESITAN AGREGARSE A LA BD ===\n";
$campos_nuevos = [
    'version' => 'VARCHAR(5) COMMENT "Versión del CFDI (4.0, 3.3, etc)"',
    'sello_cfd' => 'TEXT COMMENT "Sello digital del CFDI"',
    'sello_sat' => 'TEXT COMMENT "Sello digital del SAT"',
    'no_certificado_sat' => 'VARCHAR(50) COMMENT "Número de certificado SAT"',
    'rfc_prov_certif' => 'VARCHAR(50) COMMENT "RFC del proveedor de certificación"',
    'estatus_sat' => 'ENUM("Vigente", "Cancelado", "No encontrado") COMMENT "Estatus en el SAT"',
    'cfdi_relacionados' => 'JSON COMMENT "CFDIs relacionados (tipo relación y UUIDs)"'
];

foreach ($campos_nuevos as $campo => $definicion) {
    echo "ALTER TABLE cfdi ADD COLUMN $campo $definicion;\n";
}
