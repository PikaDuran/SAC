-- ========================================================================
-- CREAR TABLA PRINCIPAL CFDI SIMPLE SIN FOREIGN KEYS
-- ========================================================================
USE sac_db;

SET
    FOREIGN_KEY_CHECKS = 0;

-- Eliminar tabla si existe
DROP TABLE IF EXISTS cfdi;

-- Crear tabla CFDI principal SIN foreign keys
CREATE TABLE cfdi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(10) DEFAULT '4.0',
    serie VARCHAR(25),
    folio VARCHAR(40),
    fecha DATETIME NOT NULL,
    sello TEXT,
    forma_pago VARCHAR(10),
    no_certificado VARCHAR(20),
    certificado TEXT,
    condiciones_pago TEXT,
    subtotal DECIMAL(18, 6) DEFAULT 0,
    descuento DECIMAL(18, 6) DEFAULT 0,
    moneda VARCHAR(10) DEFAULT 'MXN',
    tipo_cambio DECIMAL(10, 6) DEFAULT 1,
    total DECIMAL(18, 6) DEFAULT 0,
    tipo_comprobante VARCHAR(5),
    metodo_pago VARCHAR(10),
    lugar_expedicion VARCHAR(10),
    confirmacion VARCHAR(100),
    fecha_procesamiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cfdi_fecha (fecha),
    INDEX idx_cfdi_serie_folio (serie, folio),
    INDEX idx_cfdi_tipo (tipo_comprobante),
    INDEX idx_cfdi_total (total),
    INDEX idx_cfdi_moneda (moneda)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

SELECT
    'TABLA CFDI CREADA' AS status;

-- Crear las demás tablas relacionadas
DROP TABLE IF EXISTS emisor;

CREATE TABLE emisor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    rfc VARCHAR(15) NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    regimen_fiscal VARCHAR(10),
    fac_atr_adquirente VARCHAR(5),
    INDEX idx_emisor_cfdi (cfdi_id),
    INDEX idx_emisor_rfc (rfc)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS receptor;

CREATE TABLE receptor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    rfc VARCHAR(15) NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    uso_cfdi VARCHAR(10),
    domicilio_fiscal_receptor VARCHAR(10),
    residencia_fiscal VARCHAR(5),
    num_reg_id_trib VARCHAR(40),
    INDEX idx_receptor_cfdi (cfdi_id),
    INDEX idx_receptor_rfc (rfc)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS conceptos;

CREATE TABLE conceptos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    clave_prod_serv VARCHAR(20),
    no_identificacion VARCHAR(100),
    cantidad DECIMAL(18, 6) DEFAULT 1,
    clave_unidad VARCHAR(10),
    unidad VARCHAR(100),
    descripcion TEXT NOT NULL,
    valor_unitario DECIMAL(18, 6) DEFAULT 0,
    importe DECIMAL(18, 6) DEFAULT 0,
    descuento DECIMAL(18, 6) DEFAULT 0,
    objeto_imp VARCHAR(5),
    INDEX idx_conceptos_cfdi (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS impuestos_trasladados;

CREATE TABLE impuestos_trasladados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto_id BIGINT UNSIGNED NOT NULL,
    impuesto VARCHAR(10) NOT NULL,
    tipo_factor VARCHAR(20),
    tasa_cuota DECIMAL(10, 8),
    importe DECIMAL(18, 6) DEFAULT 0,
    base DECIMAL(18, 6) DEFAULT 0,
    INDEX idx_imp_tras_concepto (concepto_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DROP TABLE IF EXISTS impuestos_retenidos;

CREATE TABLE impuestos_retenidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto_id BIGINT UNSIGNED NOT NULL,
    impuesto VARCHAR(10) NOT NULL,
    tipo_factor VARCHAR(20),
    tasa_cuota DECIMAL(10, 8),
    importe DECIMAL(18, 6) DEFAULT 0,
    base DECIMAL(18, 6) DEFAULT 0,
    INDEX idx_imp_ret_concepto (concepto_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar que la tabla CFDI esté creada
DESCRIBE cfdi;

SET
    FOREIGN_KEY_CHECKS = 1;

SELECT
    'TODAS LAS TABLAS PRINCIPALES CREADAS EXITOSAMENTE' AS status;