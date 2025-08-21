-- Script para agregar columnas faltantes a las tablas CFDI
-- Fecha: 19 de Agosto 2025
USE sac_db;

-- Agregar columnas faltantes a la tabla cfdi
ALTER TABLE cfdi
ADD COLUMN IF NOT EXISTS observaciones TEXT AFTER exportacion;

-- Agregar columna faltante a cfdi_conceptos
ALTER TABLE cfdi_conceptos
ADD COLUMN IF NOT EXISTS no_identificacion VARCHAR(100) AFTER clave_prodserv;

-- Verificar que las columnas se agregaron correctamente
SELECT
    'Verificando tabla cfdi...' as mensaje;

DESCRIBE cfdi;

SELECT
    'Verificando tabla cfdi_conceptos...' as mensaje;

DESCRIBE cfdi_conceptos;

SELECT
    'Verificando tabla cfdi_impuestos...' as mensaje;

DESCRIBE cfdi_impuestos;

SELECT
    'Verificando tabla cfdi_pagos...' as mensaje;

DESCRIBE cfdi_pagos;

SELECT
    'Verificando tabla cfdi_pago_documentos_relacionados...' as mensaje;

DESCRIBE cfdi_pago_documentos_relacionados;

SELECT
    'Columnas agregadas exitosamente!' as resultado;