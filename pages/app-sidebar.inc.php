<!--begin::Sidebar-->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <!--begin::Sidebar Brand-->
    <div class="sidebar-brand">
        <!--begin::Brand Link-->
        <a href="index" class="brand-link">
            <!--begin::Brand Image-->
            <!--<img src="../assets/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image opacity-75 shadow" />-->
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light"><?php echo SYSNAME . ' ' . VERSION; ?></span>
            <!--end::Brand Text-->
        </a>
        <!--end::Brand Link-->
    </div>
    <!--end::Sidebar Brand-->

    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                aria-label="Main navigation" data-accordion="false" id="navigation">
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>
                            Dashboard
                            <i class="#"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">



                    </ul>
                </li>
                <!--end::menu-->

                <!--begin::Modulo Planeación -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-clipboard"></i>
                        <p>
                            Planeacion
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="panel?pg=proyectos-casos" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-text"></i>
                                <p>Proyectos/casos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="panel?pg=visualizar-Tareas" class="nav-link">
                                <i class="nav-icon bi bi-check-square"></i>
                                <p>Tareas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="panel?pg=reportes-planeacion" class="nav-link">
                                <i class="nav-icon bi bi-bar-chart"></i>
                                <p>Reportes</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!--begin::Modulo Facturación -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-receipt-cutoff"></i>
                        <p>
                            Facturación
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="panel?pg=crear-facturas" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-text"></i>
                                <p>Crear factura</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="panel?pg=visualizar-facturas" class="nav-link">
                                <i class="nav-icon bi bi-folder2-open"></i>
                                <p>Ver facturas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="panel?pg=cargar-facturas" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-arrow-down"></i>
                                <p>Cargar facturas</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <!--end::Modulo Facturación -->

                <!--end::Modulo Planeación -->

                <!--begin::Modulo Configuración 
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-gear-fill"></i>
                        <p>
                            Configuracion
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="panel?pg=usuarios-config" class="nav-link">
                                <i class="nav-icon bi bi-person"></i>
                                <p>Usuarios</p>
                            </a>
                        </li>
                       <li class="nav-item">
                            <a href="panel?pg=empleados-config" class="nav-link">
                                <i class="nav-icon bi bi-people-fill"></i>
                                <p>Empleados</p>
                            </a>
                        </li>
                    </ul>
                </li>-->
                <!--end::Modulo Configuración -->

                <!--begin::Modulo Documentacion 
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-motherboard-fill"></i>
                        <p>
                            Documentacion
                            <i class="nav-arrow bi bi-chevron-right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-speedometer2"></i>
                                <p>Inicio/panel principal</p>
                            </a>
                        </li>
                       <li class="nav-item">
                            <a href="panel?pg=usuarios-doc" class="nav-link">
                                <i class="nav-icon bi bi-people-fill"></i>
                                <p>Usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-collection"></i>
                                <p>Categorias</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-building"></i>
                                <p>Instituciones</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-file-earmark-text"></i>
                                <p>Documentos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-chat-dots"></i>
                                <p>Mensajes</p>
                            </a>
                        </li>
                    </ul>
                </li> -->
                <!--end::Modulo Documentacion -->


            </ul>
            <!--end::Sidebar Menu-->
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>