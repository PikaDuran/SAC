-- Creación de tablas para Complementos de Pago CFDI
-- Fecha: 25 de Agosto 2025
-- Sistema SAC - Manejo completo de pagos
USE sac_db;

-- Tabla principal de pagos
CREATE TABLE IF NOT EXISTS cfdi_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cfdi_id INT NOT NULL,
    fecha_pago DATETIME,
    forma_pago_p VARCHAR(10) COMMENT 'Forma de pago del complemento',
    moneda_p VARCHAR(10) DEFAULT 'MXN',
    tipo_cambio_p DECIMAL(19, 6) DEFAULT 1.000000,
    monto DECIMAL(19, 6),
    num_operacion VARCHAR(100) COMMENT 'Número de operación bancaria',
    rfc_emisor_cta_ord VARCHAR(13) COMMENT 'RFC emisor cuenta ordenante',
    nom_banco_ord_ext VARCHAR(300) COMMENT 'Nombre banco ordenante extranjero',
    cta_ordenante VARCHAR(50) COMMENT 'Cuenta ordenante',
    rfc_emisor_cta_ben VARCHAR(13) COMMENT 'RFC emisor cuenta beneficiario',
    cta_beneficiario VARCHAR(50) COMMENT 'Cuenta beneficiario',
    tipo_cad_pago VARCHAR(10) COMMENT 'Tipo cadena pago',
    cert_pago TEXT COMMENT 'Certificado de pago',
    cad_pago TEXT COMMENT 'Cadena de pago',
    sello_pago TEXT COMMENT 'Sello digital del pago',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cfdi_id (cfdi_id),
    INDEX idx_fecha_pago (fecha_pago),
    INDEX idx_forma_pago (forma_pago_p),
    INDEX idx_moneda (moneda_p),
    FOREIGN KEY (cfdi_id) REFERENCES cfdi (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Complementos de pago de CFDIs';

-- Tabla de documentos relacionados en cada pago
CREATE TABLE IF NOT EXISTS cfdi_pago_documentos_relacionados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pago_id INT NOT NULL,
    id_documento VARCHAR(36) NOT NULL COMMENT 'UUID del documento relacionado',
    serie VARCHAR(25) COMMENT 'Serie del documento',
    folio VARCHAR(40) COMMENT 'Folio del documento',
    moneda_dr VARCHAR(10) DEFAULT 'MXN' COMMENT 'Moneda del documento relacionado',
    equivalencia_dr DECIMAL(19, 6) DEFAULT 1.000000 COMMENT 'Equivalencia de moneda',
    num_parcialidad INT COMMENT 'Número de parcialidad',
    imp_saldo_ant DECIMAL(19, 6) COMMENT 'Importe saldo anterior',
    imp_pagado DECIMAL(19, 6) COMMENT 'Importe pagado',
    imp_saldo_insoluto DECIMAL(19, 6) COMMENT 'Importe saldo insoluto',
    objetivo_imp_dr VARCHAR(10) COMMENT 'Objetivo del impuesto DR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pago_id (pago_id),
    INDEX idx_id_documento (id_documento),
    INDEX idx_serie_folio (serie, folio),
    INDEX idx_num_parcialidad (num_parcialidad),
    FOREIGN KEY (pago_id) REFERENCES cfdi_pagos (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Documentos relacionados en complementos de pago';

-- Tabla de impuestos trasladados en pagos (DrP)
CREATE TABLE IF NOT EXISTS cfdi_pago_impuestos_dr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_relacionado_id INT NOT NULL,
    base_dr DECIMAL(19, 6) COMMENT 'Base del impuesto DR',
    impuesto_dr VARCHAR(10) COMMENT 'Tipo de impuesto DR',
    tipo_factor_dr VARCHAR(10) COMMENT 'Tipo de factor DR',
    tasa_o_cuota_dr DECIMAL(8, 6) COMMENT 'Tasa o cuota DR',
    importe_dr DECIMAL(19, 6) COMMENT 'Importe del impuesto DR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc_relacionado (documento_relacionado_id),
    INDEX idx_impuesto_dr (impuesto_dr),
    INDEX idx_tipo_factor_dr (tipo_factor_dr),
    FOREIGN KEY (documento_relacionado_id) REFERENCES cfdi_pago_documentos_relacionados (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Impuestos de documentos relacionados en complementos de pago';

-- Tabla de totales de impuestos en pagos
CREATE TABLE IF NOT EXISTS cfdi_pago_totales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pago_id INT NOT NULL,
    total_retenciones_iva DECIMAL(19, 6) DEFAULT 0.00,
    total_retenciones_isr DECIMAL(19, 6) DEFAULT 0.00,
    total_retenciones_ieps DECIMAL(19, 6) DEFAULT 0.00,
    total_traslados_base_iva16 DECIMAL(19, 6) DEFAULT 0.00,
    total_traslados_impuesto_iva16 DECIMAL(19, 6) DEFAULT 0.00,
    total_traslados_base_iva0 DECIMAL(19, 6) DEFAULT 0.00,
    total_traslados_base_iva_exento DECIMAL(19, 6) DEFAULT 0.00,
    monto_total_pagos DECIMAL(19, 6) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pago_id (pago_id),
    FOREIGN KEY (pago_id) REFERENCES cfdi_pagos (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Totales de impuestos en complementos de pago';

-- Crear vista para consulta rápida de pagos
CREATE
OR REPLACE VIEW vista_cfdi_pagos AS
SELECT
    p.id as pago_id,
    c.uuid as cfdi_uuid,
    c.serie as cfdi_serie,
    c.folio as cfdi_folio,
    c.rfc_emisor,
    c.rfc_receptor,
    c.fecha,
    c.total as cfdi_total,
    p.fecha_pago,
    p.forma_pago_p,
    p.moneda_p,
    p.monto as monto_pago,
    p.num_operacion,
    COUNT(dr.id) as documentos_relacionados,
    SUM(dr.imp_pagado) as total_documentos_pagados
FROM
    cfdi_pagos p
    INNER JOIN cfdi c ON p.cfdi_id = c.id
    LEFT JOIN cfdi_pago_documentos_relacionados dr ON p.id = dr.pago_id
GROUP BY
    p.id,
    c.uuid,
    c.serie,
    c.folio,
    c.rfc_emisor,
    c.rfc_receptor,
    c.fecha,
    c.total,
    p.fecha_pago,
    p.forma_pago_p,
    p.moneda_p,
    p.monto,
    p.num_operacion;

-- Mensaje de confirmación
SELECT
    'Tablas de complementos de pago creadas exitosamente!' as resultado;

SELECT
    'Tablas creadas:' as info;

SELECT
    '- cfdi_pagos' as tabla1;

SELECT
    '- cfdi_pago_documentos_relacionados' as tabla2;

SELECT
    '- cfdi_pago_impuestos_dr' as tabla3;

SELECT
    '- cfdi_pago_totales' as tabla4;

SELECT
    '- vista_cfdi_pagos (vista)' as vista;