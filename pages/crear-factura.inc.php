<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Facturar</h2>
        </div>
        <div class="card-body">
            <div class="content">
                <div class="container-fluid px-0">
                    <form id="facturaForm" action="procesar_factura.php" method="POST">
                        <div class="row g-4">
                            <!-- Columna izquierda -->
                            <div class="col-md-8">
                                <!-- Buscar cliente -->
                                <div class="card card-primary mb-4">
                                    <div class="card-header">
                                        <h3 class="card-title">Buscar Cliente</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="input-group mb-3">
                                            <input type="text" id="buscar-cliente" class="form-control" placeholder="Ingrese cliente por nombre o RFC" autocomplete="off">
                                            <button id="btn-buscar-cliente" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                                        </div>
                                        <input type="hidden" name="cliente_id" id="cliente-id" required="">
                                        <div id="resultados-clientes" class="mt-2 list-group"></div>
                                        <!-- Cliente Seleccionado -->
                                        <div id="cliente-seleccionado" class="alert alert-primary d-none mt-3">
                                            <span id="cliente-info"></span>
                                            <button id="quitar-cliente" class="btn btn-danger btn-sm float-end">
                                                <i class="fas fa-times"></i> Quitar
                                            </button>
                                        </div>
                                        <!-- Datos obligatorios CFDI 4.0 -->
                                        <div class="row mt-3">
                                            <div class="col-md-6 mb-2">
                                                <label>RFC Receptor</label>
                                                <input type="text" name="rfc_receptor" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label>Nombre Receptor</label>
                                                <input type="text" name="nombre_receptor" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label>Régimen Fiscal Receptor</label>
                                                <select name="regimen_receptor" class="form-control select2" required>
                                                    <option value="">Selecciona Régimen</option>
                                                    <option value="601">601 - General de Ley Personas Morales</option>
                                                    <option value="605">605 - Sueldos y Salarios</option>
                                                    <!-- Agregar más del catálogo c_RegimenFiscal -->
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label>Código Postal</label>
                                                <input type="text" name="cp_receptor" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Buscar producto/servicio -->
                                <div class="card card-info mb-4">
                                    <div class="card-header">
                                        <h3 class="card-title">Buscar servicio / producto</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="input-group mb-3">
                                            <input type="text" id="buscar-producto" class="form-control" placeholder="Nombre o código del servicio" autocomplete="off">
                                            <button id="btn-buscar-producto" class="btn btn-info"><i class="fas fa-search"></i> Buscar</button>
                                        </div>
                                        <div id="lista-productos" class="mt-2"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Columna derecha -->
                            <div class="col-md-4">
                                <div class="card card-secondary mb-4">
                                    <div class="card-header">
                                        <h3 class="card-title">Opciones de Factura</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label>Método de Pago</label>
                                            <select name="metododepago" class="form-control select2" required>
                                                <option value="">Selecciona Método de Pago</option>
                                                <option value="PUE">PUE - Pago en una sola exhibición</option>
                                                <option value="PPD">PPD - Pago en parcialidades o diferido</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label>Forma de Pago</label>
                                            <select name="formadepago" class="form-control select2" required>
                                                <option value="">Selecciona la Forma de Pago</option>
                                                <option value="01">[01] Efectivo</option>
                                                <option value="03">[03] Transferencia</option>
                                                <option value="99">[99] Por definir</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label>Uso de CFDI</label>
                                            <select name="UsoCFDI" class="form-control select2" required>
                                                <option value="">Selecciona el Uso de CFDI</option>
                                                <option value="G01">[G01] Adquisición de mercancías</option>
                                                <option value="P01">[P01] Por definir</option>
                                                <option value="CP01">[CP01] Pagos</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label>Exportación</label>
                                            <select name="exportacion" class="form-control select2" required>
                                                <option value="">Selecciona Exportación</option>
                                                <option value="01">01 - No aplica</option>
                                                <option value="02">02 - Definitiva</option>
                                                <option value="03">03 - Temporal</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label>Factura Global (InformacionGlobal)</label>
                                            <div class="row">
                                                <div class="col-6 mb-2">
                                                    <select name="periodicidad" class="form-control">
                                                        <option value="">Periodicidad</option>
                                                        <option value="01">01 - Diario</option>
                                                        <option value="02">02 - Semanal</option>
                                                        <option value="03">03 - Quincenal</option>
                                                        <option value="04">04 - Mensual</option>
                                                    </select>
                                                </div>
                                                <div class="col-6 mb-2">
                                                    <input type="text" name="meses" class="form-control" placeholder="Meses (ej. 01,02)">
                                                </div>
                                                <div class="col-12">
                                                    <input type="number" name="anio" class="form-control" placeholder="Año (ej. 2025)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Productos seleccionados -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h3 class="card-title">Productos Seleccionados</h3>
                            </div>
                            <div class="card-body">
                                <input type="hidden" name="productos" id="productosInput">
                                <table class="table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario</th>
                                            <th>Importe</th>
                                            <th>ObjetoImp</th>
                                            <th>ACuentaTerceros</th>
                                            <th>Impuestos</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productos-seleccionados"></tbody>
                                </table>
                            </div>
                        </div>
                        <!-- Totales -->
                        <div class="card-footer bg-white border-0 text-end">
                            <h4 class="mb-2">Total: <span id="total-factura">$0.00</span></h4>
                            <h5 class="mb-1">Total Trasladados: <span id="impuestos-totales-t">$0.00</span></h5>
                            <h5 class="mb-3">Total Retenidos: <span id="impuestos-totales-r">$0.00</span></h5>
                            <input type="hidden" name="total" id="input-total-factura">
                            <input type="hidden" name="impuestos-totales-t" id="input-impuestos-totales-t">
                            <input type="hidden" name="impuestos-totales-r" id="input-impuestos-totales-r">
                            <button type="submit" class="btn btn-primary px-4">Generar Factura</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>