<?php
session_start();
require_once '../../src/helpers/db.php';
require_once '../../src/models/User.php';
require_once '../../src/helpers/security.php';
require_once '../../src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$usuario || !$password) {
        http_response_code(400);
        echo 'Usuario y contraseña requeridos.';
        exit;
    }

    $userModel = new User();
    $user = $userModel->getByUsername($usuario);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['estado'] !== 'activo') {
            http_response_code(403);
            echo 'Usuario inactivo.';
            exit;
        }

        // Login exitoso
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['last_activity'] = time();


        header('Location: ../dashboard/dashboard.php');
        exit;
    } else {
        http_response_code(401);
        echo 'Credenciales incorrectas.';
        exit;
    }
}
