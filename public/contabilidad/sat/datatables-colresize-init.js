// DataTables y colResize para tabla de solicitudes SAT
// Este script se carga después de DataTables y colResize

document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado.');
        return;
    }
    if (typeof $.fn.colResize === 'undefined') {
        console.error('colResize no está cargado.');
        return;
    }
    // Inicializar DataTable con colResize
    $('table').DataTable({
        colResize: {
            handleWidth: 10,
            saveState: true,
            hoverClass: 'dt-colresize-hover',
            hasBoundCheck: true
        },
        paging: false,
        searching: false,
        info: false,
        ordering: true
    });
});
