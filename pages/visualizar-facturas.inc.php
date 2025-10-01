<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Consulta de CFDI</h2>
        </div>

        <!-- Filtros -->
        <div class="card-body">
            <h5 class="mb-3">Filtros de busqueda</h5>
            <form id="filtros-cfdi" class="row g-3">
                <!-- Periodo -->
                <div class="col-md-2">
                    <label class="form-label">Mes</label>
                    <select class="form-select">
                        <option value="">Todos</option>
                        <option>Enero</option>
                        <option>Febrero</option>
                        <option>Marzo</option>
                        <option>Abril</option>
                        <option>Mayo</option>
                        <option>Junio</option>
                        <option>Julio</option>
                        <option>Agosto</option>
                        <option>Septiembre</option>
                        <option>Octubre</option>
                        <option>Noviembre</option>
                        <option>Diciembre</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Año</label>
                    <select class="form-select">
                        <option value="">Todos</option>
                        <option>2023</option>
                        <option>2024</option>
                        <option>2025</option>
                    </select>
                </div>

                <!-- Tipo CFDI -->
                <div class="col-md-3">
                    <label class="form-label">Tipo de CFDI</label>
                    <select class="form-select">
                        <option value="">Todos</option>
                        <option>Ingreso</option>
                        <option>Egreso</option>
                        <option>Nómina</option>
                        <option>Traslado</option>
                        <option>Recepción de pagos</option>
                        <option>Retenciones e información de pagos</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body border-top">
            <h5 class="mb-3">Buscar por</h5>
            <form id="busquedas cfdi" class="row g-3">
                <!-- Folio fiscal -->
                <div class="col-md-3">
                    <label class="form-label">Folio Fiscal (UUID)</label>
                    <input type="text" class="form-control" placeholder="UUID">
                </div>

                <!-- RFC -->
                <div class="col-md-3">
                    <label class="form-label">RFC</label>
                    <input type="text" class="form-control" placeholder="RFC Emisor/Receptor">
                </div>

                <!-- Razón Social -->
                <div class="col-md-3">
                    <label class="form-label">Razón Social</label>
                    <input type="text" class="form-control" placeholder="Nombre/Razón Social">
                </div>

                <!-- Código Postal -->
                <div class="col-md-2">
                    <label class="form-label">C.P.</label>
                    <input type="text" class="form-control" placeholder="Código Postal">
                </div>

                <!-- Forma de Pago -->
                <div class="col-md-2">
                    <label class="form-label">Forma de Pago</label>
                    <select class="form-select">
                        <option value="">Todas</option>
                        <option value="02">[02] Cheque nominativo</option>
                        <option value="03">[03] Transferencia electronica de fondos</option>
                        <option value="04">[04] Tarjeta de credito</option>
                        <option value="05">[05] Monedero electronico</option>
                        <option value="01">[01] Efectivo</option>
                        <option value="06">[06] Dinero electronico</option>
                        <option value="08">[08] Vales de despensa</option>
                        <option value="12">[12] Dacion en pago</option>
                        <option value="13">[13] Pago por subrogacion</option>
                        <option value="14">[14] Pago por consignacion</option>
                        <option value="15">[15] Condonacion</option>
                        <option value="17">[17] Compensacion</option>
                        <option value="23">[23] Novacion</option>
                        <option value="24">[24] Confusion</option>
                        <option value="25">[25] Remision de deuda</option>
                        <option value="26">[26] Prescripcion o caducidad</option>
                        <option value="27">[27] A satisfaccion del acreedor</option>
                        <option value="28">[28] Tarjeta de debito</option>
                        <option value="29">[29] Tarjeta de servicios</option>
                        <option value="30">[30] Aplicacion de Anticipos</option>
                        <option value="31">[31] Intermediario pagos</option>
                        <option value="99">[99] Por definir</option>
                    </select>
                </div>

                <!-- Botón Buscar -->
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>

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
        echo '<th>Fecha Emisión</th>';
        echo '<th>Serie</th>';
        echo '<th>Folio Fiscal</th>';
        echo '<th>RFC Emisor</th>';
        echo '<th>RFC Receptor</th>';
        echo '<th>Razón Social</th>';
        echo '<th>Tipo</th>';
        echo '<th>Uso CFDI</th>';
        echo '<th>Importe</th>';
        echo '<th>Total</th>';
        echo '<th>Forma de Pago</th>';
        echo '<th>Metodo de Pago</th>';
        echo '<th>Archivos</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="facturas-cargadas">';
        while ($row = $result->fetch_assoc()) {
            $pdfPath = 'uploads/pdf/' . $row['pdf_file'];
            $xmlPath = 'uploads/xml/' . $row['xml_file'];
            echo '<tr>';
            echo '<td>' . $row['uuid'] . '</td>';
            echo '<td>' . $row['fecha'] . '</td>';
            echo '<td>' . $row['serie'] . '</td>';
            echo '<td>' . $row['folio'] . '</td>';
            echo '<td>' . $row['emisor_rfc'] . '</td>';
            echo '<td>' . $row['receptor_rfc'] . '</td>';
            echo '<td>' . $row['emisor_nombre'] . '</td>';
            echo '<td>' . $row['tipo_comprobante'] .'</td>';
            echo '<td>' . $row['receptor_uso_cfdi'] . '</td>';
            echo '<td>' . number_format($row['subtotal'], 2) . '</td>';
            echo '<td>' . number_format($row['total'], 2) . '</td>';
            echo '<td>' . $row['forma_pago'] . '</td>';
            echo '<td>' . $row['metodo_pago'] . '</td>';
            echo '<td>
                <a href="' . $pdfPath . '" class="text-danger" download title="Descargar PDF"> <i class="fas fa-file-pdf fa-lg">pdf</i></a>
                <a href="' . $xmlPath . '" class="text-primary ms-2" download title="Descargar XML"> <i class="fas fa-file-code fa-lg">xml</i></a>
                </td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        ?>
        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-end pagination-sm">
                <li class="page-item disabled">
                    <a class="page-link">Anterior</a>
                </li>
                <li class="page-item"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Siguiente</a>
                </li>
            </ul>
        </nav>
    </div>
</div>
</div>