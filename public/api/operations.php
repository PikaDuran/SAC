<?php

/**
 * Controlador de operaciones con auditoría completa
 * Maneja todas las operaciones CRUD con logging automático
 */

session_start();
require_once '../../src/config/database.php';
require_once '../../src/helpers/auth.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabase();

    switch ($module) {
        case 'clientes':
            handleClienteOperations($pdo, $method, $action);
            break;

        case 'rh_solicitudes':
            handleRHSolicitudes($pdo, $method, $action);
            break;

        case 'usuarios':
            handleUsuarios($pdo, $method, $action);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Módulo no válido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}

function handleClienteOperations($pdo, $method, $action)
{
    checkAuth(['admin', 'operaciones']);

    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nombre");
                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                logUserActivity(LOG_VIEW, "Consultó lista de clientes (" . count($clientes) . " registros)", MODULE_CLIENTES);
                echo json_encode($clientes);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                INSERT INTO clientes (nombre, rfc, email, telefono, direccion, estado) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['nombre'],
                $data['rfc'],
                $data['email'],
                $data['telefono'],
                $data['direccion'],
                $data['estado'] ?? 'activo'
            ]);

            $clienteId = $pdo->lastInsertId();

            logUserActivity(
                LOG_CREATE,
                "Creó cliente: {$data['nombre']} (RFC: {$data['rfc']})",
                MODULE_CLIENTES,
                $clienteId
            );

            echo json_encode(['success' => true, 'id' => $clienteId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];

            $stmt = $pdo->prepare("
                UPDATE clientes 
                SET nombre=?, rfc=?, email=?, telefono=?, direccion=?, estado=? 
                WHERE id=?
            ");

            $stmt->execute([
                $data['nombre'],
                $data['rfc'],
                $data['email'],
                $data['telefono'],
                $data['direccion'],
                $data['estado'],
                $id
            ]);

            logUserActivity(
                LOG_UPDATE,
                "Actualizó cliente: {$data['nombre']} (ID: {$id})",
                MODULE_CLIENTES,
                $id
            );

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $id = $_GET['id'];

            // Obtener info del cliente antes de eliminarlo
            $stmt = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);

            logUserActivity(
                LOG_DELETE,
                "Eliminó cliente: {$cliente['nombre']} (ID: {$id})",
                MODULE_CLIENTES,
                $id
            );

            echo json_encode(['success' => true]);
            break;
    }
}

function handleRHSolicitudes($pdo, $method, $action)
{
    checkAuth(['admin', 'hr']);

    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->query("
                    SELECT s.*, u.nombre as solicitante_nombre 
                    FROM solicitudes_rh s 
                    LEFT JOIN usuarios u ON s.usuario_id = u.id 
                    ORDER BY s.fecha_solicitud DESC
                ");
                $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                logUserActivity(LOG_VIEW, "Consultó solicitudes de RH (" . count($solicitudes) . " registros)", MODULE_SOLICITUDES);
                echo json_encode($solicitudes);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                INSERT INTO solicitudes_rh (usuario_id, tipo_solicitud, descripcion, fecha_inicio, fecha_fin, estado) 
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $data['tipo_solicitud'],
                $data['descripcion'],
                $data['fecha_inicio'],
                $data['fecha_fin']
            ]);

            $solicitudId = $pdo->lastInsertId();

            logUserActivity(
                LOG_CREATE,
                "Creó solicitud de RH: {$data['tipo_solicitud']}",
                MODULE_SOLICITUDES,
                $solicitudId
            );

            echo json_encode(['success' => true, 'id' => $solicitudId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];

            $stmt = $pdo->prepare("
                UPDATE solicitudes_rh 
                SET estado=?, comentarios_admin=? 
                WHERE id=?
            ");

            $stmt->execute([
                $data['estado'],
                $data['comentarios_admin'],
                $id
            ]);

            logUserActivity(
                LOG_UPDATE,
                "Actualizó solicitud de RH: Estado {$data['estado']} (ID: {$id})",
                MODULE_SOLICITUDES,
                $id
            );

            echo json_encode(['success' => true]);
            break;
    }
}

function handleUsuarios($pdo, $method, $action)
{
    checkAuth(['admin']); // Solo admin puede gestionar usuarios

    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->query("SELECT id, usuario, nombre, apellido, email, rol, estado FROM usuarios ORDER BY nombre");
                $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                logUserActivity(LOG_VIEW, "Consultó lista de usuarios (" . count($usuarios) . " registros)", MODULE_USUARIOS);
                echo json_encode($usuarios);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (usuario, password, nombre, apellido, email, rol, estado) 
                VALUES (?, ?, ?, ?, ?, ?, 'activo')
            ");

            $stmt->execute([
                $data['usuario'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['nombre'],
                $data['apellido'],
                $data['email'],
                $data['rol']
            ]);

            $usuarioId = $pdo->lastInsertId();

            logUserActivity(
                LOG_CREATE,
                "Creó usuario: {$data['usuario']} ({$data['nombre']} {$data['apellido']}) - Rol: {$data['rol']}",
                MODULE_USUARIOS,
                $usuarioId
            );

            echo json_encode(['success' => true, 'id' => $usuarioId]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];

            $updateFields = [];
            $params = [];

            if (!empty($data['nombre'])) {
                $updateFields[] = "nombre=?";
                $params[] = $data['nombre'];
            }
            if (!empty($data['apellido'])) {
                $updateFields[] = "apellido=?";
                $params[] = $data['apellido'];
            }
            if (!empty($data['email'])) {
                $updateFields[] = "email=?";
                $params[] = $data['email'];
            }
            if (!empty($data['rol'])) {
                $updateFields[] = "rol=?";
                $params[] = $data['rol'];
            }
            if (!empty($data['estado'])) {
                $updateFields[] = "estado=?";
                $params[] = $data['estado'];
            }
            if (!empty($data['password'])) {
                $updateFields[] = "password=?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $params[] = $id;

            $sql = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            logUserActivity(
                LOG_UPDATE,
                "Actualizó usuario ID: {$id} - Campos: " . implode(', ', array_keys($data)),
                MODULE_USUARIOS,
                $id
            );

            echo json_encode(['success' => true]);
            break;
    }
}
