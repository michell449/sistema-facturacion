<?php
$nMessage = 6;
//Incluir rutina para recuperar los mensajes de la base de de datos

if ($nMessage > 0) {
  //Ciclo para mostrar los mensajes recuperados 
  for ($i = 1; $i <= $nMessage; $i++) {
    echo '<div class="col-lg-4 col-6 mb-3">';
    echo '<div class="small-box bg-info">';
    echo '<div class="inner text-white">';
    echo '<h3>Ver</h3>';
    echo '<p>Total de proyectos</p>';
    echo '</div>';
    echo '<div class="icon">';
    echo '<i class="ion ion-social-buffer"></i>';
    echo '</div>';
    echo '<a href="?pg=visuProyectos-Casos" class="small-box-footer">Más información <i class="fas fa-arrow-circle-right"></i></a>';
    echo '</div>';
    echo '</div>';
  }

  // Separador visual entre botones y tabla de proyectos
  echo '<!-- Separador visual entre botones y tabla de proyectos -->';
  echo '<div style="height: 48px; display: flex; align-items: center; justify-content: center;">';
  echo '<hr style="width: 80%; border: 0; border-top: 2px solid #bbb; margin: 0;">';
  echo '</div>';

 
// agergar tabla de proyectos 
  $numRegistros = 1;
  $proyectos = [
    [
      'nombre' => 'Demanda civil',
      'progreso' => 70,
      'estado' => 'En progreso',
      'badge' => 'primary',
      'progreso_class' => 'text-bg-primary',
    ],
    [
      'nombre' => 'Caso jurídico',
      'progreso' => 100,
      'estado' => 'Completada',
      'badge' => 'success',
      'progreso_class' => 'text-bg-success',
    ],
    [
      'nombre' => 'Demanda matrimonial',
      'progreso' => 5,
      'estado' => 'Pendiente',
      'badge' => 'secondary',
      'progreso_class' => 'text-bg-secondary',
    ],
  ];

  // Si el valor definido arriba es inválido, se ajusta automáticamente
  if ($numRegistros < 2) $numRegistros = 2;
  if ($numRegistros > count($proyectos)) $numRegistros = count($proyectos);

  echo '<!-- Tabla de progreso de proyectos -->';
  echo '<div class="row">';
  echo '<div class="col-lg-12 connectedSortable">';
  echo '<div class="bg-primary text-white p-3 mb-3 rounded">';
  echo '<h5 class="mb-0">Lista de proyectos</h5>';
  echo '</div>';
  echo '<div class="table-responsive">';
  echo '<table id="clientsTable" class="table table-bordered table-striped">';
  echo '<thead>';
  echo '<tr>';
  echo '<th>No.</th>';
  echo '<th>Proyecto</th>';
  echo '<th>Progreso</th>';
  echo '<th>Estado</th>';
  echo '<th></th>';
  echo '</tr>';
  echo '</thead>';
  echo '<tbody>';
  
  
  for ($i = 0; $i < $numRegistros; $i++) {
    $p = $proyectos[$i];
    echo '<tr>';
    echo '<td>'.($i+1).'</td>';
    echo '<td>'.$p['nombre'].'</td>';
    echo '<td>';
    echo '<div class="progress progress-xs">';
    echo '<div class="progress-bar '.$p['progreso_class'].'" style="width: '.$p['progreso'].'%"></div>';
    echo '</div>';
    echo '<span class="badge '.$p['progreso_class'].'">'.$p['progreso'].'%</span>';
    echo '</td>';
    echo '<td><span class="badge bg-'.$p['badge'].'">'.$p['estado'].'</span></td>';
    echo '<td align="center">';
    echo '<a href="?pg=proyectos-casos" class="btn btn-info btn-sm">';
    echo '<i class="bi bi-archive-fill px-2"></i>Ver';
    echo '</a>';
    echo '</td>';
    echo '</tr>';
  }
  echo '/tbody>';
  echo '</table>';
  echo '</div>';
  echo '</div>';
  echo '</div>';
}

// Separador visual entre tablas
echo '<!-- Separador visual entre tablas -->';
echo '<div style="height: 48px; display: flex; align-items: center; justify-content: center;">';
echo '<hr style="width: 80%; border: 0; border-top: 2px solid #bbb; margin: 0;">';
echo '</div>';

// Gestor de categorías
echo '<!-- Gestor de categorías -->';
echo '<div class="row">';
echo '<div class="col-lg-12 connectedSortable">';
echo '<div class="bg-primary text-white p-3 mb-3 rounded">';
echo '<h5 class="mb-0">Documentos del proyecto</h5>';
echo '</div>';
echo '<!-- Buscador -->';
echo '<div style="display: flex; align-items: center; gap: 8px; max-width: 420px; margin-bottom: 16px;">';
echo '<input type="text" placeholder="Buscar los archivos" style="height: 36px; font-size: 16px; padding: 4px 14px; width: 260px;">';
echo '<button type="button" style="height: 36px; font-size: 16px; padding: 4px 18px;">Buscar</button>';
echo '</div>';
echo '<!-- Sección de carpetas -->';
echo '<button class="btn btn-secondary btn-block mb-3" data-toggle="modal" data-target="#modalNuevaCarpeta">';
echo '<i class="fas fa-folder-plus mr-1"></i> Nueva carpeta';
echo '</button>';
echo '<h5 class="mb-3">Nueva carpetas</h5>';
echo '<div class="row">';
echo '<div class="col-lg-4 col-md-4 col-6">';
echo '<div class="card text-center">';
echo '<div class="card-body d-flex flex-column align-items-center">';
echo '<i class="fas fa-folder fa-3x text-primary mb-3"></i>';
echo '<h6 class="card-title mb-1">PRUEBA1</h6>';
echo '<p class="card-text text-muted mb-3">1 archivos</p>';
echo '<form>';
echo '<input type="file" id="fileInput1" style="display:none;">';
echo '<label for="fileInput1" style="display:inline-block; padding:6px 16px; background:#007bff; color:#fff; border-radius:4px; cursor:pointer; font-size:15px; margin:0;">';
echo '<span style="font-size:18px; font-weight:bold; vertical-align:middle;">+</span> Subir archivo';
echo '</label>';
echo '</form>';
echo '</div>';
echo '        </div>';
echo '      </div>';
echo '      <!-- Carpeta PRUEBA2 -->';
echo '      <div class="col-lg-4 col-md-4 col-6">';
echo '        <div class="card text-center">';
echo '          <div class="card-body d-flex flex-column align-items-center">';
echo '            <i class="fas fa-folder fa-3x text-primary mb-3"></i>';
echo '            <h6 class="card-title mb-1">PRUEBA2</h6>';
echo '            <p class="card-text text-muted mb-3">5 archivos</p>';
echo '            <form>';
echo '              <input type="file" id="fileInput2" style="display:none;">';
echo '              <label for="fileInput2" style="display:inline-block; padding:6px 16px; background:#007bff; color:#fff; border-radius:4px; cursor:pointer; font-size:15px; margin:0;">';
echo '                <span style="font-size:18px; font-weight:bold; vertical-align:middle;">+</span> Subir archivo';
echo '              </label>';
echo '            </form>';
echo '          </div>';
echo '        </div>';
echo '      </div>';
echo '      <!-- Carpeta PRUEBA3 -->';
echo '      <div class="col-lg-4 col-md-4 col-6">';
echo '        <div class="card text-center">';
echo '          <div class="card-body d-flex flex-column align-items-center">';
echo '            <i class="fas fa-folder fa-3x text-primary mb-3"></i>';
echo '            <h6 class="card-title mb-1">PRUEBA3</h6>';
echo '            <p class="card-text text-muted mb-3">2 archivos</p>';
echo '            <form>';
echo '              <input type="file" id="fileInput3" style="display:none;">';
echo '              <label for="fileInput3" style="display:inline-block; padding:6px 16px; background:#007bff; color:#fff; border-radius:4px; cursor:pointer; font-size:15px; margin:0;">';
echo '                <span style="font-size:18px; font-weight:bold; vertical-align:middle;">+</span> Subir archivo';
echo '              </label>';
echo '            </form>';
echo '          </div>';
echo '        </div>';
echo '      </div>';
echo '    </div>';
echo '    <!-- Últimos archivos subidos -->';
echo '    <h5 class="mt-4">Últimos archivos subidos</h5>';
echo '    <div class="table-responsive">';
echo '      <table class="table table-bordered table-striped">';
echo '        <thead class="bg-primary text-white">';
echo '          <tr>';
echo '            <th>Nombre</th>';
echo '            <th>Miembros</th>';
echo '            <th>Última modificación</th>';
echo '            <th>Peso</th>';
echo '            <th class="text-center">Acciones</th>';
echo '          </tr>';
echo '        </thead>';
echo '        <tbody>';
echo '          <tr>';
echo '            <td>';
echo '              <a href="#"><i class="fas fa-file-archive mr-1 text-blue"></i> planes.zip</a>';
echo '            </td>';
echo '            <td>2 Miembros</td>';
echo '            <td>2022-11-22 20:57:53</td>';
echo '            <td>1.2 MB</td>';
echo '            <td class="text-center">';
echo '              <button class="btn btn-danger btn-sm">Eliminar</button>';
echo '            </td>';
echo '          </tr>';
echo '          <tr>';
echo '            <td>';
echo '              <a href="#"><i class="fas fa-file-archive mr-1 text-blue"></i> posventa.zip</a>';
echo '</td>';
echo '<td>Solo tú</td>';
echo '<td>2022-11-22 16:52:49</td>';
echo '<td>1.8 MB</td>';
echo '<td class="text-center">';
echo '<button class="btn btn-danger btn-sm">Eliminar</button>';
echo '</td>';
echo '</tr>';
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';