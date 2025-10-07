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

        formAutenticacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(formAutenticacion);

            Swal.fire({
                title: 'Autenticando...',
                text: 'Por favor, espere mientras validamos su e.firma.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch('core/autenticar_sat.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message);
                }

                autenticadoRFC = result.rfc; // Guardar RFC autenticado

                Swal.fire('¡Éxito!', `Autenticado correctamente para el RFC: ${autenticadoRFC}`, 'success');

                modalSat.hide();
                modalDescarga.show();

                // Prellenar RFC al mostrar el modal
                const tipoActual = tipoDescargaSelect.value;
                actualizarRFCPorTipo(tipoActual);

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
            const tipo = tipoDescargaSelect.value;


            if (fechaInicio >= fechaFin) {
                Swal.fire('Rango de fechas inválido', 'La fecha de inicio debe ser menor que la fecha final.', 'warning');
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
                    html: `El rango de <strong>${diffDays} días</strong> es demasiado amplio y puede causar timeouts.<br><br>
                <strong>Recomendación:</strong> Divida la consulta en rangos máximos de 31 días.`,
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            try {
                Swal.fire({
                    title: 'Enviando Solicitud',
                    html: `Conectando con el SAT...<br>
               <small>Rango: ${data.fecha_inicio} al ${data.fecha_fin} (${diffDays} días)</small>`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                console.log("Enviando datos:", data);

                const solicitarResponse = await fetch('core/solicitar-descarga.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const solicitarResult = await solicitarResponse.json();

                if (!solicitarResult.success) {
                    throw new Error(solicitarResult.message);
                }

                currentRequestId = solicitarResult.requestId;
                const idSolicitudLocal = solicitarResult.id_solicitud;

                if (!currentRequestId || currentRequestId.length < 10) {
                    throw new Error("No se recibió un ID de solicitud válido del SAT.");
                }

                await verificarSolicitud(idSolicitudLocal, diffDays);

            } catch (error) {
                Swal.fire('Error en la solicitud', error.message, 'error');
            }
        });

        async function verificarSolicitud(idSolicitud, diffDays) {
            const estadoSolicitudMap = {
                1: "Aceptada",
                2: "En proceso",
                3: "Terminada",
                4: "Error",
                5: "Rechazada",
                6: "Vencida"
            };

            const codEstatusMap = {
                300: "Usuario No Válido",
                301: "XML Mal Formado",
                302: "Sello Mal Formado",
                303: "Sello no corresponde con RFC",
                304: "Certificado Revocado o Caduco",
                305: "Certificado Inválido",
                5000: "Solicitud recibida con éxito",
                5003: "Tope máximo de elementos",
                5004: "No se encontró la información",
                5011: "Límite de descargas por día"
            };

            let attempts = 0;
            const maxAttempts = 30;
            const checkInterval = 30000;
            let startTime = Date.now();

            Swal.fire({
                title: 'Verificando Estado SAT',
                html: `Consulta en proceso...<br><small>Solicitud # ${idSolicitud}</small>`,

                allowOutsideClick: false,
                showConfirmButton: true,
                confirmButtonText: 'Cancelar Consulta',
                didOpen: () => Swal.showLoading()
            }).then((result) => {
                if (result.isConfirmed && verificationInterval) {
                    clearInterval(verificationInterval);
                    cancelarSolicitud(requestId);
                }
            });

            const performVerification = async () => {
                if (attempts >= maxAttempts) {
                    clearInterval(verificationInterval);
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tiempo de espera agotado',
                        html: `El SAT no respondió en el tiempo esperado.<br><br>
           <strong>Recomendaciones:</strong><br>
           • Intente con un rango más pequeño<br>
           • Espere unos minutos<br>
           • El SAT puede estar saturado`
                    });
                    return;
                }

                attempts++;
                const elapsedMinutes = Math.floor((Date.now() - startTime) / 60000);

                try {
                    const verificarResponse = await fetch('core/verificar-descarga.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id_solicitud: idSolicitud
                        })
                    });

                    const result = await verificarResponse.json();

                    if (!result.success) throw new Error(result.message);

                    const estadoDescripcion = estadoSolicitudMap[result.estadoSolicitud] || "❓ Estado desconocido";
                    const codigoDescripcion = codEstatusMap[result.status_code] || "⚠️ Código desconocido";

                    Swal.update({
                        html: `
            <strong>Estado:</strong> ${estadoDescripcion}<br>
            <strong>Código SAT:</strong> ${result.status_code || '-'}<br>
            <small>${codigoDescripcion}</small><br><br>
            <small>Solicitud # ${idSolicitud}</small><br>
            <small>Intento: ${attempts}/${maxAttempts}</small><br>
            <small>Tiempo transcurrido: ${elapsedMinutes} min</small>
          `
                    });

                    if (result.is_finished) {
                        clearInterval(verificationInterval);
                        if (result.has_packages && result.packageIds?.length > 0) {
                            Swal.close();
                            await descargarPaquetes(idSolicitud, result.packageIds);
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Consulta Finalizada',
                                html: `El SAT completó la consulta pero no se encontraron CFDI.<br><br>
               Estado final: <strong>${estadoDescripcion}</strong><br>
               Detalle: ${codigoDescripcion}`,
                                confirmButtonText: 'Entendido'
                            });
                        }
                    }

                } catch (error) {
                    clearInterval(verificationInterval);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Verificación',
                        html: `No se pudo verificar con el SAT.<br><br><strong>Error:</strong> ${error.message}`,
                        confirmButtonText: 'Entendido'
                    });
                }
            };

            await performVerification();
            verificationInterval = setInterval(performVerification, checkInterval);
        }

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

        async function descargarPaquetes(idSolicitud, packageIds) {
            const downloadResults = {
                success: [],
                failed: []
            };

            for (let i = 0; i < packageIds.length; i++) {
                const packageId = packageIds[i];

                Swal.fire({
                    title: `Descargando Paquetes`,
                    html: `Procesando paquete <strong>${i + 1} de ${packageIds.length}</strong><br>
               <small>ID: ${packageId.substring(0, 20)}...</small>`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const descargarResponse = await fetch('core/cargar-cfdi-sat.php?action=descargar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id_solicitud: idSolicitud,
                            package_id: packageId
                        })
                    });

                    const result = await descargarResponse.json();

                    if (result.success) {
                        downloadResults.success.push({
                            packageId,
                            file: result.file
                        });
                    } else {
                        downloadResults.failed.push({
                            packageId,
                            error: result.message
                        });
                    }

                } catch (error) {
                    downloadResults.failed.push({
                        packageId,
                        error: error.message
                    });
                }
            }

            let resultMessage = `Se descargaron <strong>${downloadResults.success.length}</strong> paquetes exitosamente.`;

            if (downloadResults.failed.length > 0) {
                resultMessage += `<br><br>Fallaron <strong>${downloadResults.failed.length}</strong> paquetes:`;
                downloadResults.failed.forEach(failed => {
                    resultMessage += `<br>• ${failed.packageId}: ${failed.error}`;
                });
            }

            Swal.fire({
                icon: downloadResults.failed.length === 0 ? 'success' : 'warning',
                title: downloadResults.failed.length === 0 ? '¡Descarga Completada!' : 'Descarga Parcial',
                html: resultMessage,
                confirmButtonText: 'Continuar'
            }).then(() => {
                location.reload();
            });
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
                    const diffTime = Math.abs(dateFin - dateInicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
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