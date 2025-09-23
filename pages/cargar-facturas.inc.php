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
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Descargar facturas desde SAT</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Conecta tu cuenta del SAT para descargar automáticamente tus facturas.</p>
                            <!-- Botón que abre el modal de conexión -->
                            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalSAT">
                                <i class="fas fa-cloud-download-alt"></i> Conectar con SAT
                            </button>
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
                            <form id="form-manual">
                                <div class="mb-3">
                                    <label for="xmlFile" class="form-label">Subir un solo XML</label>
                                    <input type="file" id="xmlFile" class="form-control" accept=".xml">
                                </div>
                                <div class="mb-3">
                                    <label for="zipFile" class="form-label">Subir archivo ZIP con varios CFDI</label>
                                    <input type="file" id="zipFile" class="form-control" accept=".zip">
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload"></i> Cargar Archivos
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
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#loginSAT" type="button" role="tab">
                                    Acceso con RFC y Contraseña
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="efirma-tab" data-bs-toggle="tab" data-bs-target="#efirmaSAT" type="button" role="tab">
                                    Acceso con e.firma
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Login normal -->
                            <div class="tab-pane fade show active" id="loginSAT" role="tabpanel">
                                <label class="form-label">RFC</label>
                                <input type="text" class="form-control mb-3" placeholder="Ingrese su RFC">
                                <label class="form-label">Contraseña</label>
                                <input type="password" class="form-control mb-3" placeholder="Ingrese su contraseña">
                                <label class="form-label">Captcha</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control" placeholder="Ingrese el captcha">
                                    <img src="#" alt="Captcha SAT" style="height:40px; border:1px solid #ced4da; border-radius:.25rem;">
                                </div>
                            </div>

                            <!-- Primera vez con e.firma -->
                            <div class="tab-pane fade" id="efirmaSAT" role="tabpanel">
                                <div class="mb-3">
                                    <label for="certificado" class="form-label">Archivo .cer</label>
                                    <input type="file" id="certificado" class="form-control" accept=".cer">
                                </div>
                                <div class="mb-3">
                                    <label for="llave" class="form-label">Archivo .key</label>
                                    <input type="file" id="llave" class="form-control" accept=".key">
                                </div>
                                <div class="mb-3">
                                    <label for="passwordFiel" class="form-label">Contraseña FIEL</label>
                                    <input type="password" id="passwordFiel" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <!-- Simula conexión exitosa -->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDescarga" data-bs-dismiss="modal">
                            Conectar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal descarga CFDI -->
        <div class="modal fade" id="modalDescarga" tabindex="-1" aria-labelledby="modalDescargaLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalDescargaLabel">Descargar CFDI desde SAT</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <form id="form-descarga" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de facturas</label>
                                <select class="form-select">
                                    <option value="emitidas">Emitidas</option>
                                    <option value="recibidas">Recibidas</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha inicio</label>
                                <input type="date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha fin</label>
                                <input type="date" class="form-control">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-download"></i> Descargar CFDI
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Facturas cargadas -->
        <div class="card-header mt-3">
            <h3 class="card-title">Facturas Cargadas</h3>
        </div>
        <div class="card-body">
            <table class="table table-hover text-center align-middle">
                <thead class="table-info">
                    <tr>
                        <th>Serie</th>
                        <th>Folio</th>
                        <th>Folio Fiscal (UUID)</th>
                        <th>RFC Emisor</th>
                        <th>RFC Receptor</th>
                        <th>Razón Social</th>
                        <th>Total</th>
                        <th>Fecha Emisión</th>
                        <th>Uso CFDI</th>
                        <th>Forma de Pago</th>
                        <th>Estado</th>
                        <th>Archivos</th>
                    </tr>
                </thead>
                <tbody id="facturas-cargadas">
                    <tr>
                        <td>F</td>
                        <td>256</td>
                        <td>123e4567-e89b-12d3-a456-426614174000</td>
                        <td>EKU9003173C9</td>
                        <td>XAXX010101000</td>
                        <td>Público en general</td>
                        <td>$1,500.00</td>
                        <td>2025-09-21</td>
                        <td>G03</td>
                        <td>03 - Transferencia</td>
                        <td><span class="badge bg-success">Válida</span></td>
                        <td><a href="#" class="btn btn-sm btn-secondary"><i class="fas fa-file-code bi-file-earmark-excel"></i></a>
                            <a href="#" class="btn btn-sm btn-secondary"><i class="fas fa-file-pdf bi-file-earmark-pdf"></i></a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>