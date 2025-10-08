<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Cargar CFDI</h2>
        </div>

        <!-- Opciones de carga -->
        <div class="card-body">
            <div class="row g-4">

                <!-- Conexión con SAT -->
                <div class="col-md-6">
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Descargar facturas desde SAT</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Conecta tu cuenta del SAT para descargar automáticamente tus facturas.</p>
                            <!-- Botón que abre el modal de conexión -->
                            <button type="button" class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#modalSAT">
                                <i class="fas fa-cloud-download-alt"></i> Conectar con SAT
                            </button>
                            <a href="panel?pg=ver-peticiones" class="btn btn-success w-100">
                                <i class="fas fa-list"></i> Ver peticiones
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Carga manual -->
                <div class="col-md-6">
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Cargar Facturas Manualmente</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-body">
                                <form action="../../app-m/core/cargar-xml.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="xmlFile" class="form-label">Subir un solo XML</label>
                                        <input type="file" id="xmlFile" name="xmlFile" class="form-control" accept=".xml" required>
                                    </div>
                                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#cfdiModal"> Cargar Archivo</button>
                                </form>
                            </div>
                            <div class="card-body">
                                <form action="../core/cargar-xml.php" method="POST" enctype="multipart/form-data" id="form-manual">
                                    <div class="mb-3">
                                        <label for="zipFile" class="form-label">Subir archivo ZIP con varios CFDI</label>
                                        <input type="file" id="zipFile" class="form-control" accept=".zip">
                                    </div>
                                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#cfdiModal" onclick="enviarArchivosParse()">
                                        <i class="fas fa-upload"></i> Cargar Archivo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal conexión SAT -->
            <div class="modal fade" id="modalSAT" tabindex="-1" aria-labelledby="modalSATLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <!-- Encabezado -->
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalSATLabel">Conectar con SAT</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <!-- Cuerpo -->
                        <div class="modal-body">
                            <ul class="nav nav-tabs mb-3" id="satTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="efirma-tab" data-bs-toggle="tab" data-bs-target="#efirmaSAT" type="button" role="tab">
                                        Acceso con e.firma
                                    </button>
                                </li>
                                <!-- <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginSAT" type="button" role="tab">
                                        Acceso con RFC y Contraseña
                                    </button>
                                </li> -->
                            </ul>

                            <div class="tab-content">

                                <div class="tab-pane fade show active" id="efirmaSAT" role="tabpanel">
                                    <form id="form-autenticacion-efirma">
                                        <div class="mb-3">
                                            <label for="cerFile" class="form-label">Archivo .cer</label>
                                            <input type="file" id="cerFile" name="cerFile" class="form-control" accept=".cer" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="keyFile" class="form-label">Archivo .key</label>
                                            <input type="file" id="keyFile" name="keyFile" class="form-control" accept=".key" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="passwordFiel" class="form-label">Contraseña FIEL</label>
                                            <input type="password" id="passwordFiel" name="password" class="form-control" required>
                                        </div>
                                        <div class="modal-footer mt-3 p-0">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            <button type="submit" class="btn btn-primary">
                                                Autenticar y Conectar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal descarga CFDI  -->
            <div class="modal fade" id="modalDescarga" tabindex="-1" aria-labelledby="modalDescargaLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalDescargaLabel">
                                <i class="fas fa-download me-2"></i>Descargar CFDI desde SAT
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>Recomendaciones para mejor rendimiento:</strong><br>
                                    • Use rangos máximos de 15-31 días<br>
                                    • Evite períodos muy amplios<br>
                                    • El SAT puede tardar hasta 72hrs en procesar su solicitud<br>
                                </small>
                            </div>

                            <form id="form-descarga-sat" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tipo de facturas</label>
                                    <select class="form-select" name="tipo_descarga" required>
                                        <option value="recibidas">Recibidas</option>
                                        <option value="emitidas">Emitidas</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">RFC</label>
                                    <input type="text" class="form-control" name="rfc" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fecha inicio</label>
                                    <input type="date" class="form-control" name="fecha_inicio" id="fecha_inicio" required
                                        max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fecha fin</label>
                                    <input type="date" class="form-control" name="fecha_fin" id="fecha_fin" required
                                        max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-12">
                                    <div id="fecha-validation" class="text-danger small" style="display: none;">
                                        <i class="fas fa-exclamation-triangle me-1"></i>La fecha de inicio debe ser menor que la fecha final.
                                    </div>
                                    <div id="fecha-info" class="text-muted small mt-1">
                                        <span id="dias-rango">0 días seleccionados</span>
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-success" id="btn-solicitar">
                                        <i class="fas fa-download me-1"></i> Solicitar Descarga
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para subir archivo xml y leer información -->

            <div class="modal fade r" id="cfdiModal" tabindex="-1" aria-labelledby="modalCfdi" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalDescargaLabel">Ver datos de CFDI</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-bordered ">
                                <tbody id="cfdiReviewBody">
                                    <thead>
                                        <tr>
                                            <th>Seleccionar</th>
                                            <th>#</th>
                                            <th>UUID</th>
                                            <th>Fecha</th>
                                            <th>RFC Emisor</th>
                                            <th>RFC Receptor</th>
                                            <th>Subtotal</th>
                                            <th>Total</th>
                                            <th>Serie</th>
                                            <th>Folio Fiscal</th>
                                            <th>Estado UUID</th>
                                        </tr>
                                    </thead>
                                </tbody>
                            </table>
                            <div id="cfdiParseErrors" class="text-danger"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Facturas cargadas -->
            <div class="card-header mt-3">
                <h3 class="card-title">Facturas Cargadas</h3>

            </div>
        </div>
        <?php include __DIR__ . '/../core/listar-facturas.php'; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modalSat = new bootstrap.Modal(document.getElementById('modalSAT'));
        const modalDescarga = new bootstrap.Modal(document.getElementById('modalDescarga'));
        let verificationInterval = null;
        let currentRequestId = null;
        let autenticadoRFC = null;

        const formAutenticacion = document.getElementById('form-autenticacion-efirma');
        const formDescarga = document.getElementById('form-descarga-sat');
        const tipoDescargaSelect = document.querySelector('select[name="tipo_descarga"]');
        const rfcInput = document.querySelector('input[name="rfc"]');

        function actualizarRFCPorTipo(tipo) {
            if (!autenticadoRFC) return;
            if (tipo === 'emitidas' || tipo === 'recibidas') {
                rfcInput.value = autenticadoRFC;
            }
        }

        tipoDescargaSelect.addEventListener('change', (e) => {
            actualizarRFCPorTipo(e.target.value);
        });

        // ---- AUTENTICAR EFIRMA ----
        formAutenticacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formAutenticacion);

            Swal.fire({
                title: 'Autenticando...',
                text: 'Por favor, espere mientras validamos su e.firma.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('core/autenticar-sat.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (!result.success) throw new Error(result.message);

                autenticadoRFC = result.rfc;

                Swal.fire('¡Éxito!', `Autenticado correctamente para el RFC: ${autenticadoRFC}`, 'success');

                modalSat.hide();
                modalDescarga.show();

                actualizarRFCPorTipo(tipoDescargaSelect.value);
            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        });

        formDescarga.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (verificationInterval) {
                clearInterval(verificationInterval);
                verificationInterval = null;
            }

            const formData = new FormData(formDescarga);
            const data = Object.fromEntries(formData.entries());
            data.tipo = data.tipo_descarga;

            const fechaInicio = new Date(data.fecha_inicio);
            const fechaFin = new Date(data.fecha_fin);

            if (fechaInicio >= fechaFin) {
                Swal.fire('Rango inválido', 'La fecha de inicio debe ser menor que la fecha final.', 'warning');
                return;
            }

            if (!data.fecha_inicio || !data.fecha_fin) {
                Swal.fire('Atención', 'Debe seleccionar una fecha de inicio y fin.', 'warning');
                return;
            }

            const diffTime = Math.abs(fechaFin - fechaInicio);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 31) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Rango muy amplio',
                    html: `El rango de <strong>${diffDays} días</strong> es demasiado amplio.<br>Use rangos de máximo 31 días.`,
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            try {
                Swal.fire({
                    title: 'Enviando Solicitud...',
                    html: `Conectando con el SAT...<br><small>Rango: ${data.fecha_inicio} al ${data.fecha_fin}</small>`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const response = await fetch('core/solicitar-descarga.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (!result.success) throw new Error(result.message);

                currentRequestId = result.requestId;

                Swal.fire({
                    icon: 'success',
                    title: 'Solicitud enviada correctamente',
                    html: `
                    <p>El SAT ha recibido la solicitud <strong>#${result.requestId}</strong>.</p>
                    <p><b>El SAT puede tardar hasta 24 horas en procesarla.</b></p>
                    <p>La solicitud se registró en la lista y se actualizará automáticamente cuando haya cambios.</p>
                `,
                    confirmButtonText: 'Entendido'
                }).then(() => {
                    modalDescarga.hide();
                    // Refrescar la lista de solicitudes
                    if (typeof cargarSolicitudes === 'function') {
                        cargarSolicitudes();
                    } else {
                        location.reload();
                    }
                });

            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        });

        async function cancelarSolicitud(requestId) {
            try {
                const response = await fetch('core/cargar-cfdi-sat.php?action=cancelar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        requestId
                    })
                });

                const result = await response.json();

                Swal.fire({
                    icon: result.success ? 'success' : 'warning',
                    title: result.success ? 'Consulta Cancelada' : 'Atención',
                    text: result.message,
                    confirmButtonText: 'Entendido'
                });
            } catch (error) {
                console.error("Error al cancelar:", error);
                Swal.fire('Error', 'No se pudo cancelar la consulta.', 'error');
            }
        }

        modalDescarga._element.addEventListener('hidden.bs.modal', () => {
            if (verificationInterval) {
                clearInterval(verificationInterval);
                verificationInterval = null;
            }
            currentRequestId = null;
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInicio = document.getElementById('fecha_inicio');
        const fechaFin = document.getElementById('fecha_fin');
        const diasRango = document.getElementById('dias-rango');

        function actualizarDiasRango() {
            const inicio = fechaInicio.value;
            const fin = fechaFin.value;
            if (inicio && fin) {
                const dateInicio = new Date(inicio);
                const dateFin = new Date(fin);
                if (dateFin >= dateInicio) {
                    const diffDays = Math.ceil((dateFin - dateInicio) / (1000 * 60 * 60 * 24)) + 1;
                    diasRango.textContent = `${diffDays} día${diffDays === 1 ? '' : 's'} seleccionados`;
                } else {
                    diasRango.textContent = 'Rango inválido';
                }
            } else {
                diasRango.textContent = '0 días seleccionados';
            }
        }

        fechaInicio.addEventListener('change', actualizarDiasRango);
        fechaFin.addEventListener('change', actualizarDiasRango);
    });
</script>