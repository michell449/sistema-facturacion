<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Ver Facturas</h2>
        </div>

        <!-- Búsqueda -->
        <div class="card-header">
            <h3 class="card-title">Buscar Facturas</h3>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="buscar">RFC, Folio o Serie:</label>
                    <input type="text" id="buscar" class="form-control" placeholder="Ingresa RFC, folio o serie...">
                </div>
                <div class="col-md-3">
                    <label for="fecha-inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha-inicio" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="fecha-fin">Fecha Fin:</label>
                    <input type="date" id="fecha-fin" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="buscarFacturas();">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>
        </div>

        <!-- Listado -->
        <div class="card-header">
            <h3 class="card-title">Listado de Facturas</h3>
        </div>
        <div class="card-body">
            <div id="facturas">
                <table class="table table-hover text-center align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>Serie</th>
                            <th>Folio</th>
                            <th>RFC Emisor</th>
                            <th>RFC Receptor</th>
                            <th>Nombre Receptor</th>
                            <th>Total</th>
                            <th>Fecha Emisión</th>
                            <th>Método de Pago</th>
                            <th>Uso CFDI</th>
                            <th>Exportación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>F</td>
                            <td>255</td>
                            <td>EKU9003173C9</td>
                            <td>XAXX010101000</td>
                            <td>Público en general</td>
                            <td>$116.00</td>
                            <td>2025-09-03 17:10:42</td>
                            <td>PUE</td>
                            <td>G03 - Gastos en general</td>
                            <td>01 - No aplica</td>
                            <td><span class="badge bg-success">Timbrada</span></td>
                            <td>
                                <div class="btn-group">
                                    <a href="detalle_de_factura.php?id=1" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="descargar_xml.php?id=1" class="btn btn-info btn-sm">
                                        <i class="fas fa-file-code"></i>
                                    </a>
                                    <a href="descargar_pdf.php?id=1" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <button class="btn btn-danger btn-sm" onclick="cancelarFactura(1)">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <ul class="pagination justify-content-center" id="paginacion">
            <li class="page-item active">
                <a class="page-link" href="#" onclick="cargarFacturas(1); return false;">1</a>
            </li>
        </ul>
    </div>
</div>