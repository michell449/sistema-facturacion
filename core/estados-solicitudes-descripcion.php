<?php
$estados = [
    'pendiente' => [
        'label' => 'Pendiente',
        'cls'   => 'bg-secondary',
        'icon'  => 'fas fa-clock',
        'desc'  => 'Solicitud registrada, aún sin paquetes.'
    ],
    'aceptada' => [
        'label' => 'Aceptada',
        'cls'   => 'bg-info',
        'icon'  => 'fas fa-hourglass-half',
        'desc'  => 'El SAT aceptó la solicitud; puede seguir generando paquetes.'
    ],
    'terminada' => [
        'label' => 'Terminada',
        'cls'   => 'bg-success',
        'icon'  => 'fas fa-check-circle',
        'desc'  => 'El SAT terminó la generación de paquetes.'
    ],
    'rechazada' => [
        'label' => 'Rechazada',
        'cls'   => 'bg-danger',
        'icon'  => 'fas fa-times-circle',
        'desc'  => 'Solicitud rechazada por el SAT (revisar mensaje / filtros).'
    ],
    'error' => [
        'label' => 'Error',
        'cls'   => 'bg-danger',
        'icon'  => 'fas fa-exclamation-triangle',
        'desc'  => 'Error interno durante descarga / procesamiento.'
    ],
    'vencida' => [
        'label' => 'Vencida',
        'cls'   => 'bg-warning',
        'icon'  => 'fas fa-hourglass-end',
        'desc'  => 'Expirada (72h después de terminada) – ya no se garantiza descarga.'
    ],
];

echo '<table class="table table-sm table-bordered w-auto small mb-0">';
echo ' <thead class="table-light"><tr><th style="white-space:nowrap;">Estado</th><th>Descripción</th></tr></thead>';
echo ' <tbody>';
foreach ($estados as $k => $data) {
    $badge = '<span class="badge '.$data['cls'].'"><i class="'.$data['icon'].' me-1"></i>'.$data['label'].'</span>';
    echo '<tr><td>'.$badge.'</td><td>'.htmlspecialchars($data['desc']).'</td></tr>';
}
echo ' </tbody>';
echo '</table>';


