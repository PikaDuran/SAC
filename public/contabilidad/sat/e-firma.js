// FUNCIONES PARA E.FIRMA

// Inicialización cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    initializeFileUpload();
    initializeForm();
    checkForSuccessMessage();
});

// Verificar si hay mensaje de éxito y limpiar formulario
function checkForSuccessMessage() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        // Si hay mensaje de éxito, limpiar formulario después de 3 segundos
        setTimeout(() => {
            resetForm();
            // Ocultar el mensaje de éxito gradualmente
            successAlert.style.transition = 'all 0.5s ease';
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (successAlert.parentElement) {
                    successAlert.remove();
                }
            }, 500);
        }, 3000);
    }
}

// Inicializar manejo de archivos
function initializeFileUpload() {
    const certFileInput = document.getElementById('cert_file');
    const keyFileInput = document.getElementById('key_file');
    
    // Manejar selección de archivo .CER
    certFileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const label = e.target.nextElementSibling || e.target.parentElement.querySelector('.file-upload-label');
        
        if (file) {
            const fileText = label.querySelector('.file-text');
            fileText.textContent = file.name;
            label.style.borderColor = '#1a7f37';
            label.style.background = '#d1f7d1';
            
            // Validar extensión
            if (!file.name.toLowerCase().endsWith('.cer')) {
                showAlert('El archivo debe tener extensión .cer', 'error');
                resetFileInput(certFileInput, label);
            }
        }
    });
    
    // Manejar selección de archivo .KEY
    keyFileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const label = e.target.nextElementSibling || e.target.parentElement.querySelector('.file-upload-label');
        
        if (file) {
            const fileText = label.querySelector('.file-text');
            fileText.textContent = file.name;
            label.style.borderColor = '#1a7f37';
            label.style.background = '#d1f7d1';
            
            // Validar extensión
            if (!file.name.toLowerCase().endsWith('.key')) {
                showAlert('El archivo debe tener extensión .key', 'error');
                resetFileInput(keyFileInput, label);
            }
        }
    });
}

// Inicializar validaciones del formulario
function initializeForm() {
    const form = document.querySelector('.efirma-form');
    const rfcInput = document.getElementById('rfc');
    
    // Convertir RFC a mayúsculas automáticamente
    rfcInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
    
    // Validar formulario antes de enviar
    form.addEventListener('submit', function(e) {
        console.log('Submit detectado, enviando formulario directamente al servidor...');
        // Temporalmente deshabilitamos la validación JS para que el PHP maneje todo
        showLoading();
    });
}

// Validar formulario
function validateForm() {
    const rfc = document.getElementById('rfc').value.trim();
    const password = document.getElementById('password').value;
    const certFile = document.getElementById('cert_file').files[0];
    const keyFile = document.getElementById('key_file').files[0];
    
    console.log('Validando formulario:');
    console.log('RFC:', rfc);
    console.log('Password length:', password.length);
    console.log('Cert file:', certFile ? certFile.name : 'NO SELECCIONADO');
    console.log('Key file:', keyFile ? keyFile.name : 'NO SELECCIONADO');
    // Validar RFC
    const rfcPattern = /^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
    if (!rfcPattern.test(rfc)) {
        showAlert('Formato de RFC inválido', 'error');
        document.getElementById('rfc').focus();
        return false;
    }
    
    // Validar contraseña
    if (password.length < 4) {
        showAlert('La contraseña debe tener al menos 4 caracteres', 'error');
        document.getElementById('password').focus();
        return false;
    }
    
    // Validar archivos
    if (!certFile || !keyFile) {
        showAlert('Debe seleccionar ambos archivos (.cer y .key)', 'error');
        return false;
    }
    
    // Validar extensiones
    if (!certFile.name.toLowerCase().endsWith('.cer')) {
        showAlert('El primer archivo debe tener extensión .cer', 'error');
        return false;
    }
    
    if (!keyFile.name.toLowerCase().endsWith('.key')) {
        showAlert('El segundo archivo debe tener extensión .key', 'error');
        return false;
    }
    
    // Validar tamaños (máximo 5MB cada uno)
    if (certFile.size > 5 * 1024 * 1024 || keyFile.size > 5 * 1024 * 1024) {
        showAlert('Los archivos no pueden exceder 5MB cada uno', 'error');
        return false;
    }
    
    return true;
}

// Resetear formulario
function resetForm() {
    const form = document.querySelector('.efirma-form');
    form.reset();
    
    // Resetear labels de archivos
    const certLabel = document.querySelector('input[name="cert_file"]').parentElement.querySelector('.file-upload-label');
    const keyLabel = document.querySelector('input[name="key_file"]').parentElement.querySelector('.file-upload-label');
    
    resetFileInput(document.getElementById('cert_file'), certLabel);
    resetFileInput(document.getElementById('key_file'), keyLabel);
    
    // Quitar alertas
    removeAlerts();
}

// Resetear input de archivo
function resetFileInput(input, label) {
    input.value = '';
    const fileText = label.querySelector('.file-text');
    
    if (input.name === 'cert_file') {
        fileText.textContent = 'Seleccionar archivo .CER';
    } else {
        fileText.textContent = 'Seleccionar archivo .KEY';
    }
    
    label.style.borderColor = '#d0d7de';
    label.style.background = '#f6f8fa';
}

// Mostrar alerta
function showAlert(message, type) {
    removeAlerts();
    
    // Crear notificación elegante
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
    
    // Auto-ocultar después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Remover alertas existentes
function removeAlerts() {
    const alerts = document.querySelectorAll('.alert, .notification');
    alerts.forEach(alert => alert.remove());
}

// Mostrar loading durante validación
function showLoading() {
    const submitBtn = document.querySelector('.btn-primary');
    const originalText = submitBtn.textContent;
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Validando con SAT...';
    
    // Crear indicador de progreso
    const progressDiv = document.createElement('div');
    progressDiv.className = 'validation-progress';
    progressDiv.innerHTML = `
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <p>Validando e.Firma con el SAT, esto puede tomar unos momentos...</p>
    `;
    
    const form = document.querySelector('.efirma-form');
    form.appendChild(progressDiv);
}

// Funciones para gestión de certificados
function editCert(id) {
    // Implementar edición de certificado
    console.log('Editar certificado ID:', id);
    showAlert('Función de edición en desarrollo', 'info');
}

function deleteCert(id) {
    // Crear modal de confirmación elegante
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Eliminar e.Firma</h3>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar esta e.Firma?</p>
                <p class="text-warning">⚠️ Esta acción no se puede deshacer</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn btn-danger" onclick="confirmDelete(${id})">Eliminar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
}

function confirmDelete(id) {
    fetch('api/delete-cert.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        closeModal();
        if (data.success) {
            showAlert('e.Firma eliminada correctamente', 'success');
            // Remover fila de la tabla
            const row = document.querySelector(`tr[data-cert-id="${id}"]`);
            if (row) {
                row.remove();
            }
            // Limpiar formulario si el registro eliminado estaba seleccionado
            const form = document.querySelector('form.efirma-form');
            if (form) {
                form.reset();
            }
        } else {
            showAlert('Error al eliminar e.Firma: ' + data.message, 'error');
        }
    })
    .catch(error => {
        closeModal();
        showAlert('Error de conexión al eliminar e.Firma', 'error');
        console.error('Error:', error);
    });
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

// Utilidades
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('Copiado al portapapeles', 'success');
    }).catch(() => {
        showAlert('Error al copiar', 'error');
    });
}

// Agregar estilos para el indicador de progreso
const style = document.createElement('style');
style.textContent = `
    .validation-progress {
        margin-top: 16px;
        padding: 16px;
        background: #f6f8fa;
        border-radius: 6px;
        border: 1px solid #d0d7de;
    }
    
    .validation-progress .progress-bar {
        width: 100%;
        height: 8px;
        background: #e1e4e8;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    
    .validation-progress .progress-fill {
        height: 100%;
        background: #0969da;
        width: 0%;
        animation: progress 3s ease-in-out infinite;
    }
    
    .validation-progress p {
        margin: 0;
        font-size: 14px;
        color: #656d76;
        text-align: center;
    }
    
    @keyframes progress {
        0% { width: 0%; }
        50% { width: 70%; }
        100% { width: 100%; }
    }
`;
document.head.appendChild(style);
