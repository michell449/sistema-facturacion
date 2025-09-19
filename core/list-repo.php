<?php
//Incluir rutina para recuperar los mensajes de la base de de datos

$nMessage = 1;

if ($nMessage > 0) {
  //Ciclo para mostrar los mensajes recuperados 
  for ($i = 1; $i <= $nMessage; $i++) {
  echo '<div class="card-tools">';
  echo '<a href="#" class="btn btn-sm btn-success float-end px-5" data-bs-toggle="modal" data-bs-target="#printPreviewModal">';
  echo 'Imprimir <i class="bi bi-printer"></i>';
  echo '</a>';
  echo '</div>';
  echo '</div>';
  echo '<div class="card-body p-0">';
  echo '<div class="table-responsive">';
  echo '<table id="clientsTable" class="table table-bordered table-striped">';
  echo '<thead>';
  echo '<tr>';
  echo '<th>ID</th>';
  echo '<th>Proyecto</th>';
  echo '<th>Tarea</th>';
  echo '<th>Tarea Completada</th>';
  echo '<th>Duración de Trabajo</th>';
  echo '<th>Progreso</th>';
  echo '<th>Estado</th>';
  echo '</tr>';
  echo '</thead>';
  echo '<tbody>';

  $numRegistros = 15; // Cambiar este valor para mostrar más o menos registros este valor para mostrar el número de registros que desees
  
  for ($i = 1; $i <= $numRegistros; $i++) {
    $proyecto = 'Proyecto ' . $i;
    $due = date('Y-m-d', strtotime("+".($i*5)." days"));
    $tarea = rand(1, 5);
    $tarea_completada = rand(0, $tarea);
    $duracion = rand(0, 8) . ' Hr/s.';
    $progreso = $tarea > 0 ? intval(($tarea_completada / $tarea) * 100) : 0;
    $progreso_class = $progreso == 100 ? 'bg-success' : ($progreso == 0 ? 'bg-secondary' : 'bg-warning');
    $progreso_text = $progreso . '% Completado';
    $estados = [
      'Pendiente' => 'bg-secondary',
      'En progreso' => 'bg-warning',
      'Finalizado' => 'bg-success',
      'Inicio' => 'bg-primary',
    ];
    $estado_keys = array_keys($estados);
    $estado = $estado_keys[array_rand($estado_keys)];
    $estado_class = $estados[$estado];
    echo '<tr>';
    echo '<td>'.$i.'</td>';
    echo '<td>'.$proyecto.'<br><small>Due: '.$due.'</small></td>';
    echo '<td>'.$tarea.'</td>';
    echo '<td>'.$tarea_completada.'</td>';
    echo '<td>'.$duracion.'</td>';
    echo '<td>';
    echo '<div class="progress progress-xs">';
    echo '<div class="progress-bar '.$progreso_class.'" style="width: '.$progreso.'%"></div>';
    echo '</div>';
    echo '<span class="badge '.$progreso_class.'">'.$progreso_text.'</span>';
    echo '</td>';
    echo '<td><span class="badge '.$estado_class.'">'.$estado.'</span></td>';
    echo '</tr>';
  }
  echo '</tbody>';
  echo '</table>';
  echo '</div>';
  echo '</div>';
  }
}