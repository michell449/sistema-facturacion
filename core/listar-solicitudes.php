<?php
// core/listar-solicitudes.php

require_once __DIR__ . '/class/db.php';

function ls_html_escape(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

try {
    $db = (new Database())->getConnection();
    
    // ... (Tu código de filtros y consulta SQL se mantiene igual aquí arriba)
    $fRfc    = $_GET['rfc'] ?? '';
    $fTipo   = $_GET['tipo'] ?? '';
    $fEstado = $_GET['estado'] ?? '';
    $fTexto  = $_GET['q'] ?? '';

    $where = [];
    $params = [];

    // Lógica de filtrado (simplificada para el ejemplo)
    if ($fRfc !== '') { $where[] = "(rfc_emisor LIKE ? OR rfc_receptor LIKE ?)"; $params[] = "%$fRfc%"; $params[] = "%$fRfc%"; }
    if ($fTipo !== '') { $where[] = "tipo = ?"; $params[] = $fTipo; }
    if ($fEstado !== '') { $where[] = "estado = ?"; $params[] = $fEstado; }

    $sql = "SELECT * FROM cf_solicitudes";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY id_solicitud DESC LIMIT 100";
    
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
                if ($total === 0) {
                    $total = count($pj);
                }
                foreach ($pj as $p) {
                    if (($p['estado'] ?? '') === 'descargado') {
                        $paquetesDesc++;
                    }
                }
            }
        }

        $tipoMap = ['emitidas' => ['primary', 'Emitidas'], 'recibidas' => ['success', 'Recibidas']];
        $tipoValor = $r['tipo'] ?? 'desconocido';
        [$tCls, $tTxt] = $tipoMap[$tipoValor] ?? ['secondary', ucfirst($tipoValor)];

        $estados = [
            'pendiente' => ['secondary', '<i class="fas fa-clock me-1"></i>Pendiente'],
            'aceptada'  => ['info', '<i class="fas fa-hourglass-half me-1"></i>Aceptada'],
            'terminada' => ['success', '<i class="fas fa-check-circle me-1"></i>Terminada'],
            'rechazada' => ['danger', '<i class="fas fa-times-circle me-1"></i>Rechazada'],
            'error'     => ['danger', '<i class="fas fa-exclamation-triangle me-1"></i>Error'],
            'vencida'   => ['warning', '<i class="fas fa-hourglass-end me-1"></i>Vencida']
        ];
        [$eCls, $eTxt] = $estados[$r['estado']] ?? ['secondary', ls_html_escape($r['estado'])];

        $rango = ls_html_escape(($r['fecha_ini'] ?? '') . ' → ' . ($r['fecha_fin'] ?? ''));

        echo '<tr data-id="' . (int)$r['id_solicitud'] . '">';
        echo '<td>' . (int)$r['id_solicitud'] . '</td>';
        echo '<td class="text-break" style="max-width:180px"><small>' . ls_html_escape($r['solicitud_id_sat']) . '</small></td>';
        $rfcMostrar = $r['rfc_emisor'] ?: ($r['rfc_receptor'] ?: ($r['rfc'] ?? ''));
        echo '<td>' . ls_html_escape($rfcMostrar) . '</td>';
        echo '<td><span class="badge bg-' . $tCls . '">' . $tTxt . '</span></td>';
        echo '<td><small>' . $rango . '</small></td>';
        echo '<td><span class="badge bg-secondary fw-normal">' . $paquetesDesc . '/' . ($total) . '</span></td>';
        echo '<td class="estado-col"><span class="badge bg-' . $eCls . '">' . $eTxt . '</span></td>';
        echo '<td><small>' . ls_html_escape($r['fecha_creacion'] ?? '') . '</small></td>';
        echo '<td><small>' . ls_html_escape($r['ultima_verificacion'] ?? '') . '</small></td>';
        
        // --- COLUMNA DE ACCIONES ---
        echo '<td>';
        echo '<a href="#" class="btn btn-xs btn-primary btn-verificar-individual" data-id="' . $r['id_solicitud'] . '" title="Verificar estado con el SAT"><i class="fas fa-sync-alt"></i></a>';

        if ($r['estado'] === 'terminada' && $paquetesDesc > 0) {
            echo ' <button class="btn btn-success btn-sm btn-procesar-paquetes" data-id="' . (int)$r['id_solicitud'] . '" title="Procesar Paquetes"><i class="fas fa-cogs"></i></button>';
        } elseif ($r['estado'] === 'terminada') {
            echo ' <button class="btn btn-outline-success btn-sm btn-descargar-paquetes" data-id="' . (int)$r['id_solicitud'] . '" title="Descargar Paquetes"><i class="fas fa-download"></i></button>';
        }
        
        if (!empty($paqs)) {
            echo '<div class="btn-group mt-1" role="group">';
            foreach ($paqs as $p) {
                if (!empty($p['zip_path']) && file_exists(__DIR__ . '/../' . ltrim($p['zip_path'], '/\\'))) {
                    $url = ls_html_escape($p['zip_path']);
                    echo '<a href="' . $url . '" class="btn btn-outline-secondary btn-xs" target="_blank" title="Abrir paquete ' . basename($url) . '"><i class="fas fa-file-archive"></i></a>';
                }
            }
            echo '</div>';
        }
        echo '</td>';
        
        echo '</tr>';
    }
} catch (Throwable $e) {
    echo '<tr><td colspan="10" class="text-danger">Error: ' . ls_html_escape($e->getMessage()) . '</td></tr>';
}