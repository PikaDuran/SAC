<?php
// Zona horaria global CDMX
date_default_timezone_set('America/Mexico_City');
// Script robusto para importar TODOS los campos de CFDI reales (v4.0, pagos, divisas, etc)
// No simulaciones, extrae todo lo presente en los XML

set_time_limit(0);
ini_set('memory_limit', '2048M');

$xmlPaths = [];
$baseDir = __DIR__ . '/storage/sat_downloads';

function getXmlFiles($dir)
{
    $files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($rii as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$xmlPaths = getXmlFiles($baseDir);
echo "Se encontraron " . count($xmlPaths) . " archivos XML para procesar.\n";


function parseCfdi($xmlFile)
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xmlFile);
    if (!$xml) return ['error' => 'No se pudo cargar XML'];

    $namespaces = $xml->getNamespaces(true);
    $data = [];

    // Detectar RFC consultado y dirección del flujo desde la ruta del archivo
    $pathParts = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $xmlFile));
    $rfcConsultado = null;
    $direccionFlujo = null;

    // Buscar el RFC consultado y la dirección en la ruta
    for ($i = 0; $i < count($pathParts); $i++) {
        if (preg_match('/^[A-Z&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $pathParts[$i])) {
            $rfcConsultado = $pathParts[$i];
            // La siguiente parte de la ruta indica la dirección del flujo
            if ($i + 1 < count($pathParts)) {
                $direccionFlujo = strtoupper($pathParts[$i + 1]);
            }
            break;
        }
    }

    $data['rfc_consultado'] = $rfcConsultado;
    $data['direccion_flujo'] = $direccionFlujo;
    $data['archivo_xml'] = $xmlFile;

    // Detectar nodo raíz y namespace principal
    $cfdiNs = isset($namespaces['cfdi']) ? $namespaces['cfdi'] : null;
    $rootName = $xml->getName();

    // Extraer versión del CFDI
    $version = null;
    foreach ($xml->attributes($cfdiNs) as $k => $v) {
        if ($k === 'Version') {
            $version = (string)$v;
            break;
        }
    }
    $data['version'] = $version;

    // Comprobante (atributos)
    foreach ($xml->attributes($cfdiNs) as $k => $v) $data['comprobante'][$k] = (string)$v;
    foreach ($xml->attributes() as $k => $v) $data['comprobante'][$k] = (string)$v;

    // Emisor
    $emisor = null;
    if ($cfdiNs) {
        $emisor = $xml->children($cfdiNs)->Emisor;
    }
    if (!$emisor && isset($xml->Emisor)) $emisor = $xml->Emisor;
    if ($emisor) {
        foreach ($emisor->attributes() as $k => $v) $data['emisor'][$k] = (string)$v;
    }

    // Receptor
    $receptor = null;
    if ($cfdiNs) {
        $receptor = $xml->children($cfdiNs)->Receptor;
    }
    if (!$receptor && isset($xml->Receptor)) $receptor = $xml->Receptor;
    if ($receptor) {
        foreach ($receptor->attributes() as $k => $v) $data['receptor'][$k] = (string)$v;
    }

    // Conceptos
    $data['conceptos'] = [];
    $conceptos = null;
    if ($cfdiNs) {
        $conceptos = $xml->children($cfdiNs)->Conceptos;
    }
    if (!$conceptos && isset($xml->Conceptos)) $conceptos = $xml->Conceptos;
    if ($conceptos) {
        foreach ($conceptos->Concepto as $concepto) {
            $c = [];
            foreach ($concepto->attributes() as $k => $v) $c[$k] = (string)$v;
            // Impuestos por concepto
            if (isset($concepto->Impuestos)) {
                foreach ($concepto->Impuestos->children() as $impType) {
                    $tipoImp = $impType->getName();
                    foreach ($impType->children() as $imp) {
                        $impData = [];
                        foreach ($imp->attributes() as $ik => $iv) $impData[$ik] = (string)$iv;
                        $impData['Tipo'] = $tipoImp;
                        $c['impuestos'][] = $impData;
                    }
                }
            }
            $data['conceptos'][] = $c;
        }
    }

    // Impuestos globales
    $data['impuestos'] = [];
    $impuestos = null;
    if ($cfdiNs) {
        $impuestos = $xml->children($cfdiNs)->Impuestos;
    }
    if (!$impuestos && isset($xml->Impuestos)) $impuestos = $xml->Impuestos;
    if ($impuestos) {
        foreach ($impuestos->children() as $impType) {
            $tipoImp = $impType->getName();
            foreach ($impType->children() as $imp) {
                $impData = [];
                foreach ($imp->attributes() as $ik => $iv) $impData[$ik] = (string)$iv;
                $impData['Tipo'] = $tipoImp;
                $data['impuestos'][] = $impData;
            }
        }
    }

    // Complementos
    $data['complementos'] = [];
    $complemento = null;
    if ($cfdiNs) {
        $complemento = $xml->children($cfdiNs)->Complemento;
    }
    if (!$complemento && isset($xml->Complemento)) $complemento = $xml->Complemento;
    if ($complemento) {
        foreach ($complemento->children() as $comp) {
            $compNs = $comp->getName();
            $compData = [];
            foreach ($comp->attributes() as $k => $v) $compData[$k] = (string)$v;
            // Subnodos
            foreach ($comp->children() as $sub) {
                $subData = [];
                foreach ($sub->attributes() as $sk => $sv) $subData[$sk] = (string)$sv;
                foreach ($sub->children() as $ssub) {
                    $ssubData = [];
                    foreach ($ssub->attributes() as $ssk => $ssv) $ssubData[$ssk] = (string)$ssv;
                    $subData[$ssub->getName()][] = $ssubData;
                }
                $compData[$sub->getName()][] = $subData;
            }
            $data['complementos'][$compNs][] = $compData;
        }
    }

    // UUID (TimbreFiscalDigital)
    $uuid = null;
    foreach ($namespaces as $prefix => $ns) {
        $tfdNodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
        if ($tfdNodes) {
            foreach ($tfdNodes as $tfdNode) {
                if ($tfdNode['UUID']) {
                    $uuid = (string)$tfdNode['UUID'];
                    break 2;
                }
            }
        }
    }
    if ($uuid) $data['uuid'] = $uuid;
    else $data['uuid'] = null;

    return $data;
}

// Ejemplo de procesamiento y muestra de los primeros 10

$total = count($xmlPaths);
$ok = 0;
$err = 0;
foreach ($xmlPaths as $i => $xmlFile) {
    $data = parseCfdi($xmlFile);
    if (isset($data['error']) || empty($data['uuid'])) {
        $err++;
    } else {
        $ok++;
    }
}
echo "\nResumen:\n";
echo "  Total XML procesados: $total\n";
echo "  Correctos: $ok\n";
echo "  Errores: $err\n";


// Usar la función de conexión real del sistema
require_once __DIR__ . '/src/config/database.php';
$pdo = getDatabase();

// Funciones auxiliares para extraer datos específicos
function extractTimbreFiscalDigital($data)
{
    $tfdData = [];
    if (isset($data['complementos']['TimbreFiscalDigital'])) {
        $tfd = $data['complementos']['TimbreFiscalDigital'][0] ?? [];
        $tfdData['FechaTimbrado'] = $tfd['FechaTimbrado'] ?? null;
        $tfdData['SelloSAT'] = $tfd['SelloSAT'] ?? null;
        $tfdData['NoCertificadoSAT'] = $tfd['NoCertificadoSAT'] ?? null;
        $tfdData['RfcProvCertif'] = $tfd['RfcProvCertif'] ?? null;
    }
    return $tfdData;
}

function extractCfdiRelacionados($data)
{
    $relacionados = [];
    if (isset($data['complementos']['CfdiRelacionados'])) {
        $cfdiRel = $data['complementos']['CfdiRelacionados'];
        if (is_array($cfdiRel) && !empty($cfdiRel)) {
            foreach ($cfdiRel as $rel) {
                if (isset($rel['CfdiRelacionado'])) {
                    foreach ($rel['CfdiRelacionado'] as $uuid) {
                        $relacionados[] = $uuid['UUID'] ?? $uuid;
                    }
                }
            }
        }
    }
    return !empty($relacionados) ? json_encode($relacionados) : null;
}

function insertCfdi($pdo, $data)
{
    // Verificar si el CFDI ya existe por UUID
    $stmtCheck = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? LIMIT 1");
    $stmtCheck->execute([$data['uuid'] ?? null]);
    $cfdi_id = null;
    if ($stmtCheck->rowCount() > 0) {
        // Si existe, obtener el id y no insertar de nuevo
        $cfdi_id = $stmtCheck->fetchColumn();
        return;
    } else {
        // Si no existe, insertar
        $sql = "INSERT INTO cfdi (uuid, tipo, serie, folio, fecha, fecha_timbrado, rfc_emisor, nombre_emisor, regimen_fiscal_emisor, rfc_receptor, nombre_receptor, regimen_fiscal_receptor, uso_cfdi, lugar_expedicion, moneda, tipo_cambio, subtotal, descuento, total, metodo_pago, forma_pago, exportacion, archivo_xml, complemento_tipo, complemento_json, rfc_consultado, direccion_flujo, version, sello_cfd, sello_sat, no_certificado_sat, rfc_prov_certif, estatus_sat, cfdi_relacionados) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);

        // Extraer TimbreFiscalDigital para campos específicos
        $tfdData = extractTimbreFiscalDigital($data);

        $stmt->execute([
            $data['uuid'] ?? null,
            $data['comprobante']['TipoDeComprobante'] ?? null,
            $data['comprobante']['Serie'] ?? null,
            $data['comprobante']['Folio'] ?? null,
            $data['comprobante']['Fecha'] ?? null,
            $tfdData['FechaTimbrado'] ?? null,
            $data['emisor']['Rfc'] ?? null,
            $data['emisor']['Nombre'] ?? null,
            $data['emisor']['RegimenFiscal'] ?? null,
            $data['receptor']['Rfc'] ?? null,
            $data['receptor']['Nombre'] ?? null,
            $data['receptor']['RegimenFiscal'] ?? null,
            $data['receptor']['UsoCFDI'] ?? null,
            $data['comprobante']['LugarExpedicion'] ?? null,
            $data['comprobante']['Moneda'] ?? null,
            $data['comprobante']['TipoCambio'] ?? null,
            $data['comprobante']['SubTotal'] ?? null,
            $data['comprobante']['Descuento'] ?? null,
            $data['comprobante']['Total'] ?? null,
            $data['comprobante']['MetodoPago'] ?? null,
            $data['comprobante']['FormaPago'] ?? null,
            $data['comprobante']['Exportacion'] ?? null,
            $data['archivo_xml'] ?? null,
            isset($data['complementos']) ? implode(',', array_keys($data['complementos'])) : null,
            json_encode($data),
            $data['rfc_consultado'] ?? null,
            $data['direccion_flujo'] ?? null,
            $data['version'] ?? null,
            $data['comprobante']['Sello'] ?? null,
            $tfdData['SelloSAT'] ?? null,
            $tfdData['NoCertificadoSAT'] ?? null,
            $tfdData['RfcProvCertif'] ?? null,
            'Vigente', // Valor por defecto
            extractCfdiRelacionados($data)
        ]);
        $cfdi_id = $pdo->lastInsertId();
    }

    // Guardar campos no contemplados en un solo registro JSON en cfdi_complementos (tipo: 'extra_comprobante')
    $campos_cfdi = ['uuid', 'tipo', 'serie', 'folio', 'fecha', 'fecha_timbrado', 'rfc_emisor', 'nombre_emisor', 'regimen_fiscal_emisor', 'rfc_receptor', 'nombre_receptor', 'regimen_fiscal_receptor', 'uso_cfdi', 'lugar_expedicion', 'moneda', 'tipo_cambio', 'subtotal', 'descuento', 'total', 'metodo_pago', 'forma_pago', 'exportacion', 'archivo_xml', 'complemento_tipo', 'complemento_json', 'rfc_consultado', 'direccion_flujo', 'version', 'sello_cfd', 'sello_sat', 'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'];
    $extras = [];
    foreach ($data['comprobante'] as $k => $v) {
        if (!in_array(strtolower($k), array_map('strtolower', $campos_cfdi))) {
            $extras[$k] = $v;
        }
    }
    if (!empty($extras)) {
        $sqlComp = "INSERT INTO cfdi_complementos (cfdi_id, tipo, datos_json) VALUES (?,?,?)";
        $stmtComp = $pdo->prepare($sqlComp);
        $stmtComp->execute([
            $cfdi_id,
            'extra_comprobante',
            json_encode($extras)
        ]);
    }

    // Log temporal: mostrar impuestos detectados por concepto
    if (!empty($data['conceptos'])) {
        foreach ($data['conceptos'] as $idx => $c) {
            if (!empty($c['impuestos'])) {
                echo "CFDI $cfdi_id - Concepto $idx: " . count($c['impuestos']) . " impuestos por concepto\n";
            }
        }
        $sqlC = "INSERT INTO cfdi_conceptos (cfdi_id, clave_prodserv, cantidad, clave_unidad, unidad, descripcion, valor_unitario, importe, descuento, objeto_imp, cuenta_predial) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $stmtC = $pdo->prepare($sqlC);
        $sqlImp = "INSERT INTO cfdi_impuestos (cfdi_id, concepto_id, tipo, datos_json) VALUES (?,?,?,?)";
        $stmtImp = $pdo->prepare($sqlImp);
        foreach ($data['conceptos'] as $c) {
            $stmtC->execute([
                $cfdi_id,
                $c['ClaveProdServ'] ?? null,
                $c['Cantidad'] ?? null,
                $c['ClaveUnidad'] ?? null,
                $c['Unidad'] ?? null,
                $c['Descripcion'] ?? null,
                $c['ValorUnitario'] ?? null,
                $c['Importe'] ?? null,
                $c['Descuento'] ?? null,
                $c['ObjetoImp'] ?? null,
                $c['CuentaPredial'] ?? null
            ]);
            $concepto_id = $pdo->lastInsertId();
            // Impuestos por concepto
            if (!empty($c['impuestos'])) {
                foreach ($c['impuestos'] as $imp) {
                    $stmtImp->execute([
                        $cfdi_id,
                        $concepto_id,
                        $imp['Tipo'] ?? 'concepto',
                        json_encode($imp)
                    ]);
                }
            }
        }
    }

    // Log temporal: mostrar impuestos globales detectados
    if (!empty($data['impuestos'])) {
        echo "CFDI $cfdi_id: " . count($data['impuestos']) . " impuestos globales\n";
        $sqlImp = "INSERT INTO cfdi_impuestos (cfdi_id, concepto_id, tipo, datos_json) VALUES (?,?,?,?)";
        $stmtImp = $pdo->prepare($sqlImp);
        foreach ($data['impuestos'] as $imp) {
            $stmtImp->execute([
                $cfdi_id,
                null,
                $imp['Tipo'] ?? 'global',
                json_encode($imp)
            ]);
        }
    }

    // Inserta complementos en cfdi_complementos (solo uno por tipo por CFDI)
    if (!empty($data['complementos'])) {
        $sqlComp = "INSERT INTO cfdi_complementos (cfdi_id, tipo, datos_json) VALUES (?,?,?)";
        $stmtComp = $pdo->prepare($sqlComp);
        foreach ($data['complementos'] as $tipo => $comps) {
            // Si hay varios, los guarda juntos en un array JSON
            $stmtComp->execute([
                $cfdi_id,
                $tipo,
                json_encode($comps)
            ]);
        }
    }
}

$total = count($xmlPaths);
$ok = 0;
$err = 0;
$procesados = 0;

foreach ($xmlPaths as $i => $xmlFile) {
    $data = parseCfdi($xmlFile);
    if (isset($data['error']) || empty($data['uuid'])) {
        $err++;
        echo "Error en archivo: $xmlFile\n";
    } else {
        insertCfdi($pdo, $data);
        $ok++;
        $procesados++;
    }

    // Mostrar progreso cada 100 archivos
    if (($i + 1) % 100 == 0) {
        echo "Procesados: " . ($i + 1) . "/$total (" . round((($i + 1) / $total) * 100, 2) . "%)\n";
    }
}

echo "\n=== RESUMEN FINAL ===\n";
echo "Total archivos XML: $total\n";
echo "CFDIs insertados correctamente: $ok\n";
echo "Errores de procesamiento: $err\n";
echo "Porcentaje de éxito: " . round(($ok / $total) * 100, 2) . "%\n";
