# MAPEO EXACTO XML → COLUMNAS BASE DE DATOS

## TABLA: `cfdi` (Principal)
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 | NOTAS |
|------------|--------------|--------------|-------|
| `uuid` | `//tfd:TimbreFiscalDigital/@UUID` | `//tfd:TimbreFiscalDigital/@UUID` | Mismo en ambas versiones |
| `tipo` | `/cfdi:Comprobante/@TipoDeComprobante` | `/cfdi:Comprobante/@TipoDeComprobante` | I/E/T/N/P |
| `serie` | `/cfdi:Comprobante/@Serie` | `/cfdi:Comprobante/@Serie` | Opcional |
| `folio` | `/cfdi:Comprobante/@Folio` | `/cfdi:Comprobante/@Folio` | Opcional |
| `fecha` | `/cfdi:Comprobante/@Fecha` | `/cfdi:Comprobante/@Fecha` | DateTime |
| `rfc_emisor` | `/cfdi:Comprobante/cfdi:Emisor/@Rfc` | `/cfdi:Comprobante/cfdi:Emisor/@Rfc` | RFC emisor |
| `nombre_emisor` | `/cfdi:Comprobante/cfdi:Emisor/@Nombre` | `/cfdi:Comprobante/cfdi:Emisor/@Nombre` | Razón social |
| `regimen_fiscal_emisor` | `/cfdi:Comprobante/cfdi:Emisor/@RegimenFiscal` | `/cfdi:Comprobante/cfdi:Emisor/cfdi:RegimenFiscal[1]/@Regimen` | **DIFERENTE** |
| `rfc_receptor` | `/cfdi:Comprobante/cfdi:Receptor/@Rfc` | `/cfdi:Comprobante/cfdi:Receptor/@Rfc` | RFC receptor |
| `nombre_receptor` | `/cfdi:Comprobante/cfdi:Receptor/@Nombre` | `/cfdi:Comprobante/cfdi:Receptor/@Nombre` | Razón social |
| `uso_cfdi` | `/cfdi:Comprobante/cfdi:Receptor/@UsoCFDI` | `/cfdi:Comprobante/cfdi:Receptor/@UsoCFDI` | Catálogo SAT |
| `lugar_expedicion` | `/cfdi:Comprobante/@LugarExpedicion` | `/cfdi:Comprobante/@LugarExpedicion` | CP |
| `moneda` | `/cfdi:Comprobante/@Moneda` | `/cfdi:Comprobante/@Moneda` | MXN, USD, etc |
| `tipo_cambio` | `/cfdi:Comprobante/@TipoCambio` | `/cfdi:Comprobante/@TipoCambio` | Si moneda != MXN |
| `subtotal` | `/cfdi:Comprobante/@SubTotal` | `/cfdi:Comprobante/@SubTotal` | Decimal |
| `descuento` | `/cfdi:Comprobante/@Descuento` | `/cfdi:Comprobante/@Descuento` | Opcional |
| `total` | `/cfdi:Comprobante/@Total` | `/cfdi:Comprobante/@Total` | Decimal |
| `metodo_pago` | `/cfdi:Comprobante/@MetodoPago` | `/cfdi:Comprobante/@MetodoPago` | PUE/PPD |
| `forma_pago` | `/cfdi:Comprobante/@FormaPago` | `/cfdi:Comprobante/@FormaPago` | Catálogo SAT |
| `exportacion` | **NO EXISTE** | `/cfdi:Comprobante/@Exportacion` | **SOLO 4.0** |
| `version` | `/cfdi:Comprobante/@Version` | `/cfdi:Comprobante/@Version` | 3.3 o 4.0 |

## TABLA: `cfdi_conceptos`
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 | NOTAS |
|------------|--------------|--------------|-------|
| `cfdi_id` | FK → cfdi.id | FK → cfdi.id | Relación |
| `clave_prodserv` | `//cfdi:Concepto/@ClaveProdServ` | `//cfdi:Concepto/@ClaveProdServ` | Catálogo SAT |
| `cantidad` | `//cfdi:Concepto/@Cantidad` | `//cfdi:Concepto/@Cantidad` | Decimal |
| `clave_unidad` | `//cfdi:Concepto/@ClaveUnidad` | `//cfdi:Concepto/@ClaveUnidad` | Catálogo SAT |
| `unidad` | `//cfdi:Concepto/@Unidad` | `//cfdi:Concepto/@Unidad` | Opcional |
| `descripcion` | `//cfdi:Concepto/@Descripcion` | `//cfdi:Concepto/@Descripcion` | Texto |
| `valor_unitario` | `//cfdi:Concepto/@ValorUnitario` | `//cfdi:Concepto/@ValorUnitario` | Decimal |
| `importe` | `//cfdi:Concepto/@Importe` | `//cfdi:Concepto/@Importe` | Decimal |
| `descuento` | `//cfdi:Concepto/@Descuento` | `//cfdi:Concepto/@Descuento` | Opcional |
| `objeto_imp` | **NO EXISTE** | `//cfdi:Concepto/@ObjetoImp` | **SOLO 4.0 OBLIGATORIO** |
| `cuenta_predial` | `//cfdi:Concepto/cfdi:CuentaPredial/@Numero` | `//cfdi:Concepto/cfdi:CuentaPredial/@Numero` | Opcional |

## TABLA: `cfdi_impuestos`
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 | NOTAS |
|------------|--------------|--------------|-------|
| `cfdi_id` | FK → cfdi.id | FK → cfdi.id | Relación |
| `concepto_id` | FK → cfdi_conceptos.id | FK → cfdi_conceptos.id | Opcional (global si NULL) |
| `tipo` | `Traslado` o `Retencion` | `Traslado` o `Retencion` | Según nodo |
| `impuesto` | `//cfdi:Traslado/@Impuesto` | `//cfdi:Traslado/@Impuesto` | 002=IVA, 001=ISR |
| `tipo_factor` | `//cfdi:Traslado/@TipoFactor` | `//cfdi:Traslado/@TipoFactor` | Tasa/Cuota/Exento |
| `tasa_cuota` | `//cfdi:Traslado/@TasaOCuota` | `//cfdi:Traslado/@TasaOCuota` | Decimal |
| `base` | **OPCIONAL** | `//cfdi:Traslado/@Base` | **OBLIGATORIO EN 4.0** |
| `importe` | `//cfdi:Traslado/@Importe` | `//cfdi:Traslado/@Importe` | Decimal |

## TABLA: `cfdi_timbre_fiscal`
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 | NOTAS |
|------------|--------------|--------------|-------|
| `cfdi_id` | FK → cfdi.id | FK → cfdi.id | Relación |
| `uuid` | `//tfd:TimbreFiscalDigital/@UUID` | `//tfd:TimbreFiscalDigital/@UUID` | Mismo |
| `fecha_timbrado` | `//tfd:TimbreFiscalDigital/@FechaTimbrado` | `//tfd:TimbreFiscalDigital/@FechaTimbrado` | DateTime |
| `sello_cfd` | `//tfd:TimbreFiscalDigital/@SelloCFD` | `//tfd:TimbreFiscalDigital/@SelloCFD` | Base64 |
| `sello_sat` | `//tfd:TimbreFiscalDigital/@SelloSAT` | `//tfd:TimbreFiscalDigital/@SelloSAT` | Base64 |
| `no_certificado_sat` | `//tfd:TimbreFiscalDigital/@NoCertificadoSAT` | `//tfd:TimbreFiscalDigital/@NoCertificadoSAT` | Número |
| `rfc_prov_certif` | `//tfd:TimbreFiscalDigital/@RfcProvCertif` | `//tfd:TimbreFiscalDigital/@RfcProvCertif` | RFC PAC |

## TABLA: `cfdi_pagos` (Solo tipo P)
| COLUMNA BD | XML CFDI 3.3 (Pago 1.0) | XML CFDI 4.0 (Pago 2.0) | NOTAS |
|------------|--------------------------|--------------------------|-------|
| `cfdi_id` | FK → cfdi.id | FK → cfdi.id | Relación |
| `version` | `1.0` | `2.0` | Versión complemento |
| `fecha_pago` | `//pago10:Pago/@FechaPago` | `//pago20:Pago/@FechaPago` | **NAMESPACE DIFERENTE** |
| `forma_pago` | `//pago10:Pago/@FormaDePagoP` | `//pago20:Pago/@FormaDePagoP` | Catálogo SAT |
| `moneda` | `//pago10:Pago/@MonedaP` | `//pago20:Pago/@MonedaP` | MXN, USD, etc |
| `tipo_cambio` | `//pago10:Pago/@TipoCambioP` | `//pago20:Pago/@TipoCambioP` | Si moneda != MXN |
| `monto` | `//pago10:Pago/@Monto` | `//pago20:Pago/@Monto` | Decimal |
| `num_operacion` | `//pago10:Pago/@NumOperacion` | `//pago20:Pago/@NumOperacion` | Opcional |
| `cuenta_ordenante` | `//pago10:Pago/@CtaOrdenante` | `//pago20:Pago/@CtaOrdenante` | Opcional |
| `cuenta_beneficiario` | `//pago10:Pago/@CtaBeneficiario` | `//pago20:Pago/@CtaBeneficiario` | Opcional |

## TABLA: `cfdi_pago_documentos_relacionados` (Solo tipo P)
| COLUMNA BD | XML CFDI 3.3 (Pago 1.0) | XML CFDI 4.0 (Pago 2.0) | NOTAS |
|------------|--------------------------|--------------------------|-------|
| `pago_id` | FK → cfdi_pagos.id | FK → cfdi_pagos.id | Relación |
| `uuid_documento` | `//pago10:DoctoRelacionado/@IdDocumento` | `//pago20:DoctoRelacionado/@IdDocumento` | UUID factura |
| `serie` | `//pago10:DoctoRelacionado/@Serie` | `//pago20:DoctoRelacionado/@Serie` | Opcional |
| `folio` | `//pago10:DoctoRelacionado/@Folio` | `//pago20:DoctoRelacionado/@Folio` | Opcional |
| `moneda_dr` | `//pago10:DoctoRelacionado/@MonedaDR` | `//pago20:DoctoRelacionado/@MonedaDR` | Moneda documento |
| `num_parcialidad` | `//pago10:DoctoRelacionado/@NumParcialidad` | `//pago20:DoctoRelacionado/@NumParcialidad` | Número pago |
| `imp_saldo_ant` | `//pago10:DoctoRelacionado/@ImpSaldoAnt` | `//pago20:DoctoRelacionado/@ImpSaldoAnt` | Saldo anterior |
| `imp_pagado` | `//pago10:DoctoRelacionado/@ImpPagado` | `//pago20:DoctoRelacionado/@ImpPagado` | Importe pagado |
| `imp_saldo_insoluto` | `//pago10:DoctoRelacionado/@ImpSaldoInsoluto` | `//pago20:DoctoRelacionado/@ImpSaldoInsoluto` | Saldo restante |
| `objeto_imp_dr` | **NO EXISTE** | `//pago20:DoctoRelacionado/@ObjetoImpDR` | **SOLO 4.0** |

## TABLA: `cfdi_pago_impuestos_dr` (Solo CFDI 4.0 Pago 2.0)
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 (Pago 2.0) | NOTAS |
|------------|--------------|--------------------------|-------|
| `documento_relacionado_id` | **NO EXISTE** | FK → cfdi_pago_documentos_relacionados.id | **SOLO 4.0** |
| `base_dr` | **NO EXISTE** | `//pago20:ImpuestosDR/pago20:TrasladoDR/@BaseDR` | Base gravable |
| `impuesto_dr` | **NO EXISTE** | `//pago20:ImpuestosDR/pago20:TrasladoDR/@ImpuestoDR` | 002=IVA |
| `tipo_factor_dr` | **NO EXISTE** | `//pago20:ImpuestosDR/pago20:TrasladoDR/@TipoFactorDR` | Tasa/Cuota |
| `tasa_o_cuota_dr` | **NO EXISTE** | `//pago20:ImpuestosDR/pago20:TrasladoDR/@TasaOCuotaDR` | Decimal |
| `importe_dr` | **NO EXISTE** | `//pago20:ImpuestosDR/pago20:TrasladoDR/@ImporteDR` | Importe impuesto |

## TABLA: `cfdi_pago_totales` (Solo CFDI 4.0 Pago 2.0)
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 (Pago 2.0) | NOTAS |
|------------|--------------|--------------------------|-------|
| `pago_id` | **NO EXISTE** | FK → cfdi_pagos.id | **SOLO 4.0** |
| `total_retenciones_iva` | **NO EXISTE** | `//pago20:Totales/@TotalRetencionesIVA` | Total retenciones |
| `total_retenciones_isr` | **NO EXISTE** | `//pago20:Totales/@TotalRetencionesISR` | Total retenciones |
| `total_traslados_base_iva16` | **NO EXISTE** | `//pago20:Totales/@TotalTrasladosBaseIVA16` | Base IVA 16% |
| `total_traslados_impuesto_iva16` | **NO EXISTE** | `//pago20:Totales/@TotalTrasladosImpuestoIVA16` | IVA 16% |
| `monto_total_pagos` | **NO EXISTE** | `//pago20:Totales/@MontoTotalPagos` | Total general |

## TABLA: `cfdi_complementos` (Genérica)
| COLUMNA BD | XML CFDI 3.3 | XML CFDI 4.0 | NOTAS |
|------------|--------------|--------------|-------|
| `cfdi_id` | FK → cfdi.id | FK → cfdi.id | Relación |
| `tipo` | `Pagos`, `Nomina`, etc | `Pagos20`, `Nomina12`, etc | Tipo detectado |
| `datos_json` | JSON completo complemento | JSON completo complemento | Respaldo completo |
