-- ========================================================================
-- CREACIÓN SIMPLIFICADA DE TABLAS PRINCIPALES - SIN FOREIGN KEYS
-- ========================================================================
USE sac_db;

-- Desactivar foreign key checks
SET
    foreign_key_checks = 0;

-- ========================================================================
-- TABLA PRINCIPAL: CFDI
-- ========================================================================
CREATE TABLE cfdi (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    -- Datos básicos del comprobante
    version VARCHAR(5) NOT NULL DEFAULT '4.0',
    serie VARCHAR(25),
    folio VARCHAR(40),
    fecha DATETIME NOT NULL,
    sello TEXT,
    forma_pago VARCHAR(2),
    no_certificado VARCHAR(20),
    certificado TEXT,
    condiciones_pago TEXT,
    -- Importes
    subtotal DECIMAL(18, 6) NOT NULL DEFAULT 0,
    descuento DECIMAL(18, 6) DEFAULT 0,
    moneda VARCHAR(3) NOT NULL DEFAULT 'MXN',
    tipo_cambio DECIMAL(10, 6) DEFAULT 1,
    total DECIMAL(18, 6) NOT NULL DEFAULT 0,
    -- Clasificación
    tipo_comprobante VARCHAR(1) NOT NULL,
    metodo_pago VARCHAR(3),
    lugar_expedicion VARCHAR(5) NOT NULL,
    confirmacion VARCHAR(5),
    -- Metadatos del sistema
    fecha_procesamiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estatus VARCHAR(20) DEFAULT 'ACTIVO',
    origen_archivo VARCHAR(500),
    -- Índices
    INDEX idx_fecha (fecha),
    INDEX idx_serie_folio (serie, folio),
    INDEX idx_tipo_comprobante (tipo_comprobante),
    INDEX idx_total (total),
    INDEX idx_estatus (estatus)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: EMISOR
-- ========================================================================
CREATE TABLE emisor (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    rfc VARCHAR(13) NOT NULL,
    nombre VARCHAR(254) NOT NULL,
    regimen_fiscal VARCHAR(3),
    fac_atr_adquirente VARCHAR(1),
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_rfc (rfc),
    INDEX idx_regimen (regimen_fiscal)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: RECEPTOR
-- ========================================================================
CREATE TABLE receptor (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    rfc VARCHAR(13) NOT NULL,
    nombre VARCHAR(254) NOT NULL,
    uso_cfdi VARCHAR(3),
    domicilio_fiscal_receptor VARCHAR(5),
    residencia_fiscal VARCHAR(3),
    num_reg_id_trib VARCHAR(40),
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_rfc (rfc),
    INDEX idx_uso_cfdi (uso_cfdi)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: CONCEPTOS
-- ========================================================================
CREATE TABLE conceptos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    clave_prod_serv VARCHAR(8),
    no_identificacion VARCHAR(100),
    cantidad DECIMAL(14, 6) NOT NULL,
    clave_unidad VARCHAR(3),
    unidad VARCHAR(20),
    descripcion TEXT NOT NULL,
    valor_unitario DECIMAL(18, 6) NOT NULL,
    importe DECIMAL(18, 6) NOT NULL,
    descuento DECIMAL(18, 6) DEFAULT 0,
    objeto_imp VARCHAR(2),
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_clave_prod_serv (clave_prod_serv),
    INDEX idx_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: IMPUESTOS TRASLADADOS
-- ========================================================================
CREATE TABLE impuestos_trasladados (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    concepto_id BIGINT NOT NULL,
    impuesto VARCHAR(3) NOT NULL,
    tipo_factor VARCHAR(6),
    tasa_cuota DECIMAL(8, 6),
    importe DECIMAL(18, 6),
    base DECIMAL(18, 6),
    INDEX idx_concepto_id (concepto_id),
    INDEX idx_impuesto (impuesto),
    INDEX idx_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: IMPUESTOS RETENIDOS
-- ========================================================================
CREATE TABLE impuestos_retenidos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    concepto_id BIGINT NOT NULL,
    impuesto VARCHAR(3) NOT NULL,
    tipo_factor VARCHAR(6),
    tasa_cuota DECIMAL(8, 6),
    importe DECIMAL(18, 6),
    base DECIMAL(18, 6),
    INDEX idx_concepto_id (concepto_id),
    INDEX idx_impuesto (impuesto),
    INDEX idx_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: CFDI RELACIONADOS
-- ========================================================================
CREATE TABLE cfdi_relacionados (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    tipo_relacion VARCHAR(2) NOT NULL,
    uuid_relacionado VARCHAR(36) NOT NULL,
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_uuid_relacionado (uuid_relacionado),
    INDEX idx_tipo_relacion (tipo_relacion)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: ADDENDA
-- ========================================================================
CREATE TABLE addenda (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    contenido_xml LONGTEXT,
    INDEX idx_cfdi_id (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: TIMBRE FISCAL DIGITAL
-- ========================================================================
CREATE TABLE cfdi_timbre_fiscal_digital (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cfdi_id BIGINT NOT NULL,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    fecha_timbrado DATETIME NOT NULL,
    rfc_prov_certif VARCHAR(13) NOT NULL,
    sello_cfd TEXT,
    no_certificado_sat VARCHAR(20),
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_uuid (uuid),
    INDEX idx_fecha_timbrado (fecha_timbrado),
    INDEX idx_rfc_pac (rfc_prov_certif)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Reactivar foreign key checks
SET
    foreign_key_checks = 1;

SELECT
    'TABLAS PRINCIPALES CREADAS EXITOSAMENTE SIN FOREIGN KEYS' AS status;