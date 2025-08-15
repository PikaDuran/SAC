// Actualizar tabla de solicitudes
function actualizarTablaSolicitudes(solicitudes) {
    const tbody = document.getElementById('tablaSolicitudes');
    if (!tbody) return;
    if (!solicitudes || solicitudes.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="empty-state">
                    <p>No hay solicitudes de descarga. Crea tu primera solicitud usando el formulario superior.</p>
                </td>
            </tr>
        `;
        return;
    }
    tbody.innerHTML = solicitudes.map(solicitud => {
        let acciones = getAccionButtons(solicitud);
        if (!acciones || acciones === '-') {
            acciones = `<button class="btn btn-secondary" onclick="verificarSolicitud(${solicitud.id})" title="Verificar estado">Verificar Estado</button>`;
        }
        return `
            <tr>
                <td>${acciones}</td>
                <td>${solicitud.rfc_emisor || '-'}</td>
                <td>${solicitud.token_sat || '-'}</td>
                <td><span class="badge badge-${getStatusColor(solicitud.estatus)}">${solicitud.estatus}</span></td>
                <td>${formatearFecha(solicitud.ultima_actualizacion)}</td>
                <td>${formatearFecha(solicitud.fecha_inicial, false)}</td>
                <td>${formatearFecha(solicitud.fecha_final, false)}</td>
                <td>${solicitud.tipo_documento || '-'}</td>
                <td>${solicitud.mensaje_verificacion || '-'}</td>
                <td>${solicitud.paquetes ?? '-'}</td>
                <td>${formatearFecha(solicitud.fecha_solicitud)}</td>
            </tr>
        `;
    }).join('');
}
// Descarga Masiva SAT - JavaScript
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar componentes
    initFormulario();
    cargarSolicitudes(); // Cargar solicitudes al inicio

    // Auto-refresh cada 30 segundos para verificar estados
    setInterval(cargarSolicitudes, 30000); // Actualizar cada 30 segundos
});

// Inicializar formulario
function initFormulario() {
    const form = document.getElementById('descargaMasivaForm');
    if (form) {
        form.addEventListener('submit', handleSolicitudDescarga);
    }

    // Validar fechas en tiempo real
    const fechaDesde = document.getElementById('fecha_desde');
    const fechaHasta = document.getElementById('fecha_hasta');

    if (fechaDesde && fechaHasta) {
        fechaDesde.addEventListener('change', validarFechas);
        fechaHasta.addEventListener('change', validarFechas);
    }
}

// Manejar solicitud de descarga
async function handleSolicitudDescarga(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const rfcSelected = document.getElementById('rfc_selected');

    if (!rfcSelected.value) {
        mostrarError('Debe seleccionar un RFC');
        return;
    }

    // Validar fechas
    if (!validarFechas()) {
        return;
    }

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    try {
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Procesando...';

        // Primero intentar sin contraseña
        let response = await fetch('api/solicitar-descarga.php', {
            method: 'POST',
            body: formData
        });

        let result = await response.json();

        // Si necesita contraseña, solicitarla
        if (!result.success && result.needs_password) {
            const password = await solicitarPasswordCertificado(rfcSelected.value);
            if (password) {
                // Agregar contraseña y reintentar
                formData.append('password_' + rfcSelected.value, password);

                response = await fetch('api/solicitar-descarga.php', {
                    method: 'POST',
                    body: formData
                });

                result = await response.json();
            } else {
                mostrarError('Contraseña requerida para procesar la solicitud');
                return;
            }
        }

        if (result.success) {
            mostrarExito(`
                ✅ Solicitud enviada exitosamente al SAT<br>
                <strong>ID de Solicitud:</strong> ${result.data.request_id}<br>
                <strong>RFC:</strong> ${result.data.rfc}<br>
                <strong>Tipo:</strong> ${result.data.tipo}<br>
                <strong>Estado SAT:</strong> ${result.data.mensaje_sat}
            `);
            limpiarFormulario();
            cargarSolicitudes(); // Recargar tabla después de crear solicitud
        } else {
            mostrarError(result.message || 'Error al procesar solicitud');
        }

    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión. Intente nuevamente.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Solicitar contraseña del certificado mediante modal
async function solicitarPasswordCertificado(certificateId) {
    return new Promise((resolve) => {
        // Crear modal para solicitar contraseña
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>🔒 Contraseña del Certificado</h3>
                </div>
                <div class="modal-body">
                    <p>Para realizar la solicitud al SAT se requiere la contraseña del certificado FIEL:</p>
                    <div class="form-group">
                        <label for="cert-password">Contraseña:</label>
                        <input type="password" id="cert-password" class="form-control" 
                               placeholder="Ingrese la contraseña del certificado" required>
                        <small class="text-muted">Esta contraseña no se almacena, solo se usa para esta operación.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cancelarPassword()">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmarPassword()">
                        Continuar
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Focus en el input
        setTimeout(() => {
            document.getElementById('cert-password').focus();
        }, 100);

        // Handlers globales para el modal
        window.cancelarPassword = () => {
            document.body.removeChild(modal);
            resolve(null);
        };

        window.confirmarPassword = () => {
            const password = document.getElementById('cert-password').value;
            if (!password.trim()) {
                mostrarError('La contraseña es requerida');
                return;
            }
            document.body.removeChild(modal);
            resolve(password);
        };

        // Enter para confirmar
        document.getElementById('cert-password').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                window.confirmarPassword();
            }
        });

        // Escape para cancelar
        document.addEventListener('keydown', function escapeHandler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escapeHandler);
                window.cancelarPassword();
            }
        });
    });
}

// Cargar solicitudes existentes
async function cargarSolicitudes() {
    try {
        // Llamar directamente al endpoint real de solicitudes
        const response = await fetch('api/listar-solicitudes.php');
        const result = await response.json();
        if (result.success) {
            actualizarTablaSolicitudes(result.data);
        } else {
            console.error('Error del API:', result.message);
            actualizarTablaSolicitudes([]); // Mostrar tabla vacía
        }
    } catch (error) {
        console.error('Error al cargar solicitudes:', error);
        actualizarTablaSolicitudes([]); // Mostrar tabla vacía en caso de error
    }
}

// Actualizar tabla de solicitudes (restaurada)
function actualizarTablaSolicitudes(solicitudes) {
    const tbody = document.getElementById('tablaSolicitudes');
    if (!tbody) return;
    if (!solicitudes || solicitudes.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="empty-state">
                    <p>No hay solicitudes de descarga. Crea tu primera solicitud usando el formulario superior.</p>
                </td>
            </tr>
        `;
        return;
    }
    tbody.innerHTML = solicitudes.map(solicitud => {
        let acciones = getAccionButtons(solicitud);
        if (!acciones || acciones === '-') {
            acciones = `<button class=\"btn btn-secondary\" onclick=\"verificarSolicitud(${solicitud.id})\" title=\"Verificar estado\">Verificar Estado</button>`;
        }
        // Mostrar el texto de estatus tal cual viene
        const estatusTexto = solicitud.estatus || '-';
        return `
            <tr>
                <td>${acciones}</td>
                <td>${solicitud.rfc_emisor || '-'}</td>
                <td>${solicitud.token_sat || '-'}</td>
                <td><span class=\"badge badge-${getStatusColor(estatusTexto)}\">${estatusTexto}</span></td>
                <td>${formatearFecha(solicitud.ultima_actualizacion)}</td>
                <td>${formatearFecha(solicitud.fecha_inicial, false)}</td>
                <td>${formatearFecha(solicitud.fecha_final, false)}</td>
                <td>${solicitud.tipo_documento || '-'}</td>
                <td>${solicitud.mensaje_verificacion || '-'}</td>
                <td>${solicitud.paquetes ?? '-'}</td>
                <td>${formatearFecha(solicitud.fecha_solicitud)}</td>
            </tr>
        `;
    }).join('');
}

// Obtener botones de acción según estado
function getAccionButtons(solicitud) {
    const buttons = [];
    if (solicitud.estatus === 'REQUESTED' || solicitud.estatus === 'PROCESSING') {
        buttons.push(`
            <button class="btn btn-secondary" onclick="verificarSolicitud(${solicitud.id})" 
                    title="Verificar estado">
                Verificar Estado
            </button>
        `);
    }
    if (solicitud.estatus === 'COMPLETED' && Number(solicitud.paquetes) > 0) {
        buttons.push(`
            <button class="btn-icon" onclick="descargarPaquetes(${solicitud.id})" 
                    title="Verificar y Descargar">
                📥 VERIFICAR Y DESCARGAR
            </button>
        `);
    }
    return buttons.join(' ') || '-';
}

// Verificar estado de solicitud
async function verificarSolicitud(solicitudId) {
    // Mostrar datos actuales de la fila antes de la verificación
    const btn = document.querySelector(`button[onclick=\"verificarSolicitud(${solicitudId})\"]`);
    let originalHtml = '';
    if (btn) {
        originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...';
    }
    // Mostrar loader global discreto
    mostrarLoaderSAT();
    try {
        // Enviar el id (autoincremental) como parámetro al endpoint
        const response = await fetch(`/SAC/public/api/verificar_solicitud.php?id=${solicitudId}`);
        let result = null;
        let errorText = '';
        try {
            result = await response.json();
        } catch (e) {
            errorText = 'Respuesta no es JSON válido';
        }
        if (result && result.success) {
            mostrarExito('Estado actualizado correctamente');
        } else {
            mostrarModalError('Error al verificar solicitud', (result && result.message) || errorText || 'Error desconocido');
        }
        // Siempre recargar la tabla visual, pase lo que pase
        cargarSolicitudes();
    } catch (error) {
        console.error('Error:', error);
        mostrarModalError('Error de conexión', error.message || error);
    } finally {
        ocultarLoaderSAT();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

// Loader discreto para operaciones SAT
function mostrarLoaderSAT() {
    if (document.getElementById('sat-loader-overlay')) return;
    const overlay = document.createElement('div');
    overlay.id = 'sat-loader-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = 0;
    overlay.style.left = 0;
    overlay.style.width = '100vw';
    overlay.style.height = '100vh';
    overlay.style.background = 'rgba(255,255,255,0.3)';
    overlay.style.zIndex = 9999;
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.innerHTML = `<div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0002; padding: 32px 48px; display: flex; flex-direction: column; align-items: center;">
        <div class="spinner-border text-primary" style="width: 2.5rem; height: 2.5rem; margin-bottom: 12px;" role="status"></div>
        <div style="font-size: 1.1rem; color: #333;">Consultando estado SAT...</div>
    </div>`;
    document.body.appendChild(overlay);
}

function ocultarLoaderSAT() {
    const overlay = document.getElementById('sat-loader-overlay');
    if (overlay) overlay.remove();
}

function mostrarModalError(titulo, mensaje) {
    // Remover modales previos
    const prev = document.querySelector('.modal-overlay-error');
    if (prev) prev.remove();
    const modal = document.createElement('div');
    modal.className = 'modal-overlay-error';
    modal.innerHTML = `
        <div class="modal">
            <div class="modal-header"><h3>❌ ${titulo}</h3></div>
            <div class="modal-body"><p>${mensaje}</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay-error').remove()">Cerrar</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Descargar paquetes
async function descargarPaquetes(solicitudId) {
    try {
        const response = await fetch(`api/descargar-paquetes.php?id=${solicitudId}`);
        const result = await response.json();

        if (result.success) {
            // Iniciar descarga
            window.location.href = result.download_url;
            mostrarExito('Descarga iniciada');
        } else {
            mostrarError(result.message || 'Error al descargar');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión');
    }
}

// Utilidades
function validarFechas() {
    const fechaDesde = document.getElementById('fecha_desde').value;
    const fechaHasta = document.getElementById('fecha_hasta').value;

    if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
        mostrarError('La fecha inicial no puede ser mayor que la fecha final');
        return false;
    }

    return true;
}

function limpiarFormulario() {
    document.getElementById('descargaMasivaForm').reset();

    // Restaurar fechas por defecto
    const hoy = new Date();
    const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

    document.getElementById('fecha_desde').value = primerDia.toISOString().split('T')[0];
    document.getElementById('fecha_hasta').value = hoy.toISOString().split('T')[0];
}

function getStatusColor(status) {
    const colors = {
        'REQUESTED': 'warning',
        'PROCESSING': 'info',
        'COMPLETED': 'success',
        'ERROR': 'danger',
        'Solicitud Aceptada': 'success',
        'Rechazada': 'danger',
        'En Proceso': 'info',
        'Terminada': 'primary',
        'Vencida': 'secondary',
        'Error': 'danger'
    };
    return colors[status] || 'secondary';
}

function formatearFecha(fecha, incluirHora = true) {
    if (!fecha) return '-';
    // Si la fecha es tipo 'YYYY-MM-DD', mostrarla tal cual (sin desfase)
    if (/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y}`;
    }
    // Si es datetime, usar Date para formatear
    const date = new Date(fecha);
    const opciones = incluirHora
        ? { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }
        : { year: 'numeric', month: '2-digit', day: '2-digit' };
    return date.toLocaleDateString('es-MX', opciones);
}

function getPaquetesInfo(paquetes) {
    if (!paquetes) return '-';

    try {
        const data = JSON.parse(paquetes);
        return `${data.length || 0} paquete(s)`;
    } catch (e) {
        return '-';
    }
}

function mostrarError(mensaje) {
    // Crear notificación elegante de error
    showNotification(mensaje, 'error');
}

function mostrarExito(mensaje) {
    // Crear notificación elegante de éxito
    showNotification(mensaje, 'success');
}

function showNotification(message, type) {
    // Remover notificaciones existentes
    const existing = document.querySelectorAll('.notification');
    existing.forEach(n => n.remove());

    // Crear nueva notificación
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;

    const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${icon}</span>
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    // Agregar al DOM
    document.body.appendChild(notification);

    // Auto-ocultar según el tipo (éxito más rápido)
    const hideDelay = type === 'success' ? 3000 : 5000; // 3 seg para éxito, 5 seg para error
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, hideDelay);
}
