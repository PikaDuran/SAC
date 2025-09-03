-- ========================================================================
-- PASO 2: SCRIPTS SQL COMPLETOS PARA BASE DE DATOS SAC_DB
-- ========================================================================
-- Archivo: limpiar_bd_sac.sql
-- Propósito: Eliminar todas las tablas existentes para empezar desde cero
-- Base de datos: sac_db
-- ========================================================================
USE sac_db;

-- Deshabilitar verificación de claves foráneas temporalmente
SET
    FOREIGN_KEY_CHECKS = 0;

-- ========================================================================
-- ELIMINAR TABLAS DE COMPLEMENTOS (en orden inverso por dependencias)
-- ========================================================================
DROP TABLE IF EXISTS cfdi_otros_complementos;

DROP TABLE IF EXISTS cfdi_complemento_carta_porte;

DROP TABLE IF EXISTS cfdi_complemento_impuestos_locales;

DROP TABLE IF EXISTS cfdi_complemento_nomina;

DROP TABLE IF EXISTS cfdi_pagos_documentos_relacionados;

DROP TABLE IF EXISTS cfdi_complemento_pagos_v20;

DROP TABLE IF EXISTS cfdi_complemento_pagos_v10;

DROP TABLE IF EXISTS cfdi_timbre_fiscal_digital;

-- ========================================================================
-- ELIMINAR TABLAS DE RELACIÓN
-- ========================================================================
DROP TABLE IF EXISTS cfdi_relacionados;

DROP TABLE IF EXISTS addenda;

DROP TABLE IF EXISTS impuestos_retenidos;

DROP TABLE IF EXISTS impuestos_trasladados;

DROP TABLE IF EXISTS conceptos;

DROP TABLE IF EXISTS receptor;

DROP TABLE IF EXISTS emisor;

-- ========================================================================
-- ELIMINAR TABLA PRINCIPAL
-- ========================================================================
DROP TABLE IF EXISTS cfdi;

-- ========================================================================
-- ELIMINAR TABLAS DE CATÁLOGOS (si existen)
-- ========================================================================
DROP TABLE IF EXISTS cat_formas_pago;

DROP TABLE IF EXISTS cat_metodos_pago;

DROP TABLE IF EXISTS cat_monedas;

DROP TABLE IF EXISTS cat_tipos_comprobante;

DROP TABLE IF EXISTS cat_usos_cfdi;

DROP TABLE IF EXISTS cat_regimenes_fiscales;

-- Rehabilitar verificación de claves foráneas
SET
    FOREIGN_KEY_CHECKS = 1;

-- ========================================================================
-- LIMPIAR PROCEDIMIENTOS Y FUNCIONES (si existen)
-- ========================================================================
DROP PROCEDURE IF EXISTS sp_insertar_cfdi_completo;

DROP PROCEDURE IF EXISTS sp_obtener_cfdi_por_uuid;

DROP FUNCTION IF EXISTS fn_validar_rfc;

SELECT
    'BASE DE DATOS LIMPIADA COMPLETAMENTE' AS status;