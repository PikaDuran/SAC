document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Siempre prevenir el envío default
    
    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value.trim();
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Validación frontend
    if (!usuario || !password) {
        showSweetError('Por favor, ingresa usuario y contraseña.');
        return;
    }

    if (usuario.length < 3) {
        showSweetError('El usuario debe tener al menos 3 caracteres.');
        return;
    }

    if (password.length < 6) {
        showSweetError('La contraseña debe tener al menos 6 caracteres.');
        return;
    }

    // Mostrar estado de carga
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Verificando...';

    try {
        // Enviar datos via fetch
        const formData = new FormData();
        formData.append('usuario', usuario);
        formData.append('password', password);

        const response = await fetch('login.php', {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            // Login exitoso - redirigir
            window.location.href = '../dashboard/dashboard.php';
        } else {
            // Error en login
            const errorText = await response.text();
            showSweetError(errorText || 'Credenciales incorrectas.');
        }
    } catch (error) {
        showSweetError('Error de conexión. Intenta nuevamente.');
    } finally {
        // Restaurar botón
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Entrar';
    }
});

function showSweetError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error de acceso',
        text: message,
        confirmButtonText: 'Intentar de nuevo',
        confirmButtonColor: '#3b82f6',
        customClass: {
            popup: 'login-error-popup'
        },
        timer: 5000,
        timerProgressBar: true
    });
    
    // Vibración en dispositivos móviles
    if (navigator.vibrate) {
        navigator.vibrate(200);
    }
}

// Funcionalidad del ojo para mostrar/ocultar contraseña
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = this.querySelector('.eye-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.textContent = '🙈';
    } else {
        passwordInput.type = 'password';
        eyeIcon.textContent = '👁️';
    }
});

function showError(message) {
    const errorDiv = document.getElementById('loginError');
    errorDiv.textContent = message;
    errorDiv.classList.add('show');
    
    // Vibración en dispositivos móviles
    if (navigator.vibrate) {
        navigator.vibrate(200);
    }
}

// Mejorar UX con animaciones de focus
document.querySelectorAll('.input-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
        if (this.value === '') {
            this.parentElement.classList.remove('focused');
        }
    });
});

// Auto-focus mejorado
document.addEventListener('DOMContentLoaded', function() {
    const usuarioInput = document.getElementById('usuario');
    setTimeout(() => {
        usuarioInput.focus();
    }, 300);
});
