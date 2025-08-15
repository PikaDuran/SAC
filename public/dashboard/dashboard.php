<?php
session_start();
require_once '../../src/config/database.php';

// Verificar si hay sesión activa
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.html');
    exit;
}

// Verificar timeout de sesión (25 minutos = 1500 segundos)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1500) {
    session_destroy();
    header('Location: ../login/login.html');
    exit;
}

$_SESSION['last_activity'] = time();

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SAC</title>
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../src/views/header.php'; ?>
            <div class="content">
                <h1>Dashboard</h1>
            </div>
            <footer class="dashboard-footer" style="position:fixed;left:240px;right:0;bottom:0;background:#fff;border-top:1px solid #d0d7de;z-index:100;box-shadow:0 -2px 8px rgba(0,0,0,0.03);padding:0;max-width:calc(100vw - 240px);height:110px;">
                <div class="stats-container" style="margin-bottom:0;padding:10px 18px 10px 18px;border:none;border-radius:0;box-shadow:none;">
                    <div class="stats-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid #d0d7de;padding-bottom:6px;">
                        <h3 class="stats-title" style="font-size:15px;font-weight:600;color:#24292f;margin:0;">Actividad Reciente</h3>
                        <span class="stats-period" style="font-size:13px;color:#656d76;">Últimas acciones del sistema</span>
                    </div>
                    <div id="activity-feed" class="activity-list empty-state" style="max-height:48px;overflow-y:auto;background:none;padding-right:4px;gap:6px;">
                        Cargando actividad...
                    </div>
                </div>
            </footer>


    </div>
    </main>
    </div>
    <script src="dashboard.js"></script>
    </main>
    <script>
        // Cargar actividad reciente de todos los usuarios
        fetch('activity_feed.php')
            .then(res => res.json())
            .then(data => {
                const feed = document.getElementById('activity-feed');
                if (!data || data.length === 0) {
                    feed.innerHTML = 'No hay actividad reciente para mostrar.';
                    return;
                }
                feed.classList.remove('empty-state');
                feed.innerHTML = data.map(item => `
      <div class="activity-item">
        <div class="activity-icon">👤</div>
        <div class="activity-content">
          <div class="activity-title">${item.username || 'Usuario'}: ${item.description}</div>
          <div class="activity-time">${new Date(item.created_at).toLocaleString('es-MX')}</div>
        </div>
      </div>
    `).join('');
            })
            .catch(() => {
                document.getElementById('activity-feed').innerHTML = 'No se pudo cargar la actividad.';
            });
    </script>
</body>

</html>