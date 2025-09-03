<?php
/**
 * MAPEO COMPLETO XML A BASE DE DATOS - CFDI 3.3 Y 4.0
 * ================================================================
 * Análisis basado en documentación oficial y estructura de BD
 * Fecha: 2025-01-26
 */

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Mapeo XML a BD - CFDI 3.3/4.0</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.tabla-principal { border-collapse: collapse; width: 100%; margin: 20px 0; }
.tabla-principal th, .tabla-principal td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.tabla-principal th { background-color: #f2f2f2; font-weight: bold; }
.grupo-cfdi { background-color: #e3f2fd; }
.grupo-conceptos { background-color: #f3e5f5; }
.grupo-impuestos { background-color: #e8f5e8; }
.grupo-timbre { background-color: #fff3e0; }
.grupo-pagos { background-color: #fce4ec; }
.grupo-complementos { background-color: #f1f8e9; }
.grupo-catalogos { background-color: #ede7f6; }
.version-33 { color: #1976d2; }
.version-40 { color: #388e3c; }
.diferencia { background-color: #ffecb3; }
</style></head><body>";

echo "<h1>MAPEO COMPLETO XML A BASE DE DATOS - CFDI 3.3 Y 4.0</h1>";
echo "<p><strong>Total de tablas analizadas:</strong> 24 tablas</p>";
echo "<p><strong>Sistema SAC Multi-RFC:</strong> Soporte para BFM170822P38 y BLM1706026AA</p>";

// ================================================================
// TABLA PRINCIPAL DE MAPEO
// ================================================================
echo "<h2>TABLA DE MAPEO XML → BASE DE DATOS</h2>";
echo "<table class='tabla-principal'>";
echo "<tr>
    <th>CONCEPTO XML</th>
    <th>XPATH CFDI 3.3</th>
    <th>XPATH CFDI 4.0</th>
    <th>TABLA DESTINO</th>
    <th>CAMPO DESTINO</th>
    <th>TIPO DATO</th>
    <th>NOTAS/DIFERENCIAS</th>
</tr>";

// ================================================================
// COMPROBANTE PRINCIPAL (cfdi)
// ================================================================
$mapeo_cfdi = [
    ['UUID', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@UUID', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@UUID', 'cfdi', 'uuid', 'varchar(255)', 'Identificador único'],
    ['Tipo', '/cfdi:Comprobante/@TipoDeComprobante', '/cfdi:Comprobante/@TipoDeComprobante', 'cfdi', 'tipo', 'varchar(50)', 'I=Ingreso, E=Egreso, T=Traslado, N=Nómina, P=Pago'],
    ['Serie', '/cfdi:Comprobante/@Serie', '/cfdi:Comprobante/@Serie', 'cfdi', 'serie', 'varchar(50)', 'Serie del comprobante'],
    ['Folio', '/cfdi:Comprobante/@Folio', '/cfdi:Comprobante/@Folio', 'cfdi', 'folio', 'varchar(50)', 'Folio del comprobante'],
    ['Fecha', '/cfdi:Comprobante/@Fecha', '/cfdi:Comprobante/@Fecha', 'cfdi', 'fecha', 'datetime', 'Fecha de emisión'],
    ['RFC Emisor', '/cfdi:Comprobante/cfdi:Emisor/@Rfc', '/cfdi:Comprobante/cfdi:Emisor/@Rfc', 'cfdi', 'rfc_emisor', 'varchar(13)', 'RFC del emisor'],
    ['Nombre Emisor', '/cfdi:Comprobante/cfdi:Emisor/@Nombre', '/cfdi:Comprobante/cfdi:Emisor/@Nombre', 'cfdi', 'nombre_emisor', 'varchar(500)', 'Razón social emisor'],
    ['Régimen Fiscal Emisor', '/cfdi:Comprobante/cfdi:Emisor/@RegimenFiscal', '/cfdi:Comprobante/cfdi:Emisor/cfdi:RegimenFiscal/@Regimen', 'cfdi', 'regimen_fiscal_emisor', 'varchar(10)', 'CFDI 4.0: Múltiples regímenes'],
    ['RFC Receptor', '/cfdi:Comprobante/cfdi:Receptor/@Rfc', '/cfdi:Comprobante/cfdi:Receptor/@Rfc', 'cfdi', 'rfc_receptor', 'varchar(13)', 'RFC del receptor'],
    ['Nombre Receptor', '/cfdi:Comprobante/cfdi:Receptor/@Nombre', '/cfdi:Comprobante/cfdi:Receptor/@Nombre', 'cfdi', 'nombre_receptor', 'varchar(500)', 'Razón social receptor'],
    ['Uso CFDI', '/cfdi:Comprobante/cfdi:Receptor/@UsoCFDI', '/cfdi:Comprobante/cfdi:Receptor/@UsoCFDI', 'cfdi', 'uso_cfdi', 'varchar(10)', 'Catálogo de usos'],
    ['Lugar Expedición', '/cfdi:Comprobante/@LugarExpedicion', '/cfdi:Comprobante/@LugarExpedicion', 'cfdi', 'lugar_expedicion', 'varchar(10)', 'Código postal'],
    ['Moneda', '/cfdi:Comprobante/@Moneda', '/cfdi:Comprobante/@Moneda', 'cfdi', 'moneda', 'varchar(10)', 'Clave de moneda'],
    ['Tipo Cambio', '/cfdi:Comprobante/@TipoCambio', '/cfdi:Comprobante/@TipoCambio', 'cfdi', 'tipo_cambio', 'decimal(10,6)', 'Solo si moneda != MXN'],
    ['Subtotal', '/cfdi:Comprobante/@SubTotal', '/cfdi:Comprobante/@SubTotal', 'cfdi', 'subtotal', 'decimal(15,2)', 'Suma antes de impuestos'],
    ['Descuento', '/cfdi:Comprobante/@Descuento', '/cfdi:Comprobante/@Descuento', 'cfdi', 'descuento', 'decimal(15,2)', 'Descuentos aplicados'],
    ['Total', '/cfdi:Comprobante/@Total', '/cfdi:Comprobante/@Total', 'cfdi', 'total', 'decimal(15,2)', 'Total del comprobante'],
    ['Método Pago', '/cfdi:Comprobante/@MetodoPago', '/cfdi:Comprobante/@MetodoPago', 'cfdi', 'metodo_pago', 'varchar(10)', 'PPD/PUE'],
    ['Forma Pago', '/cfdi:Comprobante/@FormaPago', '/cfdi:Comprobante/@FormaPago', 'cfdi', 'forma_pago', 'varchar(10)', 'Catálogo forma de pago'],
    ['Exportación', '', '/cfdi:Comprobante/@Exportacion', 'cfdi', 'exportacion', 'varchar(10)', 'NUEVO EN CFDI 4.0'],
    ['Versión', '/cfdi:Comprobante/@Version', '/cfdi:Comprobante/@Version', 'cfdi', 'version', 'varchar(10)', '3.3 o 4.0'],
    ['No. Certificado', '/cfdi:Comprobante/@NoCertificado', '/cfdi:Comprobante/@NoCertificado', 'cfdi', 'no_certificado', 'varchar(50)', 'Número de certificado'],
    ['Certificado', '/cfdi:Comprobante/@Certificado', '/cfdi:Comprobante/@Certificado', 'cfdi', 'certificado', 'text', 'Certificado digital'],
    ['Sello CFD', '/cfdi:Comprobante/@Sello', '/cfdi:Comprobante/@Sello', 'cfdi', 'sello_cfd', 'text', 'Sello digital del comprobante'],
    ['Condiciones Pago', '/cfdi:Comprobante/@CondicionesDePago', '/cfdi:Comprobante/@CondicionesDePago', 'cfdi', 'condiciones_de_pago', 'text', 'Condiciones de pago']
];

foreach ($mapeo_cfdi as $item) {
    echo "<tr class='grupo-cfdi'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

// ================================================================
// CONCEPTOS (cfdi_conceptos)
// ================================================================
$mapeo_conceptos = [
    ['Clave Producto/Servicio', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ClaveProdServ', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ClaveProdServ', 'cfdi_conceptos', 'clave_prodserv', 'varchar(8)', 'Catálogo SAT'],
    ['No. Identificación', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@NoIdentificacion', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@NoIdentificacion', 'cfdi_conceptos', 'no_identificacion', 'varchar(100)', 'Número de parte'],
    ['Cantidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Cantidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Cantidad', 'cfdi_conceptos', 'cantidad', 'decimal(18,6)', 'Cantidad del producto'],
    ['Clave Unidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ClaveUnidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ClaveUnidad', 'cfdi_conceptos', 'clave_unidad', 'varchar(3)', 'Catálogo unidades SAT'],
    ['Unidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Unidad', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Unidad', 'cfdi_conceptos', 'unidad', 'varchar(50)', 'Descripción unidad'],
    ['Descripción', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Descripcion', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Descripcion', 'cfdi_conceptos', 'descripcion', 'varchar(255)', 'Descripción del concepto'],
    ['Valor Unitario', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ValorUnitario', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ValorUnitario', 'cfdi_conceptos', 'valor_unitario', 'decimal(18,6)', 'Precio unitario'],
    ['Importe', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Importe', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Importe', 'cfdi_conceptos', 'importe', 'decimal(18,2)', 'Importe del concepto'],
    ['Descuento', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Descuento', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@Descuento', 'cfdi_conceptos', 'descuento', 'decimal(18,2)', 'Descuento aplicado'],
    ['Objeto Impuesto', '', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/@ObjetoImp', 'cfdi_conceptos', 'objeto_imp', 'varchar(2)', 'NUEVO EN CFDI 4.0: 01/02/03'],
    ['Cuenta Predial', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:CuentaPredial/@Numero', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:CuentaPredial/@Numero', 'cfdi_conceptos', 'cuenta_predial', 'varchar(20)', 'Número de cuenta predial']
];

foreach ($mapeo_conceptos as $item) {
    echo "<tr class='grupo-conceptos'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

// ================================================================
// IMPUESTOS (cfdi_impuestos)
// ================================================================
$mapeo_impuestos = [
    ['Tipo Impuesto', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado', 'cfdi_impuestos', 'tipo', 'varchar(10)', 'Traslado o Retención'],
    ['Impuesto', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@Impuesto', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@Impuesto', 'cfdi_impuestos', 'impuesto', 'varchar(3)', 'IVA=002, IVA=001, IEPS=003'],
    ['Tipo Factor', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@TipoFactor', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@TipoFactor', 'cfdi_impuestos', 'tipo_factor', 'varchar(10)', 'Tasa, Cuota, Exento'],
    ['Tasa o Cuota', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@TasaOCuota', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@TasaOCuota', 'cfdi_impuestos', 'tasa_cuota', 'decimal(18,6)', 'Valor del impuesto'],
    ['Base', '', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@Base', 'cfdi_impuestos', 'base', 'decimal(18,2)', 'NUEVO EN CFDI 4.0'],
    ['Importe', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@Importe', '/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado/@Importe', 'cfdi_impuestos', 'importe', 'decimal(18,2)', 'Monto del impuesto']
];

foreach ($mapeo_impuestos as $item) {
    echo "<tr class='grupo-impuestos'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

// ================================================================
// TIMBRE FISCAL DIGITAL (cfdi_timbre_fiscal)
// ================================================================
$mapeo_timbre = [
    ['UUID Timbre', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@UUID', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@UUID', 'cfdi_timbre_fiscal', 'uuid', 'varchar(255)', 'UUID del timbre'],
    ['Fecha Timbrado', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@FechaTimbrado', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@FechaTimbrado', 'cfdi_timbre_fiscal', 'fecha_timbrado', 'datetime', 'Fecha y hora del timbrado'],
    ['Sello CFD Timbre', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@SelloCFD', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@SelloCFD', 'cfdi_timbre_fiscal', 'sello_cfd', 'text', 'Sello del comprobante'],
    ['Sello SAT', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@SelloSAT', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@SelloSAT', 'cfdi_timbre_fiscal', 'sello_sat', 'text', 'Sello del SAT'],
    ['No. Certificado SAT', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@NoCertificadoSAT', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@NoCertificadoSAT', 'cfdi_timbre_fiscal', 'no_certificado_sat', 'varchar(50)', 'Certificado SAT'],
    ['RFC Proveedor Certificación', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@RfcProvCertif', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@RfcProvCertif', 'cfdi_timbre_fiscal', 'rfc_prov_certif', 'varchar(13)', 'RFC del PAC'],
    ['Versión Timbre', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@Version', '/cfdi:Comprobante/cfdi:Complemento/tfd:TimbreFiscalDigital/@Version', 'cfdi_timbre_fiscal', 'version', 'varchar(10)', 'Versión del timbre']
];

foreach ($mapeo_timbre as $item) {
    echo "<tr class='grupo-timbre'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

// ================================================================
// COMPLEMENTO DE PAGOS (cfdi_pagos)
// ================================================================
$mapeo_pagos = [
    ['Versión Pagos', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/@Version', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/@Version', 'cfdi_pagos', 'version', 'varchar(5)', 'Versión complemento'],
    ['Fecha Pago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@FechaPago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@FechaPago', 'cfdi_pagos', 'fecha_pago', 'datetime', 'Fecha del pago'],
    ['Forma Pago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@FormaDePagoP', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@FormaDePagoP', 'cfdi_pagos', 'forma_pago', 'varchar(2)', 'Catálogo forma pago'],
    ['Moneda Pago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@MonedaP', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@MonedaP', 'cfdi_pagos', 'moneda', 'varchar(3)', 'Moneda del pago'],
    ['Tipo Cambio Pago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@TipoCambioP', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@TipoCambioP', 'cfdi_pagos', 'tipo_cambio', 'decimal(18,6)', 'Tipo de cambio'],
    ['Monto Pago', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@Monto', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@Monto', 'cfdi_pagos', 'monto', 'decimal(18,2)', 'Monto del pago'],
    ['Número Operación', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@NumOperacion', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@NumOperacion', 'cfdi_pagos', 'num_operacion', 'varchar(100)', 'Número de operación'],
    ['RFC Emisor Cuenta Ordenante', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@RfcEmisorCtaOrd', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@RfcEmisorCtaOrd', 'cfdi_pagos', 'rfc_emisor_cuenta_ordenante', 'varchar(13)', 'RFC cuenta ordenante'],
    ['Cuenta Ordenante', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@CtaOrdenante', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@CtaOrdenante', 'cfdi_pagos', 'cuenta_ordenante', 'varchar(50)', 'Cuenta ordenante'],
    ['RFC Emisor Cuenta Beneficiario', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@RfcEmisorCtaBen', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@RfcEmisorCtaBen', 'cfdi_pagos', 'rfc_emisor_cuenta_beneficiario', 'varchar(13)', 'RFC cuenta beneficiario'],
    ['Cuenta Beneficiario', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@CtaBeneficiario', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/@CtaBeneficiario', 'cfdi_pagos', 'cuenta_beneficiario', 'varchar(50)', 'Cuenta beneficiario']
];

foreach ($mapeo_pagos as $item) {
    echo "<tr class='grupo-pagos'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

// ================================================================
// DOCUMENTOS RELACIONADOS DE PAGOS
// ================================================================
$mapeo_doc_relacionados = [
    ['UUID Documento', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@IdDocumento', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@IdDocumento', 'cfdi_pago_documentos_relacionados', 'uuid_documento', 'varchar(36)', 'UUID del documento'],
    ['Serie Documento', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@Serie', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@Serie', 'cfdi_pago_documentos_relacionados', 'serie', 'varchar(25)', 'Serie del documento'],
    ['Folio Documento', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@Folio', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@Folio', 'cfdi_pago_documentos_relacionados', 'folio', 'varchar(40)', 'Folio del documento'],
    ['Moneda DR', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@MonedaDR', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@MonedaDR', 'cfdi_pago_documentos_relacionados', 'moneda_dr', 'varchar(3)', 'Moneda documento'],
    ['Número Parcialidad', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@NumParcialidad', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@NumParcialidad', 'cfdi_pago_documentos_relacionados', 'num_parcialidad', 'int(11)', 'Número de parcialidad'],
    ['Importe Saldo Anterior', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpSaldoAnt', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpSaldoAnt', 'cfdi_pago_documentos_relacionados', 'imp_saldo_ant', 'decimal(18,2)', 'Saldo anterior'],
    ['Importe Pagado', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpPagado', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpPagado', 'cfdi_pago_documentos_relacionados', 'imp_pagado', 'decimal(18,2)', 'Importe pagado'],
    ['Importe Saldo Insoluto', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpSaldoInsoluto', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ImpSaldoInsoluto', 'cfdi_pago_documentos_relacionados', 'imp_saldo_insoluto', 'decimal(18,2)', 'Saldo pendiente'],
    ['Objeto Impuesto DR', '', '/cfdi:Comprobante/cfdi:Complemento/pago20:Pagos/pago20:Pago/pago20:DoctoRelacionado/@ObjetoImpDR', 'cfdi_pago_documentos_relacionados', 'objeto_imp_dr', 'varchar(2)', 'NUEVO EN CFDI 4.0']
];

foreach ($mapeo_doc_relacionados as $item) {
    echo "<tr class='grupo-pagos'>";
    echo "<td><strong>{$item[0]}</strong></td>";
    echo "<td class='version-33'>{$item[1]}</td>";
    echo "<td class='version-40'>{$item[2]}</td>";
    echo "<td>{$item[3]}</td>";
    echo "<td>{$item[4]}</td>";
    echo "<td>{$item[5]}</td>";
    echo "<td>{$item[6]}</td>";
    echo "</tr>";
}

echo "</table>";

// ================================================================
// DIFERENCIAS PRINCIPALES ENTRE CFDI 3.3 Y 4.0
// ================================================================
echo "<h2>PRINCIPALES DIFERENCIAS CFDI 3.3 vs 4.0</h2>";
echo "<table class='tabla-principal'>";
echo "<tr><th>ASPECTO</th><th>CFDI 3.3</th><th>CFDI 4.0</th><th>IMPACTO EN BD</th></tr>";

$diferencias = [
    ['Régimen Fiscal Emisor', 'Un solo régimen en atributo', 'Múltiples regímenes en nodo hijo', 'Ajustar parsing del XML'],
    ['Objeto de Impuesto', 'No existe', 'Obligatorio: 01/02/03', 'Nuevo campo objeto_imp'],
    ['Base Impuesto', 'No obligatorio', 'Obligatorio en conceptos', 'Nuevo campo base'],
    ['Exportación', 'No existe', 'Obligatorio', 'Nuevo campo exportacion'],
    ['CFDI Relacionados', 'Estructura simple', 'Estructura más compleja', 'Ajustar parsing'],
    ['Complemento Pagos', 'Versión 1.0', 'Versión 2.0', 'Cambios en estructura de pagos']
];

foreach ($diferencias as $diff) {
    echo "<tr class='diferencia'>";
    echo "<td><strong>{$diff[0]}</strong></td>";
    echo "<td>{$diff[1]}</td>";
    echo "<td>{$diff[2]}</td>";
    echo "<td>{$diff[3]}</td>";
    echo "</tr>";
}

echo "</table>";

// ================================================================
// RESUMEN DE TABLAS CON REGISTROS
// ================================================================
echo "<h2>RESUMEN DE ESTADO DE TABLAS</h2>";
echo "<table class='tabla-principal'>";
echo "<tr><th>TABLA</th><th>REGISTROS</th><th>ESTADO</th><th>OBSERVACIONES</th></tr>";

$estado_tablas = [
    ['cfdi', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_conceptos', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_impuestos', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_timbre_fiscal', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_pagos', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_pago_documentos_relacionados', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_pago_impuestos_dr', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_pago_totales', '0', 'Vacía', 'Lista para importación'],
    ['cfdi_complementos', '0', 'Vacía', 'Lista para importación'],
    ['sat_download_history', '4', 'Con datos', 'Histórico de descargas'],
    ['sat_fiel_certificates', '2', 'Con datos', 'Certificados FIEL activos'],
    ['sat_tokens', '2', 'Con datos', 'Tokens de autenticación'],
    ['activity_logs', '93', 'Con datos', 'Log de actividades'],
    ['usuarios', '2', 'Con datos', 'Usuarios del sistema']
];

foreach ($estado_tablas as $tabla) {
    $clase = $tabla[1] == '0' ? 'grupo-cfdi' : 'grupo-catalogos';
    echo "<tr class='{$clase}'>";
    echo "<td><strong>{$tabla[0]}</strong></td>";
    echo "<td>{$tabla[1]}</td>";
    echo "<td>{$tabla[2]}</td>";
    echo "<td>{$tabla[3]}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>CONCLUSIONES Y RECOMENDACIONES</h2>";
echo "<div style='background-color: #f5f5f5; padding: 15px; border-left: 4px solid #2196F3;'>";
echo "<h3>📊 Análisis Completado</h3>";
echo "<ul>";
echo "<li><strong>24 tablas analizadas</strong> en la base de datos sac_db</li>";
echo "<li><strong>Mapeo completo</strong> de XML a campos de base de datos</li>";
echo "<li><strong>Soporte dual</strong> para CFDI 3.3 y 4.0</li>";
echo "<li><strong>Sistema limpio</strong> listo para importación masiva</li>";
echo "</ul>";

echo "<h3>⚠️ Consideraciones Importantes</h3>";
echo "<ul>";
echo "<li><strong>Diferencias de versión:</strong> El importador debe detectar versión CFDI automáticamente</li>";
echo "<li><strong>Campos nuevos 4.0:</strong> Objeto impuesto, Base, Exportación son obligatorios</li>";
echo "<li><strong>Régimen fiscal:</strong> Manejo diferente entre versiones</li>";
echo "<li><strong>Complemento pagos:</strong> Versión 2.0 en CFDI 4.0</li>";
echo "</ul>";

echo "<h3>📁 Archivos Disponibles</h3>";
echo "<ul>";
echo "<li><strong>31,573 archivos XML</strong> en storage/sat_downloads/</li>";
echo "<li><strong>Certificados FIEL:</strong> BFM170822P38, BLM1706026AA</li>";
echo "<li><strong>Documentación:</strong> CFDI_33_vs_40_COMPLETO.pdf, CSV de diferencias</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>
