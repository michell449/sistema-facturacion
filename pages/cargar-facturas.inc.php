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
                            <form id="form-descarga1-1 class=" row g-3">
                                <div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de facturas</label>
                                        <select class="form-select">
                                            <option value="emitidas">Emitidas</option>
                                            <option value="recibidas">Recibidas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de solicitud</label>
                                        <select class="form-select" id="">
                                            <option value="cfdi">CFDI</option>
                                            <option value="metadata">Metadata</option>
                                        </select>
                                    </div>
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
            <?php
            require_once __DIR__ . "../../config.php";
            $result = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC LIMIT 10");
            echo '<div class="card-body">';
            echo '<table class="table table-hover text-center align-middle">';
            echo '<thead class="table-info">';
            echo '<tr>';
            echo '<th>UUID</th>';
            echo '<th>Serie</th>';
            echo '<th>Folio Fiscal</th>';
            echo '<th>RFC Emisor</th>';
            echo '<th>RFC Receptor</th>';
            echo '<th>Razón Social</th>';
            echo '<th>Fecha Emisión</th>';
            echo '<th>Uso CFDI</th>';
            echo '<th>Subtotal</th>';
            echo '<th>Total</th>';
            echo '<th>Forma de Pago</th>';
            echo '<th>Metodo de Pago</th>';
            echo '<th>Archivos</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody id="facturas-cargadas">';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['uuid'] . '</td>';
                echo '<td>' . $row['serie'] . '</td>';
                echo '<td>' . $row['folio'] . '</td>';
                echo '<td>' . $row['emisor_rfc'] . '</td>';
                echo '<td>' . $row['receptor_rfc'] . '</td>';
                echo '<td>' . $row['emisor_nombre'] . '</td>';
                echo '<td>' . $row['fecha'] . '</td>';
                echo '<td>' . $row['receptor_uso_cfdi'] . '</td>';
                echo '<td>' . number_format($row['subtotal'], 2) . '</td>';
                echo '<td>' . number_format($row['total'], 2) . '</td>';
                echo '<td>' . $row['forma_pago'] . '</td>';
                echo '<td>' . $row['metodo_pago'] . '</td>';
                echo '<td>
                        <a href="../uploads/xml/' . $row['xml_file'] . '" target="_blank">XML</a> | 
                        <a href="../uploads/pdf/' . $row['pdf_file'] . '" target="_blank">PDF</a>
                    </td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            ?>
        </div>
    </div>
</div>