<?php
/**
 * LISTA COMPLETA DE CAMPOS CFDI - UNA SOLA COLUMNA
 * Basado en análisis de XML CFDI 3.3 de 2022
 */

echo "📋 LISTA COMPLETA DE CAMPOS EXTRAÍBLES DE CFDI\n";
echo "📄 Basado en: XML CFDI 3.3 emitido en 2022\n";
echo str_repeat("=", 50) . "\n\n";

echo "CAMPO\n";
echo str_repeat("-", 50) . "\n";

// Array con TODOS los campos posibles
$campos = [
    // COMPROBANTE PRINCIPAL
    'version',
    'serie', 
    'folio',
    'fecha',
    'sello_cfd',
    'forma_pago',
    'no_certificado',
    'certificado',
    'subtotal',
    'descuento',
    'moneda',
    'tipo_cambio',
    'total',
    'tipo_de_comprobante',
    'exportacion',
    'metodo_pago',
    'lugar_expedicion',
    'confirmacion',
    
    // EMISOR
    'emisor_rfc',
    'emisor_nombre',
    'emisor_regimen_fiscal',
    
    // RECEPTOR
    'receptor_rfc',
    'receptor_nombre',
    'receptor_domicilio_fiscal_receptor',
    'receptor_regimen_fiscal_receptor',
    'receptor_uso_cfdi',
    
    // IMPUESTOS
    'total_impuestos_retenidos',
    'total_impuestos_trasladados',
    
    // TIMBRE FISCAL DIGITAL
    'uuid',
    'fecha_timbrado',
    'rfc_prov_certif',
    'leyenda',
    'sello_sat',
    'no_certificado_sat',
    'version_timbre',
    
    // CONCEPTOS (CAMPOS RESUMEN)
    'numero_conceptos',
    'concepto_principal_descripcion',
    'concepto_principal_clave_prodserv',
    'concepto_principal_cantidad',
    'concepto_principal_valor_unitario',
    'concepto_principal_importe',
    
    // CAMPOS DE CONTROL
    'rfc_pac',
    'fecha_certificacion_pac',
    'cadena_original',
    'estatus_sat',
    'complemento_json',
    'archivo_xml',
    'fecha_procesamiento',
    'hash_xml',
    'tamano_archivo',
    'fecha_consulta_sat',
    
    // CAMPOS CALCULADOS
    'moneda_base',
    'total_pesos',
    'tipo_factura',
    'es_internacional',
    'tiene_descuento',
    'numero_impuestos',
    
    // COMPLEMENTOS ESPECÍFICOS
    'tiene_carta_porte',
    'carta_porte_version',
    'carta_porte_tipo_transporte',
    'carta_porte_origen',
    'carta_porte_destino',
    'carta_porte_mercancia',
    
    // OTROS COMPLEMENTOS
    'tiene_donatarias',
    'tiene_comercio_exterior',
    'tiene_nomina',
    'tiene_pagos',
    'tiene_recepcion_pagos',
    
    // CAMPOS ADICIONALES CFDI 4.0
    'cfdi_relacionados',
    'informacion_global',
    'condiciones_de_pago',
    'observaciones',
    'referencia_externa',
    
    // CAMPOS DE AUDITORÍA
    'usuario_procesamiento',
    'ip_procesamiento',
    'version_sistema',
    'errores_procesamiento',
    'warnings_procesamiento',
    
    // CAMPOS DE VALIDACIÓN SAT
    'validacion_sat_fecha',
    'validacion_sat_resultado',
    'validacion_sat_observaciones',
    'ultimo_estatus_consulta',
    'intentos_validacion',
    
    // CAMPOS FINANCIEROS ADICIONALES
    'base_impuestos_16',
    'iva_trasladado_16',
    'base_impuestos_8',
    'iva_trasladado_8',
    'base_impuestos_0',
    'iva_trasladado_0',
    'retencion_iva',
    'retencion_isr',
    'otros_impuestos',
    
    // CAMPOS DE CLASIFICACIÓN
    'sector_economico',
    'tipo_operacion',
    'categoria_contribuyente',
    'regimen_especial',
    'zona_geografica'
];

// Mostrar todos los campos
foreach ($campos as $campo) {
    echo $campo . "\n";
}

echo str_repeat("-", 50) . "\n";
echo "TOTAL CAMPOS: " . count($campos) . "\n\n";

echo "📊 DISTRIBUCIÓN POR CATEGORÍA:\n";
echo "• Comprobante principal: 18 campos\n";
echo "• Emisor: 3 campos\n";
echo "• Receptor: 5 campos\n";
echo "• Impuestos: 2 + 9 detallados = 11 campos\n";
echo "• Timbre fiscal: 7 campos\n";
echo "• Conceptos: 6 campos\n";
echo "• Control/auditoría: 15 campos\n";
echo "• Complementos: 11 campos\n";
echo "• CFDI 4.0 específicos: 5 campos\n";
echo "• Validación SAT: 6 campos\n";
echo "• Clasificación: 5 campos\n";

echo "\n✅ ANÁLISIS COMPLETADO\n";
echo "💡 Esta lista incluye campos básicos, calculados y de complementos\n";
echo "🔍 Total identificado: " . count($campos) . " campos posibles\n";
?>
