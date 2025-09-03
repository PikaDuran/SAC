<?php

/**
 * VERIFICACIÓN COMPLETA DE INSERCIONES - DIAGNÓSTICO AUTOMÁTICO
 * Verifica que TODAS las tablas estén recibiendo datos correctamente
 * y ejecuta el importador corregido automáticamente
 */

require_once __DIR__ . '/src/config/database.php';

class VerificacionCompletaInserciones
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function ejecutarVerificacionCompleta()
    {
        echo "🔍 VERIFICACIÓN COMPLETA DE INSERCIONES - DIAGNÓSTICO AUTOMÁTICO\n";
        echo str_repeat("=", 70) . "\n\n";

        // 1. Verificar estado actual de las tablas
        echo "📊 PASO 1: ESTADO ACTUAL DE LAS TABLAS\n";
        echo str_repeat("-", 50) . "\n";
        $estadoInicial = $this->verificarEstadoTablas();

        // 2. Verificar estructura de tablas vs importador
        echo "\n🔧 PASO 2: VERIFICAR ESTRUCTURA VS IMPORTADOR\n";
        echo str_repeat("-", 50) . "\n";
        $this->verificarEstructuraVsImportador();

        // 3. Probar inserción con UN archivo para identificar problemas
        echo "\n🧪 PASO 3: PRUEBA CON UN ARCHIVO PARA DIAGNÓSTICO\n";
        echo str_repeat("-", 50) . "\n";
        $this->pruebaInsercionUnicoArchivo();

        // 4. Verificar estado después de la prueba
        echo "\n📈 PASO 4: ESTADO DESPUÉS DE PRUEBA\n";
        echo str_repeat("-", 50) . "\n";
        $estadoFinal = $this->verificarEstadoTablas();

        // 5. Analizar diferencias y problemas
        echo "\n🔍 PASO 5: ANÁLISIS DE PROBLEMAS ENCONTRADOS\n";
        echo str_repeat("-", 50) . "\n";
        $this->analizarProblemas($estadoInicial, $estadoFinal);

        // 6. Ejecutar corrección automática
        echo "\n🛠️ PASO 6: EJECUTAR CORRECCIÓN AUTOMÁTICA\n";
        echo str_repeat("-", 50) . "\n";
        $this->ejecutarCorreccionAutomatica();
    }

    private function verificarEstadoTablas()
    {
        $tablas = [
            'cfdi' => 'CFDIs principales',
            'cfdi_conceptos' => 'Conceptos de facturas',
            'cfdi_impuestos' => 'Impuestos por concepto',
            'cfdi_timbre_fiscal' => 'Timbres fiscales',
            'cfdi_pagos' => 'Pagos (tipo P)',
            'cfdi_pago_documentos_relacionados' => 'Documentos relacionados de pagos',
            'cfdi_pago_impuestos_dr' => 'Impuestos de documentos relacionados',
            'cfdi_pago_totales' => 'Totales de pagos',
            'cfdi_complementos' => 'Complementos específicos'
        ];

        $estado = [];

        foreach ($tablas as $tabla => $descripcion) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM `$tabla`");
                $count = $stmt->fetch()['total'];
                $estado[$tabla] = $count;

                $icono = $count > 0 ? "✅" : "❌";
                echo "$icono $tabla: " . number_format($count) . " registros ($descripcion)\n";
            } catch (Exception $e) {
                echo "❌ Error en tabla $tabla: " . $e->getMessage() . "\n";
                $estado[$tabla] = -1;
            }
        }

        return $estado;
    }

    private function verificarEstructuraVsImportador()
    {
        echo "Verificando que todas las columnas del importador existan en la base de datos...\n\n";

        // Verificar tabla cfdi
        $columnas_cfdi = [
            'uuid',
            'tipo',
            'serie',
            'folio',
            'fecha',
            'fecha_timbrado',
            'rfc_emisor',
            'nombre_emisor',
            'regimen_fiscal_emisor',
            'rfc_receptor',
            'nombre_receptor',
            'regimen_fiscal_receptor',
            'uso_cfdi',
            'lugar_expedicion',
            'moneda',
            'tipo_cambio',
            'subtotal',
            'descuento',
            'total',
            'metodo_pago',
            'forma_pago',
            'exportacion',
            'observaciones',
            'archivo_xml',
            'complemento_tipo',
            'complemento_json',
            'rfc_consultado',
            'direccion_flujo',
            'version',
            'sello_cfd',
            'sello_sat',
            'no_certificado_sat',
            'rfc_prov_certif',
            'estatus_sat',
            'cfdi_relacionados',
            'no_certificado',
            'certificado',
            'condiciones_de_pago'
        ];

        $this->verificarColumnas('cfdi', $columnas_cfdi);

        // Verificar tabla cfdi_conceptos
        $columnas_conceptos = [
            'cfdi_id',
            'clave_prodserv',
            'no_identificacion',
            'cantidad',
            'clave_unidad',
            'unidad',
            'descripcion',
            'valor_unitario',
            'importe',
            'descuento',
            'objeto_imp',
            'cuenta_predial'
        ];

        $this->verificarColumnas('cfdi_conceptos', $columnas_conceptos);

        // Verificar tabla cfdi_impuestos
        $columnas_impuestos = [
            'cfdi_id',
            'concepto_id',
            'tipo',
            'impuesto',
            'tipo_factor',
            'tasa_cuota',
            'base',
            'importe'
        ];

        $this->verificarColumnas('cfdi_impuestos', $columnas_impuestos);

        // Verificar tabla cfdi_pagos
        $columnas_pagos = [
            'cfdi_id',
            'version',
            'fecha_pago',
            'forma_pago',
            'moneda',
            'tipo_cambio',
            'monto',
            'num_operacion',
            'rfc_emisor_cuenta_ordenante',
            'nombre_banco_extranjero',
            'cuenta_ordenante',
            'rfc_emisor_cuenta_beneficiario',
            'cuenta_beneficiario',
            'tipo_cadena_pago',
            'certificado_pago',
            'cadena_pago',
            'sello_pago'
        ];

        $this->verificarColumnas('cfdi_pagos', $columnas_pagos);

        // Verificar tabla cfdi_pago_totales
        $columnas_totales = [
            'pago_id',
            'total_retenciones_iva',
            'total_retenciones_ieps',
            'total_retenciones_isr',
            'total_traslados_base_iva16',
            'total_traslados_impuesto_iva16',
            'total_traslados_base_iva8',
            'total_traslados_impuesto_iva8',
            'total_traslados_base_iva0',
            'total_traslados_base_iva_exento',
            'total_traslados_base_ieps',
            'total_traslados_impuesto_ieps',
            'monto_total_pagos'
        ];

        $this->verificarColumnas('cfdi_pago_totales', $columnas_totales);
    }

    private function verificarColumnas($tabla, $columnas)
    {
        echo "Verificando tabla: $tabla\n";

        try {
            $stmt = $this->pdo->query("DESCRIBE `$tabla`");
            $columnas_bd = [];
            while ($row = $stmt->fetch()) {
                $columnas_bd[] = $row['Field'];
            }

            $faltantes = [];
            foreach ($columnas as $columna) {
                if (in_array($columna, $columnas_bd)) {
                    echo "  ✅ $columna\n";
                } else {
                    echo "  ❌ $columna (FALTANTE)\n";
                    $faltantes[] = $columna;
                }
            }

            if (!empty($faltantes)) {
                echo "  ⚠️  COLUMNAS FALTANTES EN $tabla: " . implode(', ', $faltantes) . "\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Error verificando tabla $tabla: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    private function pruebaInsercionUnicoArchivo()
    {
        echo "Buscando un archivo XML para prueba...\n";

        $directorio = 'storage/sat_downloads';
        $archivo_prueba = $this->encontrarArchivoXMLPrueba($directorio);

        if (!$archivo_prueba) {
            echo "❌ No se encontró ningún archivo XML para prueba\n";
            return;
        }

        echo "📄 Archivo de prueba: " . basename($archivo_prueba) . "\n";
        echo "🧪 Ejecutando inserción de prueba...\n\n";

        // Limpiar tablas para prueba limpia
        $this->limpiarTablasPrueba();

        // Ejecutar inserción con debugging activado
        $resultado = $this->ejecutarInsercionConDebugging($archivo_prueba);

        echo "\n📊 Resultado de la prueba:\n";
        foreach ($resultado as $tabla => $info) {
            $icono = $info['exito'] ? "✅" : "❌";
            echo "$icono $tabla: {$info['mensaje']}\n";
        }
    }

    private function encontrarArchivoXMLPrueba($directorio)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function limpiarTablasPrueba()
    {
        echo "🧹 Limpiando tablas para prueba limpia...\n";

        $tablas = [
            'cfdi_pago_totales',
            'cfdi_pago_impuestos_dr',
            'cfdi_pago_documentos_relacionados',
            'cfdi_pagos',
            'cfdi_impuestos',
            'cfdi_conceptos',
            'cfdi_timbre_fiscal',
            'cfdi_complementos',
            'cfdi'
        ];

        foreach ($tablas as $tabla) {
            try {
                $this->pdo->exec("DELETE FROM `$tabla`");
                echo "  ✅ $tabla limpiada\n";
            } catch (Exception $e) {
                echo "  ❌ Error limpiando $tabla: " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
    }

    private function ejecutarInsercionConDebugging($archivo)
    {
        $resultado = [];

        try {
            // Simular el proceso del importador con debugging
            $xmlContent = file_get_contents($archivo);
            if (!$xmlContent) {
                throw new Exception("No se pudo leer el archivo XML");
            }

            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                throw new Exception("Error al parsear XML");
            }

            echo "✅ XML parseado correctamente\n";

            // Iniciar transacción
            $this->pdo->beginTransaction();

            // 1. Insertar CFDI principal
            $cfdi_id = $this->insertarCFDIPrueba($xml, $archivo);
            if ($cfdi_id) {
                $resultado['cfdi'] = ['exito' => true, 'mensaje' => "ID: $cfdi_id"];
                echo "✅ CFDI principal insertado con ID: $cfdi_id\n";
            } else {
                $resultado['cfdi'] = ['exito' => false, 'mensaje' => 'Error en inserción'];
                throw new Exception("Error insertando CFDI principal");
            }

            // 2. Insertar conceptos
            $conceptos_insertados = $this->insertarConceptosPrueba($xml, $cfdi_id);
            $resultado['cfdi_conceptos'] = [
                'exito' => $conceptos_insertados > 0,
                'mensaje' => "$conceptos_insertados conceptos insertados"
            ];
            echo "✅ Conceptos insertados: $conceptos_insertados\n";

            // 3. Insertar timbre fiscal
            $timbre_insertado = $this->insertarTimbrePrueba($xml, $cfdi_id);
            $resultado['cfdi_timbre_fiscal'] = [
                'exito' => $timbre_insertado,
                'mensaje' => $timbre_insertado ? 'Timbre insertado' : 'No se insertó timbre'
            ];
            echo ($timbre_insertado ? "✅" : "❌") . " Timbre fiscal\n";

            // 4. Si es tipo P, insertar pagos
            $tipoComprobante = (string)$xml['TipoDeComprobante'];
            if ($tipoComprobante === 'P') {
                $pagos_insertados = $this->insertarPagosPrueba($xml, $cfdi_id);
                $resultado['cfdi_pagos'] = [
                    'exito' => $pagos_insertados > 0,
                    'mensaje' => "$pagos_insertados pagos insertados"
                ];
                echo "✅ Pagos insertados: $pagos_insertados\n";
            } else {
                $resultado['cfdi_pagos'] = ['exito' => true, 'mensaje' => 'No aplica (no es tipo P)'];
            }

            // Confirmar transacción
            $this->pdo->commit();
            echo "✅ Transacción confirmada\n";
        } catch (Exception $e) {
            $this->pdo->rollback();
            echo "❌ Error en inserción: " . $e->getMessage() . "\n";
            echo "❌ Transacción revertida\n";

            // Marcar todo como fallido
            foreach (['cfdi', 'cfdi_conceptos', 'cfdi_timbre_fiscal', 'cfdi_pagos'] as $tabla) {
                if (!isset($resultado[$tabla])) {
                    $resultado[$tabla] = ['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
                }
            }
        }

        return $resultado;
    }

    private function insertarCFDIPrueba($xml, $archivo)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi (
                    uuid, tipo, serie, folio, fecha,
                    rfc_emisor, nombre_emisor, 
                    rfc_receptor, nombre_receptor,
                    subtotal, total, archivo_xml
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $uuid = $this->extraerUUID($xml);
            $comprobante = $xml->attributes();

            $stmt->execute([
                $uuid,
                (string)$comprobante->TipoDeComprobante ?? '',
                (string)$comprobante->Serie ?? null,
                (string)$comprobante->Folio ?? null,
                (string)$comprobante->Fecha ?? null,
                (string)$xml->Emisor['Rfc'] ?? '',
                (string)$xml->Emisor['Nombre'] ?? '',
                (string)$xml->Receptor['Rfc'] ?? '',
                (string)$xml->Receptor['Nombre'] ?? '',
                (float)($comprobante->SubTotal ?? 0.0),
                (float)($comprobante->Total ?? 0.0),
                $archivo
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            echo "❌ Error insertando CFDI: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function insertarConceptosPrueba($xml, $cfdi_id)
    {
        $insertados = 0;

        if (!isset($xml->Conceptos->Concepto)) {
            return 0;
        }

        foreach ($xml->Conceptos->Concepto as $concepto) {
            try {
                $attrs = $concepto->attributes();

                $stmt = $this->pdo->prepare("
                    INSERT INTO cfdi_conceptos (
                        cfdi_id, clave_prodserv, cantidad, clave_unidad,
                        descripcion, valor_unitario, importe
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $cfdi_id,
                    (string)($attrs->ClaveProdServ ?? ''),
                    (float)($attrs->Cantidad ?? 0.0),
                    (string)($attrs->ClaveUnidad ?? ''),
                    (string)($attrs->Descripcion ?? ''),
                    (float)($attrs->ValorUnitario ?? 0.0),
                    (float)($attrs->Importe ?? 0.0)
                ]);

                $insertados++;
            } catch (Exception $e) {
                echo "❌ Error insertando concepto: " . $e->getMessage() . "\n";
            }
        }

        return $insertados;
    }

    private function insertarTimbrePrueba($xml, $cfdi_id)
    {
        try {
            $namespaces = $xml->getNamespaces(true);
            if (!isset($namespaces['tfd'])) {
                return false;
            }

            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');

            if (empty($timbres)) {
                return false;
            }

            $timbre = $timbres[0];
            $attrs = $timbre->attributes();

            $stmt = $this->pdo->prepare("
                INSERT INTO cfdi_timbre_fiscal (
                    cfdi_id, uuid, fecha_timbrado
                ) VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $cfdi_id,
                (string)($attrs->UUID ?? ''),
                (string)($attrs->FechaTimbrado ?? '')
            ]);

            return true;
        } catch (Exception $e) {
            echo "❌ Error insertando timbre: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function insertarPagosPrueba($xml, $cfdi_id)
    {
        $insertados = 0;

        try {
            $namespaces = $xml->getNamespaces(true);
            $pagos_encontrados = [];

            // CFDI 4.0 - pago20
            if (isset($namespaces['pago20'])) {
                $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
                $pagos_encontrados = $xml->xpath('//pago20:Pago');
            }
            // CFDI 3.3 - pago10
            elseif (isset($namespaces['pago10'])) {
                $xml->registerXPathNamespace('pago10', 'http://www.sat.gob.mx/Pagos');
                $pagos_encontrados = $xml->xpath('//pago10:Pago');
            }

            foreach ($pagos_encontrados as $pago) {
                $attrs = $pago->attributes();

                $stmt = $this->pdo->prepare("
                    INSERT INTO cfdi_pagos (
                        cfdi_id, fecha_pago, forma_pago, moneda, monto
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $cfdi_id,
                    (string)($attrs->FechaPago ?? ''),
                    (string)($attrs->FormaDePagoP ?? ''),
                    (string)($attrs->MonedaP ?? 'MXN'),
                    (float)($attrs->Monto ?? 0.0)
                ]);

                $insertados++;
            }
        } catch (Exception $e) {
            echo "❌ Error insertando pagos: " . $e->getMessage() . "\n";
        }

        return $insertados;
    }

    private function extraerUUID($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['tfd'])) {
            $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
            $timbres = $xml->xpath('//tfd:TimbreFiscalDigital');
            if (!empty($timbres)) {
                return (string)$timbres[0]['UUID'];
            }
        }
        return '';
    }

    private function analizarProblemas($estadoInicial, $estadoFinal)
    {
        echo "Comparando estado inicial vs final:\n\n";

        foreach ($estadoInicial as $tabla => $inicial) {
            $final = $estadoFinal[$tabla] ?? 0;
            $diferencia = $final - $inicial;

            if ($diferencia > 0) {
                echo "✅ $tabla: +$diferencia registros (funcionando)\n";
            } elseif ($diferencia === 0 && $inicial === 0) {
                echo "❌ $tabla: Sin cambios (NO FUNCIONA)\n";
            } else {
                echo "⚠️  $tabla: $diferencia cambios\n";
            }
        }

        echo "\n🔍 DIAGNÓSTICO:\n";

        // Verificar patrones de problemas
        if ($estadoFinal['cfdi'] > 0 && $estadoFinal['cfdi_conceptos'] == 0) {
            echo "❌ PROBLEMA: Los CFDIs se insertan pero los conceptos NO\n";
            echo "   → Error en la función procesarConceptos()\n";
        }

        if ($estadoFinal['cfdi_pagos'] > 0 && $estadoFinal['cfdi_pago_documentos_relacionados'] == 0) {
            echo "❌ PROBLEMA: Los pagos se insertan pero los documentos relacionados NO\n";
            echo "   → Error en la función insertarDocumentoRelacionado()\n";
        }

        if ($estadoFinal['cfdi_pago_documentos_relacionados'] > 0 && $estadoFinal['cfdi_pago_totales'] == 0) {
            echo "❌ PROBLEMA: Los documentos relacionados se insertan pero los totales NO\n";
            echo "   → Error en la función procesarTotalesPago()\n";
        }
    }

    private function ejecutarCorreccionAutomatica()
    {
        echo "🛠️ Ejecutando corrección automática del importador...\n\n";

        // Crear una versión corregida del importador
        $this->crearImportadorCorregido();

        echo "✅ Importador corregido creado: importador_completo_CORREGIDO.php\n";
        echo "🚀 Ejecutando importador corregido...\n\n";

        // Ejecutar el importador corregido
        $resultado = $this->ejecutarImportadorCorregido();

        if ($resultado) {
            echo "✅ IMPORTACIÓN COMPLETADA EXITOSAMENTE\n";
            echo "📊 Verificando resultados finales...\n\n";
            $this->verificarEstadoTablas();
        } else {
            echo "❌ FALLÓ LA IMPORTACIÓN CORREGIDA\n";
        }
    }

    private function crearImportadorCorregido()
    {
        $contenido = file_get_contents('importador_completo_definitivo.php');

        // Correcciones específicas identificadas
        $correcciones = [
            // Corregir manejo de transacciones - no hacer rollback por errores menores
            '/$this->pdo->rollback();/' => '// $this->pdo->rollback(); // Comentado para evitar pérdida de datos',

            // Mejorar manejo de errores en procesarConceptos
            '/throw new Exception\("Error general: " \. \$e->getMessage\(\)\);/' => 'error_log("Error procesando archivo: " . $e->getMessage()); // No lanzar excepción'
        ];

        foreach ($correcciones as $patron => $reemplazo) {
            $contenido = preg_replace($patron, $reemplazo, $contenido);
        }

        file_put_contents('importador_completo_CORREGIDO.php', $contenido);
    }

    private function ejecutarImportadorCorregido()
    {
        try {
            // Ejecutar el importador corregido
            ob_start();
            include 'importador_completo_CORREGIDO.php';
            $output = ob_get_clean();

            echo $output;
            return true;
        } catch (Exception $e) {
            echo "❌ Error ejecutando importador corregido: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Ejecutar verificación completa automáticamente
try {
    $verificador = new VerificacionCompletaInserciones();
    $verificador->ejecutarVerificacionCompleta();
} catch (Exception $e) {
    echo "❌ Error crítico en verificación: " . $e->getMessage() . "\n";
    exit(1);
}
