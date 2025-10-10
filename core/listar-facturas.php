<?php
require_once __DIR__ . '/class/db.php';
require_once __DIR__ . '/../config.php';

if (!function_exists('ls_html_escape')) {
  function ls_html_escape(string $v): string
  {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
  }
}

try {
  $db = (new Database())->getConnection();

  $cols = [];
  try {
    $cols = $db->query('SHOW COLUMNS FROM facturas')->fetchAll(PDO::FETCH_COLUMN);
  } catch (Throwable $e) {
    error_log("Error obteniendo columnas: " . $e->getMessage());
  }

  $sql = "SELECT 
                uuid,
                serie,
                folio,
                fecha,
                emisor_rfc,
                emisor_nombre,
                receptor_rfc,
                receptor_nombre,
                receptor_uso_cfdi,
                subtotal,
                total,
                forma_pago,
                metodo_pago,
                xml_file,
                pdf_file
            FROM facturas
            ORDER BY fecha DESC
            LIMIT 200";

  $st = $db->prepare($sql);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  if (!$rows) {
    echo '<tr><td colspan="14" class="text-muted">Sin facturas registradas</td></tr>';
    return;
  }

  foreach ($rows as $f) {
  $xmlFile = $f['xml_file'] ?? '';
  $pdfFile = $f['pdf_file'] ?? '';
  $xmlPath = '/../../app-m/uploads/xml/' . ls_html_escape($xmlFile);
  $pdfPath = '/../../app-m/uploads/pdf/' . ls_html_escape($pdfFile);
    echo '<tr>';

    echo '<td><small>' . ls_html_escape($f['uuid'] ?? '') . '</small></td>';
    echo '<td>' . ls_html_escape($f['serie'] ?? '') . '</td>';
    echo '<td>' . ls_html_escape($f['folio'] ?? '') . '</td>';
    echo '<td><small>' . ls_html_escape($f['fecha'] ?? '') . '</small></td>';

    echo '<td><code>' . ls_html_escape($f['emisor_rfc'] ?? '') . '</code></td>';
    echo '<td><small>' . ls_html_escape($f['emisor_nombre'] ?? '') . '</small></td>';

    echo '<td><code>' . ls_html_escape($f['receptor_rfc'] ?? '') . '</code></td>';
    echo '<td><small>' . ls_html_escape($f['receptor_nombre'] ?? '') . '</small></td>';

    echo '<td>' . ls_html_escape($f['receptor_uso_cfdi'] ?? '') . '</td>';

    echo '<td>$' . number_format((float)($f['subtotal'] ?? 0), 2) . '</td>';
    echo '<td>$' . number_format((float)($f['total'] ?? 0), 2) . '</td>';

    echo '<td>' . ls_html_escape($f['forma_pago'] ?? '') . '</td>';
    echo '<td>' . ls_html_escape($f['metodo_pago'] ?? '') . '</td>';

    echo '<td>
                <a href="' . $pdfPath . '" class="text-danger" download title="Descargar PDF"> <i class="fas fa-file-pdf fa-lg">pdf</i></a>
                <a href="' . $xmlPath . '" class="text-primary ms-2" download title="Descargar XML"> <i class="fas fa-file-code fa-lg">xml</i></a>
                </td>';

    echo '</tr>';
  }
} catch (Throwable $e) {
  echo '<tr><td colspan="14" class="text-danger">Error: ' . ls_html_escape($e->getMessage()) . '</td></tr>';
}
