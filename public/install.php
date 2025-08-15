<?php
// Script de instalación para crear la base de datos y tablas
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'sac_db';

try {
    // Conectar sin especificar base de datos
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de datos '$dbname' creada correctamente.<br>";

    // Usar la base de datos
    $pdo->exec("USE $dbname");

    // Crear tabla usuarios
    $sql = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL,
        apellido VARCHAR(50) NOT NULL,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        rol ENUM('admin','contabilidad','hr','operaciones') NOT NULL,
        estado ENUM('activo','inactivo') DEFAULT 'activo',
        fecha_ultimo_acceso DATETIME,
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Tabla 'usuarios' creada correctamente.<br>";

    // Crear tabla log_actividades
    $sql = "CREATE TABLE IF NOT EXISTS log_actividades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        actividad VARCHAR(255) NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )";
    $pdo->exec($sql);
    echo "Tabla 'log_actividades' creada correctamente.<br>";

    // Crear tabla clientes
    $sql = "CREATE TABLE IF NOT EXISTS clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        rfc VARCHAR(13) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL,
        telefono VARCHAR(15),
        direccion TEXT,
        estado ENUM('activo','inactivo') DEFAULT 'activo',
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Tabla 'clientes' creada correctamente.<br>";

    // Crear tabla solicitudes_rh
    $sql = "CREATE TABLE IF NOT EXISTS solicitudes_rh (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_nombre VARCHAR(100) NOT NULL,
        tipo_solicitud ENUM('vacaciones','permiso','licencia','otros') NOT NULL,
        fecha_inicio DATE,
        fecha_fin DATE,
        motivo TEXT,
        estado ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Tabla 'solicitudes_rh' creada correctamente.<br>";

    // Crear tabla horarios
    $sql = "CREATE TABLE IF NOT EXISTS horarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empleado_nombre VARCHAR(100) NOT NULL,
        fecha DATE NOT NULL,
        hora_entrada TIME,
        hora_salida TIME,
        horas_trabajadas DECIMAL(4,2),
        creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Tabla 'horarios' creada correctamente.<br>";

    // Verificar si el usuario admin ya existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Crear usuario admin
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, usuario, password, rol, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Admin', 'Principal', 'admin', $password_hash, 'admin', 'activo']);
        echo "Usuario admin creado correctamente.<br>";
    } else {
        echo "Usuario admin ya existe.<br>";
    }

    echo "<br><strong>Instalación completada exitosamente!</strong><br>";
    echo "<a href='login/login.html'>Ir al Login</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
