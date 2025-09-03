-- ========================================================================
-- CREAR TODAS LAS TABLAS SIN FOREIGN KEYS PRIMERO
-- ========================================================================
USE sac_db;

-- Deshabilitar verificación de foreign keys
SET
    FOREIGN_KEY_CHECKS = 0;

-- Eliminar todas las tablas si existen
DROP TABLE IF EXISTS addenda;

DROP TABLE IF EXISTS cfdi_relacionados;

DROP TABLE IF EXISTS impuestos_retenidos;

DROP TABLE IF EXISTS impuestos_trasladados;

DROP TABLE IF EXISTS conceptos;

DROP TABLE IF EXISTS receptor;

DROP TABLE IF EXISTS emisor;

DROP TABLE IF EXISTS cfdi;

-- Eliminar tablas de complementos
DROP TABLE IF EXISTS cfdi_otros_complementos;

DROP TABLE IF EXISTS cfdi_complemento_carta_porte;

DROP TABLE IF EXISTS cfdi_complemento_comercio_exterior;

DROP TABLE IF EXISTS cfdi_complemento_impuestos_locales;

DROP TABLE IF EXISTS cfdi_complemento_nomina;

DROP TABLE IF EXISTS cfdi_pagos_documentos_relacionados;

DROP TABLE IF EXISTS cfdi_complemento_pagos_v20;

DROP TABLE IF EXISTS cfdi_complemento_pagos_v10;

DROP TABLE IF EXISTS cfdi_timbre_fiscal_digital;

SELECT
    'ELIMINANDO TABLAS EXISTENTES...' AS status;

-- ========================================================================
-- TABLA PRINCIPAL: cfdi
-- ========================================================================
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

-- ========================================================================
-- TABLA: emisor
-- ========================================================================
CREATE TABLE emisor (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    rfc VARCHAR(15) NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    regimen_fiscal VARCHAR(10),
    fac_atr_adquirente VARCHAR(5),
    INDEX idx_emisor_cfdi (cfdi_id),
    INDEX idx_emisor_rfc (rfc),
    INDEX idx_emisor_nombre (nombre (50))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: receptor
-- ========================================================================
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
    INDEX idx_receptor_rfc (rfc),
    INDEX idx_receptor_nombre (nombre (50))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: conceptos
-- ========================================================================
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
    INDEX idx_conceptos_cfdi (cfdi_id),
    INDEX idx_conceptos_clave_prod (clave_prod_serv),
    INDEX idx_conceptos_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: impuestos_trasladados
-- ========================================================================
CREATE TABLE impuestos_trasladados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto_id BIGINT UNSIGNED NOT NULL,
    impuesto VARCHAR(10) NOT NULL,
    tipo_factor VARCHAR(20),
    tasa_cuota DECIMAL(10, 8),
    importe DECIMAL(18, 6) DEFAULT 0,
    base DECIMAL(18, 6) DEFAULT 0,
    INDEX idx_imp_tras_concepto (concepto_id),
    INDEX idx_imp_tras_impuesto (impuesto),
    INDEX idx_imp_tras_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: impuestos_retenidos
-- ========================================================================
CREATE TABLE impuestos_retenidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concepto_id BIGINT UNSIGNED NOT NULL,
    impuesto VARCHAR(10) NOT NULL,
    tipo_factor VARCHAR(20),
    tasa_cuota DECIMAL(10, 8),
    importe DECIMAL(18, 6) DEFAULT 0,
    base DECIMAL(18, 6) DEFAULT 0,
    INDEX idx_imp_ret_concepto (concepto_id),
    INDEX idx_imp_ret_impuesto (impuesto),
    INDEX idx_imp_ret_importe (importe)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: cfdi_relacionados
-- ========================================================================
CREATE TABLE cfdi_relacionados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    uuid_relacionado VARCHAR(40) NOT NULL,
    tipo_relacion VARCHAR(10) NOT NULL,
    INDEX idx_cfdi_rel_cfdi (cfdi_id),
    INDEX idx_cfdi_rel_uuid (uuid_relacionado)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- TABLA: addenda
-- ========================================================================
CREATE TABLE addenda (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    contenido_xml LONGTEXT,
    INDEX idx_addenda_cfdi (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ========================================================================
-- COMPLEMENTOS
-- ========================================================================
-- TIMBRE FISCAL DIGITAL
CREATE TABLE cfdi_timbre_fiscal_digital (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    uuid VARCHAR(40) NOT NULL UNIQUE,
    fecha_timbrado DATETIME NOT NULL,
    rfc_prov_certif VARCHAR(15) NOT NULL,
    sello_cfd TEXT,
    no_certificado_sat VARCHAR(20),
    sello_sat TEXT,
    INDEX idx_tfd_cfdi (cfdi_id),
    INDEX idx_tfd_uuid (uuid),
    INDEX idx_tfd_fecha (fecha_timbrado)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO PAGOS V1.0
CREATE TABLE cfdi_complemento_pagos_v10 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    fecha_pago DATETIME NOT NULL,
    forma_pago_p VARCHAR(10) NOT NULL,
    moneda_p VARCHAR(10) DEFAULT 'MXN',
    tipo_cambio_p DECIMAL(10, 6) DEFAULT 1,
    monto DECIMAL(18, 6) DEFAULT 0,
    num_operacion VARCHAR(100),
    rfc_emisor_cta_ord VARCHAR(15),
    nom_banco_ord_ext VARCHAR(300),
    cta_ordenante VARCHAR(50),
    rfc_emisor_cta_ben VARCHAR(15),
    cta_beneficiario VARCHAR(50),
    tipo_cad_pago VARCHAR(10),
    cert_pago TEXT,
    cad_pago TEXT,
    sello_pago TEXT,
    INDEX idx_pagos_v10_cfdi (cfdi_id),
    INDEX idx_pagos_v10_fecha (fecha_pago),
    INDEX idx_pagos_v10_monto (monto)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO PAGOS V2.0
CREATE TABLE cfdi_complemento_pagos_v20 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    fecha_pago DATETIME NOT NULL,
    forma_pago_p VARCHAR(10) NOT NULL,
    moneda_p VARCHAR(10) DEFAULT 'MXN',
    tipo_cambio_p DECIMAL(10, 6) DEFAULT 1,
    monto DECIMAL(18, 6) DEFAULT 0,
    num_operacion VARCHAR(100),
    rfc_emisor_cta_ord VARCHAR(15),
    nom_banco_ord_ext VARCHAR(300),
    cta_ordenante VARCHAR(50),
    rfc_emisor_cta_ben VARCHAR(15),
    cta_beneficiario VARCHAR(50),
    tipo_cad_pago VARCHAR(10),
    cert_pago TEXT,
    cad_pago TEXT,
    sello_pago TEXT,
    INDEX idx_pagos_v20_cfdi (cfdi_id),
    INDEX idx_pagos_v20_fecha (fecha_pago),
    INDEX idx_pagos_v20_monto (monto)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- DOCUMENTOS RELACIONADOS DE PAGOS
CREATE TABLE cfdi_pagos_documentos_relacionados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pago_id BIGINT UNSIGNED NOT NULL,
    id_documento VARCHAR(40) NOT NULL,
    serie VARCHAR(25),
    folio VARCHAR(40),
    moneda_dr VARCHAR(10) DEFAULT 'MXN',
    equivalencia_dr DECIMAL(10, 6) DEFAULT 1,
    num_parcialidad INT DEFAULT 1,
    imp_saldo_ant DECIMAL(18, 6) DEFAULT 0,
    imp_pagado DECIMAL(18, 6) DEFAULT 0,
    imp_saldo_insoluto DECIMAL(18, 6) DEFAULT 0,
    objeto_imp_dr VARCHAR(5),
    INDEX idx_docs_rel_pago (pago_id),
    INDEX idx_docs_rel_doc (id_documento)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO NÓMINA
CREATE TABLE cfdi_complemento_nomina (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(10) DEFAULT '1.2',
    tipo_nomina VARCHAR(5) NOT NULL,
    fecha_pago DATE NOT NULL,
    fecha_inicial_pago DATE NOT NULL,
    fecha_final_pago DATE NOT NULL,
    num_dias_pagados DECIMAL(8, 3) DEFAULT 0,
    total_percepciones DECIMAL(18, 6) DEFAULT 0,
    total_deducciones DECIMAL(18, 6) DEFAULT 0,
    total_otros_pagos DECIMAL(18, 6) DEFAULT 0,
    -- Datos del empleado
    curp VARCHAR(18),
    nss VARCHAR(15),
    fecha_inicio_rel_laboral DATE,
    antiguedad VARCHAR(10),
    tipo_contrato VARCHAR(5),
    sindicalizado VARCHAR(5),
    tipo_jornada VARCHAR(5),
    tipo_regimen VARCHAR(5),
    num_empleado VARCHAR(50),
    departamento VARCHAR(100),
    puesto VARCHAR(100),
    riesgo_puesto VARCHAR(5),
    periodicidad_pago VARCHAR(5),
    banco VARCHAR(5),
    cuenta_bancaria VARCHAR(20),
    salario_base_cot_apor DECIMAL(18, 6),
    salario_diario_integrado DECIMAL(18, 6),
    clave_ent_fed VARCHAR(5),
    INDEX idx_nomina_cfdi (cfdi_id),
    INDEX idx_nomina_fecha_pago (fecha_pago),
    INDEX idx_nomina_curp (curp)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO IMPUESTOS LOCALES
CREATE TABLE cfdi_complemento_impuestos_locales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(10) DEFAULT '1.0',
    total_retenciones DECIMAL(18, 6) DEFAULT 0,
    total_traslados DECIMAL(18, 6) DEFAULT 0,
    datos_json JSON,
    INDEX idx_imp_locales_cfdi (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO CARTA PORTE
CREATE TABLE cfdi_complemento_carta_porte (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(10) DEFAULT '2.0',
    transp_internac VARCHAR(5),
    entrada_salida_merc VARCHAR(20),
    pais_origen_destino VARCHAR(5),
    via_entrada_salida VARCHAR(5),
    total_dist_rec DECIMAL(12, 3),
    unidad_peso VARCHAR(10),
    peso_bruto_total DECIMAL(18, 6),
    unidad_medida VARCHAR(10),
    peso_neto_total DECIMAL(18, 6),
    num_total_mercancias INT,
    -- Ubicaciones
    datos_json JSON,
    INDEX idx_carta_porte_cfdi (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- COMPLEMENTO COMERCIO EXTERIOR
CREATE TABLE cfdi_complemento_comercio_exterior (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    version VARCHAR(10) DEFAULT '1.1',
    motivo_traslado VARCHAR(5),
    tipo_operacion VARCHAR(5),
    clave_pedimento VARCHAR(5),
    certificado_origen VARCHAR(5),
    numero_certificado_origen VARCHAR(40),
    numero_exportador_confiable VARCHAR(40),
    incoterm VARCHAR(5),
    subdivision VARCHAR(10),
    observaciones TEXT,
    tipo_cambio_usd DECIMAL(10, 6),
    total_usd DECIMAL(18, 6),
    -- Datos JSON para flexibilidad
    datos_json JSON,
    INDEX idx_comercio_ext_cfdi (cfdi_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- OTROS COMPLEMENTOS (GENÉRICO)
CREATE TABLE cfdi_otros_complementos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    tipo_complemento VARCHAR(100) NOT NULL,
    namespace_uri VARCHAR(200),
    version VARCHAR(10),
    datos_json JSON,
    contenido_xml LONGTEXT,
    INDEX idx_otros_comp_cfdi (cfdi_id),
    INDEX idx_otros_comp_tipo (tipo_complemento)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Rehabilitar verificación de foreign keys
SET
    FOREIGN_KEY_CHECKS = 1;

SELECT
    'TODAS LAS TABLAS CREADAS EXITOSAMENTE' AS status;

SELECT
    COUNT(TABLE_NAME) as total_tablas
FROM
    information_schema.TABLES
WHERE
    TABLE_SCHEMA = 'sac_db';