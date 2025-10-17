<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
            <h2 class="fw-bold m-0"><i class="fas fa-list-alt me-2"></i>Peticiones de Descarga SAT</h2>
            <div>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../core/filtros-solicitudes.php'; ?>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover mb-2" id="tabla-solicitudes" style="width:100%;">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th style="width:5%;">ID</th>
                            <th style="width:16%;">Folio SAT</th>
                            <th style="width:12%;">RFC</th>
                            <th style="width:8%;">Tipo</th>
                            <th style="width:18%;">Rango / UUID</th>
                            <th style="width:8%;">Paquetes</th>
                            <th style="width:10%;">Estado</th>
                            <th style="width:11%;">Creada</th>
                            <th style="width:12%;">Últ. Verif.</th>
                            <th style="width:5%;">Verificar</th>
                        </tr>
                    </thead>
                    <tbody class="text-center" id="tbody-solicitudes">
                        <?php require __DIR__ . '/../core/listar-solicitudes.php'; ?>
                    </tbody>
                </table>

                <div class="d-flex justify-content-end mb-2">
                    <button id="btn-verificar" class="btn btn-primary">
                        <i class="fas fa-sync-alt me-1"></i>Verificar Solicitudes
                    </button>
                </div>
                <a href="panel?pg=cargar-facturas" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Regresar</a>
            </div>

            <details class="mt-3">
                <summary class="fw-bold">Descripción de estados</summary>
                <div class="mt-2">
                    <?php require __DIR__ . '/../core/estados-solicitudes-descripcion.php'; ?>
                </div>
            </details>
        </div>
    </div>
</div>
