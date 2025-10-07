<?php
/* Endpoint: descarga un package_id directamente del SAT actualizando cf_solicitudes.paquetes_json
 * POST JSON: { "id_solicitud": 1, "package_id": "..." }
 */
use App\SatDescarga\CredentialLoader;
use App\SatDescarga\ServiceFactory;

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
    $sel = $db->prepare('SELECT paquetes_json, total_paquetes FROM cf_solicitudes WHERE id_solicitud = ?');
    $sel->execute([$idSolicitud]);
    $sol = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$sol) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Solicitud no encontrada']); exit; }
    $paquetes = [];
    if (!empty($sol['paquetes_json'])) { $decoded = json_decode($sol['paquetes_json'], true); if (is_array($decoded)) $paquetes = $decoded; }
    $foundIndex = null;
    foreach ($paquetes as $idx=>$p) { if (($p['package_id'] ?? '') === $packageId) { $foundIndex = $idx; break; } }
    if ($foundIndex === null) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'package_id no pertenece a la solicitud']); exit; }
    if (!empty($paquetes[$foundIndex]['zip_path'])) {
        echo json_encode(['success'=>true,'package_id'=>$packageId,'zip_path'=>$paquetes[$foundIndex]['zip_path'],'message'=>'Ya existía archivo']);
        exit;
    }

    $credentials = CredentialLoader::load();
    $service = ServiceFactory::create();
    $auth = $service->authorize($credentials);

    $download = $service->download($auth, $packageId);
    if (!$download->isValid()) {
        $paquetes[$foundIndex]['estado'] = 'error';
        $paquetes[$foundIndex]['mensaje_error'] = 'Descarga inválida: '.$download->message();
        $upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json=? WHERE id_solicitud = ?');
        $upd->execute([json_encode($paquetes), $idSolicitud]);
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Descarga inválida','message'=>$download->message()]);
        exit;
    }

    $contenido = $download->packageContent(); // binario ZIP
    $fechaDir = date('Ymd');
    $baseDir = dirname(__DIR__) . '/uploads/cfdis/descargas/' . $fechaDir;
    if (!is_dir($baseDir)) mkdir($baseDir, 0775, true);
    $zipPath = $baseDir . '/pkg_' . preg_replace('/[^A-Za-z0-9_-]/','_', $packageId) . '.zip';
    if (@file_put_contents($zipPath, $contenido) === false) {
        $paquetes[$foundIndex]['estado'] = 'error';
        $paquetes[$foundIndex]['mensaje_error'] = 'No se pudo escribir ZIP';
        $upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json=? WHERE id_solicitud = ?');
        $upd->execute([json_encode($paquetes), $idSolicitud]);
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'No se pudo escribir ZIP']);
        exit;
    }

    $paquetes[$foundIndex]['zip_path'] = $zipPath;
    $paquetes[$foundIndex]['fecha_descarga'] = date('Y-m-d H:i:s');
    $paquetes[$foundIndex]['estado'] = 'descargado';
    $descargados = 0; foreach ($paquetes as $p) { if (in_array(($p['estado']??''), ['descargado','procesado'])) $descargados++; }
    $upd = $db->prepare('UPDATE cf_solicitudes SET paquetes_json=? WHERE id_solicitud=?');
    $upd->execute([json_encode($paquetes), $idSolicitud]);

    echo json_encode(['success'=>true,'package_id'=>$packageId,'zip_path'=>$zipPath,'descargados'=>$descargados]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
