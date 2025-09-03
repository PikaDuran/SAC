USE sac_db;

-- Función para validar RFC
DROP FUNCTION IF EXISTS fn_validar_rfc;

DELIMITER $$

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

DELIMITER ;
