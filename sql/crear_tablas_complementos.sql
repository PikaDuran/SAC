-- ========================================================================
-- PASO 2: CREACIÓN DE TABLAS DE COMPLEMENTOS
-- ========================================================================
-- Archivo: crear_tablas_complementos.sql
-- Propósito: Crear todas las tablas de complementos CFDI
-- Base de datos: sac_db
-- ========================================================================
USE sac_db;

-- ========================================================================
-- TIMBRE FISCAL DIGITAL (OBLIGATORIO EN TODOS LOS CFDI)
-- ========================================================================
CREATE TABLE cfdi_timbre_fiscal_digital (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Relación 1:1 con CFDI',
    -- Datos del timbre
    version VARCHAR(5) NOT NULL COMMENT 'Versión del timbre (1.1)',
    uuid VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID del timbre fiscal',
    fecha_timbrado DATETIME NOT NULL COMMENT 'Fecha y hora del timbrado',
    rfc_prov_certif VARCHAR(13) NOT NULL COMMENT 'RFC del proveedor de certificación',
    leyenda TEXT NULL COMMENT 'Leyenda del PAC',
    -- Sellos digitales
    sello_cfd TEXT NOT NULL COMMENT 'Sello del CFD',
    no_certificado_sat VARCHAR(20) NOT NULL COMMENT 'Número de certificado del SAT',
    sello_sat TEXT NOT NULL COMMENT 'Sello del SAT',
    -- Metadatos técnicos
    schema_location TEXT NULL COMMENT 'Schema location del timbre',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_timbre_fecha (fecha_timbrado),
    INDEX idx_timbre_rfc_pac (rfc_prov_certif),
    INDEX idx_timbre_no_cert (no_certificado_sat),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Timbre Fiscal Digital - Obligatorio en todos los CFDIs';

-- ========================================================================
-- COMPLEMENTO DE PAGOS VERSION 1.0 (CFDI 3.3)
-- ========================================================================
CREATE TABLE cfdi_complemento_pagos_v10 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Datos básicos del pago
    version VARCHAR(5) NOT NULL DEFAULT '1.0',
    fecha_pago DATETIME NOT NULL COMMENT 'Fecha del pago',
    forma_de_pago_p VARCHAR(2) NOT NULL COMMENT 'Forma de pago del pago',
    moneda_p VARCHAR(3) NOT NULL COMMENT 'Moneda del pago',
    monto DECIMAL(18, 6) NOT NULL COMMENT 'Monto del pago',
    -- Datos bancarios (opcionales)
    num_operacion VARCHAR(100) NULL COMMENT 'Número de operación',
    rfc_emisor_cta_ord VARCHAR(13) NULL COMMENT 'RFC del emisor de la cuenta ordenante',
    nom_banco_ord_ext VARCHAR(300) NULL COMMENT 'Nombre del banco ordenante extranjero',
    cta_ordenante VARCHAR(50) NULL COMMENT 'Cuenta ordenante',
    rfc_emisor_cta_ben VARCHAR(13) NULL COMMENT 'RFC del emisor de la cuenta beneficiaria',
    cta_beneficiario VARCHAR(50) NULL COMMENT 'Cuenta beneficiaria',
    -- Certificación del pago (opcionales)
    tipo_cadena_pago VARCHAR(2) NULL COMMENT 'Tipo de cadena de pago',
    cert_pago TEXT NULL COMMENT 'Certificado de pago',
    cadena_pago TEXT NULL COMMENT 'Cadena de pago',
    sello_pago TEXT NULL COMMENT 'Sello de pago',
    -- Metadatos técnicos
    schema_location TEXT NULL,
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_pagos_v10_cfdi (cfdi_id),
    INDEX idx_pagos_v10_fecha (fecha_pago),
    INDEX idx_pagos_v10_forma (forma_de_pago_p),
    INDEX idx_pagos_v10_moneda (moneda_p),
    INDEX idx_pagos_v10_monto (monto),
    INDEX idx_pagos_v10_operacion (num_operacion),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (forma_de_pago_p) REFERENCES cat_formas_pago (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (moneda_p) REFERENCES cat_monedas (codigo) ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complemento de Pagos versión 1.0 (CFDI 3.3)';

-- ========================================================================
-- COMPLEMENTO DE PAGOS VERSION 2.0 (CFDI 4.0)
-- ========================================================================
CREATE TABLE cfdi_complemento_pagos_v20 (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Datos básicos del pago
    version VARCHAR(5) NOT NULL DEFAULT '2.0',
    fecha_pago DATETIME NOT NULL COMMENT 'Fecha del pago',
    forma_de_pago_p VARCHAR(2) NOT NULL COMMENT 'Forma de pago del pago',
    moneda_p VARCHAR(3) NOT NULL COMMENT 'Moneda del pago',
    tipo_cambio_p DECIMAL(10, 6) NULL COMMENT 'Tipo de cambio del pago',
    monto DECIMAL(18, 6) NOT NULL COMMENT 'Monto del pago',
    -- Datos bancarios (opcionales)
    num_operacion VARCHAR(100) NULL COMMENT 'Número de operación',
    rfc_emisor_cta_ord VARCHAR(13) NULL COMMENT 'RFC del emisor de la cuenta ordenante',
    nom_banco_ord_ext VARCHAR(300) NULL COMMENT 'Nombre del banco ordenante extranjero',
    cta_ordenante VARCHAR(50) NULL COMMENT 'Cuenta ordenante',
    rfc_emisor_cta_ben VARCHAR(13) NULL COMMENT 'RFC del emisor de la cuenta beneficiaria',
    cta_beneficiario VARCHAR(50) NULL COMMENT 'Cuenta beneficiaria',
    -- Totales del complemento (nuevos en v2.0)
    monto_total_pagos DECIMAL(18, 6) NULL COMMENT 'Monto total de pagos',
    total_traslados_base_iva16 DECIMAL(18, 6) NULL COMMENT 'Total base para IVA 16%',
    total_traslados_impuesto_iva16 DECIMAL(18, 6) NULL COMMENT 'Total IVA 16% trasladado',
    -- Metadatos técnicos
    schema_location TEXT NULL,
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_pagos_v20_cfdi (cfdi_id),
    INDEX idx_pagos_v20_fecha (fecha_pago),
    INDEX idx_pagos_v20_forma (forma_de_pago_p),
    INDEX idx_pagos_v20_moneda (moneda_p),
    INDEX idx_pagos_v20_monto (monto),
    INDEX idx_pagos_v20_operacion (num_operacion),
    INDEX idx_pagos_v20_total (monto_total_pagos),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (forma_de_pago_p) REFERENCES cat_formas_pago (codigo) ON UPDATE CASCADE,
    FOREIGN KEY (moneda_p) REFERENCES cat_monedas (codigo) ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complemento de Pagos versión 2.0 (CFDI 4.0)';

-- ========================================================================
-- DOCUMENTOS RELACIONADOS EN COMPLEMENTO DE PAGOS
-- ========================================================================
CREATE TABLE cfdi_pagos_documentos_relacionados (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pago_v10_id BIGINT UNSIGNED NULL COMMENT 'FK a tabla pagos v1.0',
    pago_v20_id BIGINT UNSIGNED NULL COMMENT 'FK a tabla pagos v2.0',
    -- Identificación del documento
    id_documento VARCHAR(36) NOT NULL COMMENT 'UUID del documento relacionado',
    serie VARCHAR(25) NULL COMMENT 'Serie del documento',
    folio VARCHAR(40) NULL COMMENT 'Folio del documento',
    -- Moneda y tipo de cambio
    moneda_dr VARCHAR(3) NOT NULL COMMENT 'Moneda del documento relacionado',
    equivalencia_dr DECIMAL(10, 6) NULL COMMENT 'Equivalencia de la moneda',
    -- Datos del pago
    num_parcialidad INT NOT NULL COMMENT 'Número de parcialidad',
    imp_saldo_ant DECIMAL(18, 6) NOT NULL COMMENT 'Importe saldo anterior',
    imp_pagado DECIMAL(18, 6) NOT NULL COMMENT 'Importe pagado',
    imp_saldo_insoluto DECIMAL(18, 6) NOT NULL COMMENT 'Importe saldo insoluto',
    -- Campos específicos v2.0
    objeto_imp_dr VARCHAR(2) NULL COMMENT 'Objeto de impuestos (solo v2.0)',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_doc_rel_pago_v10 (pago_v10_id),
    INDEX idx_doc_rel_pago_v20 (pago_v20_id),
    INDEX idx_doc_rel_uuid (id_documento),
    INDEX idx_doc_rel_parcialidad (num_parcialidad),
    INDEX idx_doc_rel_moneda (moneda_dr),
    -- Constraints
    FOREIGN KEY (pago_v10_id) REFERENCES cfdi_complemento_pagos_v10 (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (pago_v20_id) REFERENCES cfdi_complemento_pagos_v20 (id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (moneda_dr) REFERENCES cat_monedas (codigo) ON UPDATE CASCADE,
    -- Constraint: debe tener relación con una versión de pagos
    CONSTRAINT chk_pago_version CHECK (
        (
            pago_v10_id IS NOT NULL
            AND pago_v20_id IS NULL
        )
        OR (
            pago_v10_id IS NULL
            AND pago_v20_id IS NOT NULL
        )
    )
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Documentos relacionados en complementos de pagos';

-- ========================================================================
-- COMPLEMENTO DE NÓMINA
-- ========================================================================
CREATE TABLE cfdi_complemento_nomina (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Datos básicos de la nómina
    version VARCHAR(5) NOT NULL DEFAULT '1.2',
    tipo_nomina VARCHAR(1) NOT NULL COMMENT 'O=Ordinaria, E=Extraordinaria',
    fecha_pago DATE NOT NULL COMMENT 'Fecha de pago',
    fecha_inicial_pago DATE NOT NULL COMMENT 'Fecha inicial del periodo',
    fecha_final_pago DATE NOT NULL COMMENT 'Fecha final del periodo',
    num_dias_pagados DECIMAL(5, 3) NOT NULL COMMENT 'Número de días pagados',
    -- Totales
    total_percepciones DECIMAL(18, 6) NULL COMMENT 'Total de percepciones',
    total_deducciones DECIMAL(18, 6) NULL COMMENT 'Total de deducciones',
    total_otros_pagos DECIMAL(18, 6) NULL COMMENT 'Total de otros pagos',
    -- Datos del emisor
    registro_patronal VARCHAR(20) NULL COMMENT 'Registro patronal',
    -- Datos del receptor (trabajador)
    curp VARCHAR(18) NULL COMMENT 'CURP del trabajador',
    num_seg_social VARCHAR(15) NULL COMMENT 'Número de seguridad social',
    fecha_inicio_rel_laboral DATE NULL COMMENT 'Fecha inicio relación laboral',
    antiguedad VARCHAR(5) NULL COMMENT 'Antigüedad (P1Y2M3D format)',
    tipo_contrato VARCHAR(2) NULL COMMENT 'Tipo de contrato',
    sindicalizado VARCHAR(2) NULL COMMENT 'Sindicalizado (Sí/No)',
    tipo_jornada VARCHAR(2) NULL COMMENT 'Tipo de jornada',
    tipo_regimen VARCHAR(2) NULL COMMENT 'Tipo de régimen',
    num_empleado VARCHAR(15) NULL COMMENT 'Número de empleado',
    departamento VARCHAR(100) NULL COMMENT 'Departamento',
    puesto VARCHAR(100) NULL COMMENT 'Puesto',
    riesgo_puesto VARCHAR(1) NULL COMMENT 'Riesgo del puesto',
    periodicidad_pago VARCHAR(2) NULL COMMENT 'Periodicidad de pago',
    -- Datos bancarios
    banco VARCHAR(3) NULL COMMENT 'Clave del banco',
    cuenta_bancaria VARCHAR(50) NULL COMMENT 'Cuenta bancaria',
    -- Salarios
    salario_base_cot_apor DECIMAL(18, 6) NULL COMMENT 'Salario base cotización aportación',
    salario_diario_integrado DECIMAL(18, 6) NULL COMMENT 'Salario diario integrado',
    -- Ubicación
    clave_ent_fed VARCHAR(3) NULL COMMENT 'Clave entidad federativa',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_nomina_cfdi (cfdi_id),
    INDEX idx_nomina_fecha_pago (fecha_pago),
    INDEX idx_nomina_tipo (tipo_nomina),
    INDEX idx_nomina_curp (curp),
    INDEX idx_nomina_num_empleado (num_empleado),
    INDEX idx_nomina_periodo (fecha_inicial_pago, fecha_final_pago),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complemento de Nómina versión 1.2';

-- ========================================================================
-- COMPLEMENTO DE IMPUESTOS LOCALES
-- ========================================================================
CREATE TABLE cfdi_complemento_impuestos_locales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Datos básicos
    version VARCHAR(5) NOT NULL DEFAULT '1.0',
    total_de_retenciones DECIMAL(18, 6) NULL COMMENT 'Total de retenciones locales',
    total_de_traslados DECIMAL(18, 6) NULL COMMENT 'Total de traslados locales',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_imp_locales_cfdi (cfdi_id),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complemento de Impuestos Locales';

-- ========================================================================
-- COMPLEMENTO DE CARTA PORTE
-- ========================================================================
CREATE TABLE cfdi_complemento_carta_porte (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Datos básicos
    version VARCHAR(5) NOT NULL DEFAULT '2.0',
    transp_internac VARCHAR(2) NULL COMMENT 'Transporte internacional',
    total_dist_rec DECIMAL(12, 3) NULL COMMENT 'Total distancia recorrida en KM',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_carta_porte_cfdi (cfdi_id),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complemento de Carta Porte versión 2.0';

-- ========================================================================
-- OTROS COMPLEMENTOS (GENÉRICO)
-- ========================================================================
CREATE TABLE cfdi_otros_complementos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cfdi_id BIGINT UNSIGNED NOT NULL,
    -- Identificación del complemento
    tipo_complemento VARCHAR(50) NOT NULL COMMENT 'Tipo de complemento identificado',
    version VARCHAR(10) NULL COMMENT 'Versión del complemento',
    namespace_uri VARCHAR(500) NULL COMMENT 'URI del namespace',
    -- Contenido completo
    contenido_xml LONGTEXT NOT NULL COMMENT 'Contenido XML completo del complemento',
    -- Campos de auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Índices
    INDEX idx_otros_comp_cfdi (cfdi_id),
    INDEX idx_otros_comp_tipo (tipo_complemento),
    INDEX idx_otros_comp_version (version),
    -- Constraints
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Otros complementos menos comunes';

SELECT
    'TABLAS DE COMPLEMENTOS CREADAS EXITOSAMENTE' AS status;