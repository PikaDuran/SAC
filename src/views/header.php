<?php // Asegura que Font Awesome esté disponible en todas las páginas principales 
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<header class="top-bar">
    <div class="breadcrumb">
        Dashboard / Inicio
    </div>
    <div class="user-header-group">
        <div class="notification-bell-container">
            <span class="bell-icon" id="notifBell">
                <i class="fa-solid fa-bell"></i>
                <span id="notifCount" class="notif-count" style="display:none;"></span>
            </span>
            <div id="notifDropdown" class="notif-dropdown" style="display:none; left:0; right:auto;">
                <div class="notif-header">
                    <span>Notificaciones</span>
                    <button id="markAllRead" class="notif-mark-read">Marcar todas como leídas</button>
                </div>
                <div id="notifList" class="notif-list">
                    <div class="notif-empty">Sin notificaciones recientes</div>
                </div>
            </div>
        </div>
        <div class="user-info-content">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1) . substr($_SESSION['apellido'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['rol']); ?></span>
            </div>
        </div>
        <a href="/SAC/public/dashboard/logout.php" class="logout-btn">Cerrar sesión</a>
    </div>
</header>
<link rel="stylesheet" href="/SAC/public/assets/css/header-menu.css?v=1" />
<script>
    // Notificaciones personales (usuario logueado)
    const notifBell = document.getElementById('notifBell');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifCount = document.getElementById('notifCount');
    const notifList = document.getElementById('notifList');
    const markAllReadBtn = document.getElementById('markAllRead');

    let notifOpen = false;

    function fetchNotifications() {
        fetch('/SAC/public/dashboard/user_notifications.php')
            .then(res => res.json())
            .then(data => {
                // Actualizar contador
                if (data.newCount > 0) {
                    notifCount.textContent = data.newCount;
                    notifCount.style.display = 'inline-block';
                } else {
                    notifCount.style.display = 'none';
                }
                // Renderizar lista
                notifList.innerHTML = '';
                if (!data.notifications || data.notifications.length === 0) {
                    notifList.innerHTML = '<div class="notif-empty">Sin notificaciones recientes</div>';
                } else {
                    data.notifications.forEach(n => {
                        notifList.innerHTML += `<div class="notif-item${n.is_read == 0 ? ' notif-unread' : ''}" data-id="${n.id}">
                        <span>${n.description}</span>
                        <span class="notif-time">${new Date(n.created_at).toLocaleString('es-MX')}</span>
                    </div>`;
                    });
                }
                // Agregar evento para marcar como leída
                document.querySelectorAll('.notif-item.notif-unread').forEach(item => {
                    item.addEventListener('click', function() {
                        const notifId = this.getAttribute('data-id');
                        fetch('/SAC/public/dashboard/mark_notifications_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'id=' + encodeURIComponent(notifId)
                        }).then(() => {
                            this.classList.remove('notif-unread');
                            // Actualizar contador visual
                            let count = parseInt(notifCount.textContent) || 0;
                            if (count > 1) {
                                notifCount.textContent = count - 1;
                            } else {
                                notifCount.style.display = 'none';
                            }
                        });
                    });
                });
            });
    }

    notifBell.addEventListener('click', function(e) {
        notifOpen = !notifOpen;
        notifDropdown.style.display = notifOpen ? 'block' : 'none';
        if (notifOpen) fetchNotifications();
    });

    document.addEventListener('click', function(e) {
        if (!notifDropdown.contains(e.target) && !notifBell.contains(e.target)) {
            notifDropdown.style.display = 'none';
            notifOpen = false;
        }
    });

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            fetch('/SAC/public/dashboard/mark_notifications_read.php', {
                    method: 'POST'
                })
                .then(() => fetchNotifications());
        });
    }

    // Refrescar contador cada 60s
    setInterval(fetchNotifications, 60000);
    fetchNotifications();
</script>
<!-- El bloque de usuario y logout vuelve a estar dentro de user-header-group, junto con la campana -->
</header>