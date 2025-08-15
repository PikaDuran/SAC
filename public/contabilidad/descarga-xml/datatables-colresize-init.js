// DataTables y colResize para tabla de solicitudes SAT
// Este script se carga después de DataTables y colResize

document.addEventListener('DOMContentLoaded', function() {
    // Cargar DataTables y colResize si no están presentes
    if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado.');
        return;
    }
    if (typeof $.fn.colResize === 'undefined') {
        console.error('colResize no está cargado.');
        return;
    }
    // Inicializar DataTable con colReorder
    $('#tabla-solicitudes').DataTable({
        colReorder: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        autoWidth: true,
        scrollX: true,
        ordering: true,
        info: true,
        searching: true
    });
});
