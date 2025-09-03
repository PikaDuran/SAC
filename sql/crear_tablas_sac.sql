-- ========================================================================
-- PASO 2: CREACIÓN COMPLETA DE TABLAS PARA SAC_DB
-- ========================================================================
-- Archivo: crear_tablas_sac.sql
-- Propósito: Crear toda la estructura de BD basada en análisis de 387 campos
-- Base de datos: sac_db
-- Charset: utf8mb4 (soporte completo Unicode)
-- Motor: InnoDB (transaccional, claves foráneas)
-- ========================================================================
USE sac_db;

-- Configurar charset y collation por defecto
SET
    NAMES utf8mb4;

SET
    CHARACTER_SET_DATABASE = utf8mb4;

SET
    collation_database = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLAS DE CATÁLOGOS SAT
-- ========================================================================
-- Catálogo de Formas de Pago
CREATE TABLE cat_formas_pago (
    codigo VARCHAR(2) PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Catálogo de Métodos de Pago
CREATE TABLE cat_metodos_pago (
    codigo VARCHAR(3) PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Catálogo de Monedas
CREATE TABLE cat_monedas (
    codigo VARCHAR(3) PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Catálogo de Tipos de Comprobante
CREATE TABLE cat_tipos_comprobante (
    codigo VARCHAR(1) PRIMARY KEY,
    descripcion VARCHAR(50) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Catálogo de Usos de CFDI
CREATE TABLE cat_usos_cfdi (
    codigo VARCHAR(3) PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Catálogo de Regímenes Fiscales
CREATE TABLE cat_regimenes_fiscales (
    codigo VARCHAR(3) PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA PRINCIPAL CFDI
-- ========================================================================
CREATE TABLE cfdi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL COMMENT 'UUID del TimbreFiscalDigital',
    version VARCHAR(10) NOT NULL COMMENT 'Versión CFDI: 3.3 o 4.0',
    tipo_documento ENUM ('EMITIDO', 'RECIBIDO') NOT NULL COMMENT 'Tipo de documento',
    -- Datos básicos del comprobante
    fecha DATETIME NOT NULL COMMENT 'Fecha de emisión',
    folio VARCHAR(20) NULL COMMENT 'Folio del comprobante',
    serie VARCHAR(25) NULL COMMENT 'Serie del comprobante',
    tipo_de_comprobante VARCHAR(1) NOT NULL COMMENT 'I=Ingreso, E=Egreso, T=Traslado, N=Nómina, P=Pago',
    lugar_expedicion VARCHAR(5) NOT NULL COMMENT 'Código postal de expedición',
    -- Forma y método de pago
    metodo_pago VARCHAR(3) NULL COMMENT 'PUE, PPD, etc',
    forma_pago VARCHAR(2) NULL COMMENT 'Forma de pago SAT',
    condiciones_de_pago TEXT NULL COMMENT 'Condiciones de pago',
    -- Moneda y tipo de cambio
    moneda VARCHAR(3) DEFAULT 'MXN' COMMENT 'Código de moneda',
    tipo_cambio DECIMAL(10, 6) NULL COMMENT 'Tipo de cambio aplicado',
    -- Importes
    subtotal DECIMAL(18, 6) NOT NULL COMMENT 'Subtotal del comprobante',
    descuento DECIMAL(18, 6) NULL COMMENT 'Descuento aplicado',
    total DECIMAL(18, 6) NOT NULL COMMENT 'Total del comprobante',
    -- Datos de certificación
    no_certificado VARCHAR(20) NULL COMMENT 'Número de certificado',
    certificado TEXT NULL COMMENT 'Certificado del emisor',
    sello TEXT NULL COMMENT 'Sello digital del comprobante',
    -- Campos específicos CFDI 4.0
    exportacion VARCHAR(2) NULL COMMENT 'Clave de exportación (CFDI 4.0)',
    -- Información global (para comprobantes globales)
    informacion_global_periodicidad VARCHAR(2) NULL,
    informacion_global_meses VARCHAR(2) NULL,
    informacion_global_anio YEAR NULL,
    -- Metadatos técnicos
    schema_location TEXT NULL COMMENT 'Schema location del XML',
    archivo_original VARCHAR(500) NULL COMMENT 'Ruta del archivo XML original',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_cfdi_fecha (fecha),
    INDEX idx_cfdi_tipo (tipo_documento, tipo_de_comprobante),
    INDEX idx_cfdi_folio_serie (serie, folio),
    INDEX idx_cfdi_metodo_forma (metodo_pago, forma_pago),
    INDEX idx_cfdi_moneda (moneda),
    INDEX idx_cfdi_version (version),
    INDEX idx_cfdi_created (created_at),
    -- Constraints con catálogos
    FOREIGN KEY (forma_pago) REFERENCES cat_formas_pago (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (metodo_pago) REFERENCES cat_metodos_pago (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (moneda) REFERENCES cat_monedas (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (tipo_de_comprobante) REFERENCES cat_tipos_comprobante (codigo) ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tabla principal de CFDIs (Comprobantes Fiscales Digitales)';

-- ========================================================================
-- TABLA EMISOR
-- ========================================================================
CREATE TABLE emisor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    rfc VARCHAR(13) NOT NULL COMMENT 'RFC del emisor',
    nombre VARCHAR(254) NULL COMMENT 'Nombre o razón social del emisor',
    regimen_fiscal VARCHAR(3) NOT NULL COMMENT 'Régimen fiscal del emisor',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_emisor_rfc (rfc),
    INDEX idx_emisor_regimen (regimen_fiscal),
    INDEX idx_emisor_cfdi (cfdi_id),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (regimen_fiscal) REFERENCES cat_regimenes_fiscales (codigo) ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Datos del emisor del CFDI';

-- ========================================================================
-- TABLA RECEPTOR
-- ========================================================================
CREATE TABLE receptor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    rfc VARCHAR(13) NOT NULL COMMENT 'RFC del receptor',
    nombre VARCHAR(254) NULL COMMENT 'Nombre o razón social del receptor',
    uso_cfdi VARCHAR(3) NOT NULL COMMENT 'Uso que dará al CFDI',
    -- Campos específicos CFDI 4.0
    domicilio_fiscal_receptor VARCHAR(5) NULL COMMENT 'CP del domicilio fiscal (CFDI 4.0)',
    regimen_fiscal_receptor VARCHAR(3) NULL COMMENT 'Régimen fiscal del receptor (CFDI 4.0)',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_receptor_rfc (rfc),
    INDEX idx_receptor_uso (uso_cfdi),
    INDEX idx_receptor_regimen (regimen_fiscal_receptor),
    INDEX idx_receptor_cfdi (cfdi_id),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (uso_cfdi) REFERENCES cat_usos_cfdi (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (regimen_fiscal_receptor) REFERENCES cat_regimenes_fiscales (codigo) ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Datos del receptor del CFDI';

-- ========================================================================
-- TABLA CONCEPTOS
-- ========================================================================
CREATE TABLE conceptos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    numero_concepto INT NOT NULL COMMENT 'Número secuencial del concepto',
    -- Datos del producto/servicio
    cantidad DECIMAL(18, 6) NOT NULL COMMENT 'Cantidad del producto/servicio',
    clave_unidad VARCHAR(3) NOT NULL COMMENT 'Clave de unidad SAT',
    unidad VARCHAR(20) NULL COMMENT 'Descripción de la unidad',
    clave_prod_serv VARCHAR(8) NOT NULL COMMENT 'Clave de producto/servicio SAT',
    no_identificacion VARCHAR(100) NULL COMMENT 'Número de identificación del producto',
    descripcion TEXT NOT NULL COMMENT 'Descripción del producto/servicio',
    -- Importes
    valor_unitario DECIMAL(18, 6) NOT NULL COMMENT 'Valor unitario del producto',
    importe DECIMAL(18, 6) NOT NULL COMMENT 'Importe total del concepto',
    descuento DECIMAL(18, 6) NULL COMMENT 'Descuento aplicado al concepto',
    -- Campos específicos CFDI 4.0
    objeto_imp VARCHAR(2) NULL COMMENT 'Objeto de impuestos (CFDI 4.0)',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_conceptos_cfdi (cfdi_id, numero_concepto),
    INDEX idx_conceptos_clave_prod (clave_prod_serv),
    INDEX idx_conceptos_clave_unidad (clave_unidad),
    INDEX idx_conceptos_importe (importe),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_conceptos_cfdi_numero (cfdi_id, numero_concepto)
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Conceptos (productos/servicios) del CFDI';

-- ========================================================================
-- TABLA IMPUESTOS TRASLADADOS
-- ========================================================================
CREATE TABLE impuestos_trasladados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    concepto_id BIGINT UNSIGNED NULL COMMENT 'NULL si es a nivel comprobante',
    -- Datos del impuesto
    base DECIMAL(18, 6) NOT NULL COMMENT 'Base gravable',
    impuesto VARCHAR(3) NOT NULL COMMENT 'Código del impuesto (002=IVA, etc)',
    tipo_factor VARCHAR(8) NOT NULL COMMENT 'Tasa, Cuota, Exento',
    tasa_o_cuota DECIMAL(8, 6) NULL COMMENT 'Tasa o cuota del impuesto',
    importe DECIMAL(18, 6) NULL COMMENT 'Importe del impuesto trasladado',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_traslados_cfdi (cfdi_id),
    INDEX idx_traslados_concepto (concepto_id),
    INDEX idx_traslados_impuesto (impuesto),
    INDEX idx_traslados_factor (tipo_factor),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (concepto_id) REFERENCES conceptos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Impuestos trasladados del CFDI';

-- ========================================================================
-- TABLA IMPUESTOS RETENIDOS
-- ========================================================================
CREATE TABLE impuestos_retenidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    concepto_id BIGINT UNSIGNED NULL COMMENT 'NULL si es a nivel comprobante',
    -- Datos del impuesto
    base DECIMAL(18, 6) NOT NULL COMMENT 'Base gravable',
    impuesto VARCHAR(3) NOT NULL COMMENT 'Código del impuesto',
    tipo_factor VARCHAR(8) NOT NULL COMMENT 'Tasa, Cuota',
    tasa_o_cuota DECIMAL(8, 6) NULL COMMENT 'Tasa o cuota del impuesto',
    importe DECIMAL(18, 6) NOT NULL COMMENT 'Importe del impuesto retenido',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_retenciones_cfdi (cfdi_id),
    INDEX idx_retenciones_concepto (concepto_id),
    INDEX idx_retenciones_impuesto (impuesto),
    INDEX idx_retenciones_factor (tipo_factor),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (concepto_id) REFERENCES conceptos (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Impuestos retenidos del CFDI';

-- ========================================================================
-- TABLA CFDI RELACIONADOS
-- ========================================================================
CREATE TABLE cfdi_relacionados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    tipo_relacion VARCHAR(2) NOT NULL COMMENT 'Tipo de relación entre CFDIs',
    uuid_relacionado VARCHAR(36) NOT NULL COMMENT 'UUID del CFDI relacionado',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_relacionados_cfdi (cfdi_id),
    INDEX idx_relacionados_uuid (uuid_relacionado),
    INDEX idx_relacionados_tipo (tipo_relacion),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'CFDIs relacionados';

-- ========================================================================
-- TABLA ADDENDA
-- ========================================================================
CREATE TABLE addenda (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    tipo_addenda VARCHAR(50) NULL COMMENT 'Tipo de addenda identificada',
    contenido_xml LONGTEXT NOT NULL COMMENT 'Contenido XML completo de la addenda',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_addenda_cfdi (cfdi_id),
    INDEX idx_addenda_tipo (tipo_addenda),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Addendas de los CFDIs';

SELECT
    'TABLAS PRINCIPALES CREADAS EXITOSAMENTE' AS status;