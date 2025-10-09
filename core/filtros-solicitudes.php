<?php
$fRfc    = isset($_GET['rfc']) ? trim($_GET['rfc']) : '';
$fTipo   = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$fEstado = isset($_GET['estado']) ? $_GET['estado'] : '';

$estados = ['pendiente','aceptada','terminada','rechazada','error','vencida'];

echo '<form class="row g-2 mb-3" method="get" autocomplete="off">';
echo '  <input type="hidden" name="pg" value="ver-peticiones" />';
// RFC
echo '  <div class="col-md-2">';
echo '    <label class="form-label small mb-1">RFC</label>';
echo '    <input type="text" name="rfc" value="'.htmlspecialchars($fRfc).'" class="form-control form-control-sm" placeholder="RFC">';
echo '  </div>';
// Tipo
echo '  <div class="col-md-2">';
echo '    <label class="form-label small mb-1">Tipo</label>';
echo '    <select name="tipo" class="form-select form-select-sm">';
echo '      <option value="">Todos</option>';
echo '      <option value="emitidas" '.($fTipo==='emitidas'?'selected':'').'>Emitidas</option>';
echo '      <option value="recibidas" '.($fTipo==='recibidas'?'selected':'').'>Recibidas</option>';
echo '    </select>';
echo '  </div>';
// Estado
echo '  <div class="col-md-2">';
echo '    <label class="form-label small mb-1">Estado</label>';
echo '    <select name="estado" class="form-select form-select-sm">';
echo '      <option value="">Todos</option>';
foreach ($estados as $e) {
    $sel = ($fEstado === $e) ? 'selected' : '';
    echo '    <option value="'.$e.'" '.$sel.'>'.ucfirst($e).'</option>';
}
echo '    </select>';
echo '  </div>';
// Botones
echo '  <div class="col-md-3 d-flex align-items-end gap-2">';
echo '    <button class="btn btn-success btn-sm w-100"><i class="fas fa-search"></i> Filtrar</button>';
echo '    <a href="panel?pg=ver-peticiones" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros"><i class="fas fa-undo"></i></a>';
echo '  </div>';
echo '</form>';
