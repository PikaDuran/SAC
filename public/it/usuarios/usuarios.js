// Administración de Usuarios - JavaScript
document.addEventListener('DOMContentLoaded', function () {
    cargarUsuarios();
    cargarEstadisticas();
    
    // Cerrar modal al hacer clic fuera
    document.getElementById('modal-usuario').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModalUsuario();
        }
    });
});

// Variables globales
let usuarioEditando = null;

// Función alternativa con SweetAlert2 (respaldo)
function abrirModalUsuarioSweetAlert() {
    Swal.fire({
        title: 'Nuevo Usuario',
        html: `
            <form id="swal-form">
                <div style="margin-bottom: 15px;">
                    <input type="text" id="swal-nombre" class="swal2-input" placeholder="Nombre" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <input type="text" id="swal-apellido" class="swal2-input" placeholder="Apellido" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <input type="text" id="swal-usuario" class="swal2-input" placeholder="Usuario" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <input type="password" id="swal-password" class="swal2-input" placeholder="Contraseña" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <select id="swal-rol" class="swal2-input" required>
                        <option value="">Seleccionar Rol</option>
                        <option value="admin">Administrador</option>
                        <option value="contabilidad">Contabilidad</option>
                        <option value="operaciones">Operaciones</option>
                        <option value="hr">Recursos Humanos</option>
                    </select>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Crear Usuario',
        cancelButtonText: 'Cancelar',
        width: '500px',
        customClass: {
            container: 'swal-container'
        },
        preConfirm: () => {
            const nombre = document.getElementById('swal-nombre').value;
            const apellido = document.getElementById('swal-apellido').value;
            const usuario = document.getElementById('swal-usuario').value;
            const password = document.getElementById('swal-password').value;
            const rol = document.getElementById('swal-rol').value;

            if (!nombre || !apellido || !usuario || !password || !rol) {
                Swal.showValidationMessage('Todos los campos son requeridos');
                return false;
            }

            return { nombre, apellido, usuario, password, rol, estado: 'activo' };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            crearUsuarioDirecto(result.value);
        }
    });
}

// Crear usuario directamente (para usar con SweetAlert)
async function crearUsuarioDirecto(userData) {
    try {
        const formData = new FormData();
        formData.append('action', 'crear');
        formData.append('nombre', userData.nombre);
        formData.append('apellido', userData.apellido);
        formData.append('usuario', userData.usuario);
        formData.append('password', userData.password);
        formData.append('rol', userData.rol);
        formData.append('estado', userData.estado);

        const response = await fetch('api/usuarios-api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            mostrarExito('Usuario creado correctamente');
            cargarUsuarios();
            cargarEstadisticas();
        } else {
            mostrarError('Error al crear usuario: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión al crear usuario');
    }
}

// Cargar lista de usuarios
async function cargarUsuarios() {
    try {
        const response = await fetch('api/usuarios-api.php?action=listar');
        const result = await response.json();

        if (result.success) {
            mostrarUsuarios(result.data);
        } else {
            mostrarError('Error al cargar usuarios: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión al cargar usuarios');
    }
}

// Mostrar usuarios en la tabla
function mostrarUsuarios(usuarios) {
    const tbody = document.getElementById('tabla-usuarios');
    
    if (!usuarios || usuarios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    <i class="fa-solid fa-users"></i><br>
                    No hay usuarios registrados
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = usuarios.map(usuario => {
        const nombreCompleto = `${usuario.nombre} ${usuario.apellido}`;
        const rolBadge = getRolBadge(usuario.rol);
        const estadoBadge = getEstadoBadge(usuario.estado);
        const ultimoAcceso = formatearFecha(usuario.fecha_ultimo_acceso);
        const fechaCreado = formatearFecha(usuario.creado_en);

        return `
            <tr>
                <td>${usuario.id}</td>
                <td>${nombreCompleto}</td>
                <td>${usuario.usuario}</td>
                <td>${rolBadge}</td>
                <td>${estadoBadge}</td>
                <td>${ultimoAcceso}</td>
                <td>${fechaCreado}</td>
                <td>
                    <div class="acciones-btn">
                        <button class="btn-accion btn-editar" onclick="editarUsuario(${usuario.id})" title="Editar">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <button class="btn-accion btn-toggle-estado" onclick="toggleEstadoUsuario(${usuario.id}, '${usuario.estado}')" title="Cambiar estado">
                            <i class="fa-solid fa-toggle-${usuario.estado === 'activo' ? 'on' : 'off'}"></i>
                        </button>
                        <button class="btn-accion btn-eliminar" onclick="eliminarUsuario(${usuario.id}, '${usuario.usuario}')" title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// Cargar estadísticas
async function cargarEstadisticas() {
    try {
        const response = await fetch('api/usuarios-api.php?action=estadisticas');
        const result = await response.json();

        if (result.success) {
            const stats = result.data;
            document.getElementById('total-usuarios').textContent = stats.total || 0;
            document.getElementById('usuarios-activos').textContent = stats.activos || 0;
            document.getElementById('usuarios-admin').textContent = stats.admins || 0;
            document.getElementById('usuarios-inactivos').textContent = stats.inactivos || 0;
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

// Abrir modal para nuevo usuario
function abrirModalUsuario(usuario = null) {
    console.log('Abriendo modal para usuario:', usuario); // Debug
    
    // Crear modal dinámicamente para evitar problemas CSS
    const modalHTML = `
        <div id="dynamic-modal" style="
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(0, 0, 0, 0.8) !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            z-index: 2147483647 !important;
            opacity: 1 !important;
        ">
            <div style="
                background: white !important;
                border-radius: 8px !important;
                padding: 0 !important;
                max-width: 500px !important;
                width: 90% !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            ">
                <div style="background: #007cba; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                    <h3 style="margin: 0;">${usuario ? 'Editar Usuario' : 'Nuevo Usuario'}</h3>
                </div>
                <div style="padding: 20px;">
                    <form id="dynamic-form">
                        <input type="hidden" id="dynamic-usuario-id" value="${usuario ? usuario.id : ''}">
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nombre:</label>
                            <input type="text" id="dynamic-nombre" value="${usuario ? usuario.nombre : ''}" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Apellido:</label>
                            <input type="text" id="dynamic-apellido" value="${usuario ? usuario.apellido : ''}" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Usuario:</label>
                            <input type="text" id="dynamic-usuario" value="${usuario ? usuario.usuario : ''}" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Contraseña:</label>
                            <input type="password" id="dynamic-password" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                                   ${usuario ? '' : 'required'}>
                            ${usuario ? '<small style="color: #666;">Dejar en blanco para mantener la contraseña actual</small>' : ''}
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Rol:</label>
                            <select id="dynamic-rol" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                                <option value="">Seleccionar Rol</option>
                                <option value="admin" ${usuario && usuario.rol === 'admin' ? 'selected' : ''}>Administrador</option>
                                <option value="contabilidad" ${usuario && usuario.rol === 'contabilidad' ? 'selected' : ''}>Contabilidad</option>
                                <option value="operaciones" ${usuario && usuario.rol === 'operaciones' ? 'selected' : ''}>Operaciones</option>
                                <option value="hr" ${usuario && usuario.rol === 'hr' ? 'selected' : ''}>Recursos Humanos</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Estado:</label>
                            <select id="dynamic-estado" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="activo" ${!usuario || usuario.estado === 'activo' ? 'selected' : ''}>Activo</option>
                                <option value="inactivo" ${usuario && usuario.estado === 'inactivo' ? 'selected' : ''}>Inactivo</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div style="padding: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="cerrarModalDinamico()" 
                            style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="button" onclick="guardarUsuarioDinamico()" 
                            style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente si existe
    const existingModal = document.getElementById('dynamic-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Añadir modal al body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Guardar referencia del usuario editando
    usuarioEditando = usuario;
    
    console.log('Modal dinámico creado y mostrado');
}

// Cerrar modal dinámico
function cerrarModalDinamico() {
    const modal = document.getElementById('dynamic-modal');
    if (modal) {
        modal.remove();
    }
    usuarioEditando = null;
}

// Guardar usuario desde modal dinámico
async function guardarUsuarioDinamico() {
    const nombre = document.getElementById('dynamic-nombre').value.trim();
    const apellido = document.getElementById('dynamic-apellido').value.trim();
    const usuario = document.getElementById('dynamic-usuario').value.trim();
    const password = document.getElementById('dynamic-password').value;
    const rol = document.getElementById('dynamic-rol').value;
    const estado = document.getElementById('dynamic-estado').value;

    // Validaciones
    if (!nombre || !apellido || !usuario || !rol) {
        mostrarError('Todos los campos son requeridos');
        return;
    }

    if (!usuarioEditando && !password) {
        mostrarError('La contraseña es requerida para usuarios nuevos');
        return;
    }

    if (password && password.length < 6) {
        mostrarError('La contraseña debe tener al menos 6 caracteres');
        return;
    }

    try {
        const formData = new FormData();
        const action = usuarioEditando ? 'editar' : 'crear';
        
        formData.append('action', action);
        formData.append('nombre', nombre);
        formData.append('apellido', apellido);
        formData.append('usuario', usuario);
        formData.append('rol', rol);
        formData.append('estado', estado);
        
        if (password) {
            formData.append('password', password);
        }
        
        if (usuarioEditando) {
            formData.append('id', usuarioEditando.id);
        }

        const response = await fetch('api/usuarios-api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            mostrarExito(result.message);
            cerrarModalDinamico();
            cargarUsuarios();
            cargarEstadisticas();
        } else {
            mostrarError('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión');
    }
}

// Cerrar modal original (mantener compatibilidad)

// Cerrar modal
function cerrarModalUsuario() {
    const modal = document.getElementById('modal-usuario');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    usuarioEditando = null;
}

// Guardar usuario (crear o editar)
async function guardarUsuario() {
    const form = document.getElementById('form-usuario');
    const formData = new FormData(form);

    // Validaciones
    if (!validarFormulario()) {
        return;
    }

    try {
        const action = usuarioEditando ? 'editar' : 'crear';
        formData.append('action', action);

        const response = await fetch('api/usuarios-api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: usuarioEditando ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente',
                timer: 2000,
                timerProgressBar: true
            });

            cerrarModalUsuario();
            cargarUsuarios();
            cargarEstadisticas();
        } else {
            mostrarError(result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión al guardar usuario');
    }
}

// Editar usuario
async function editarUsuario(id) {
    try {
        const response = await fetch(`api/usuarios-api.php?action=obtener&id=${id}`);
        const result = await response.json();

        if (result.success) {
            abrirModalUsuario(result.data);
        } else {
            mostrarError('Error al cargar datos del usuario');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error de conexión');
    }
}

// Toggle estado usuario
async function toggleEstadoUsuario(id, estadoActual) {
    const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
    const accion = nuevoEstado === 'activo' ? 'activar' : 'desactivar';

    const result = await Swal.fire({
        title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} usuario?`,
        text: `El usuario será ${nuevoEstado}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado === 'activo' ? '#28a745' : '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('action', 'toggle_estado');
            formData.append('id', id);
            formData.append('estado', nuevoEstado);

            const response = await fetch('api/usuarios-api.php', {
                method: 'POST',
                body: formData
            });

            const apiResult = await response.json();

            if (apiResult.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: `Usuario ${nuevoEstado === 'activo' ? 'activado' : 'desactivado'} correctamente`,
                    timer: 2000,
                    timerProgressBar: true
                });

                cargarUsuarios();
                cargarEstadisticas();
            } else {
                mostrarError(apiResult.message);
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarError('Error de conexión');
        }
    }
}

// Eliminar usuario
async function eliminarUsuario(id, usuario) {
    const result = await Swal.fire({
        title: '¿Eliminar usuario?',
        html: `Se eliminará permanentemente el usuario:<br><strong>${usuario}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);

            const response = await fetch('api/usuarios-api.php', {
                method: 'POST',
                body: formData
            });

            const apiResult = await response.json();

            if (apiResult.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: 'Usuario eliminado correctamente',
                    timer: 2000,
                    timerProgressBar: true
                });

                cargarUsuarios();
                cargarEstadisticas();
            } else {
                mostrarError(apiResult.message);
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarError('Error de conexión');
        }
    }
}

// Validar formulario
function validarFormulario() {
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value;
    const rol = document.getElementById('rol').value;

    if (!nombre || !apellido || !usuario || !rol) {
        mostrarError('Todos los campos son requeridos');
        return false;
    }

    if (!usuarioEditando && !password) {
        mostrarError('La contraseña es requerida para nuevos usuarios');
        return false;
    }

    if (password && password.length < 6) {
        mostrarError('La contraseña debe tener al menos 6 caracteres');
        return false;
    }

    if (usuario.length < 3) {
        mostrarError('El nombre de usuario debe tener al menos 3 caracteres');
        return false;
    }

    return true;
}

// Utilidades
function getRolBadge(rol) {
    const badges = {
        'admin': '<span class="badge badge-admin">Administrador</span>',
        'contabilidad': '<span class="badge badge-contabilidad">Contabilidad</span>',
        'operaciones': '<span class="badge badge-operaciones">Operaciones</span>',
        'hr': '<span class="badge badge-hr">Recursos Humanos</span>'
    };
    return badges[rol] || `<span class="badge">${rol}</span>`;
}

function getEstadoBadge(estado) {
    return estado === 'activo' 
        ? '<span class="badge badge-activo">Activo</span>'
        : '<span class="badge badge-inactivo">Inactivo</span>';
}

function formatearFecha(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    return date.toLocaleDateString('es-MX', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonColor: '#dc3545'
    });
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalUsuario();
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modal-usuario').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalUsuario();
    }
});
