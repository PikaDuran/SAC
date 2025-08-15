// --- Configuración ---
const API_URL = '/SAC/public/api/verificar_solicitud.php';

// --- Loader ---
function showLoader() {
	let loader = document.getElementById('loader-overlay');
	if (!loader) {
		loader = document.createElement('div');
		loader.id = 'loader-overlay';
		loader.style.position = 'fixed';
		loader.style.top = 0;
		loader.style.left = 0;
		loader.style.width = '100vw';
		loader.style.height = '100vh';
		loader.style.background = 'rgba(255,255,255,0.7)';
		loader.style.zIndex = 9999;
		loader.innerHTML = '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:2em;">Verificando... <span class="loader"></span></div>';
		document.body.appendChild(loader);
	} else {
		loader.style.display = 'block';
	}
}

function hideLoader() {
	const loader = document.getElementById('loader-overlay');
	if (loader) loader.style.display = 'none';
}

// --- Verificar solicitud SAT ---
async function verificarSolicitud(id) {
	showLoader();
	try {
		const formData = new FormData();
		formData.append('id', id);
		const response = await fetch(API_URL, {
			method: 'POST',
			body: formData
		});
		const data = await response.json();
		if (data.success) {
			alert('Verificación exitosa.\nStatus: ' + data.data.status + '\nMensaje: ' + data.data.mensaje_verificacion);
			actualizarFilaTabla(id, data.data.status, data.data.mensaje_verificacion, data.data.estatus_solicitud);
		} else {
			alert('Error: ' + data.message);
		}
	} catch (e) {
		alert('Error en la petición: ' + e);
	} finally {
		hideLoader();
	}
}

// --- Actualizar fila de la tabla ---
function actualizarFilaTabla(id, status, mensaje, estatus) {
	// Suponiendo que cada fila tiene id="solicitud-row-<id>"
	const row = document.getElementById('solicitud-row-' + id);
	if (row) {
		// Suponiendo que las celdas de status y mensaje tienen clases específicas
		const statusCell = row.querySelector('.celda-status');
		const mensajeCell = row.querySelector('.celda-mensaje');
		const estatusCell = row.querySelector('.celda-estatus');
		if (statusCell) statusCell.textContent = status;
		if (mensajeCell) mensajeCell.textContent = mensaje;
		if (estatusCell) estatusCell.textContent = estatus;
	}
}

// --- Asignar evento a los botones de verificación ---
function asignarEventosVerificar() {
	// Suponiendo que los botones tienen clase 'btn-verificar-sat' y data-id
	document.querySelectorAll('.btn-verificar-sat').forEach(btn => {
		btn.addEventListener('click', function() {
			const id = this.getAttribute('data-id');
			if (id) {
				if (confirm('¿Deseas verificar la solicitud SAT con id ' + id + '?')) {
					verificarSolicitud(id);
				}
			}
		});
	});
}

// --- Inicialización ---
document.addEventListener('DOMContentLoaded', function() {
	asignarEventosVerificar();
});
