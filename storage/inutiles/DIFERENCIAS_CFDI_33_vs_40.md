DIFERENCIAS ENTRE CFDI 3.3 Y CFDI 4.0
=====================================

ESTRUCTURA GENERAL:
==================

| Aspecto                  | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| Namespace Principal      | http://www.sat.gob.mx/cfd/3        | http://www.sat.gob.mx/cfd/4        |
| Versión Atributo         | Version="3.3"                      | Version="4.0"                      |
| Fecha Vigencia           | 2017 - 2021                       | 2022 - Actual                     |

CAMPOS NUEVOS EN CFDI 4.0:
==========================

| Campo                    | CFDI 3.3      | CFDI 4.0                          | Descripción                        |
|--------------------------|---------------|-----------------------------------|------------------------------------|
| Exportacion              | ❌ No existe  | ✅ Obligatorio                    | Indica si es exportación           |
| ObjetoImp (Conceptos)    | ❌ No existe  | ✅ Obligatorio                    | Objeto del impuesto en conceptos   |
| ObjetoImpDR (Pagos)      | ❌ No existe  | ✅ Condicional                    | Objeto impuesto doc. relacionados  |

COMPLEMENTOS DE PAGO:
====================

| Aspecto                  | CFDI 3.3 (pago10)                 | CFDI 4.0 (pago20)                 |
|--------------------------|------------------------------------|------------------------------------|
| Namespace                | http://www.sat.gob.mx/Pagos        | http://www.sat.gob.mx/Pagos20      |
| Versión                  | 1.0                                | 2.0                                |
| Prefix                   | pago10:                            | pago20:                            |
| Campos Adicionales       | Básicos                            | + ObjetoImpDR, TipoCadPago         |

IMPUESTOS:
==========

| Campo                    | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| ObjetoImp                | ❌ No requerido                    | ✅ Obligatorio (01,02,03)          |
| Validaciones             | Menos estrictas                    | Más validaciones                   |
| Catálogos                | Versiones anteriores               | Catálogos actualizados             |

EMISOR/RECEPTOR:
===============

| Campo                    | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| RegimenFiscal            | Código de 3 dígitos                | Código de 3 dígitos (actualizado) |
| UsoCFDI                  | Códigos específicos                | Códigos actualizados               |
| Validación RFC           | Básica                             | Más estricta                       |

VALIDACIONES ADICIONALES CFDI 4.0:
==================================

| Validación               | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| Monedas                  | Permite más variaciones            | Lista restringida                  |
| Exportación              | No validada                        | Obligatoria para exportaciones    |
| Objeto Impuesto          | No requerido                       | Obligatorio y validado             |
| Lugar Expedición         | CP básico                          | Validación estricta CP             |

NAMESPACES COMUNES:
==================

| Complemento              | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| TimbreFiscalDigital      | tfd (igual)                        | tfd (igual)                        |
| Pagos                    | pago10                             | pago20                             |
| Nomina                   | nomina12                           | nomina12 (igual)                   |
| CartaPorte               | ❌ No disponible                   | cartaporte20                       |

CAMPOS TABLA cfdi:
=================

| Campo                    | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| version                  | "3.3"                              | "4.0"                              |
| exportacion              | NULL                               | "01", "02", "03"                   |
| uso_cfdi                 | Catálogo v3.3                      | Catálogo v4.0                      |
| regimen_fiscal_emisor    | Catálogo v3.3                      | Catálogo v4.0                      |

CAMPOS TABLA cfdi_conceptos:
===========================

| Campo                    | CFDI 3.3                           | CFDI 4.0                           |
|--------------------------|------------------------------------|------------------------------------|
| objeto_imp               | NULL                               | "01", "02", "03" (Obligatorio)     |
| clave_prodserv           | Validación básica                  | Validación estricta                |
| clave_unidad             | Validación básica                  | Validación estricta                |

RESUMEN IMPLEMENTACIÓN:
======================

Para manejar ambas versiones en el importador:

1. **Detectar versión**: Leer atributo Version
2. **Condicionar campos**: if (version >= 4.0) { agregar campos 4.0 }
3. **Namespaces dinámicos**: Detectar pago10 vs pago20
4. **Validaciones**: Aplicar según versión
5. **Defaults**: NULL para campos no existentes en 3.3
