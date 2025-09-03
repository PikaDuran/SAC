-- ========================================================================
-- PROCEDIMIENTOS ALMACENADOS PARA SISTEMA CFDI
-- ========================================================================

USE sac_db;

DELIMITER $$

-- Procedimiento para insertar CFDI completo
DROP PROCEDURE IF EXISTS sp_insertar_cfdi_completo$$
CREATE PROCEDURE sp_insertar_cfdi_completo(
    IN p_version VARCHAR(10),
    IN p_serie VARCHAR(25),
    IN p_folio VARCHAR(40),
    IN p_fecha DATETIME,
    IN p_total DECIMAL(18,6),
    IN p_tipo_comprobante VARCHAR(1),
    IN p_emisor_rfc VARCHAR(13),
    IN p_emisor_nombre VARCHAR(254),
    IN p_receptor_rfc VARCHAR(13),
    IN p_receptor_nombre VARCHAR(254),
    IN p_uuid VARCHAR(36),
    OUT p_cfdi_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insertar CFDI principal
    INSERT INTO cfdi (version, serie, folio, fecha, total, tipo_comprobante)
    VALUES (p_version, p_serie, p_folio, p_fecha, p_total, p_tipo_comprobante);
    
    SET p_cfdi_id = LAST_INSERT_ID();
    
    -- Insertar emisor
    INSERT INTO emisor (cfdi_id, rfc, nombre)
    VALUES (p_cfdi_id, p_emisor_rfc, p_emisor_nombre);
    
    -- Insertar receptor
    INSERT INTO receptor (cfdi_id, rfc, nombre)
    VALUES (p_cfdi_id, p_receptor_rfc, p_receptor_nombre);
    
    -- Insertar timbre fiscal si se proporciona UUID
    IF p_uuid IS NOT NULL THEN
        INSERT INTO cfdi_timbre_fiscal_digital (cfdi_id, uuid)
        VALUES (p_cfdi_id, p_uuid);
    END IF;
    
    COMMIT;
END$$

-- Procedimiento para obtener CFDI por UUID
DROP PROCEDURE IF EXISTS sp_obtener_cfdi_por_uuid$$
CREATE PROCEDURE sp_obtener_cfdi_por_uuid(
    IN p_uuid VARCHAR(36)
)
BEGIN
    SELECT 
        c.*,
        e.rfc as emisor_rfc, e.nombre as emisor_nombre,
        r.rfc as receptor_rfc, r.nombre as receptor_nombre,
        t.fecha_timbrado, t.rfc_prov_certif
    FROM cfdi c
    LEFT JOIN emisor e ON c.id = e.cfdi_id
    LEFT JOIN receptor r ON c.id = r.cfdi_id
    LEFT JOIN cfdi_timbre_fiscal_digital t ON c.id = t.cfdi_id
    WHERE t.uuid = p_uuid;
END$$

-- Procedimiento para estadísticas mensuales
DROP PROCEDURE IF EXISTS sp_estadisticas_mensuales$$
CREATE PROCEDURE sp_estadisticas_mensuales(
    IN p_año INT,
    IN p_mes INT
)
BEGIN
    SELECT 
        COUNT(*) as total_cfdis,
        SUM(total) as total_monto,
        AVG(total) as promedio_monto,
        tipo_comprobante,
        COUNT(*) as cantidad_por_tipo
    FROM cfdi 
    WHERE YEAR(fecha) = p_año AND MONTH(fecha) = p_mes
    GROUP BY tipo_comprobante
    ORDER BY cantidad_por_tipo DESC;
END$$

-- Procedimiento para buscar por RFC
DROP PROCEDURE IF EXISTS sp_buscar_por_rfc$$
CREATE PROCEDURE sp_buscar_por_rfc(
    IN p_rfc VARCHAR(13),
    IN p_rol VARCHAR(10)
)
BEGIN
    IF p_rol = 'EMISOR' THEN
        SELECT c.*, e.nombre as nombre_emisor
        FROM cfdi c
        JOIN emisor e ON c.id = e.cfdi_id
        WHERE e.rfc = p_rfc
        ORDER BY c.fecha DESC
        LIMIT 100;
    ELSEIF p_rol = 'RECEPTOR' THEN
        SELECT c.*, r.nombre as nombre_receptor
        FROM cfdi c
        JOIN receptor r ON c.id = r.cfdi_id
        WHERE r.rfc = p_rfc
        ORDER BY c.fecha DESC
        LIMIT 100;
    ELSE
        SELECT c.*, 
               e.nombre as nombre_emisor,
               r.nombre as nombre_receptor,
               CASE 
                   WHEN e.rfc = p_rfc THEN 'EMISOR'
                   WHEN r.rfc = p_rfc THEN 'RECEPTOR'
               END as rol
        FROM cfdi c
        LEFT JOIN emisor e ON c.id = e.cfdi_id
        LEFT JOIN receptor r ON c.id = r.cfdi_id
        WHERE e.rfc = p_rfc OR r.rfc = p_rfc
        ORDER BY c.fecha DESC
        LIMIT 100;
    END IF;
END$$

-- Función para validar RFC
DROP FUNCTION IF EXISTS fn_validar_rfc$$
CREATE FUNCTION fn_validar_rfc(p_rfc VARCHAR(13))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE es_valido BOOLEAN DEFAULT FALSE;
    
    -- RFC de persona moral (12 caracteres)
    IF LENGTH(p_rfc) = 12 THEN
        IF p_rfc REGEXP '^[A-Z&Ñ]{3}[0-9]{6}[A-Z0-9]{3}$' THEN
            SET es_valido = TRUE;
        END IF;
    -- RFC de persona física (13 caracteres)
    ELSEIF LENGTH(p_rfc) = 13 THEN
        IF p_rfc REGEXP '^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$' THEN
            SET es_valido = TRUE;
        END IF;
    END IF;
    
    RETURN es_valido;
END$$

-- Función para validar UUID
DROP FUNCTION IF EXISTS fn_validar_uuid$$
CREATE FUNCTION fn_validar_uuid(p_uuid VARCHAR(36))
RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE es_valido BOOLEAN DEFAULT FALSE;
    
    IF LENGTH(p_uuid) = 36 THEN
        IF p_uuid REGEXP '^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$' THEN
            SET es_valido = TRUE;
        END IF;
    END IF;
    
    RETURN es_valido;
END$$

-- Procedimiento para reportes de impuestos
DROP PROCEDURE IF EXISTS sp_reporte_impuestos$$
CREATE PROCEDURE sp_reporte_impuestos(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    SELECT 
        'TRASLADADOS' as tipo_impuesto,
        it.impuesto,
        SUM(it.importe) as total_importe,
        COUNT(*) as cantidad_registros,
        AVG(it.importe) as promedio_importe
    FROM impuestos_trasladados it
    JOIN conceptos con ON it.concepto_id = con.id
    JOIN cfdi c ON con.cfdi_id = c.id
    WHERE DATE(c.fecha) BETWEEN p_fecha_inicio AND p_fecha_fin
    GROUP BY it.impuesto
    
    UNION ALL
    
    SELECT 
        'RETENIDOS' as tipo_impuesto,
        ir.impuesto,
        SUM(ir.importe) as total_importe,
        COUNT(*) as cantidad_registros,
        AVG(ir.importe) as promedio_importe
    FROM impuestos_retenidos ir
    JOIN conceptos con ON ir.concepto_id = con.id
    JOIN cfdi c ON con.cfdi_id = c.id
    WHERE DATE(c.fecha) BETWEEN p_fecha_inicio AND p_fecha_fin
    GROUP BY ir.impuesto
    
    ORDER BY tipo_impuesto, total_importe DESC;
END$$

DELIMITER ;
