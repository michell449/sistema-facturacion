<?php
/* Procesa un paquete ya descargado registrado en cf_solicitudes.paquetes_json
 * POST JSON: { "id_solicitud":1, "package_id":"..." }
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../class/db.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit; }
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$idSolicitud = (int)($input['id_solicitud'] ?? 0);
$packageId = trim($input['package_id'] ?? '');
if ($idSolicitud<=0 || $packageId==='') { http_response_code(400); echo json_encode(['success'=>false,'error'=>'id_solicitud y package_id requeridos']); exit; }

try {
    $db = (new Database())->getConnection();
    $sel = $db->prepare('SELECT paquetes_json, total_cfdis FROM cf_solicitudes WHERE id_solicitud = ?');
    $sel->execute([$idSolicitud]);
    $sol = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$sol) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Solicitud no encontrada']); exit; }

    $paquetes = json_decode($sol['paquetes_json'] ?? '[]', true);
    if (!is_array($paquetes)) $paquetes = [];
    $foundIndex = null; for ($i=0; $i<count($paquetes); $i++) { if (($paquetes[$i]['package_id']??'') === $packageId) { $foundIndex = $i; break; } }
    if ($foundIndex===null) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'package_id no pertenece a solicitud']); exit; }
    $pkg = $paquetes[$foundIndex];
    if (empty($pkg['zip_path'])) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Paquete sin zip_path']); exit; }
    if (($pkg['procesado'] ?? 0) == 1) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Paquete ya procesado']); exit; }

    $zipPath = $pkg['zip_path'];
    if (!file_exists($zipPath)) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'ZIP no existe']); exit; }

    $zip = new ZipArchive();
    if ($zip->open($zipPath)!==true) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'No se pudo abrir ZIP']); exit; }

    $destDir = dirname($zipPath) . '/xmls_' . pathinfo($zipPath, PATHINFO_FILENAME);
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);

    $insertados=0; $omitidos=0; $errores=0; $warns=[]; $addWarn=function($c) use (&$warns){ $warns[$c]=($warns[$c]??0)+1; };

    for ($i=0; $i<$zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'xml') continue;
        $content = $zip->getFromIndex($i);
        if ($content===false) { $errores++; $addWarn('ZIP_ENTRY_READ_ERROR'); continue; }
        $nombre = basename($entry);
        $nombreSeguro = preg_replace('/[^A-Za-z0-9_.-]/','_', $nombre);
        file_put_contents($destDir . '/' . $nombreSeguro, $content);
        $sx = @simplexml_load_string($content);
        if (!$sx) { $errores++; $addWarn('XML_PARSE_ERROR'); continue; }
        $attrs = $sx->attributes();
        $folio = (string)($attrs['Folio'] ?? '');
        $fecha = (string)($attrs['Fecha'] ?? date('Y-m-d H:i:s'));
        $tipo = (string)($attrs['TipoDeComprobante'] ?? '');
        $total = (float)($attrs['Total'] ?? 0);
        $emisorNombre=''; $rfcEmisor='';
        if (isset($sx->Emisor)) { $aE = $sx->Emisor->attributes(); $emisorNombre=(string)($aE['Nombre']??''); $rfcEmisor=(string)($aE['Rfc']??''); }
        $uuid=''; $ns=$sx->getNamespaces(true); if(isset($ns['tfd'])) { $tfd=$sx->children($ns['tfd']); if($tfd && isset($tfd->TimbreFiscalDigital)) { $uuid=(string)$tfd->TimbreFiscalDigital['UUID']; } }
        if ($uuid==='') { $omitidos++; $addWarn('CFDI_UUID_MISSING'); continue; }
        $chk = $db->prepare('SELECT 1 FROM cf_cfdis WHERE uuid=?');
        $chk->execute([$uuid]);
        if ($chk->fetch()) { $omitidos++; $addWarn('CFDI_DUPLICATE'); continue; }
        $ins = $db->prepare('INSERT INTO cf_cfdis (uuid, folio, fecha_emision, importe, emisor, tipo, estado, archivo_xml, id_cliente, rfc, total) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$uuid,$folio,$fecha,$total,$emisorNombre,$tipo,'pendiente',$nombreSeguro,null,$rfcEmisor,$total]);
        $insertados++;
    }
    $zip->close();

    $paquetes[$foundIndex]['estado'] = 'procesado';
    $paquetes[$foundIndex]['num_cfdis'] = $insertados;
    $paquetes[$foundIndex]['procesado'] = 1;
    $paquetes[$foundIndex]['warnings'] = $warns;

    $totalCfdis = (int)$sol['total_cfdis'];
    $totalCfdis += $insertados; // solo sumar nuevos
    $upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json=?, total_cfdis=? WHERE id_solicitud = ?');
    $upd->execute([json_encode($paquetes), $totalCfdis, $idSolicitud]);

    echo json_encode([
        'success'=>true,
        'package_id'=>$packageId,
        'insertados'=>$insertados,
        'omitidos'=>$omitidos,
        'errores'=>$errores,
        'warnings'=>array_map(fn($k)=>['code'=>$k,'count'=>$warns[$k]], array_keys($warns)),
        'total_cfdis'=>$totalCfdis
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
