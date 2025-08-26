-- ========================================================================
-- PASO 2: SCRIPT MAESTRO PARA EJECUTAR TODA LA CONFIGURACIÓN
-- ========================================================================
-- Archivo: ejecutar_configuracion_completa_bd.sql
-- Propósito: Script maestro que ejecuta toda la configuración de BD
-- Base de datos: sac_db
-- IMPORTANTE: Ejecutar este archivo para configurar completamente la BD
-- ========================================================================
-- Verificar que estamos en la base correcta
SELECT
    'INICIANDO CONFIGURACIÓN COMPLETA DE BASE DE DATOS SAC_DB' AS mensaje;

SELECT
    DATABASE () AS base_datos_actual;

-- ========================================================================
-- PASO 1: LIMPIAR BASE DE DATOS
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'PASO 1: LIMPIANDO BASE DE DATOS' AS paso;

SELECT
    '========================================' AS separador;

SOURCE sql / limpiar_bd_sac.sql;

-- ========================================================================
-- PASO 2: CREAR TABLAS PRINCIPALES
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'PASO 2: CREANDO TABLAS PRINCIPALES' AS paso;

SELECT
    '========================================' AS separador;

SOURCE sql / crear_tablas_sac.sql;

-- ========================================================================
-- PASO 3: CREAR TABLAS DE COMPLEMENTOS
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'PASO 3: CREANDO TABLAS DE COMPLEMENTOS' AS paso;

SELECT
    '========================================' AS separador;

SOURCE sql / crear_tablas_complementos.sql;

-- ========================================================================
-- PASO 4: INSERTAR CATÁLOGOS SAT
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'PASO 4: INSERTANDO CATÁLOGOS SAT' AS paso;

SELECT
    '========================================' AS separador;

SOURCE sql / insertar_catalogos_sat.sql;

-- ========================================================================
-- PASO 5: CREAR PROCEDIMIENTOS Y FUNCIONES
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'PASO 5: CREANDO PROCEDIMIENTOS Y FUNCIONES' AS paso;

SELECT
    '========================================' AS separador;

SOURCE sql / crear_procedimientos_bd.sql;

-- ========================================================================
-- VERIFICACIONES FINALES
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'VERIFICACIONES FINALES' AS paso;

SELECT
    '========================================' AS separador;

-- Verificar que todas las tablas fueron creadas
SELECT
    'TABLAS CREADAS:' AS verificacion;

SELECT
    TABLE_NAME as tabla,
    TABLE_ROWS as filas_aprox,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as tamaño_mb
FROM
    information_schema.TABLES
WHERE
    TABLE_SCHEMA = 'sac_db'
ORDER BY
    TABLE_NAME;

-- Verificar que los catálogos tienen datos
SELECT
    'CATÁLOGOS POBLADOS:' AS verificacion;

SELECT
    'Formas de Pago' as catalogo,
    COUNT(*) as registros
FROM
    cat_formas_pago
UNION ALL
SELECT
    'Métodos de Pago',
    COUNT(*)
FROM
    cat_metodos_pago
UNION ALL
SELECT
    'Monedas',
    COUNT(*)
FROM
    cat_monedas
UNION ALL
SELECT
    'Tipos Comprobante',
    COUNT(*)
FROM
    cat_tipos_comprobante
UNION ALL
SELECT
    'Usos CFDI',
    COUNT(*)
FROM
    cat_usos_cfdi
UNION ALL
SELECT
    'Regímenes Fiscales',
    COUNT(*)
FROM
    cat_regimenes_fiscales;

-- Verificar procedimientos creados
SELECT
    'PROCEDIMIENTOS CREADOS:' AS verificacion;

SELECT
    ROUTINE_NAME as procedimiento,
    ROUTINE_TYPE as tipo
FROM
    information_schema.ROUTINES
WHERE
    ROUTINE_SCHEMA = 'sac_db'
ORDER BY
    ROUTINE_TYPE,
    ROUTINE_NAME;

-- Verificar triggers creados
SELECT
    'TRIGGERS CREADOS:' AS verificacion;

SELECT
    TRIGGER_NAME as trigger_name,
    EVENT_MANIPULATION as evento,
    EVENT_OBJECT_TABLE as tabla
FROM
    information_schema.TRIGGERS
WHERE
    TRIGGER_SCHEMA = 'sac_db'
ORDER BY
    EVENT_OBJECT_TABLE;

-- Verificar vistas creadas
SELECT
    'VISTAS CREADAS:' AS verificacion;

SELECT
    TABLE_NAME as vista
FROM
    information_schema.VIEWS
WHERE
    TABLE_SCHEMA = 'sac_db'
ORDER BY
    TABLE_NAME;

-- ========================================================================
-- PRUEBAS BÁSICAS DE FUNCIONAMIENTO
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'EJECUTANDO PRUEBAS BÁSICAS' AS paso;

SELECT
    '========================================' AS separador;

-- Probar función de validación RFC
SELECT
    'PRUEBA VALIDACIÓN RFC:' AS prueba;

SELECT
    fn_validar_rfc ('XAXX010101000') as rfc_valido_moral,
    fn_validar_rfc ('XEXX010101HNEXXR09') as rfc_valido_fisica,
    fn_validar_rfc ('INVALID') as rfc_invalido;

-- Probar función de validación UUID
SELECT
    'PRUEBA VALIDACIÓN UUID:' AS prueba;

SELECT
    fn_validar_uuid ('550E8400-E29B-41D4-A716-446655440000') as uuid_valido,
    fn_validar_uuid ('invalid-uuid') as uuid_invalido;

-- ========================================================================
-- CONFIGURACIÓN FINAL Y OPTIMIZACIONES
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'APLICANDO OPTIMIZACIONES FINALES' AS paso;

SELECT
    '========================================' AS separador;

-- Optimizar tablas
OPTIMIZE TABLE cfdi;

OPTIMIZE TABLE emisor;

OPTIMIZE TABLE receptor;

OPTIMIZE TABLE conceptos;

-- Actualizar estadísticas de las tablas
ANALYZE TABLE cfdi;

ANALYZE TABLE cfdi_timbre_fiscal_digital;

ANALYZE TABLE cfdi_complemento_pagos_v10;

ANALYZE TABLE cfdi_complemento_pagos_v20;

-- Configurar variables de sesión para mejor rendimiento
SET
    SESSION innodb_buffer_pool_size = 268435456;

-- 256MB
SET
    SESSION query_cache_size = 67108864;

-- 64MB
-- ========================================================================
-- RESUMEN FINAL
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'CONFIGURACIÓN COMPLETADA EXITOSAMENTE' AS resultado;

SELECT
    '========================================' AS separador;

SELECT
    'BASE DE DATOS CONFIGURADA CORRECTAMENTE' AS status,
    COUNT(TABLE_NAME) as total_tablas
FROM
    information_schema.TABLES
WHERE
    TABLE_SCHEMA = 'sac_db';

SELECT
    'SISTEMA LISTO PARA IMPORTAR CFDIs' AS mensaje,
    NOW () as fecha_configuracion;

-- ========================================================================
-- INFORMACIÓN IMPORTANTE PARA EL USUARIO
-- ========================================================================
SELECT
    '========================================' AS separador;

SELECT
    'INFORMACIÓN IMPORTANTE' AS titulo;

SELECT
    '========================================' AS separador;

SELECT
    'La base de datos SAC_DB ha sido configurada completamente.' AS info
UNION ALL
SELECT
    'Tablas principales: cfdi, emisor, receptor, conceptos, impuestos'
UNION ALL
SELECT
    'Tablas de complementos: timbre_fiscal, pagos_v10, pagos_v20, nomina'
UNION ALL
SELECT
    'Catálogos SAT: formas_pago, metodos_pago, monedas, regimenes'
UNION ALL
SELECT
    'Procedimientos: sp_insertar_cfdi_completo, sp_obtener_cfdi_por_uuid'
UNION ALL
SELECT
    'Sistema de auditoría: audit_log con triggers automáticos'
UNION ALL
SELECT
    'Vistas: v_cfdi_completo, v_estadisticas_mensuales'
UNION ALL
SELECT
    ''
UNION ALL
SELECT
    'SIGUIENTE PASO: Crear el importador completo de XMLs';

SELECT
    'CONFIGURACIÓN DE BASE DE DATOS COMPLETADA' AS final_status;