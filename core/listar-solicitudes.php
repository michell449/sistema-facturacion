<?php
// app-m/core/listar-solicitudes.php

require_once __DIR__ . '/class/db.php';

function ls_html_escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

try {
    $db = (new Database())->getConnection();
    $cols = [];
    try {
        $cols = $db->query('SHOW COLUMNS FROM cf_solicitudes')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
    }

    $has = fn(string $c) => in_array($c, $cols, true);
    $rfcCol = $has('rfc') ? 'rfc' : ($has('rfc_emisor') ? 'rfc_emisor' : ($has('rfc_receptor') ? 'rfc_receptor' : 'NULL'));

    $sqlRFC = '';
    if ($has('rfc')) $sqlRFC .= 'rfc,';
    if ($has('rfc_emisor')) $sqlRFC .= 'rfc_emisor,';
    if ($has('rfc_receptor')) $sqlRFC .= 'rfc_receptor,';
    $createdCol = $has('created_at') ? 'created_at' : ($has('fecha_creacion') ? 'fecha_creacion' : 'fecha_ini');

    $fRfc    = $_GET['rfc'] ?? '';
    $fTipo   = $_GET['tipo'] ?? '';
    $fEstado = $_GET['estado'] ?? '';
    $fTexto  = $_GET['q'] ?? '';

    $where = [];
    $params = [];

    if ($fRfc !== '' && $rfcCol !== 'NULL') {
        $where[] = "$rfcCol LIKE ?";
        $params[] = "%$fRfc%";
    }

    if ($fTipo !== '' && in_array($fTipo, ['emitidas', 'recibidas', 'emitidos'])) {
        if ($fTipo === 'emitidas') {
            $where[] = "(tipo='emitidas' OR tipo='emitidos')";
        } else {
            $where[] = 'tipo=?';
            $params[] = $fTipo;
        }
    }

    if ($fEstado !== '' && in_array($fEstado, ['pendiente', 'aceptada', 'terminada', 'rechazada', 'error', 'vencida'])) {
        $where[] = 'estado=?';
        $params[] = $fEstado;
    }

    if ($fTexto !== '') {
        $where[] = "(solicitud_id_sat LIKE ? OR $rfcCol LIKE ? OR tipo LIKE ?)";
        $params[] = "%$fTexto%";
        $params[] = "%$fTexto%";
        $params[] = "%$fTexto%";
    }

    $sql = "SELECT id_solicitud, solicitud_id_sat, " . $sqlRFC . "tipo, fecha_ini, fecha_fin, estado, paquetes_json, total_paquetes, ultima_verificacion, $createdCol AS created_at FROM cf_solicitudes";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id_solicitud DESC LIMIT 100';

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo '<tr><td colspan="10" class="text-muted">Sin resultados</td></tr>';
        return;
    }

    foreach ($rows as $r) {
        $paquetesDesc = 0;
        $total = (int)($r['total_paquetes'] ?? 0);
        $paqs = [];

        if (!empty($r['paquetes_json'])) {
            $pj = json_decode($r['paquetes_json'], true);
            if (is_array($pj)) {
                $paqs = $pj;
                $total = $total ?: count($pj);
                foreach ($pj as $p) {
                    if (in_array($p['estado'] ?? '', ['descargado', 'procesado'])) $paquetesDesc++;
                }
            }
        }

        $tipoMap = [
            'emitidas' => ['primary', 'Emitidas'],
            'recibidas' => ['success', 'Recibidas'],
        ];
        $tipoValor = $r['tipo'] ?? 'desconocido';
        [$tCls, $tTxt] = $tipoMap[$tipoValor] ?? ['secondary', ucfirst($tipoValor)];

        $estados = [
            'pendiente' => ['secondary', '<i class="fas fa-clock me-1"></i>Esperando verificación'],
            'aceptada'  => ['info', '<i class="fas fa-hourglass-half me-1"></i>Aceptada'],
            'terminada' => ['success', '<i class="fas fa-check-circle me-1"></i>Terminada'],
            'rechazada' => ['danger', '<i class="fas fa-times-circle me-1"></i>Rechazada'],
            'error'     => ['danger', '<i class="fas fa-exclamation-triangle me-1"></i>Error'],
            'vencida'   => ['warning', '<i class="fas fa-hourglass-end me-1"></i>Vencida']
        ];
        [$eCls, $eTxt] = $estados[$r['estado']] ?? ['secondary', ls_html_escape($r['estado'])];

        $rango = ($r['tipo'] === 'folio') ? '<code>UUID</code>' : ls_html_escape(($r['fecha_ini'] ?? '') . ' → ' . ($r['fecha_fin'] ?? ''));

        echo '<tr data-id="' . (int)$r['id_solicitud'] . '">';
        echo '<td>' . (int)$r['id_solicitud'] . '</td>';
        echo '<td class="text-break" style="max-width:180px"><small>' . ls_html_escape($r['solicitud_id_sat']) . '</small></td>';

        // RFC a mostrar
        $rfcMostrar = '';
        if (($r['tipo'] ?? '') === 'recibidas') {
            $rfcMostrar = $r['rfc_receptor'] ?? '';
        } elseif (($r['tipo'] ?? '') === 'emitidas' || ($r['tipo'] ?? '') === 'emitidos') {
            $rfcMostrar = $r['rfc_emisor'] ?? '';
        } else {
            $rfcMostrar = $r['rfc'] ?? ($r['rfc_emisor'] ?? $r['rfc_receptor'] ?? '');
        }
        echo '<td>' . ls_html_escape($rfcMostrar) . '</td>';

        echo '<td><span class="badge bg-' . $tCls . '">' . $tTxt . '</span></td>';
        echo '<td><small>' . $rango . '</small></td>';
        echo '<td><span class="badge bg-secondary fw-normal" style="font-size:.75rem;">' . $paquetesDesc . '/' . ($total) . '</span></td>';
        echo '<td class="estado-col"><span class="badge bg-' . $eCls . '">' . $eTxt . '</span></td>';
        echo '<td><small>' . ls_html_escape($r['created_at'] ?? '') . '</small></td>';
        echo '<td><small>' . ls_html_escape($r['ultima_verificacion'] ?? '') . '</small></td>';

        // Acciones
        echo '<td>';
        echo '<a href="#" class="btn btn-xs btn-primary btn-verificar-individual" data-id="' . $r['id_solicitud'] . '" title="Verificar estado con el SAT"><i class="fas fa-sync-alt"></i>     </a>';
        echo ' <button class="btn btn-outline-success btn-sm btn-descargar-paquetes" title="Descargar paquetes"><i class="fas fa-download"></i></button>';
        foreach ($paqs as $p) {
            if (!empty($p['zip_path']) && file_exists(__DIR__ . '/../' . ltrim($p['zip_path'], '/\\'))) {
                $url = ls_html_escape($p['zip_path']);
                echo ' <a href="' . $url . '" class="btn btn-link btn-sm" target="_blank" title="Descargar ZIP"><i class="fas fa-file-archive"></i></a>';
            }
        }


        echo '</td>';
        echo '</tr>';
    }
} catch (Throwable $e) {
    echo '<tr><td colspan="10" class="text-danger">Error: ' . ls_html_escape($e->getMessage()) . '</td></tr>';
}
