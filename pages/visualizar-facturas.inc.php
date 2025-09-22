<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Dashboard General - Análisis de CFDI</h2>
        </div>

        <!-- Filtros -->
        <div class="card-body">
            <form class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="mes" class="form-label">Mes</label>
                    <select id="mes" class="form-select">
                        <option value="09" selected>Septiembre</option>
                        <option value="08">Agosto</option>
                        <option value="07">Julio</option>
                        <option value="06">Junio</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="anio" class="form-label">Año</label>
                    <select id="anio" class="form-select">
                        <option value="2025" selected>2025</option>
                        <option value="2024">2024</option>
                        <option value="2023">2023</option>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </form>

            <!-- KPIs principales -->
            <div class="row text-center mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Total Facturado</h6>
                            <h4 class="text-success fw-bold">$250,000.00</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Facturas Emitidas</h6>
                            <h4 class="fw-bold">120</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Facturas Recibidas</h6>
                            <h4 class="fw-bold">85</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Canceladas</h6>
                            <h4 class="text-danger fw-bold">12</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Resumen -->
            <div class="card-header bg-light">
                <h3 class="card-title">Resumen de Facturación</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped text-center align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Subtotal Facturado</td>
                            <td>$230,000.00</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>IVA Trasladado</td>
                            <td>$36,800.00</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>ISR Retenido</td>
                            <td>$10,500.00</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>Total Emitidas</td>
                            <td>$250,000.00</td>
                            <td>120</td>
                        </tr>
                        <tr>
                            <td>Total Recibidas</td>
                            <td>$180,000.00</td>
                            <td>85</td>
                        </tr>
                        <tr>
                            <td>Canceladas</td>
                            <td>$25,000.00</td>
                            <td>12</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tabla Detalle -->
            <div class="card-header bg-light mt-3">
                <h3 class="card-title">Detalle de Facturas (Mes Seleccionado)</h3>
            </div>
            <div class="card-body">
                <table class="table table-hover text-center align-middle">
                    <thead class="table-secondary">
                        <tr>
                            <th>Serie</th>
                            <th>Folio</th>
                            <th>RFC Emisor</th>
                            <th>RFC Receptor</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>F</td>
                            <td>256</td>
                            <td>EKU9003173C9</td>
                            <td>XAXX010101000</td>
                            <td>$1,500.00</td>
                            <td>2025-09-21</td>
                            <td><span class="badge bg-success">Válida</span></td>
                            <td>Emitida</td>
                        </tr>
                        <tr>
                            <td>A</td>
                            <td>125</td>
                            <td>XAXX010101000</td>
                            <td>EKU9003173C9</td>
                            <td>$5,000.00</td>
                            <td>2025-09-15</td>
                            <td><span class="badge bg-danger">Cancelada</span></td>
                            <td>Recibida</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>