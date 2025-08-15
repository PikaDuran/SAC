document.addEventListener('DOMContentLoaded', function() {
    // Manejo de menús desplegables con animaciones suaves
    const navToggles = document.querySelectorAll('.nav-toggle');
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    
    navToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const navSection = this.parentElement;
            const submenu = navSection.querySelector('.submenu');
            
            // Cerrar otros menús abiertos
            navToggles.forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    otherToggle.parentElement.classList.remove('open');
                    otherToggle.classList.remove('active');
                }
            });
            
            navSection.classList.toggle('open');
            
            // Efecto visual más sutil para el toggle
            if (navSection.classList.contains('open')) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        });
    });
    
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenuSection = this.parentElement;
            submenuSection.classList.toggle('open');
            
            // Efecto visual para submenu toggle
            if (submenuSection.classList.contains('open')) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        });
    });
    
    // Actualizar tiempo de actividad cada minuto
    setInterval(function() {
        fetch('update_activity.php')
            .catch(error => console.log('Error updating activity:', error));
    }, 60000);
    
    // Cerrar dropdown de notificaciones al hacer click fuera
    document.addEventListener('click', function(event) {
        const notificationContainer = document.querySelector('.notification-container');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (notificationContainer && !notificationContainer.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
});

// Función para toggle de notificaciones
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const isVisible = dropdown.style.display === 'block';
    
    dropdown.style.display = isVisible ? 'none' : 'block';
}
    
    // Animaciones de entrada escalonadas para las tarjetas
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Contador animado para métricas
    function animateValue(element, start, end, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            element.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }
    
    // Animar métricas cuando las tarjetas son visibles
    const metrics = document.querySelectorAll('.metric');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const value = parseInt(entry.target.textContent.replace(/,/g, ''));
                entry.target.textContent = '0';
                animateValue(entry.target, 0, value, 1500);
                observer.unobserve(entry.target);
            }
        });
    });
    
    metrics.forEach(metric => {
        observer.observe(metric);
    });
    
    // Notificaciones en tiempo real (simulado)
    function updateNotifications() {
        const bellIcon = document.querySelector('.bell-icon');
        const notificationCard = document.querySelector('.card:nth-child(3) .metric');
        
        // Simulación de nueva notificación
        if (Math.random() > 0.7) {
            bellIcon.style.animation = 'bellShake 0.5s ease-in-out';
            const currentValue = parseInt(notificationCard.textContent);
            notificationCard.textContent = currentValue + 1;
            
            // Mostrar toast de notificación
            showToast('Nueva notificación recibida', 'info');
            
            setTimeout(() => {
                bellIcon.style.animation = '';
            }, 500);
        }
    }
    
    // Verificar notificaciones cada 30 segundos
    setInterval(updateNotifications, 30000);
    
    // Sistema de toast notifications
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${type === 'info' ? 'ℹ️' : type === 'success' ? '✅' : '⚠️'}</span>
                <span class="toast-message">${message}</span>
            </div>
            <button class="toast-close">&times;</button>
        `;
        
        document.body.appendChild(toast);
        
        // Mostrar toast
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
        
        // Cerrar al hacer clic
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });
    }
    
    // Función para refrescar datos del dashboard
    function refreshDashboard() {
        const refreshBtn = document.querySelector('.refresh-btn');
        if (refreshBtn) {
            refreshBtn.classList.add('spinning');
            
            // Simular carga de datos
            setTimeout(() => {
                refreshBtn.classList.remove('spinning');
                showToast('Dashboard actualizado', 'success');
            }, 1500);
        }
    }
    
    // Manejo de teclas de acceso rápido
    document.addEventListener('keydown', function(e) {
        // Ctrl + R para refrescar dashboard
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshDashboard();
        }
        
        // Escape para cerrar menús
        if (e.key === 'Escape') {
            document.querySelectorAll('.nav-section.open').forEach(section => {
                section.classList.remove('open');
            });
        }
    });
    
    // Modo de pantalla completa para métricas
    const metricCards = document.querySelectorAll('.card');
    metricCards.forEach(card => {
        card.addEventListener('dblclick', function() {
            this.classList.toggle('fullscreen');
        });
    });
    
    // Hora en tiempo real
    function updateTime() {
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString();
        }
    }
    
    setInterval(updateTime, 1000);
    updateTime();
});

// Añadir estilos CSS para toasts y animaciones
const additionalStyles = `
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        border-left: 4px solid #0052cc;
        padding: 16px;
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        min-width: 300px;
    }
    
    .toast.show {
        transform: translateX(0);
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .toast-close {
        position: absolute;
        top: 8px;
        right: 8px;
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #6b778c;
    }
    
    @keyframes bellShake {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(10deg); }
        75% { transform: rotate(-10deg); }
    }
    
    .refresh-btn.spinning {
        animation: spin 1s linear infinite;
    }
    
    .card.fullscreen {
        position: fixed;
        top: 20px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        z-index: 1000;
        transform: scale(1);
    }
`;

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);
