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
                            <th style="width:5%;">Verificar</th>
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Inicializando sistema de peticiones v3.1 (Unificado)...');

    // Usamos el cuerpo de la tabla para delegar los eventos
    const tablaPeticionesBody = document.querySelector('#tbody-solicitudes');

    if (!tablaPeticionesBody) {
        console.error('❌ No se encontró el cuerpo de la tabla (tbody) con id="peticiones-tbody". Los botones no funcionarán.');
        return;
    }

    // Un único manejador de eventos para todos los clics en la tabla
    tablaPeticionesBody.addEventListener('click', async function(event) { // 'event' se define aquí, solucionando el error "e is not defined"
        
        const botonClickeado = event.target.closest('a.btn, button.btn');

        if (!botonClickeado || !botonClickeado.dataset.id) {
            return; // No se hizo clic en un botón de acción con data-id
        }
        
        event.preventDefault(); // Prevenir la acción por defecto del botón
        const idSolicitud = botonClickeado.dataset.id;

        // --- Lógica para VERIFICAR ---
        if (botonClickeado.classList.contains('btn-verificar-individual')) {
            botonClickeado.disabled = true;
            botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const response = await fetch('core/actualizar-estado-solicitud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_solicitud: idSolicitud, descargar_paquetes: true }) // Siempre intenta descargar al verificar
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Error en el servidor');
                
                Swal.fire('¡Éxito!', result.message || 'Estado de la solicitud verificado.', 'success').then(() => location.reload());

            } catch (error) {
                Swal.fire('Error', 'No se pudo verificar el estado: ' + error.message, 'error');
                botonClickeado.disabled = false;
                botonClickeado.innerHTML = '<i class="fas fa-sync-alt"></i>';
            }
        }

        // --- Lógica para DESCARGAR ---
        if (botonClickeado.classList.contains('btn-descargar-paquetes')) {
            botonClickeado.disabled = true;
            botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            Swal.fire({
                title: 'Descargando Paquetes',
                text: 'Conectando con el SAT, esto puede tardar un momento...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('core/actualizar-estado-solicitud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_solicitud: idSolicitud, descargar_paquetes: true })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Error en el servidor');

                Swal.fire('¡Éxito!', result.message, 'success').then(() => location.reload());

            } catch (error) {
                Swal.fire('Error', 'No se pudieron descargar los paquetes: ' + error.message, 'error');
                botonClickeado.disabled = false;
                botonClickeado.innerHTML = '<i class="fas fa-download"></i>';
            }
        }

        // --- Lógica para PROCESAR ---
        if (botonClickeado.classList.contains('btn-procesar-paquetes')) {
            botonClickeado.disabled = true;
            botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            Swal.fire({
                title: 'Procesando Facturas',
                text: 'Extrayendo, guardando y generando PDFs. Por favor, espere...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('core/procesar_paquetes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_solicitud: idSolicitud })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Error desconocido durante el procesamiento.');

                Swal.fire({
                    icon: 'success',
                    title: '¡Proceso Completado!',
                    text: result.message,
                }).then(() => {
                    location.reload();
                });

            } catch (error) {
                Swal.fire('Error', 'No se pudieron procesar los paquetes: ' + error.message, 'error');
                botonClickeado.disabled = false;
                botonClickeado.innerHTML = '<i class="fas fa-cogs"></i>';
            }
        }
    });

    console.log('✅ Sistema de peticiones v3.1 listo y unificado.');
});

    // Usando jQuery
    $(document).ready(function() {
        // Función para reordenar los IDs visuales (primera columna)
        function updateDisplayIds() {
            $('#tbody-solicitudes tr').each(function(index) {
                // El índice es base 0, por lo que el ID a mostrar es index + 1
                $(this).find('td:first').text(index + 1);
            });
        }
        
        // Delegación de eventos para el botón de eliminar por su clase CSS
        $('#tbody-solicitudes').on('click', '.btn-eliminar-solicitud', function(e) {
            e.preventDefault();
            const idSolicitud = $(this).data('id');
            const row = $(this).closest('tr');
            
            if (confirm('¿Estás seguro de que deseas eliminar la solicitud Rechazada con ID ' + idSolicitud + '? Esta acción es irreversible y eliminará los archivos de paquete asociados.')) {
                $.ajax({
                    url: 'core/eliminar-solicitud.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { id: idSolicitud },
                    beforeSend: function() {
                        row.find('button, a').prop('disabled', true).addClass('disabled');
                        row.css('opacity', 0.5);
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Éxito: ' + response.message);

                            row.remove();
                            
                            // Reordenar los IDs
                            updateDisplayIds();

                        } else {
                            alert('Error al eliminar: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error de comunicación con el servidor. Por favor, revisa la consola (F12) para más detalles.');
                        console.error('Error AJAX:', status, error, xhr.responseText);
                    },
                    complete: function() {
                        if (row.length && row.parent().length) { 
                            row.find('button, a').prop('disabled', false).removeClass('disabled');
                            row.css('opacity', 1);
                        }
                    }
                });
            }
        });
    });
</script>