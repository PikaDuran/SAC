CREATE TABLE IF NOT EXISTS cfdi_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    archivo VARCHAR(512) NOT NULL,
    uuid VARCHAR(64),
    estado VARCHAR(32) NOT NULL,
    mensaje TEXT,
    fecha DATETIME NOT NULL
);