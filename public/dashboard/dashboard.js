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
});
