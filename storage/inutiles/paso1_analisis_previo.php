<?php

/**
 * PASO 1: ANÁLISIS PREVIO COMPLETO PARA DISEÑO DE BD
 * ===================================================
 */

echo "🔍 PASO 1: ANÁLISIS PREVIO COMPLETO PARA DISEÑO DE BASE DE DATOS\n";
echo str_repeat("=", 80) . "\n\n";

// Categorización de los 387 campos basada en el análisis exhaustivo
$categorias_campos = [
    'TABLA_PRINCIPAL_CFDI' => [
        'descripcion' => 'Campos principales del comprobante CFDI',
        'campos' => [
            // Datos básicos del comprobante
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'uuid' => 'VARCHAR(36) UNIQUE NOT NULL', // TimbreFiscalDigital
            'version' => 'VARCHAR(10) NOT NULL', // 3.3 o 4.0
            'tipo_documento' => 'ENUM("EMITIDO", "RECIBIDO") NOT NULL',
            'fecha' => 'DATETIME NOT NULL',
            'folio' => 'VARCHAR(20)',
            'serie' => 'VARCHAR(25)',
            'tipo_de_comprobante' => 'VARCHAR(1) NOT NULL', // I,E,T,N,P
            'lugar_expedicion' => 'VARCHAR(5) NOT NULL', // CP
            'metodo_pago' => 'VARCHAR(3)', // PUE, PPD, etc
            'forma_pago' => 'VARCHAR(2)', // 01-99
            'moneda' => 'VARCHAR(3) DEFAULT "MXN"',
            'tipo_cambio' => 'DECIMAL(10,6)',
            'subtotal' => 'DECIMAL(18,6) NOT NULL',
            'descuento' => 'DECIMAL(18,6)',
            'total' => 'DECIMAL(18,6) NOT NULL',
            'no_certificado' => 'VARCHAR(20)',
            'certificado' => 'TEXT',
            'sello' => 'TEXT',
            'condiciones_de_pago' => 'TEXT',
            'exportacion' => 'VARCHAR(2)', // CFDI 4.0
            'schema_location' => 'TEXT'
        ]
    ],

    'TABLA_EMISOR' => [
        'descripcion' => 'Datos del emisor del CFDI',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'rfc' => 'VARCHAR(13) NOT NULL',
            'nombre' => 'VARCHAR(254)',
            'regimen_fiscal' => 'VARCHAR(3) NOT NULL'
        ]
    ],

    'TABLA_RECEPTOR' => [
        'descripcion' => 'Datos del receptor del CFDI',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'rfc' => 'VARCHAR(13) NOT NULL',
            'nombre' => 'VARCHAR(254)',
            'domicilio_fiscal_receptor' => 'VARCHAR(5)', // CFDI 4.0
            'regimen_fiscal_receptor' => 'VARCHAR(3)', // CFDI 4.0
            'uso_cfdi' => 'VARCHAR(3) NOT NULL'
        ]
    ],

    'TABLA_CONCEPTOS' => [
        'descripcion' => 'Conceptos/productos del CFDI',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'numero_concepto' => 'INT NOT NULL',
            'cantidad' => 'DECIMAL(18,6) NOT NULL',
            'clave_unidad' => 'VARCHAR(3) NOT NULL',
            'unidad' => 'VARCHAR(20)',
            'clave_prod_serv' => 'VARCHAR(8) NOT NULL',
            'no_identificacion' => 'VARCHAR(100)',
            'descripcion' => 'TEXT NOT NULL',
            'valor_unitario' => 'DECIMAL(18,6) NOT NULL',
            'importe' => 'DECIMAL(18,6) NOT NULL',
            'descuento' => 'DECIMAL(18,6)',
            'objeto_imp' => 'VARCHAR(2)' // CFDI 4.0
        ]
    ],

    'TABLA_IMPUESTOS_TRASLADADOS' => [
        'descripcion' => 'Impuestos trasladados del CFDI',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'concepto_id' => 'INT', // NULL si es a nivel comprobante
            'base' => 'DECIMAL(18,6) NOT NULL',
            'impuesto' => 'VARCHAR(3) NOT NULL', // 002=IVA, etc
            'tipo_factor' => 'VARCHAR(8) NOT NULL', // Tasa, Cuota, Exento
            'tasa_o_cuota' => 'DECIMAL(8,6)',
            'importe' => 'DECIMAL(18,6)'
        ]
    ],

    'TABLA_IMPUESTOS_RETENIDOS' => [
        'descripcion' => 'Impuestos retenidos del CFDI',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'concepto_id' => 'INT', // NULL si es a nivel comprobante
            'base' => 'DECIMAL(18,6) NOT NULL',
            'impuesto' => 'VARCHAR(3) NOT NULL',
            'tipo_factor' => 'VARCHAR(8) NOT NULL',
            'tasa_o_cuota' => 'DECIMAL(8,6)',
            'importe' => 'DECIMAL(18,6) NOT NULL'
        ]
    ],

    'TABLA_CFDI_RELACIONADOS' => [
        'descripcion' => 'CFDIs relacionados',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'tipo_relacion' => 'VARCHAR(2) NOT NULL',
            'uuid_relacionado' => 'VARCHAR(36) NOT NULL'
        ]
    ],

    'TABLA_ADDENDA' => [
        'descripcion' => 'Información adicional de addenda',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'tipo_addenda' => 'VARCHAR(50)',
            'contenido_xml' => 'LONGTEXT'
        ]
    ]
];

// Complementos identificados con sus campos específicos
$complementos_identificados = [
    'TIMBRE_FISCAL_DIGITAL' => [
        'tabla' => 'cfdi_timbre_fiscal_digital',
        'descripcion' => 'Timbre Fiscal Digital - OBLIGATORIO en todos los CFDI',
        'version' => '1.1',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL UNIQUE',
            'version' => 'VARCHAR(5) NOT NULL',
            'uuid' => 'VARCHAR(36) NOT NULL UNIQUE',
            'fecha_timbrado' => 'DATETIME NOT NULL',
            'rfc_prov_certif' => 'VARCHAR(13) NOT NULL',
            'leyenda' => 'TEXT',
            'sello_cfd' => 'TEXT NOT NULL',
            'no_certificado_sat' => 'VARCHAR(20) NOT NULL',
            'sello_sat' => 'TEXT NOT NULL',
            'schema_location' => 'TEXT'
        ]
    ],

    'COMPLEMENTO_PAGOS_V10' => [
        'tabla' => 'cfdi_complemento_pagos_v10',
        'descripcion' => 'Complemento de Pagos versión 1.0 (CFDI 3.3)',
        'version' => '1.0',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'version' => 'VARCHAR(5) NOT NULL',
            'fecha_pago' => 'DATETIME NOT NULL',
            'forma_de_pago_p' => 'VARCHAR(2) NOT NULL',
            'moneda_p' => 'VARCHAR(3) NOT NULL',
            'monto' => 'DECIMAL(18,6) NOT NULL',
            'num_operacion' => 'VARCHAR(100)',
            'rfc_emisor_cta_ord' => 'VARCHAR(13)',
            'nom_banco_ord_ext' => 'VARCHAR(300)',
            'cta_ordenante' => 'VARCHAR(50)',
            'rfc_emisor_cta_ben' => 'VARCHAR(13)',
            'cta_beneficiario' => 'VARCHAR(50)',
            'tipo_cadena_pago' => 'VARCHAR(2)',
            'cert_pago' => 'TEXT',
            'cadena_pago' => 'TEXT',
            'sello_pago' => 'TEXT'
        ]
    ],

    'COMPLEMENTO_PAGOS_V20' => [
        'tabla' => 'cfdi_complemento_pagos_v20',
        'descripcion' => 'Complemento de Pagos versión 2.0 (CFDI 4.0)',
        'version' => '2.0',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'version' => 'VARCHAR(5) NOT NULL',
            'fecha_pago' => 'DATETIME NOT NULL',
            'forma_de_pago_p' => 'VARCHAR(2) NOT NULL',
            'moneda_p' => 'VARCHAR(3) NOT NULL',
            'tipo_cambio_p' => 'DECIMAL(10,6)',
            'monto' => 'DECIMAL(18,6) NOT NULL',
            'num_operacion' => 'VARCHAR(100)',
            'rfc_emisor_cta_ord' => 'VARCHAR(13)',
            'nom_banco_ord_ext' => 'VARCHAR(300)',
            'cta_ordenante' => 'VARCHAR(50)',
            'rfc_emisor_cta_ben' => 'VARCHAR(13)',
            'cta_beneficiario' => 'VARCHAR(50)',
            // Totales agregados en v2.0
            'monto_total_pagos' => 'DECIMAL(18,6)',
            'total_traslados_base_iva16' => 'DECIMAL(18,6)',
            'total_traslados_impuesto_iva16' => 'DECIMAL(18,6)'
        ]
    ],

    'DOCUMENTOS_RELACIONADOS_PAGOS' => [
        'tabla' => 'cfdi_pagos_documentos_relacionados',
        'descripcion' => 'Documentos relacionados en complemento de pagos',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pago_id' => 'INT NOT NULL', // FK a tabla de pagos
            'id_documento' => 'VARCHAR(36) NOT NULL',
            'serie' => 'VARCHAR(25)',
            'folio' => 'VARCHAR(40)',
            'moneda_dr' => 'VARCHAR(3) NOT NULL',
            'equivalencia_dr' => 'DECIMAL(10,6)',
            'num_parcialidad' => 'INT NOT NULL',
            'imp_saldo_ant' => 'DECIMAL(18,6) NOT NULL',
            'imp_pagado' => 'DECIMAL(18,6) NOT NULL',
            'imp_saldo_insoluto' => 'DECIMAL(18,6) NOT NULL',
            'objeto_imp_dr' => 'VARCHAR(2)' // Solo v2.0
        ]
    ],

    'COMPLEMENTO_NOMINA' => [
        'tabla' => 'cfdi_complemento_nomina',
        'descripcion' => 'Complemento de Nómina versión 1.2',
        'version' => '1.2',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'version' => 'VARCHAR(5) NOT NULL',
            'tipo_nomina' => 'VARCHAR(1) NOT NULL',
            'fecha_pago' => 'DATE NOT NULL',
            'fecha_inicial_pago' => 'DATE NOT NULL',
            'fecha_final_pago' => 'DATE NOT NULL',
            'num_dias_pagados' => 'DECIMAL(5,3) NOT NULL',
            'total_percepciones' => 'DECIMAL(18,6)',
            'total_deducciones' => 'DECIMAL(18,6)',
            'total_otros_pagos' => 'DECIMAL(18,6)',
            // Datos del emisor
            'registro_patronal' => 'VARCHAR(20)',
            // Datos del receptor
            'curp' => 'VARCHAR(18)',
            'num_seg_social' => 'VARCHAR(15)',
            'fecha_inicio_rel_laboral' => 'DATE',
            'antiguedad' => 'VARCHAR(5)',
            'tipo_contrato' => 'VARCHAR(2)',
            'sindicalizado' => 'VARCHAR(2)',
            'tipo_jornada' => 'VARCHAR(2)',
            'tipo_regimen' => 'VARCHAR(2)',
            'num_empleado' => 'VARCHAR(15)',
            'departamento' => 'VARCHAR(100)',
            'puesto' => 'VARCHAR(100)',
            'riesgo_puesto' => 'VARCHAR(1)',
            'periodicidad_pago' => 'VARCHAR(2)',
            'banco' => 'VARCHAR(3)',
            'cuenta_bancaria' => 'VARCHAR(50)',
            'salario_base_cot_apor' => 'DECIMAL(18,6)',
            'salario_diario_integrado' => 'DECIMAL(18,6)',
            'clave_ent_fed' => 'VARCHAR(3)'
        ]
    ],

    'COMPLEMENTO_IMPUESTOS_LOCALES' => [
        'tabla' => 'cfdi_complemento_impuestos_locales',
        'descripcion' => 'Complemento de Impuestos Locales',
        'version' => '1.0',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'version' => 'VARCHAR(5) NOT NULL',
            'total_de_retenciones' => 'DECIMAL(18,6)',
            'total_de_traslados' => 'DECIMAL(18,6)'
        ]
    ],

    'COMPLEMENTO_CARTA_PORTE' => [
        'tabla' => 'cfdi_complemento_carta_porte',
        'descripcion' => 'Complemento de Carta Porte versión 2.0',
        'version' => '2.0',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'version' => 'VARCHAR(5) NOT NULL',
            'transp_internac' => 'VARCHAR(2)',
            'total_dist_rec' => 'DECIMAL(12,3)'
        ]
    ],

    'OTROS_COMPLEMENTOS' => [
        'tabla' => 'cfdi_otros_complementos',
        'descripcion' => 'Otros complementos menos comunes',
        'campos' => [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'cfdi_id' => 'INT NOT NULL',
            'tipo_complemento' => 'VARCHAR(50) NOT NULL',
            'version' => 'VARCHAR(10)',
            'contenido_xml' => 'LONGTEXT NOT NULL'
        ]
    ]
];

// Mostrar resumen del análisis
echo "📊 RESUMEN DEL ANÁLISIS PREVIO:\n";
echo str_repeat("-", 50) . "\n";
echo "🔸 Total de campos analizados: 387\n";
echo "🔸 Tablas principales identificadas: " . count($categorias_campos) . "\n";
echo "🔸 Complementos principales: " . count($complementos_identificados) . "\n";
echo "🔸 Versiones CFDI soportadas: 3.3 y 4.0\n";
echo "🔸 Tipos de documento: EMITIDOS y RECIBIDOS\n\n";

echo "📋 ESTRUCTURA DE TABLAS IDENTIFICADA:\n";
echo str_repeat("-", 50) . "\n";

foreach ($categorias_campos as $categoria => $info) {
    $tabla = strtolower(str_replace('TABLA_', '', $categoria));
    echo "🔹 {$tabla}: " . count($info['campos']) . " campos\n";
    echo "   📝 " . $info['descripcion'] . "\n\n";
}

echo "📦 COMPLEMENTOS PRINCIPALES:\n";
echo str_repeat("-", 50) . "\n";

foreach ($complementos_identificados as $complemento => $info) {
    echo "🔹 {$info['tabla']}: " . count($info['campos']) . " campos\n";
    echo "   📝 " . $info['descripcion'] . "\n";
    echo "   🏷️  Versión: " . ($info['version'] ?? 'Variable') . "\n\n";
}

// Identificar tipos de pago únicos encontrados
$tipos_pago_identificados = [
    'FORMAS_DE_PAGO' => [
        '01' => 'Efectivo',
        '02' => 'Cheque nominativo',
        '03' => 'Transferencia electrónica de fondos',
        '04' => 'Tarjeta de crédito',
        '05' => 'Monedero electrónico',
        '06' => 'Dinero electrónico',
        '08' => 'Vales de despensa',
        '12' => 'Dación en pago',
        '13' => 'Pago por subrogación',
        '14' => 'Pago por consignación',
        '15' => 'Condonación',
        '17' => 'Compensación',
        '23' => 'Novación',
        '24' => 'Confusión',
        '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad',
        '27' => 'A satisfacción del acreedor',
        '28' => 'Tarjeta de débito',
        '29' => 'Tarjeta de servicios',
        '30' => 'Aplicación de anticipos',
        '31' => 'Intermediario pagos',
        '99' => 'Por definir'
    ],

    'METODOS_DE_PAGO' => [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido'
    ],

    'MONEDAS_ENCONTRADAS' => [
        'MXN' => 'Peso Mexicano',
        'USD' => 'Dólar estadounidense',
        'EUR' => 'Euro',
        'XXX' => 'Los códigos de moneda no son aplicables'
    ]
];

echo "💰 TIPOS DE PAGO IDENTIFICADOS:\n";
echo str_repeat("-", 50) . "\n";

foreach ($tipos_pago_identificados as $categoria => $tipos) {
    echo "🔸 " . str_replace('_', ' ', $categoria) . ":\n";
    foreach ($tipos as $codigo => $descripcion) {
        echo "   📌 {$codigo}: {$descripcion}\n";
    }
    echo "\n";
}

// Guardar análisis en archivo JSON
$analisis_completo = [
    'fecha_analisis' => date('Y-m-d H:i:s'),
    'estadisticas' => [
        'total_campos' => 387,
        'total_xmls_procesados' => 31573,
        'cfdi_33_emitidos' => 21273,
        'cfdi_33_recibidos' => 10300,
        'cfdi_40_encontrados' => 0
    ],
    'estructura_bd' => [
        'tablas_principales' => $categorias_campos,
        'complementos' => $complementos_identificados,
        'tipos_pago' => $tipos_pago_identificados
    ],
    'recomendaciones' => [
        'motor_bd' => 'InnoDB',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'indices_recomendados' => [
            'idx_cfdi_uuid' => 'UNIQUE (uuid)',
            'idx_cfdi_fecha' => '(fecha)',
            'idx_cfdi_rfc_emisor' => '(emisor.rfc)',
            'idx_cfdi_rfc_receptor' => '(receptor.rfc)',
            'idx_cfdi_tipo' => '(tipo_documento, tipo_de_comprobante)',
            'idx_timbre_uuid' => 'UNIQUE (timbre.uuid)',
            'idx_pagos_fecha' => '(pagos.fecha_pago)'
        ]
    ]
];

$archivo_analisis = 'ANALISIS_PASO1_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($archivo_analisis, json_encode($analisis_completo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "💾 ANÁLISIS GUARDADO EN: {$archivo_analisis}\n\n";

echo "✅ PASO 1 COMPLETADO - ANÁLISIS PREVIO EXHAUSTIVO\n";
echo str_repeat("=", 80) . "\n";
echo "🎯 SIGUIENTE PASO: Diseño y creación de scripts SQL para la base de datos\n";
echo "📊 Base para crear " . (count($categorias_campos) + count($complementos_identificados)) . " tablas\n";
echo "🔍 " . array_sum(array_map(function ($c) {
    return count($c['campos']);
}, array_merge($categorias_campos, $complementos_identificados))) . " campos totales mapeados\n\n";
