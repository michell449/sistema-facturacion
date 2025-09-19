<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Ver facturas</h2>
        </div>
        <div class="card-header">
    <h3 class="card-title">Buscar Facturas</h3>
</div>
<div class="card-body">
    <label>Buscar por RFC, folio o serie:</label>
    <input type="text" id="buscar" class="form-control" placeholder="Ingresa RFC, folio o serie...">
</div>
        <div class="card-header">
            <h3 class="card-title">Listado de Facturas</h3>
        </div>
        <div class="card-body">
            <div id="facturas">
                <table class="table table-hover text-center">
                    <thead class="table-info">
                        <tr>
                            <th>Serie</th>
                            <th>Folio</th>
                            <th>RFC Emisor</th>
                            <th>RFC Receptor</th>
                            <th>Total</th>
                            <th>Fecha Emisión</th>
                            <th>Estado</th>
                            <th>Método de Pago</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>F</td>
                            <td>255</td>
                            <td>EKU9003173C9</td>
                            <td>XAXX010101000</td>
                            <td>$116.00</td>
                            <td>2025-09-03 17:10:42</td>
                            <td><span class="badge bg-info">TIMBRADA</span></td>
                            <td>PUE</td>
                            <td><a href="detalle_de_factura.php?id=1" class="btn btn-primary btn-sm">Ver</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <ul class="pagination justify-content-center" id="paginacion">
            <li class="page-item active">
                <a class="page-link" href="#" onclick="cargarFacturas(1); return false;">1</a>
            </li>
        </ul>