<!-- Row principal -->
<div class="row g-4">

    <!-- Mis tareas -->
    <div class="col-12">
        <div class="card shadow-sm" style="min-height: 400px; padding-left: 4px; padding-right: 4px;">

            <!-- Contenedor Título -->
            <div class="card-header border-bottom-0 bg-primary">
                <div class="d-flex align-items-center" style="gap: 16px;">
                    <h4 class="mb-0 text-white">Panel de tareas</h4>
                </div>
            </div>

            <!-- Contenedor Tabs -->
            <div class="card-header border-bottom">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Próximas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Semanales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Mensuales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Semestrales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Unicas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Eventuales</a>
                    </li>
                </ul>
            </div>

            <!-- Botón Crear tarea -->
            <div class="px-3 pt-3">
                <a href="panel?pg=crear-tarea" id="btnCrearTarea" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Crear tarea
                </a>
            </div>

            <div class="card-body">
                <!-- Formulario oculto -->
                <div id="formTareaContainer" style="display:none;">
                    <form id="formTarea">
                        <div class="mb-2">
                            <input type="text" id="task-name" class="form-control form-control-sm" placeholder="Nombre de la tarea" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" id="priority" class="form-control form-control-sm" placeholder="Prioridad: Alta, Media, Baja" required>
                        </div>
                        <div class="mb-2">
                            <input type="date" id="deadline" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <input type="text" id="status" class="form-control form-control-sm" placeholder="Estado: Pendiente, En progreso, Completada" required>
                        </div>
                        <div class="mb-3">
                            <label for="compose-textarea" class="form-label">Descripción</label>
                            <textarea id="compose-textarea" class="form-control" style="height: 300px;" placeholder="Descripción"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save me-1"></i> Guardar tarea
                        </button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0">
                            <thead class="bg-light">
                                <tr class="bg-white">
                                    <td colspan="11" class="font-weight-bold text-primary">
                                        <i class="fas fa-chevron-down me-1"></i> Lista
                                    </td>
                                </tr>
                                <tr>
                                    <th>Proyecto</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Vencimiento</th>
                                    <th>Prioridad</th>
                                    <th>Notas</th>
                                    <th>Gastos</th>
                                    <th>Cronograma</th>
                                    <th>Última actualización</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- FILA DE PROYECTO -->
                                <tr>
                                    <td>Proyecto 1</td>
                                    <td><i class="far fa-user-circle"></i> Camila Torres</td>
                                    <td>En curso</td>
                                    <td>jul. 31</td>
                                     <td><span class="badge" style="background-color: #198754; color: #fff;">Baja</span></td>
                                    <td>Elementos de acc...</td>
                                    <td>$100</td>
                                    <td>jul. 31 - ago. 1</td>
                                    <td><span class="text-muted small">Hace 2 min</span></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                Acción
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="?pg=panelTareas"><i class="fas fa-edit text-warning mr-1"></i> Editar</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                   
                                    <td>Proyecto 2</td>
                                    <td><i class="far fa-user-circle"></i> Camila Torres</td>
                                    <td>En curso</td>
                                    <td>jul. 31</td>
                                    <td><span class="badge" style="background-color: #198754; color: #fff;">Baja</span></td>
                                    <td>Elementos de acc...</td>
                                    <td>$1500</td>
                                    <td>jul. 31 - ago. 1</td>
                                    <td><span class="text-muted small">Hace 2 min</span></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                Acción
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="?pg=panelTareas"><i class="fas fa-edit text-warning mr-1"></i> Editar</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                              
                                    <td>Proyecto 3</td>
                                    <td><i class="far fa-user-circle"></i> Camila Torres</td>
                                    <td>En curso</td>
                                    <td>jul. 31</td>
                                    <td><span class="badge" style="background-color: #198754; color: #fff;">Baja</span></td>
                                    <td>Elementos de acc...</td>
                                    <td>$100</td>
                                    <td>jul. 31 - ago. 1</td>
                                    <td><span class="text-muted small">Hace 2 min</span></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                Acción
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="?pg=panelTareas"><i class="fas fa-edit text-warning mr-1"></i> Editar</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- TOTAL DE GASTOS SOLO EN SU COLUMNA -->
                                <tr class="total-gastos">
                                    <td colspan="6"></td>
                                    <td>Total: $1,700</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>