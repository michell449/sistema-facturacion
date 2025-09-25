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
                            <!-- Botón para abrir selector -->
                            <button type="button" class="btn btn-success w-100 mb-3" onclick="document.getElementById('xmlInput').click()">
                                <i class="fas fa-file-upload"></i> Subir CFDI
                            </button>
                            <input type="file" id="xmlInput" accept=".xml" style="display:none">

                            <!-- Modal de vista previa -->
                            <div class="modal fade" id="cfdiModal" tabindex="-1" aria-labelledby="cfdiModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title" id="cfdiModalLabel">Vista previa CFDI</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div id="cfdiData" class="p-2"></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="button" class="btn btn-primary" onclick="guardarCFDI()">Guardar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subir ZIP -->
                            <form action="../core/cargar-xml.php" method="POST" enctype="multipart/form-data" id="form-manual">
                                <div class="mb-3">
                                    <label for="zipFile" class="form-label">Subir archivo ZIP con varios CFDI</label>
                                    <input type="file" id="zipFile" name="zipFile" class="form-control" accept=".zip">
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload"></i> Cargar Archivo ZIP
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Facturas cargadas -->
            <div class="card-header mt-3">
                <h3 class="card-title">Facturas Cargadas</h3>
            </div>
            <?php
            require_once __DIR__ . "/../config.php";

            $result = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC LIMIT 10");
            if (!$result) {
                echo "<p class='text-danger'>Error en la consulta: " . $conn->error . "</p>";
            } else {
                while ($row = $result->fetch_assoc()) {
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
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
    </div>
</div>