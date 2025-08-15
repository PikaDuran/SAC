<?php
function checkAuth($allowedRoles = [])
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../login/login.html');
        exit;
    }

    // Verificar timeout de sesión (25 minutos = 1500 segundos)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1500) {
        session_destroy();
        header('Location: ../../login/login.html');
        exit;
    }

    // Verificar rol si se especifica
    if (!empty($allowedRoles) && !in_array($_SESSION['rol'], $allowedRoles)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Acceso denegado. No tienes permisos para esta sección.';
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function getCurrentUser()
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nombre' => $_SESSION['nombre'] ?? '',
        'apellido' => $_SESSION['apellido'] ?? '',
        'rol' => $_SESSION['rol'] ?? ''
    ];
}
