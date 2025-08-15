-- Tabla: catalogo_sat_tipo_comprobante
CREATE TABLE IF NOT EXISTS catalogo_sat_tipo_comprobante (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(4) NOT NULL,
    descripcion VARCHAR(64) NOT NULL,
    valor_maximo VARCHAR(32),
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_forma_pago
CREATE TABLE IF NOT EXISTS catalogo_sat_forma_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(4) NOT NULL,
    descripcion VARCHAR(128) NOT NULL,
    bancarizado VARCHAR(8),
    numero_operacion VARCHAR(32),
    rfc_emisor_cuenta_ordenante VARCHAR(16),
    cuenta_ordenante VARCHAR(32),
    patron_cuenta_ordenante VARCHAR(64),
    rfc_emisor_cuenta_beneficiario VARCHAR(16),
    cuenta_beneficiario VARCHAR(32),
    patron_cuenta_beneficiario VARCHAR(64),
    tipo_cadena_pago VARCHAR(32),
    nombre_banco_extranjero VARCHAR(128),
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_metodo_pago
CREATE TABLE IF NOT EXISTS catalogo_sat_metodo_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(8) NOT NULL,
    descripcion VARCHAR(128) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_moneda
CREATE TABLE IF NOT EXISTS catalogo_sat_moneda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(8) NOT NULL,
    descripcion VARCHAR(64) NOT NULL,
    decimales INT,
    porcentaje_variacion VARCHAR(8),
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_regimen_fiscal
CREATE TABLE IF NOT EXISTS catalogo_sat_regimen_fiscal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(8) NOT NULL,
    descripcion VARCHAR(128) NOT NULL,
    fisica VARCHAR(4),
    moral VARCHAR(4),
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_tasa_o_cuota
CREATE TABLE IF NOT EXISTS catalogo_sat_tasa_o_cuota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(16),
    valor_minimo DECIMAL(12, 6),
    valor_maximo DECIMAL(12, 6),
    impuesto VARCHAR(32),
    factor VARCHAR(16),
    traslado VARCHAR(4),
    retencion VARCHAR(4),
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_tipo_factor
CREATE TABLE IF NOT EXISTS catalogo_sat_tipo_factor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(16) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_tipo_relacion
CREATE TABLE IF NOT EXISTS catalogo_sat_tipo_relacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(8) NOT NULL,
    descripcion VARCHAR(128) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE
);

-- Tabla: catalogo_sat_uso_cfdi
CREATE TABLE IF NOT EXISTS catalogo_sat_uso_cfdi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(8) NOT NULL,
    descripcion VARCHAR(128) NOT NULL,
    aplica_fisica VARCHAR(4),
    aplica_moral VARCHAR(4),
    fecha_inicio DATE,
    fecha_fin DATE,
    regimenes VARCHAR(128)
);