<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
            <h2 class="fw-bold m-0"><i class="fas fa-list-alt me-2"></i>Peticiones de Descarga SAT</h2>
            <div>
            </div>
        </div>
        <div class="card-body">
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
                <a href="panel?pg=cargar-facturas" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Regresar</a>
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
        console.log('🔧 Inicializando sistema de verificación v2.0...');

        const btnVerificarTodas = document.getElementById('btn-verificar'); // Renombrado para más claridad

        if (!btnVerificarTodas) {
            console.error('❌ No se encontró el botón con id="btn-verificar"');
            return;
        }

        console.log('✅ Botón principal encontrado, agregando funcionalidad...');

        // Función para mostrar los badges de estado
        function parseEstadoBadge(estado) {
            const map = {
                'pendiente': '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>Pendiente</span>',
                'aceptada': '<span class="badge bg-info"><i class="fas fa-hourglass-half me-1"></i>Aceptada</span>',
                'terminada': '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terminada</span>',
                'rechazada': '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rechazada</span>',
                'error': '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>',
                'vencida': '<span class="badge bg-warning"><i class="fas fa-hourglass-end me-1"></i>Vencida</span>'
            };
            return map[estado] || `<span class="badge bg-dark">${estado}</span>`;
        }

        async function verificarConSAT(idSolicitud = null) {
            const payload = {};
            if (idSolicitud) {
                payload.id_solicitud = parseInt(idSolicitud, 10);
                console.log(`🔄 Verificando estado para solicitud individual: ${idSolicitud}`);
            } else {
                console.log('🔄 Verificando estado para TODAS las solicitudes pendientes...');
            }

            try {
                const response = await fetch('core/actualizar-estado-solicitud.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
                }

                const data = await response.json();
                console.log('📊 Respuesta del servidor:', data);
                return data;

            } catch (error) {
                console.error('❌ Error en la llamada de verificación:', error);
                return {
                    success: false,
                    message: error.message
                };
            }
        }

        // Función para actualizar una fila específica en la tabla
        function actualizarFilaTabla(idSolicitud, nuevoEstado) {
            const fila = document.querySelector(`tr[data-id="${idSolicitud}"]`);
            if (!fila) {
                console.warn('⚠️ No se encontró la fila para la solicitud:', idSolicitud);
                return;
            }

            // Actualizar columna de estado
            const estadoCol = fila.querySelector('.estado-col');
            if (estadoCol) {
                estadoCol.innerHTML = parseEstadoBadge(nuevoEstado);
            }

            const ultVerifCell = fila.cells[8];
            if (ultVerifCell) {
                ultVerifCell.textContent = new Date().toLocaleString();
            }

            console.log(`✅ Fila actualizada para la solicitud: ${idSolicitud}`);
        }

        btnVerificarTodas.addEventListener('click', async function() {
            console.log(' Iniciando verificación de todas las solicitudes...');

            const btnOriginalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i> Verificando...';

            const resultado = await verificarConSAT(); // No se pasa ID para verificar todas

            this.disabled = false;
            this.innerHTML = btnOriginalHTML;

            if (resultado.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Verificación Completada',
                    text: resultado.message,
                });

                if (resultado.nuevos_estados) {
                    for (const id in resultado.nuevos_estados) {
                        actualizarFilaTabla(id, resultado.nuevos_estados[id]);
                    }
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error en la Verificación',
                    text: resultado.message || 'Ocurrió un error desconocido.',
                });
            }
        });


        document.addEventListener('click', async function(e) {
            const btnIndividual = e.target.closest('.btn-verificar-individual'); // Usar una clase específica
            if (!btnIndividual) {
                return;
            }
            e.preventDefault();

            const idSolicitud = btnIndividual.getAttribute('data-id');
            const fila = btnIndividual.closest('tr');
            const estadoCol = fila ? fila.querySelector('.estado-col') : null;
            const estadoOriginalHTML = estadoCol ? estadoCol.innerHTML : '';

            console.log(`🔍 Iniciando verificación individual para: ${idSolicitud}`);

            // Mostrar estado de carga
            if (estadoCol) {
                estadoCol.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin me-1"></i>Verificando...</span>';
            }
            btnIndividual.disabled = true;

            const resultado = await verificarConSAT(idSolicitud);

            btnIndividual.disabled = false;

            if (resultado.success && resultado.nuevos_estados && resultado.nuevos_estados[idSolicitud]) {
                const nuevoEstado = resultado.nuevos_estados[idSolicitud];
                actualizarFilaTabla(idSolicitud, nuevoEstado);
                Swal.fire({
                    icon: 'success',
                    title: 'Verificación Exitosa',
                    text: `El estado de la solicitud #${idSolicitud} es ahora: ${nuevoEstado}`,
                    timer: 2500,
                    showConfirmButton: false
                });
            } else {
                if (estadoCol) {
                    estadoCol.innerHTML = estadoOriginalHTML; // Restaurar en caso de error
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: resultado.message || `No se pudo verificar la solicitud #${idSolicitud}.`,
                });
            }
        });

        document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-descargar-paquetes');
    if (!btn) return;

    const fila = btn.closest('tr');
    const idSolicitud = fila.getAttribute('data-id');

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';

    try {
        const resp = await fetch('core/actualizar-estado-solicitud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_solicitud: parseInt(idSolicitud, 10) })
        });
        const data = await resp.json();
        console.log('Respuesta descarga paquetess:', data);

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Proceso completado',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
            // Recargar la página para que se muestren los links ZIP
            location.reload();
        } else {
            Swal.fire('Error', data.message || 'No se pudo descargar paquetes', 'error');
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'Error de conexión al servidor', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});


        console.log('✅ Sistema de verificación v2.0 inicializado correctamente.');
    });
</script>