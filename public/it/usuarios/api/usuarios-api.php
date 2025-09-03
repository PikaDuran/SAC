<?php
// API para Administración de Usuarios - Solo Admin
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../../src/helpers/auth.php';
require_once __DIR__ . '/../../../../src/config/database.php';

// Solo admin puede acceder a este API
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado'
    ]);
    exit;
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado. Solo administradores pueden acceder.'
    ]);
    exit;
}

try {
    $pdo = getDatabase();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'listar':
            listarUsuarios($pdo);
            break;

        case 'obtener':
            obtenerUsuario($pdo);
            break;

        case 'crear':
            crearUsuario($pdo);
            break;

        case 'editar':
            editarUsuario($pdo);
            break;

        case 'eliminar':
            eliminarUsuario($pdo);
            break;

        case 'toggle_estado':
            toggleEstadoUsuario($pdo);
            break;

        case 'estadisticas':
            obtenerEstadisticas($pdo);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Listar todos los usuarios
function listarUsuarios($pdo)
{
    $stmt = $pdo->query("
        SELECT id, nombre, apellido, usuario, rol, estado, 
               fecha_ultimo_acceso, creado_en
        FROM usuarios 
        ORDER BY creado_en DESC
    ");

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $usuarios
    ]);
}

// Obtener un usuario específico
function obtenerUsuario($pdo)
{
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('ID de usuario requerido');
    }

    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, usuario, rol, estado
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    echo json_encode([
        'success' => true,
        'data' => $usuario
    ]);
}

// Crear nuevo usuario
function crearUsuario($pdo)
{
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $estado = $_POST['estado'] ?? 'activo';

    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($usuario) || empty($password) || empty($rol)) {
        throw new Exception('Todos los campos son requeridos');
    }

    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    if (strlen($usuario) < 3) {
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }

    // Verificar que el usuario no exista
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);

    if ($stmt->fetch()) {
        throw new Exception('El nombre de usuario ya existe');
    }

    // Crear usuario
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, apellido, usuario, password, rol, estado) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$nombre, $apellido, $usuario, $passwordHash, $rol, $estado]);

    // Log de actividad
    logUserActivity('CREATE_USER', "Usuario creado: {$usuario} (Rol: {$rol})", 'USUARIOS', $pdo->lastInsertId());

    echo json_encode([
        'success' => true,
        'message' => 'Usuario creado correctamente',
        'data' => ['id' => $pdo->lastInsertId()]
    ]);
}

// Editar usuario existente
function editarUsuario($pdo)
{
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $estado = $_POST['estado'] ?? 'activo';

    if (!$id) {
        throw new Exception('ID de usuario requerido');
    }

    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($usuario) || empty($rol)) {
        throw new Exception('Nombre, apellido, usuario y rol son requeridos');
    }

    if (strlen($usuario) < 3) {
        throw new Exception('El nombre de usuario debe tener al menos 3 caracteres');
    }

    // Verificar que el usuario no exista (excepto el actual)
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
    $stmt->execute([$usuario, $id]);

    if ($stmt->fetch()) {
        throw new Exception('El nombre de usuario ya existe');
    }

    // Preparar query de actualización
    if (!empty($password)) {
        if (strlen($password) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET nombre = ?, apellido = ?, usuario = ?, password = ?, rol = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $apellido, $usuario, $passwordHash, $rol, $estado, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET nombre = ?, apellido = ?, usuario = ?, rol = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $apellido, $usuario, $rol, $estado, $id]);
    }

    // Log de actividad
    logUserActivity('UPDATE_USER', "Usuario actualizado: {$usuario} (Rol: {$rol})", 'USUARIOS', $id);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario actualizado correctamente'
    ]);
}

// Eliminar usuario
function eliminarUsuario($pdo)
{
    $id = $_POST['id'] ?? null;

    if (!$id) {
        throw new Exception('ID de usuario requerido');
    }

    // No permitir eliminar el usuario actual
    if ($id == $_SESSION['user_id']) {
        throw new Exception('No puedes eliminar tu propio usuario');
    }

    // Obtener datos del usuario antes de eliminar
    $stmt = $pdo->prepare("SELECT usuario, rol FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    // Eliminar usuario
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);

    // Log de actividad
    logUserActivity('DELETE_USER', "Usuario eliminado: {$usuario['usuario']} (Rol: {$usuario['rol']})", 'USUARIOS', $id);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario eliminado correctamente'
    ]);
}

// Cambiar estado de usuario (activo/inactivo)
function toggleEstadoUsuario($pdo)
{
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;

    if (!$id || !$estado) {
        throw new Exception('ID y estado son requeridos');
    }

    if (!in_array($estado, ['activo', 'inactivo'])) {
        throw new Exception('Estado no válido');
    }

    // No permitir desactivar el usuario actual
    if ($id == $_SESSION['user_id'] && $estado === 'inactivo') {
        throw new Exception('No puedes desactivar tu propio usuario');
    }

    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    // Actualizar estado
    $stmt = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);

    // Log de actividad
    $accion = $estado === 'activo' ? 'ACTIVATE_USER' : 'DEACTIVATE_USER';
    logUserActivity($accion, "Usuario {$estado}: {$usuario['usuario']}", 'USUARIOS', $id);

    echo json_encode([
        'success' => true,
        'message' => "Usuario {$estado} correctamente"
    ]);
}

// Obtener estadísticas de usuarios
function obtenerEstadisticas($pdo)
{
    $stats = [];

    // Total de usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Usuarios activos
    $stmt = $pdo->query("SELECT COUNT(*) as activos FROM usuarios WHERE estado = 'activo'");
    $stats['activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['activos'];

    // Usuarios inactivos
    $stmt = $pdo->query("SELECT COUNT(*) as inactivos FROM usuarios WHERE estado = 'inactivo'");
    $stats['inactivos'] = $stmt->fetch(PDO::FETCH_ASSOC)['inactivos'];

    // Administradores
    $stmt = $pdo->query("SELECT COUNT(*) as admins FROM usuarios WHERE rol = 'admin'");
    $stats['admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['admins'];

    // Por rol
    $stmt = $pdo->query("
        SELECT rol, COUNT(*) as cantidad 
        FROM usuarios 
        GROUP BY rol
    ");
    $stats['por_rol'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}
