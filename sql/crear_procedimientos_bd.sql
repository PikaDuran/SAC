-- ========================================================================
-- PASO 2: PROCEDIMIENTOS ALMACENADOS Y FUNCIONES
-- ========================================================================
-- Archivo: crear_procedimientos_bd.sql
-- Propósito: Crear procedimientos y funciones para manejo de CFDIs
-- Base de datos: sac_db
-- ========================================================================

USE sac_db;

DELIMITER //

-- ========================================================================
-- FUNCIÓN: VALIDAR RFC
-- ========================================================================

CREATE FUNCTION fn_validar_rfc(rfc_input VARCHAR(13))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
COMMENT 'Valida formato básico de RFC mexicano'
BEGIN
    DECLARE es_valido BOOLEAN DEFAULT FALSE;
    
    -- RFC de persona moral (12 caracteres) o persona física (13 caracteres)
    IF LENGTH(rfc_input) IN (12, 13) THEN
        -- Validar que contenga solo letras y números
        IF rfc_input REGEXP '^[A-Z0-9]+$' THEN
            -- Validar patrón básico
            IF LENGTH(rfc_input) = 12 THEN
                -- Persona moral: 3 letras + 6 dígitos + 3 caracteres
                IF rfc_input REGEXP '^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$' THEN
                    SET es_valido = TRUE;
                END IF;
            ELSE
                -- Persona física: 4 letras + 6 dígitos + 3 caracteres
                IF rfc_input REGEXP '^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$' THEN
                    SET es_valido = TRUE;
                END IF;
            END IF;
        END IF;
    END IF;
    
    RETURN es_valido;
END//

-- ========================================================================
-- FUNCIÓN: CALCULAR DÍGITO VERIFICADOR UUID
-- ========================================================================

CREATE FUNCTION fn_validar_uuid(uuid_input VARCHAR(36))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
COMMENT 'Valida formato de UUID'
BEGIN
    DECLARE es_valido BOOLEAN DEFAULT FALSE;
    
    -- Validar longitud y formato de UUID
    IF LENGTH(uuid_input) = 36 THEN
        IF uuid_input REGEXP '^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$' THEN
            SET es_valido = TRUE;
        END IF;
    END IF;
    
    RETURN es_valido;
END//

-- ========================================================================
-- PROCEDIMIENTO: INSERTAR CFDI COMPLETO
-- ========================================================================

CREATE PROCEDURE sp_insertar_cfdi_completo(
    IN p_uuid VARCHAR(36),
    IN p_version VARCHAR(10),
    IN p_tipo_documento ENUM('EMITIDO', 'RECIBIDO'),
    IN p_fecha DATETIME,
    IN p_folio VARCHAR(20),
    IN p_serie VARCHAR(25),
    IN p_tipo_de_comprobante VARCHAR(1),
    IN p_lugar_expedicion VARCHAR(5),
    IN p_metodo_pago VARCHAR(3),
    IN p_forma_pago VARCHAR(2),
    IN p_moneda VARCHAR(3),
    IN p_tipo_cambio DECIMAL(10,6),
    IN p_subtotal DECIMAL(18,6),
    IN p_descuento DECIMAL(18,6),
    IN p_total DECIMAL(18,6),
    IN p_emisor_rfc VARCHAR(13),
    IN p_emisor_nombre VARCHAR(254),
    IN p_emisor_regimen VARCHAR(3),
    IN p_receptor_rfc VARCHAR(13),
    IN p_receptor_nombre VARCHAR(254),
    IN p_receptor_uso_cfdi VARCHAR(3),
    IN p_archivo_original VARCHAR(500),
    OUT p_cfdi_id BIGINT,
    OUT p_resultado VARCHAR(100)
)
COMMENT 'Inserta un CFDI completo con validaciones'
BEGIN
    DECLARE v_error_msg VARCHAR(500) DEFAULT '';
    DECLARE v_emisor_id BIGINT;
    DECLARE v_receptor_id BIGINT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            v_error_msg = MESSAGE_TEXT;
        SET p_resultado = CONCAT('ERROR: ', v_error_msg);
        SET p_cfdi_id = 0;
    END;
    
    START TRANSACTION;
    
    -- Validaciones básicas
    IF NOT fn_validar_uuid(p_uuid) THEN
        SET p_resultado = 'ERROR: UUID inválido';
        SET p_cfdi_id = 0;
        ROLLBACK;
        LEAVE sp_insertar_cfdi_completo;
    END IF;
    
    IF NOT fn_validar_rfc(p_emisor_rfc) THEN
        SET p_resultado = 'ERROR: RFC emisor inválido';
        SET p_cfdi_id = 0;
        ROLLBACK;
        LEAVE sp_insertar_cfdi_completo;
    END IF;
    
    IF NOT fn_validar_rfc(p_receptor_rfc) THEN
        SET p_resultado = 'ERROR: RFC receptor inválido';
        SET p_cfdi_id = 0;
        ROLLBACK;
        LEAVE sp_insertar_cfdi_completo;
    END IF;
    
    -- Verificar si ya existe el UUID
    SELECT id INTO p_cfdi_id FROM cfdi WHERE uuid = p_uuid LIMIT 1;
    
    IF p_cfdi_id IS NOT NULL THEN
        SET p_resultado = 'WARNING: CFDI ya existe';
        ROLLBACK;
        LEAVE sp_insertar_cfdi_completo;
    END IF;
    
    -- Insertar CFDI principal
    INSERT INTO cfdi (
        uuid, version, tipo_documento, fecha, folio, serie,
        tipo_de_comprobante, lugar_expedicion, metodo_pago, forma_pago,
        moneda, tipo_cambio, subtotal, descuento, total, archivo_original
    ) VALUES (
        p_uuid, p_version, p_tipo_documento, p_fecha, p_folio, p_serie,
        p_tipo_de_comprobante, p_lugar_expedicion, p_metodo_pago, p_forma_pago,
        p_moneda, p_tipo_cambio, p_subtotal, p_descuento, p_total, p_archivo_original
    );
    
    SET p_cfdi_id = LAST_INSERT_ID();
    
    -- Insertar emisor
    INSERT INTO emisor (cfdi_id, rfc, nombre, regimen_fiscal)
    VALUES (p_cfdi_id, p_emisor_rfc, p_emisor_nombre, p_emisor_regimen);
    
    -- Insertar receptor
    INSERT INTO receptor (cfdi_id, rfc, nombre, uso_cfdi)
    VALUES (p_cfdi_id, p_receptor_rfc, p_receptor_nombre, p_receptor_uso_cfdi);
    
    COMMIT;
    SET p_resultado = 'SUCCESS: CFDI insertado correctamente';
    
END//

-- ========================================================================
-- PROCEDIMIENTO: OBTENER CFDI POR UUID
-- ========================================================================

CREATE PROCEDURE sp_obtener_cfdi_por_uuid(
    IN p_uuid VARCHAR(36)
)
COMMENT 'Obtiene información completa de un CFDI por UUID'
BEGIN
    SELECT 
        c.*,
        e.rfc as emisor_rfc,
        e.nombre as emisor_nombre,
        e.regimen_fiscal as emisor_regimen,
        r.rfc as receptor_rfc,
        r.nombre as receptor_nombre,
        r.uso_cfdi as receptor_uso_cfdi,
        t.fecha_timbrado,
        t.rfc_prov_certif
    FROM cfdi c
    LEFT JOIN emisor e ON c.id = e.cfdi_id
    LEFT JOIN receptor r ON c.id = r.cfdi_id
    LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
    WHERE c.uuid = p_uuid;
    
    -- Obtener conceptos
    SELECT * FROM conceptos WHERE cfdi_id = (SELECT id FROM cfdi WHERE uuid = p_uuid);
    
    -- Obtener impuestos trasladados
    SELECT * FROM impuestos_trasladados WHERE cfdi_id = (SELECT id FROM cfdi WHERE uuid = p_uuid);
    
    -- Obtener impuestos retenidos
    SELECT * FROM impuestos_retenidos WHERE cfdi_id = (SELECT id FROM cfdi WHERE uuid = p_uuid);
    
END//

-- ========================================================================
-- PROCEDIMIENTO: ACTUALIZAR ESTADÍSTICAS
-- ========================================================================

CREATE PROCEDURE sp_actualizar_estadisticas_diarias(
    IN p_fecha DATE
)
COMMENT 'Actualiza estadísticas diarias de CFDIs'
BEGIN
    DECLARE v_terminado INT DEFAULT FALSE;
    DECLARE v_tipo_documento ENUM('EMITIDO', 'RECIBIDO');
    DECLARE v_version_cfdi VARCHAR(10);
    DECLARE v_tipo_comprobante VARCHAR(1);
    DECLARE v_total_docs INT;
    DECLARE v_total_importe DECIMAL(18,6);
    DECLARE v_total_impuestos DECIMAL(18,6);
    
    DECLARE cursor_estadisticas CURSOR FOR
        SELECT 
            tipo_documento,
            version,
            tipo_de_comprobante,
            COUNT(*) as total_docs,
            COALESCE(SUM(total), 0) as total_importe,
            COALESCE(SUM(it.importe), 0) as total_impuestos
        FROM cfdi c
        LEFT JOIN impuestos_trasladados it ON c.id = it.cfdi_id
        WHERE DATE(c.fecha) = p_fecha
        GROUP BY tipo_documento, version, tipo_de_comprobante;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_terminado = TRUE;
    
    -- Limpiar estadísticas del día
    DELETE FROM estadisticas_cfdi WHERE fecha = p_fecha;
    
    OPEN cursor_estadisticas;
    
    leer_loop: LOOP
        FETCH cursor_estadisticas INTO 
            v_tipo_documento, v_version_cfdi, v_tipo_comprobante,
            v_total_docs, v_total_importe, v_total_impuestos;
            
        IF v_terminado THEN
            LEAVE leer_loop;
        END IF;
        
        INSERT INTO estadisticas_cfdi (
            fecha, tipo_documento, version_cfdi, tipo_comprobante,
            total_documentos, total_importe, total_impuestos
        ) VALUES (
            p_fecha, v_tipo_documento, v_version_cfdi, v_tipo_comprobante,
            v_total_docs, v_total_importe, v_total_impuestos
        );
        
    END LOOP;
    
    CLOSE cursor_estadisticas;
    
    SELECT CONCAT('Estadísticas actualizadas para ', p_fecha) as resultado;
    
END//

-- ========================================================================
-- PROCEDIMIENTO: LIMPIAR DATOS ANTIGUOS
-- ========================================================================

CREATE PROCEDURE sp_limpiar_datos_antiguos(
    IN p_dias_retencion INT
)
COMMENT 'Limpia datos antiguos según política de retención'
BEGIN
    DECLARE v_fecha_limite DATE;
    DECLARE v_registros_eliminados INT DEFAULT 0;
    
    SET v_fecha_limite = DATE_SUB(CURDATE(), INTERVAL p_dias_retencion DAY);
    
    -- Eliminar logs de auditoría antiguos
    DELETE FROM audit_log WHERE created_at < v_fecha_limite;
    SET v_registros_eliminados = ROW_COUNT();
    
    SELECT CONCAT('Eliminados ', v_registros_eliminados, ' registros de auditoría anteriores a ', v_fecha_limite) as resultado;
    
END//

DELIMITER ;

-- ========================================================================
-- TRIGGERS DE AUDITORÍA
-- ========================================================================

DELIMITER //

-- Trigger para auditar inserciones en CFDI
CREATE TRIGGER tr_cfdi_insert_audit
AFTER INSERT ON cfdi
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (tabla, operacion, registro_id, datos_nuevos)
    VALUES ('cfdi', 'INSERT', NEW.id, JSON_OBJECT(
        'uuid', NEW.uuid,
        'tipo_documento', NEW.tipo_documento,
        'fecha', NEW.fecha,
        'total', NEW.total
    ));
END//

-- Trigger para auditar actualizaciones en CFDI
CREATE TRIGGER tr_cfdi_update_audit
AFTER UPDATE ON cfdi
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (tabla, operacion, registro_id, datos_anteriores, datos_nuevos)
    VALUES ('cfdi', 'UPDATE', NEW.id,
        JSON_OBJECT('uuid', OLD.uuid, 'total', OLD.total),
        JSON_OBJECT('uuid', NEW.uuid, 'total', NEW.total)
    );
END//

-- Trigger para auditar eliminaciones en CFDI
CREATE TRIGGER tr_cfdi_delete_audit
AFTER DELETE ON cfdi
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (tabla, operacion, registro_id, datos_anteriores)
    VALUES ('cfdi', 'DELETE', OLD.id, JSON_OBJECT(
        'uuid', OLD.uuid,
        'tipo_documento', OLD.tipo_documento,
        'fecha', OLD.fecha,
        'total', OLD.total
    ));
END//

DELIMITER ;

-- ========================================================================
-- VISTAS ÚTILES
-- ========================================================================

-- Vista para reporte de CFDIs completos
CREATE VIEW v_cfdi_completo AS
SELECT 
    c.id,
    c.uuid,
    c.version,
    c.tipo_documento,
    c.fecha,
    c.folio,
    c.serie,
    c.tipo_de_comprobante,
    c.subtotal,
    c.total,
    e.rfc as emisor_rfc,
    e.nombre as emisor_nombre,
    r.rfc as receptor_rfc,
    r.nombre as receptor_nombre,
    t.fecha_timbrado,
    (SELECT COUNT(*) FROM conceptos WHERE cfdi_id = c.id) as total_conceptos
FROM cfdi c
LEFT JOIN emisor e ON c.id = e.cfdi_id
LEFT JOIN receptor r ON c.id = r.cfdi_id
LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id;

-- Vista para estadísticas mensuales
CREATE VIEW v_estadisticas_mensuales AS
SELECT 
    YEAR(fecha) as anio,
    MONTH(fecha) as mes,
    tipo_documento,
    tipo_de_comprobante,
    COUNT(*) as total_documentos,
    SUM(total) as total_importe
FROM cfdi
GROUP BY YEAR(fecha), MONTH(fecha), tipo_documento, tipo_de_comprobante
ORDER BY anio DESC, mes DESC;

SELECT 'PROCEDIMIENTOS Y FUNCIONES CREADOS EXITOSAMENTE' AS status;
