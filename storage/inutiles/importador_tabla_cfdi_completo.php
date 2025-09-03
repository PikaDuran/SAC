<?php
require_once 'vendor/autoload.php';

class ImportadorTablaCFDI
{
    private $pdo;
    private $stats = [];
    private $errores = [];

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=localhost;dbname=sac_db;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            echo "✅ Conexión a base de datos establecida\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage() . "\n");
        }
    }

    public function ejecutar()
    {
        echo "🚀 INICIANDO IMPORTACIÓN COMPLETA TABLA CFDI\n";
        echo "============================================\n";

        $this->inicializarStats();
        $directorios = [
            'C:\xampp\htdocs\SAC\storage\sat_downloads\BFM170822P38\EMITIDAS',
            'C:\xampp\htdocs\SAC\storage\sat_downloads\BFM170822P38\RECIBIDAS',
            'C:\xampp\htdocs\SAC\storage\sat_downloads\BLM1706026AA\EMITIDAS',
            'C:\xampp\htdocs\SAC\storage\sat_downloads\BLM1706026AA\RECIBIDAS'
        ];

        foreach ($directorios as $directorio) {
            if (!is_dir($directorio)) {
                echo "⚠️ Directorio no encontrado: $directorio\n";
                continue;
            }

            echo "📁 Procesando directorio: $directorio\n";
            $this->procesarDirectorio($directorio);
        }

        $this->mostrarResultados();
    }

    private function inicializarStats()
    {
        $this->stats = [
            'archivos_procesados' => 0,
            'cfdi_insertados' => 0,
            'errores' => 0,
            'duplicados' => 0
        ];
    }

    private function procesarDirectorio($directorio)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterator as $archivo) {
            if ($archivo->getExtension() === 'xml') {
                $this->procesarArchivo($archivo->getPathname());
            }
        }
    }

    private function procesarArchivo($rutaArchivo)
    {
        try {
            $this->stats['archivos_procesados']++;

            // Leer y parsear XML
            $xmlContent = file_get_contents($rutaArchivo);
            if (!$xmlContent) {
                throw new Exception("No se pudo leer el archivo XML");
            }

            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                throw new Exception("Error al parsear XML - formato inválido");
            }

            // Verificar CFDI válido
            if (!isset($xml['Version'])) {
                throw new Exception("No es un CFDI válido - falta atributo Version");
            }

            // Extraer información de la ruta
            $info = $this->extraerInfoRuta($rutaArchivo);

            // Iniciar transacción
            $this->pdo->beginTransaction();

            // Insertar CFDI completo
            $cfdi_id = $this->insertarCFDICompleto($xml, $info, $rutaArchivo);

            if ($cfdi_id) {
                $this->pdo->commit();
                $this->stats['cfdi_insertados']++;

                if ($this->stats['archivos_procesados'] % 100 == 0) {
                    echo "📊 Procesados: {$this->stats['archivos_procesados']} | Insertados: {$this->stats['cfdi_insertados']}\n";
                }
            } else {
                $this->pdo->rollback();
                $this->stats['errores']++;
            }
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollback();
            }
            $this->registrarError($rutaArchivo, $e->getMessage());
            $this->stats['errores']++;
        }
    }

    private function insertarCFDICompleto($xml, $info, $rutaArchivo)
    {
        // Extraer UUID desde Complemento TimbreFiscalDigital
        $uuid = $this->extraerUUID($xml);

        // Verificar si ya existe
        if ($this->existeUUID($uuid)) {
            $this->stats['duplicados']++;
            return false;
        }

        // Preparar SQL con TODOS los campos de la tabla cfdi
        $sql = "INSERT INTO cfdi (
            uuid, tipo, serie, folio, fecha, fecha_timbrado,
            rfc_emisor, nombre_emisor, regimen_fiscal_emisor,
            rfc_receptor, nombre_receptor, regimen_fiscal_receptor,
            uso_cfdi, lugar_expedicion, moneda, tipo_cambio,
            subtotal, descuento, total, metodo_pago, forma_pago,
            exportacion, observaciones, archivo_xml, complemento_tipo,
            complemento_json, rfc_consultado, direccion_flujo, version,
            sello_cfd, sello_sat, no_certificado_sat, rfc_prov_certif,
            estatus_sat, cfdi_relacionados, no_certificado, certificado,
            condiciones_de_pago
        ) VALUES (
            :uuid, :tipo, :serie, :folio, :fecha, :fecha_timbrado,
            :rfc_emisor, :nombre_emisor, :regimen_fiscal_emisor,
            :rfc_receptor, :nombre_receptor, :regimen_fiscal_receptor,
            :uso_cfdi, :lugar_expedicion, :moneda, :tipo_cambio,
            :subtotal, :descuento, :total, :metodo_pago, :forma_pago,
            :exportacion, :observaciones, :archivo_xml, :complemento_tipo,
            :complemento_json, :rfc_consultado, :direccion_flujo, :version,
            :sello_cfd, :sello_sat, :no_certificado_sat, :rfc_prov_certif,
            :estatus_sat, :cfdi_relacionados, :no_certificado, :certificado,
            :condiciones_de_pago
        )";

        $stmt = $this->pdo->prepare($sql);

        // Extraer datos del timbre fiscal
        $timbreFiscal = $this->extraerTimbreFiscal($xml);

        // Mapear TODOS los campos
        $datos = [
            ':uuid' => $uuid,
            ':tipo' => (string)$xml['TipoDeComprobante'] ?: null,
            ':serie' => (string)$xml['Serie'] ?: null,
            ':folio' => (string)$xml['Folio'] ?: null,
            ':fecha' => $this->convertirFecha((string)$xml['Fecha']),
            ':fecha_timbrado' => $timbreFiscal ? $this->convertirFecha($timbreFiscal['FechaTimbrado']) : null,

            // Emisor
            ':rfc_emisor' => (string)$xml->Emisor['Rfc'] ?: null,
            ':nombre_emisor' => (string)$xml->Emisor['Nombre'] ?: null,
            ':regimen_fiscal_emisor' => (string)$xml->Emisor['RegimenFiscal'] ?: null,

            // Receptor
            ':rfc_receptor' => (string)$xml->Receptor['Rfc'] ?: null,
            ':nombre_receptor' => (string)$xml->Receptor['Nombre'] ?: null,
            ':regimen_fiscal_receptor' => (string)$xml->Receptor['RegimenFiscalReceptor'] ?: null,
            ':uso_cfdi' => (string)$xml->Receptor['UsoCFDI'] ?: null,

            // Datos generales
            ':lugar_expedicion' => (string)$xml['LugarExpedicion'] ?: null,
            ':moneda' => (string)$xml['Moneda'] ?: 'MXN',
            ':tipo_cambio' => $xml['TipoCambio'] ? (float)$xml['TipoCambio'] : null,
            ':subtotal' => (float)$xml['SubTotal'] ?: 0,
            ':descuento' => $xml['Descuento'] ? (float)$xml['Descuento'] : null,
            ':total' => (float)$xml['Total'] ?: 0,
            ':metodo_pago' => (string)$xml['MetodoPago'] ?: null,
            ':forma_pago' => (string)$xml['FormaPago'] ?: null,
            ':exportacion' => (string)$xml['Exportacion'] ?: null,

            // Campos adicionales
            ':observaciones' => (string)$xml['Observaciones'] ?: null,
            ':archivo_xml' => $rutaArchivo,
            ':complemento_tipo' => $this->detectarComplementos($xml),
            ':complemento_json' => json_encode($this->extraerDatosCompletos($xml)),
            ':rfc_consultado' => $info['rfc_consultado'],
            ':direccion_flujo' => $info['direccion_flujo'],
            ':version' => (string)$xml['Version'] ?: null,

            // Datos de timbrado
            ':sello_cfd' => (string)$xml['Sello'] ?: null,
            ':sello_sat' => $timbreFiscal ? (string)$timbreFiscal['SelloSAT'] : null,
            ':no_certificado_sat' => $timbreFiscal ? (string)$timbreFiscal['NoCertificadoSAT'] : null,
            ':rfc_prov_certif' => $timbreFiscal ? (string)$timbreFiscal['RfcProvCertif'] : null,
            ':estatus_sat' => 'Vigente', // Por defecto

            // CFDIs relacionados
            ':cfdi_relacionados' => $this->extraerCFDIRelacionados($xml),

            // Certificado
            ':no_certificado' => (string)$xml['NoCertificado'] ?: null,
            ':certificado' => (string)$xml['Certificado'] ?: null,
            ':condiciones_de_pago' => (string)$xml['CondicionesDePago'] ?: null
        ];

        if ($stmt->execute($datos)) {
            return $this->pdo->lastInsertId();
        }

        return false;
    }

    private function extraerInfoRuta($rutaArchivo)
    {
        $ruta = str_replace('\\', '/', $rutaArchivo);

        if (strpos($ruta, 'BFM170822P38') !== false) {
            $rfc_consultado = 'BFM170822P38';
        } elseif (strpos($ruta, 'BLM1706026AA') !== false) {
            $rfc_consultado = 'BLM1706026AA';
        } else {
            $rfc_consultado = 'DESCONOCIDO';
        }

        $direccion_flujo = (strpos($ruta, 'EMITIDAS') !== false) ? 'EMITIDA' : 'RECIBIDA';

        return [
            'rfc_consultado' => $rfc_consultado,
            'direccion_flujo' => $direccion_flujo
        ];
    }

    private function extraerUUID($xml)
    {
        // Buscar en el complemento TimbreFiscalDigital
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                if ($complemento->getName() === 'TimbreFiscalDigital') {
                    return (string)$complemento['UUID'];
                }
            }
        }
        return null;
    }

    private function extraerTimbreFiscal($xml)
    {
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                if ($complemento->getName() === 'TimbreFiscalDigital') {
                    return $complemento;
                }
            }
        }
        return null;
    }

    private function detectarComplementos($xml)
    {
        $complementos = [];
        if (isset($xml->Complemento)) {
            foreach ($xml->Complemento->children() as $complemento) {
                $complementos[] = $complemento->getName();
            }
        }
        return implode(',', $complementos);
    }

    private function extraerDatosCompletos($xml)
    {
        // Convertir XML completo a array para JSON
        return json_decode(json_encode($xml), true);
    }

    private function extraerCFDIRelacionados($xml)
    {
        $relacionados = [];
        if (isset($xml->CfdiRelacionados)) {
            foreach ($xml->CfdiRelacionados->CfdiRelacionado as $relacionado) {
                $relacionados[] = (string)$relacionado['UUID'];
            }
        }
        return !empty($relacionados) ? json_encode($relacionados) : null;
    }

    private function convertirFecha($fecha)
    {
        if (empty($fecha)) return null;
        try {
            $dt = new DateTime($fecha);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function existeUUID($uuid)
    {
        if (!$uuid) return false;

        $stmt = $this->pdo->prepare("SELECT 1 FROM cfdi WHERE uuid = ? LIMIT 1");
        $stmt->execute([$uuid]);
        return $stmt->fetchColumn() !== false;
    }

    private function registrarError($archivo, $mensaje)
    {
        $this->errores[] = [
            'archivo' => $archivo,
            'error' => $mensaje,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo "❌ Error en $archivo: $mensaje\n";
    }

    private function mostrarResultados()
    {
        echo "\n🎯 RESULTADOS FINALES - TABLA CFDI\n";
        echo "==================================\n";
        echo "📁 Archivos procesados: " . $this->stats['archivos_procesados'] . "\n";
        echo "✅ CFDIs insertados: " . $this->stats['cfdi_insertados'] . "\n";
        echo "🔄 Duplicados omitidos: " . $this->stats['duplicados'] . "\n";
        echo "❌ Errores: " . $this->stats['errores'] . "\n";

        if (!empty($this->errores)) {
            echo "\n📋 DETALLE DE ERRORES:\n";
            foreach (array_slice($this->errores, 0, 10) as $error) {
                echo "  - {$error['archivo']}: {$error['error']}\n";
            }
            if (count($this->errores) > 10) {
                echo "  ... y " . (count($this->errores) - 10) . " errores más\n";
            }
        }

        // Verificar inserción en base de datos
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM cfdi");
        $total_bd = $stmt->fetchColumn();
        echo "\n📊 Total registros en BD: $total_bd\n";

        echo "\n✨ IMPORTACIÓN TABLA CFDI COMPLETADA ✨\n";
    }
}

// Ejecutar importador
echo "🚀 IMPORTADOR COMPLETO TABLA CFDI\n";
echo "=================================\n";

$importador = new ImportadorTablaCFDI();
$importador->ejecutar();
