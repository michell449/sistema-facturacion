<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Cargar CFDI</h2>
        </div>

        <!-- Cargar Facturas -->
        <div class="card-body">
            <div class="row g-4">
                <!-- Subir Archivos -->
                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">Descargar facturas desde SAT</h3>
                        </div>
                        <div class="card-body">
                            <!-- Button trigger modal -->
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                Conectar con SAT
                            </button>

                            <!-- Modal -->
                            <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h1 class="modal-title fs-5" id="exampleModalLabel">SAT</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <label for="exampleFormControlInput1" class="form-label">RFC</label>
                                            <input class="form-control" type="text" placeholder="Ingrese su RFC" aria-label="default input">
                                            <label for="exampleFormControlInput1" class="form-label mt-3">Contraseña</label>
                                            <input class="form-control" type="password" placeholder="Ingrese su contraseña" aria-label="default input">
                                            <label for="exampleFormControlInput1" class="form-label mt-3">e.firma portable:</label>
                                            <input class="form-control" disabled="" id="claveDinamica" placeholder="Clave dinámica" type="password">
                                            <label for="exampleFormControlInput1" class="form-label mt-3">Captcha</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input class="form-control" type="text" placeholder="Ingrese el captcha" aria-label="default input">
                                                <img src="#" alt="Captcha SAT" style="height: 40px; border: 1px solid #ced4da; border-radius: .25rem;">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                <button type="button" class="btn btn-primary">Continuar</button>
                                                <button type="button" class="btn btn-primary">e.firma</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Descargar con FIEL -->
                    <div class="col-md-6">
                        <div class="card card-secondary">
                            <div class="card-header">
                                <h3 class="card-title">Descargar Facturas</h3>
                            </div>
                            <div class="card-body">
                                <form id="form-fiel">
                                    <div class="mb-3">
                                        <label for="certificado" class="form-label">Archivo .cer</label>
                                        <input type="file" id="certificado" class="form-control" accept=".cer">
                                    </div>
                                    <div class="mb-3">
                                        <label for="llave" class="form-label">Archivo .key</label>
                                        <input type="file" id="llave" class="form-control" accept=".key">
                                    </div>
                                    <div class="mb-3">
                                        <label for="contrasena" class="form-label">Contraseña FIEL</label>
                                        <input type="password" id="contrasena" class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-download"></i> Descargar CFDI
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Facturas Cargadas -->
            <div class="card-header mt-3">
                <h3 class="card-title">Facturas Cargadas</h3>
            </div>
            <div class="card-body">
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
                            <th>Uso CFDI</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="facturas-cargadas">
                        <tr>
                            <td>F</td>
                            <td>256</td>
                            <td>EKU9003173C9</td>
                            <td>XAXX010101000</td>
                            <td>Público en general</td>
                            <td>$1,500.00</td>
                            <td>2025-09-21</td>
                            <td>G03</td>
                            <td><span class="badge bg-success">Válida</span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-info btn-sm"><i class="fas fa-file-code"></i></button>
                                    <button class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>