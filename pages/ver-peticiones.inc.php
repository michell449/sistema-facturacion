<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
	<div class="card bg-white shadow-sm mt-4 mb-4">
		<div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
			<h2 class="fw-bold m-0"><i class="fas fa-list-alt me-2"></i>Peticiones de Descarga SAT</h2>
			<div>
			</div>
		</div>
		<div class="card-body">
			<a href="panel?pg=cargar-facturas" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Regresar</a>
			<?php require __DIR__ . '/../core/filtros-solicitudes.php'; ?>

			<div class="table-responsive">
				<table class="table table-bordered table-striped table-hover mb-2" id="tabla-solicitudes" style="width:100%;">
					<thead class="thead-dark text-center">
						<tr>
							<th style="width:5%;">ID</th>
							<th style="width:16%;">Folio SAT</th>
							<th style="width:12%;">RFC</th>
							<th style="width:8%;">Tipo</th>
							<th style="width:18%;">Rango / UUID</th>
							<th style="width:8%;">Paquetes</th>
							<th style="width:10%;">Estado</th>
							<th style="width:11%;">Creada</th>
							<th style="width:12%;">Últ. Verif.</th>
							<th style="width:10%;">Acciones</th>
						</tr>
					</thead>
					<tbody class="text-center" id="tbody-solicitudes">
						<?php require __DIR__ . '/../core/listar-solicitudes.php'; ?>
					</tbody>
				</table>

				<div class="d-flex justify-content-end mb-2">
					<button id="btn-verificar" class="btn btn-primary">
						<i class="fas fa-sync-alt me-1"></i>Verificar Solicitudes
					</button>
				</div>
			</div>

			<details class="mt-3">
				<summary class="fw-bold">Descripción de estados</summary>
				<div class="mt-2">
					<?php require __DIR__ . '/../core/estados-solicitudes-descripcion.php'; ?>
				</div>
			</details>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Inicializando sistema de verificación...');
    
    const btnVerificar = document.getElementById('btn-verificar');
    
    if (!btnVerificar) {
        console.error('❌ No se encontró el botón btn-verificar');
        return;
    }
    
    console.log('✅ Botón encontrado, agregando funcionalidad...');
    
    // Función para mostrar estados
    function parseEstadoBadge(estado) {
        const map = {
            'pendiente': '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>Pendiente</span>',
            'aceptada': '<span class="badge bg-info"><i class="fas fa-hourglass-half me-1"></i>Aceptada</span>',
            'terminada': '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terminada</span>',
            'rechazada': '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rechazada</span>',
            'error': '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>',
            'vencida': '<span class="badge bg-warning"><i class="fas fa-hourglass-end me-1"></i>Vencida</span>'
        };
        return map[estado] || '<span class="badge bg-secondary">' + estado + '</span>';
    }
    
    // Función para hacer petición POST a actualizar-estado-solicitud.php
    async function actualizarEstadoSolicitud(idSolicitud) {
        console.log('🔄 Actualizando estado para solicitud:', idSolicitud);
        
        try {
            const response = await fetch('core/actualizar-estado-solicitud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_solicitud: parseInt(idSolicitud)
                })
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('📊 Respuesta actualizar estado:', data);
            return data;
            
        } catch (error) {
            console.error('❌ Error actualizando estado:', error);
            return { success: false, error: error.message };
        }
    }
    
    // Función para hacer petición POST a verificar-descarga.php
    async function verificarDescargaSAT(idSolicitud) {
        console.log('🔍 Verificando con SAT para solicitud:', idSolicitud);
        
        try {
            const response = await fetch('core/verificar-descarga.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_solicitud: parseInt(idSolicitud)
                })
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('📨 Respuesta verificación SAT:', data);
            return data;
            
        } catch (error) {
            console.error('❌ Error verificando con SAT:', error);
            return { success: false, error: error.message };
        }
    }
    
    // Función para actualizar una fila en la tabla
    function actualizarFilaTabla(idSolicitud, data) {
        const fila = document.querySelector(`tr[data-id="${idSolicitud}"]`);
        if (!fila) {
            console.warn('⚠️ No se encontró fila para solicitud:', idSolicitud);
            return;
        }
        
        // Actualizar estado
        const estadoCol = fila.querySelector('.estado-col');
        if (estadoCol && data.estado) {
            estadoCol.innerHTML = parseEstadoBadge(data.estado);
        }
        
        // Actualizar paquetes
        const pkgCell = fila.cells[5];
        const descargados = data.paquetes_descargados || 0;
        const total = data.total_paquetes || 0;
        pkgCell.innerHTML = `<span class="badge bg-dark">${descargados}/${total}</span>`;
        
        // Actualizar última verificación
        const ultVerifCell = fila.cells[8];
        const ahora = new Date();
        ultVerifCell.textContent = ahora.toLocaleString();
        
        console.log('✅ Fila actualizada para solicitud:', idSolicitud);
    }
    
    // Función para verificar una solicitud individual
    async function verificarSolicitudIndividual(idSolicitud) {
        console.log(`🎯 Iniciando verificación individual para: ${idSolicitud}`);
        
        try {
            // 1. Primero verificamos con el SAT
            const resultadoSAT = await verificarDescargaSAT(idSolicitud);
            
            if (!resultadoSAT.success) {
                throw new Error(resultadoSAT.message || 'Error al verificar con SAT');
            }
            
            console.log('✅ Verificación SAT iniciada, esperando procesamiento...');
            
            // 2. Esperar para que se procese en segundo plano
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            // 3. Obtener el estado actualizado
            const estadoActualizado = await actualizarEstadoSolicitud(idSolicitud);
            
            if (!estadoActualizado.success) {
                throw new Error(estadoActualizado.message || 'Error al obtener estado actualizado');
            }
            
            console.log('✅ Estado actualizado obtenido:', estadoActualizado.estado);
            
            return {
                success: true,
                id: idSolicitud,
                estado: estadoActualizado.estado,
                total_paquetes: estadoActualizado.total_paquetes,
                paquetes_descargados: estadoActualizado.paquetes_descargados,
                data: estadoActualizado
            };
            
        } catch (error) {
            console.error(`❌ Error en verificación individual ${idSolicitud}:`, error);
            return {
                success: false,
                id: idSolicitud,
                error: error.message
            };
        }
    }
    
    // Función principal para verificar todas las solicitudes
    async function verificarTodasLasSolicitudes() {
        console.log('🚀 Iniciando verificación de todas las solicitudes...');
        
        const filas = document.querySelectorAll('tr[data-id]');
        console.log(`📊 Encontradas ${filas.length} solicitudes`);
        
        if (filas.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No hay solicitudes',
                text: 'No hay solicitudes para verificar.'
            });
            return;
        }
        
        // Deshabilitar botón principal
        btnVerificar.disabled = true;
        const btnOriginalHTML = btnVerificar.innerHTML;
        btnVerificar.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i>Verificando...';
        
        // Pedir confirmación
        const { value: confirmar } = await Swal.fire({
            title: 'Verificar todas las solicitudes',
            html: `Se verificarán <strong>${filas.length}</strong> solicitudes con el SAT.<br>
                   <small>Este proceso puede tomar varios minutos.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Iniciar verificación',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false
        });
        
        if (!confirmar) {
            btnVerificar.disabled = false;
            btnVerificar.innerHTML = btnOriginalHTML;
            return;
        }
        
        // Iniciar proceso con barra de progreso
        let procesadas = 0;
        const total = filas.length;
        const resultados = [];
        
        Swal.fire({
            title: 'Verificando solicitudes SAT',
            html: `Conectando con el servidor SAT...<br>
                   <div class="progress mt-3" style="height: 20px;">
                     <div class="progress-bar" id="swal-progress-bar" role="progressbar" style="width: 0%">0%</div>
                   </div>
                   <p class="mt-2" id="swal-progress-text">0/${total} procesadas</p>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                procesarSolicitudesSecuencialmente();
            }
        });
        
        async function procesarSolicitudesSecuencialmente() {
            for (let i = 0; i < filas.length; i++) {
                const fila = filas[i];
                const idSolicitud = fila.getAttribute('data-id');
                
                // Actualizar progreso
                procesadas++;
                const porcentaje = Math.round((procesadas / total) * 100);
                document.getElementById('swal-progress-bar').style.width = `${porcentaje}%`;
                document.getElementById('swal-progress-bar').textContent = `${porcentaje}%`;
                document.getElementById('swal-progress-text').textContent = `${procesadas}/${total} procesadas`;
                
                // Mostrar estado de verificación en la fila
                const estadoCol = fila.querySelector('.estado-col');
                if (estadoCol) {
                    estadoCol.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin me-1"></i>Verificando SAT</span>';
                }
                
                console.log(`📝 Procesando solicitud ${procesadas}/${total}: ID ${idSolicitud}`);
                
                try {
                    // Verificar la solicitud
                    const resultado = await verificarSolicitudIndividual(idSolicitud);
                    resultados.push(resultado);
                    
                    // Actualizar la tabla si fue exitoso
                    if (resultado.success) {
                        actualizarFilaTabla(idSolicitud, resultado);
                    } else {
                        // Mostrar error en la fila
                        if (estadoCol) {
                            estadoCol.innerHTML = '<span class="badge bg-danger">Error</span>';
                        }
                    }
                    
                } catch (error) {
                    console.error('❌ Error en procesamiento:', error);
                    resultados.push({ success: false, id: idSolicitud, error: error.message });
                }
                
                // Pequeña pausa entre solicitudes
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
            
            // Mostrar resultados finales
            const exitosas = resultados.filter(r => r.success).length;
            const fallidas = resultados.filter(r => !r.success).length;
            
            Swal.fire({
                icon: exitosas === total ? 'success' : (exitosas > 0 ? 'warning' : 'error'),
                title: 'Verificación completada',
                html: `Resultados:<br>
                       <span class="text-success">✓ ${exitosas} solicitudes verificadas</span><br>
                       <span class="text-danger">✗ ${fallidas} con errores</span>`,
                confirmButtonText: 'Aceptar'
            });
            
            // Restaurar botón
            btnVerificar.disabled = false;
            btnVerificar.innerHTML = btnOriginalHTML;
        }
    }
    
    // Event listener para el botón principal
    btnVerificar.addEventListener('click', verificarTodasLasSolicitudes);
    
    // Event listener para botones individuales de verificación
    document.addEventListener('click', function(e) {
        const btnIndividual = e.target.closest('.btn-verificar');
        if (btnIndividual && !btnIndividual.id) { // Solo botones individuales, no el principal
            e.preventDefault();
            
            const fila = btnIndividual.closest('tr');
            const idSolicitud = fila.getAttribute('data-id');
            
            console.log('🔍 Verificación individual para:', idSolicitud);
            
            // Mostrar estado de carga
            const estadoCol = fila.querySelector('.estado-col');
            const estadoOriginal = estadoCol ? estadoCol.innerHTML : '';
            
            if (estadoCol) {
                estadoCol.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin me-1"></i>Verificando</span>';
            }
            
            // Deshabilitar botón temporalmente
            btnIndividual.disabled = true;
            
            // Ejecutar verificación
            verificarSolicitudIndividual(idSolicitud).then(resultado => {
                // Rehabilitar botón
                btnIndividual.disabled = false;
                
                if (resultado.success) {
                    actualizarFilaTabla(idSolicitud, resultado);
                    Swal.fire({
                        icon: 'success',
                        title: 'Verificación exitosa',
                        text: `Estado actualizado: ${resultado.estado}`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    // Restaurar estado original en caso de error
                    if (estadoCol) {
                        estadoCol.innerHTML = estadoOriginal;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: resultado.error || 'Error al verificar',
                        timer: 3000
                    });
                }
            });
        }
    });
    
    console.log('✅ Sistema de verificación inicializado correctamente');
});
</script>