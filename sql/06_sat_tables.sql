-- Paso 1: Seleccionar la base de datos
USE sac_db;

-- Paso 2: Crear tabla de certificados FIEL
CREATE TABLE IF NOT EXISTS sat_fiel_certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfc VARCHAR(15) NOT NULL,
    legal_name VARCHAR(255) DEFAULT NULL,
    certificate_serial VARCHAR(100) NOT NULL,
    certificate_path VARCHAR(500) NOT NULL,
    key_path VARCHAR(500) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rfc_serial (rfc, certificate_serial),
    INDEX idx_rfc (rfc),
    INDEX idx_valid_to (valid_to),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES usuarios (id) ON DELETE CASCADE
);

-- Paso 3: Crear tabla de logs de actividad
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    module VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    INDEX idx_module_record (module, record_id),
    FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

-- Paso 4: Crear tabla de tokens SAT
CREATE TABLE IF NOT EXISTS sat_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_id INT NOT NULL,
    token_value TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_certificate_id (certificate_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (certificate_id) REFERENCES sat_fiel_certificates (id) ON DELETE CASCADE
);

-- Paso 5: Crear tabla de historial de descargas
CREATE TABLE IF NOT EXISTS sat_download_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    certificate_id INT NOT NULL,
    request_type ENUM ('CFDI', 'METADATA') NOT NULL DEFAULT 'CFDI',
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    rfc_emisor VARCHAR(15) DEFAULT NULL,
    rfc_receptor VARCHAR(15) DEFAULT NULL,
    request_id VARCHAR(100) DEFAULT NULL,
    download_id VARCHAR(100) DEFAULT NULL,
    status ENUM ('REQUESTED', 'PROCESSING', 'COMPLETED', 'ERROR') NOT NULL DEFAULT 'REQUESTED',
    files_count INT DEFAULT 0,
    total_size_bytes BIGINT DEFAULT 0,
    download_path VARCHAR(500) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    requested_by INT NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_certificate_id (certificate_id),
    INDEX idx_status (status),
    INDEX idx_requested_by (requested_by),
    INDEX idx_requested_at (requested_at),
    INDEX idx_request_id (request_id),
    FOREIGN KEY (certificate_id) REFERENCES sat_fiel_certificates (id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES usuarios (id) ON DELETE CASCADE
);

-- Paso 6: Insertar registro inicial
INSERT IGNORE INTO activity_logs (
    user_id,
    action,
    description,
    ip_address,
    created_at
)
VALUES
    (
        1,
        'SYSTEM_INIT',
        'Inicialización de tablas SAT',
        '127.0.0.1',
        NOW ()
    );

-- Paso 7: Verificar que las tablas se crearon correctamente
SHOW TABLES LIKE 'sat_%';

SHOW TABLES LIKE 'activity_logs';