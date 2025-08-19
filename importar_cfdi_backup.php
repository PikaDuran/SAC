<?php
// Zona horaria global CDMX
date_default_timezone_set('America/Mexico_City');
// Script robusto para importar TODOS los campos de CFDI reales (v4.0, pagos, divisas, etc)
// No simulaciones, extrae todo lo presente en los XML

set_time_limit(0);
ini_set('memory_limit', '2048M');

$xmlPaths = [];
$baseDir = __DIR__ . '/s    // Guardar campos no contemplados en un solo registro JSON en cfdi_complementos (tipo: 'extra_comprobante')
    $campos_cfdi = ['uuid', 'tipo', 'serie', 'folio', 'fecha', 'fecha_timbrado', 'rfc_emisor', 'nombre_emisor', 'regimen_fiscal_emisor', 'rfc_receptor', 'nombre_receptor', 'regimen_fiscal_receptor', 'uso_cfdi', 'lugar_expedicion', 'moneda', 'tipo_cambio', 'subtotal', 'descuento', 'total', 'metodo_pago', 'forma_pago', 'exportacion', 'archivo_xml', 'complemento_tipo', 'complemento_json', 'rfc_consultado', 'direccion_flujo', 'version', 'sello_cfd', 'sello_sat', 'no_certificado_sat', 'rfc_prov_certif', 'estatus_sat', 'cfdi_relacionados'];
    $extras = [];
    foreach ($data['comprobante'] as $k => $v) {
        if (!in_array(strtolower($k), array_map('strtolower', $campos_cfdi))) {
            $extras[$k] = $v;
        }
    }at_downloads';

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
        // Buscar patrón de RFC (3 letras + 6 dígitos + 3 caracteres alfanuméricos)
        if (preg_match('/^[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $pathParts[$i])) {
            $rfcConsultado = $pathParts[$i];
            // Buscar EMITIDAS o RECIBIDAS después del RFC
            if (isset($pathParts[$i + 1]) && in_array(strtoupper($pathParts[$i + 1]), ['EMITIDAS', 'RECIBIDAS'])) {
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

function extractTimbreFiscalDigital($data) {
    $tfdData = [
        'FechaTimbrado' => null,
        'SelloSAT' => null,
        'NoCertificadoSAT' => null,
        'RfcProvCertif' => null
    ];
    
    if (isset($data['complementos']['TimbreFiscalDigital'])) {
        $tfd = $data['complementos']['TimbreFiscalDigital'][0] ?? [];
        $tfdData['FechaTimbrado'] = $tfd['FechaTimbrado'] ?? null;
        $tfdData['SelloSAT'] = $tfd['SelloSAT'] ?? null;
        $tfdData['NoCertificadoSAT'] = $tfd['NoCertificadoSAT'] ?? null;
        $tfdData['RfcProvCertif'] = $tfd['RfcProvCertif'] ?? null;
    }
    
    return $tfdData;
}

function extractCfdiRelacionados($data) {
    $relacionados = [];
    
    if (isset($data['comprobante']['CfdiRelacionados'])) {
        if (is_array($data['comprobante']['CfdiRelacionados'])) {
            foreach ($data['comprobante']['CfdiRelacionados'] as $rel) {
                if (isset($rel['UUID'])) {
                    $relacionados[] = $rel['UUID'];
                }
            }
        }
    }
    
    return !empty($relacionados) ? json_encode($relacionados) : null;
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
function extractTimbreFiscalDigital($data) {
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

function extractCfdiRelacionados($data) {
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
    // ...existing code...
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
        if (!in_array(strtolower($k), $campos_cfdi)) {
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

    // Insertar en tablas especializadas de complementos
    insertSpecializedComplements($pdo, $cfdi_id, $data);

    // Inserta complementos restantes en cfdi_complementos (solo uno por tipo por CFDI)
    if (!empty($data['complementos'])) {
        $sqlComp = "INSERT INTO cfdi_complementos (cfdi_id, tipo, datos_json) VALUES (?,?,?)";
        $stmtComp = $pdo->prepare($sqlComp);
        foreach ($data['complementos'] as $tipo => $comps) {
            // Saltar complementos que ya tienen tabla especializada
            if (in_array($tipo, ['TimbreFiscalDigital', 'Pagos'])) {
                continue;
            }
            // Si hay varios, los guarda juntos en un array JSON
            $stmtComp->execute([
                $cfdi_id,
                $tipo,
                json_encode($comps)
            ]);
        }
    }
}

function insertSpecializedComplements($pdo, $cfdi_id, $data) {
    // Insertar TimbreFiscalDigital
    if (isset($data['complementos']['TimbreFiscalDigital'])) {
        $tfd = $data['complementos']['TimbreFiscalDigital'][0] ?? [];
        $sql = "INSERT INTO cfdi_timbre_fiscal (cfdi_id, version, uuid, fecha_timbrado, rfc_prov_certif, leyenda, sello_cfd, no_certificado_sat, sello_sat) VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cfdi_id,
            $tfd['Version'] ?? null,
            $tfd['UUID'] ?? null,
            $tfd['FechaTimbrado'] ?? null,
            $tfd['RfcProvCertif'] ?? null,
            $tfd['Leyenda'] ?? null,
            $tfd['SelloCFD'] ?? null,
            $tfd['NoCertificadoSAT'] ?? null,
            $tfd['SelloSAT'] ?? null
        ]);
    }
    
    // Insertar Pagos
    if (isset($data['complementos']['Pagos'])) {
        $pagos = $data['complementos']['Pagos'][0] ?? [];
        if (isset($pagos['Pago'])) {
            foreach ($pagos['Pago'] as $pago) {
                $sql = "INSERT INTO cfdi_pagos (cfdi_id, fecha_pago, forma_pago_p, moneda_p, tipo_cambio_p, monto, num_operacion, rfc_emisor_cta_ord, nom_banco_ord_ext, cta_ordenante, rfc_emisor_cta_ben, cta_beneficiario) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $cfdi_id,
                    $pago['FechaPago'] ?? null,
                    $pago['FormaDePagoP'] ?? null,
                    $pago['MonedaP'] ?? null,
                    $pago['TipoCambioP'] ?? null,
                    $pago['Monto'] ?? null,
                    $pago['NumOperacion'] ?? null,
                    $pago['RfcEmisorCtaOrd'] ?? null,
                    $pago['NomBancoOrdExt'] ?? null,
                    $pago['CtaOrdenante'] ?? null,
                    $pago['RfcEmisorCtaBen'] ?? null,
                    $pago['CtaBeneficiario'] ?? null
                ]);
                $pago_id = $pdo->lastInsertId();
                
                // Insertar DocumentoRelacionado
                if (isset($pago['DoctoRelacionado'])) {
                    foreach ($pago['DoctoRelacionado'] as $doc) {
                        $sql = "INSERT INTO cfdi_pago_documentos_relacionados (pago_id, id_documento, serie, folio, moneda_dr, equivalencia_dr, num_parcialidad, imp_saldo_ant, imp_pagado, imp_saldo_insoluto, objetivo_imp_dr) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $pago_id,
                            $doc['IdDocumento'] ?? null,
                            $doc['Serie'] ?? null,
                            $doc['Folio'] ?? null,
                            $doc['MonedaDR'] ?? null,
                            $doc['EquivalenciaDR'] ?? null,
                            $doc['NumParcialidad'] ?? null,
                            $doc['ImpSaldoAnt'] ?? null,
                            $doc['ImpPagado'] ?? null,
                            $doc['ImpSaldoInsoluto'] ?? null,
                            $doc['ObjetoImpDR'] ?? null
                        ]);
                    }
                }
            }
        }
    }
}

$total = count($xmlPaths);
$ok = 0;
$err = 0;
// Auditoría de procesamiento
$auditLog = __DIR__ . '/importar_cfdi_auditoria.log';
file_put_contents($auditLog, "", LOCK_EX); // Limpiar log antes de iniciar
foreach ($xmlPaths as $i => $xmlFile) {
    $data = parseCfdi($xmlFile);
    $msg = "";
    if (isset($data['error'])) {
        $err++;
        $msg = date('Y-m-d H:i:s') . " | ERROR | $xmlFile | " . $data['error'] . "\n";
    } elseif (empty($data['uuid'])) {
        $err++;
        $msg = date('Y-m-d H:i:s') . " | SIN_UUID | $xmlFile | No se encontró UUID\n";
    } else {
        // Verificar duplicado antes de insertar
        $stmtCheck = $pdo->prepare("SELECT id FROM cfdi WHERE uuid = ? LIMIT 1");
        $stmtCheck->execute([$data['uuid']]);
        if ($stmtCheck->rowCount() > 0) {
            $msg = date('Y-m-d H:i:s') . " | DUPLICADO | $xmlFile | UUID: " . $data['uuid'] . "\n";
        } else {
            insertCfdi($pdo, $data);
            $ok++;
            $msg = date('Y-m-d H:i:s') . " | INSERTADO | $xmlFile | UUID: " . $data['uuid'] . "\n";
        }
    }
    if ($msg) file_put_contents($auditLog, $msg, FILE_APPEND | LOCK_EX);
    // Insertar registro en la tabla cfdi_auditoria
    $stmtAudit = $pdo->prepare("INSERT INTO cfdi_auditoria (archivo, uuid, estado, mensaje, fecha) VALUES (?, ?, ?, ?, ?)");
    $stmtAudit->execute([
        $xmlFile,
        isset($data['uuid']) ? $data['uuid'] : null,
        (strpos($msg, 'ERROR') !== false ? 'ERROR' : (strpos($msg, 'SIN_UUID') !== false ? 'SIN_UUID' : (strpos($msg, 'DUPLICADO') !== false ? 'DUPLICADO' : 'INSERTADO'))),
        $msg,
        date('Y-m-d H:i:s')
    ]);
}
echo "\nResumen:\n";
echo "  Total XML procesados: $total\n";
echo "  Importados correctamente: $ok\n";
echo "  Errores: $err\n";
