CREATE DATABASE IF NOT EXISTS sac_db DEFAULT CHARACTER
SET
    utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sac_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM ('admin', 'contabilidad', 'hr', 'operaciones') NOT NULL,
    estado ENUM ('activo', 'inactivo') DEFAULT 'activo',
    fecha_ultimo_acceso DATETIME,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS log_actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    actividad VARCHAR(255) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
);

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    rfc VARCHAR(13) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(15),
    direccion TEXT,
    estado ENUM ('activo', 'inactivo') DEFAULT 'activo',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS solicitudes_rh (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_nombre VARCHAR(100) NOT NULL,
    tipo_solicitud ENUM ('vacaciones', 'permiso', 'licencia', 'otros') NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE,
    motivo TEXT,
    estado ENUM ('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_nombre VARCHAR(100) NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME,
    hora_salida TIME,
    horas_trabajadas DECIMAL(4, 2),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Usuario inicial admin/admin123
INSERT INTO
    usuarios (nombre, apellido, usuario, password, rol, estado)
VALUES
    (
        'Admin',
        'Principal',
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin',
        'activo'
    );

-- La contraseña hash corresponde a 'admin123' usando password_hash