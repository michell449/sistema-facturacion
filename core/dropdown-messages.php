<?php
$nMessage = 0;
//Incluir rutina para recuperar los mensajes de la base de de datos

//Desplegando menu de mensajes desplegable
echo '<li class="nav-item dropdown">';
echo '<a class="nav-link" data-bs-toggle="dropdown" href="#">';
echo '<i class="bi bi-chat-text"></i>';
if ($nMessage < 1) {
  echo '<span class="navbar-badge badge text-bg-danger">' . $nMessage . '</span> </a>';
  echo '<div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">';
  echo '<a href="#" class="dropdown-item">';
  //Ciclo para mostrar los mensajes recuperados 
  for ($i = 1; $i <= $nMessage; $i++) {
    //<!--begin::Message-->
    echo '<div class="d-flex">';
    echo '<div class="flex-shrink-0">';
    echo '<img src="../assets/img/user1-128x128.jpg" alt="User Avatar"  class="img-size-50 rounded-circle me-3"/>';
    echo '</div>';
    echo '<div class="flex-grow-1"><h3 class="dropdown-item-title">';
    echo 'Brad Diesel'; //nombre de usuario recuperado
    echo '<span class="float-end fs-7 text-danger"><i class="bi bi-star-fill"></i></span></h3>';
    echo '<p class="fs-7">';
    echo 'Call me whenever you can...'; // Mensaje corto recuperado 
    echo '</p>';
    echo '<p class="fs-7 text-secondary">';
    echo '<i class="bi bi-clock-fill me-1"></i>';
    echo '4 Hours Ago'; //Tiempo en horas o dias del mensaje 
    echo '</p>';
    echo '</div>';
    echo '</div>';
    //<!--end::Message-->
  }
  echo '<div class="dropdown-divider"></div>';
  echo '<a href="#" class="dropdown-item dropdown-footer">Ver todos los mensajes</a>';
  echo '</div>';
  //   <!--end::Messages Dropdown Menu-->
}
echo '</li>';
echo '</a>';
