<div class="content-wrapper" style="margin-left:0!important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Cargar CFDI</h2>
        </div>

        <div class="card-body">
            <div class="row g-4">

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

                <div class="col-md-6">
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Cargar Facturas Manualmente</h3>
                        </div>
                        <div class="card-body">
                            <form id="form-manual-upload" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="xmlFile" class="form-label">Subir uno o varios archivos XML</label>
                                    <input type="file" id="xmlFile" name="xmlFile" class="form-control" accept=".xml" multiple>
                                </div>
                                <div class="mb-3">
                                    <label for="zipFile" class="form-label">Subir archivo ZIP con varios CFDI</label>
                                    <input type="file" id="zipFile" name="zipFile" class="form-control" accept=".zip" multiple>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload"></i> Cargar y Revisar Archivos
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalSAT" tabindex="-1" aria-labelledby="modalSATLabel" aria-hidden="true">
            </div>

            <div class="modal fade" id="modalDescarga" tabindex="-1" aria-labelledby="modalDescargaLabel" aria-hidden="true">
            </div>

            <div class="modal fade" id="cfdiModal" tabindex="-1" aria-labelledby="modalCfdiLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCfdiLabel">Ver datos de CFDI</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="cfdiParseErrors" class="alert alert-danger" style="display:none;"></div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAllCfdi"></th>
                                            <th>#</th>
                                            <th>UUID</th>
                                            <th>Fecha</th>
                                            <th>RFC Emisor</th>
                                            <th>RFC Receptor</th>
                                            <th>Subtotal</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cfdiReviewBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" id="guardarCfdiBtn" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Seleccionados
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-header mt-3">
                <h3 class="card-title">Facturas Cargadas</h3>
            </div>
            <?php
            require_once __DIR__ . "../../config.php";
            $result = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC LIMIT 10");
            echo '<div class="card-body table-responsive">';
            echo '<table class="table table-hover text-center align-middle">';
            echo '<thead class="table-info">';
            echo '<tr><th>UUID</th><th>Serie</th><th>Folio</th><th>RFC Emisor</th><th>RFC Receptor</th><th>Razón Social</th><th>Fecha Emisión</th><th>Uso CFDI</th><th>Subtotal</th><th>Total</th><th>Forma de Pago</th><th>Metodo de Pago</th><th>Archivos</th></tr>';
            echo '</thead>';
            echo '<tbody id="facturas-cargadas">';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['uuid']) . '</td>';
                echo '<td>' . htmlspecialchars($row['serie']) . '</td>';
                echo '<td>' . htmlspecialchars($row['folio']) . '</td>';
                echo '<td>' . htmlspecialchars($row['emisor_rfc']) . '</td>';
                echo '<td>' . htmlspecialchars($row['receptor_rfc']) . '</td>';
                echo '<td>' . htmlspecialchars($row['emisor_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fecha']) . '</td>';
                echo '<td>' . htmlspecialchars($row['receptor_uso_cfdi']) . '</td>';
                echo '<td>$' . number_format($row['subtotal'], 2) . '</td>';
                echo '<td>$' . number_format($row['total'], 2) . '</td>';
                echo '<td>' . htmlspecialchars($row['forma_pago']) . '</td>';
                echo '<td>' . htmlspecialchars($row['metodo_pago']) . '</td>';
                echo '<td>
                        <a href="../uploads/xml/' . htmlspecialchars($row['xml_file']) . '" target="_blank" class="btn btn-sm btn-outline-secondary">XML</a> 
                        <a href="../uploads/pdf/' . htmlspecialchars($row['pdf_file']) . '" target="_blank" class="btn btn-sm btn-outline-danger">PDF</a>
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