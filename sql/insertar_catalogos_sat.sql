-- ========================================================================
-- PASO 2: INSERTAR CATÁLOGOS SAT Y CONFIGURACIONES
-- ========================================================================
-- Archivo: insertar_catalogos_sat.sql
-- Propósito: Insertar todos los catálogos SAT necesarios
-- Base de datos: sac_db
-- ========================================================================
USE sac_db;

-- ========================================================================
-- CATÁLOGO DE FORMAS DE PAGO
-- ========================================================================
INSERT INTO
    cat_formas_pago (codigo, descripcion)
VALUES
    ('01', 'Efectivo'),
    ('02', 'Cheque nominativo'),
    ('03', 'Transferencia electrónica de fondos'),
    ('04', 'Tarjeta de crédito'),
    ('05', 'Monedero electrónico'),
    ('06', 'Dinero electrónico'),
    ('08', 'Vales de despensa'),
    ('12', 'Dación en pago'),
    ('13', 'Pago por subrogación'),
    ('14', 'Pago por consignación'),
    ('15', 'Condonación'),
    ('17', 'Compensación'),
    ('23', 'Novación'),
    ('24', 'Confusión'),
    ('25', 'Remisión de deuda'),
    ('26', 'Prescripción o caducidad'),
    ('27', 'A satisfacción del acreedor'),
    ('28', 'Tarjeta de débito'),
    ('29', 'Tarjeta de servicios'),
    ('30', 'Aplicación de anticipos'),
    ('31', 'Intermediario pagos'),
    ('99', 'Por definir');

-- ========================================================================
-- CATÁLOGO DE MÉTODOS DE PAGO
-- ========================================================================
INSERT INTO
    cat_metodos_pago (codigo, descripcion)
VALUES
    ('PUE', 'Pago en una sola exhibición'),
    ('PPD', 'Pago en parcialidades o diferido');

-- ========================================================================
-- CATÁLOGO DE MONEDAS
-- ========================================================================
INSERT INTO
    cat_monedas (codigo, descripcion)
VALUES
    ('MXN', 'Peso Mexicano'),
    ('USD', 'Dólar estadounidense'),
    ('EUR', 'Euro'),
    ('GBP', 'Libra Esterlina'),
    ('JPY', 'Yen'),
    ('CAD', 'Dólar Canadiense'),
    ('CHF', 'Franco Suizo'),
    ('CNY', 'Yuan Chino'),
    ('ARS', 'Peso Argentino'),
    ('BRL', 'Real Brasileño'),
    ('CLP', 'Peso Chileno'),
    ('COP', 'Peso Colombiano'),
    ('PEN', 'Nuevo Sol Peruano'),
    ('UYU', 'Peso Uruguayo'),
    ('XXX', 'Los códigos de moneda no son aplicables');

-- ========================================================================
-- CATÁLOGO DE TIPOS DE COMPROBANTE
-- ========================================================================
INSERT INTO
    cat_tipos_comprobante (codigo, descripcion)
VALUES
    ('I', 'Ingreso'),
    ('E', 'Egreso'),
    ('T', 'Traslado'),
    ('N', 'Nómina'),
    ('P', 'Pago');

-- ========================================================================
-- CATÁLOGO DE USOS DE CFDI (PRINCIPALES)
-- ========================================================================
INSERT INTO
    cat_usos_cfdi (codigo, descripcion)
VALUES
    ('G01', 'Adquisición de mercancías'),
    (
        'G02',
        'Devoluciones, descuentos o bonificaciones'
    ),
    ('G03', 'Gastos en general'),
    ('I01', 'Construcciones'),
    (
        'I02',
        'Mobiliario y equipo de oficina por inversiones'
    ),
    ('I03', 'Equipo de transporte'),
    ('I04', 'Equipo de computo y accesorios'),
    (
        'I05',
        'Dados, troqueles, moldes, matrices y herramental'
    ),
    ('I06', 'Comunicaciones telefónicas'),
    ('I07', 'Comunicaciones satelitales'),
    ('I08', 'Otra maquinaria y equipo'),
    (
        'D01',
        'Honorarios médicos, dentales y gastos hospitalarios'
    ),
    (
        'D02',
        'Gastos médicos por incapacidad o discapacidad'
    ),
    ('D03', 'Gastos funerales'),
    ('D04', 'Donativos'),
    (
        'D05',
        'Intereses reales efectivamente pagados por créditos hipotecarios'
    ),
    ('D06', 'Aportaciones voluntarias al SAR'),
    ('D07', 'Primas por seguros de gastos médicos'),
    (
        'D08',
        'Gastos de transportación escolar obligatoria'
    ),
    (
        'D09',
        'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones'
    ),
    ('D10', 'Pagos por servicios educativos'),
    ('P01', 'Por definir'),
    ('S01', 'Sin efectos fiscales'),
    ('CP01', 'Pagos'),
    ('CN01', 'Nómina');

-- ========================================================================
-- CATÁLOGO DE REGÍMENES FISCALES (PRINCIPALES)
-- ========================================================================
INSERT INTO
    cat_regimenes_fiscales (codigo, descripcion)
VALUES
    ('601', 'General de Ley Personas Morales'),
    ('603', 'Personas Morales con Fines no Lucrativos'),
    (
        '605',
        'Sueldos y Salarios e Ingresos Asimilados a Salarios'
    ),
    ('606', 'Arrendamiento'),
    (
        '607',
        'Régimen de Enajenación o Adquisición de Bienes'
    ),
    ('608', 'Demás ingresos'),
    (
        '610',
        'Residentes en el Extranjero sin Establecimiento Permanente en México'
    ),
    (
        '611',
        'Ingresos por Dividendos (socios y accionistas)'
    ),
    (
        '612',
        'Personas Físicas con Actividades Empresariales y Profesionales'
    ),
    ('614', 'Ingresos por intereses'),
    (
        '615',
        'Régimen de los ingresos por obtención de premios'
    ),
    ('616', 'Sin obligaciones fiscales'),
    (
        '620',
        'Sociedades Cooperativas de Producción que optan por diferir sus ingresos'
    ),
    ('621', 'Incorporación Fiscal'),
    (
        '622',
        'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras'
    ),
    ('623', 'Opcional para Grupos de Sociedades'),
    ('624', 'Coordinados'),
    (
        '625',
        'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas'
    ),
    ('626', 'Régimen Simplificado de Confianza');

-- ========================================================================
-- CONFIGURACIONES DEL SISTEMA
-- ========================================================================
CREATE TABLE configuracion_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM ('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    configuracion_sistema (clave, valor, descripcion, tipo)
VALUES
    (
        'version_bd',
        '1.0',
        'Versión de la base de datos',
        'string'
    ),
    (
        'charset_default',
        'utf8mb4',
        'Charset por defecto',
        'string'
    ),
    (
        'timezone',
        'America/Mexico_City',
        'Zona horaria del sistema',
        'string'
    ),
    (
        'max_file_size_mb',
        '50',
        'Tamaño máximo de archivo XML en MB',
        'integer'
    ),
    (
        'backup_retention_days',
        '90',
        'Días de retención de respaldos',
        'integer'
    ),
    (
        'enable_audit_log',
        'true',
        'Habilitar log de auditoría',
        'boolean'
    ),
    (
        'xml_storage_path',
        '/storage/sat_downloads/',
        'Ruta de almacenamiento de XMLs',
        'string'
    ),
    (
        'batch_size_import',
        '1000',
        'Tamaño de lote para importación',
        'integer'
    );

-- ========================================================================
-- TABLA DE LOG DE AUDITORÍA
-- ========================================================================
CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(50) NOT NULL,
    operacion ENUM ('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    registro_id BIGINT UNSIGNED NOT NULL,
    usuario VARCHAR(50) NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_tabla (tabla),
    INDEX idx_audit_operacion (operacion),
    INDEX idx_audit_registro (registro_id),
    INDEX idx_audit_fecha (created_at),
    INDEX idx_audit_usuario (usuario)
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Log de auditoría del sistema';

-- ========================================================================
-- TABLA DE ESTADÍSTICAS
-- ========================================================================
CREATE TABLE estadisticas_cfdi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    tipo_documento ENUM ('EMITIDO', 'RECIBIDO') NOT NULL,
    version_cfdi VARCHAR(10) NOT NULL,
    tipo_comprobante VARCHAR(1) NOT NULL,
    total_documentos INT DEFAULT 0,
    total_importe DECIMAL(18, 6) DEFAULT 0,
    total_impuestos DECIMAL(18, 6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_estadisticas (
        fecha,
        tipo_documento,
        version_cfdi,
        tipo_comprobante
    ),
    INDEX idx_estadisticas_fecha (fecha),
    INDEX idx_estadisticas_tipo (tipo_documento, tipo_comprobante)
) ENGINE = InnoDB CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Estadísticas diarias de CFDIs';

SELECT
    'CATÁLOGOS SAT INSERTADOS EXITOSAMENTE' AS status,
    COUNT(*) as total_formas_pago
FROM
    cat_formas_pago
UNION ALL
SELECT
    'MÉTODOS DE PAGO',
    COUNT(*)
FROM
    cat_metodos_pago
UNION ALL
SELECT
    'MONEDAS',
    COUNT(*)
FROM
    cat_monedas
UNION ALL
SELECT
    'TIPOS COMPROBANTE',
    COUNT(*)
FROM
    cat_tipos_comprobante
UNION ALL
SELECT
    'USOS CFDI',
    COUNT(*)
FROM
    cat_usos_cfdi
UNION ALL
SELECT
    'REGÍMENES FISCALES',
    COUNT(*)
FROM
    cat_regimenes_fiscales;