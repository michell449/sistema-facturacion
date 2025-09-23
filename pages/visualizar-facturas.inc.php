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

        <!-- Resultados -->
        <div class="card-header mt-3">
            <h3 class="card-title">Resultados de búsqueda</h3>
        </div>
        <div class="card-body">
            <table class="table table-hover text-center align-middle">
                <thead class="table-info">
                    <tr>
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
                <tbody id="facturas-filtradas">
                    <tr>
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