<?php
header('Content-Type: application/json; charset=utf-8');

$autoloadPrimary = __DIR__ . '/../vendor/autoload.php';
$autoloadFallback = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPrimary)) {
    require_once $autoloadPrimary;
} elseif (file_exists($autoloadFallback)) {
    require_once $autoloadFallback;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sin autoload composer']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/class/db.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use GuzzleHttp\Client;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

// --- INICIA SECCIÓN DE EJECUCIÓN EN SEGUNDO PLANO ---
// Esta parte del código se ejecuta cuando se llama desde la línea de comandos con el argumento --background
if (isset($argv) && count($argv) >= 3 && $argv[1] === '--background') {
    $idLocal = (int)$argv[2];
    $requestId = $argv[3] ?? '';

    $db = (new Database())->getConnection();
    // Se ha modificado la consulta para obtener también el RFC del emisor o receptor.
    $sel = $db->prepare('SELECT id_solicitud, solicitud_id_sat, paquetes_json, estado, rfc_emisor, rfc_receptor FROM cf_solicitudes WHERE id_solicitud=?');
    $sel->execute([$idLocal]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        exit("Solicitud $idLocal no encontrada\n");
    }

    // ¡CAMBIO IMPORTANTE!
    // Se obtiene el RFC de la solicitud para poder cargar la FIEL sin depender de la sesión.
    $rfc = $row['rfc_emisor'] ?: $row['rfc_receptor'];

    // Se asume que tienes una función `getFielFromDatabase` o un mecanismo similar
    // para obtener los datos de la FIEL de forma segura (por ejemplo, desde la base de datos o un archivo seguro).
    // Esta es la parte que necesitas implementar según la arquitectura de tu aplicación.
    $fiel = getFielFromSource($rfc); // Debes implementar esta función

    if (!$fiel) {
        $db->prepare('UPDATE cf_solicitudes SET estado=?, mensaje_error=?, ultima_verificacion=NOW() WHERE id_solicitud=?')
            ->execute(['error', 'No se pudo cargar la FIEL para la verificación en segundo plano.', $idLocal]);
        exit("Error: No se pudo cargar la FIEL para el RFC $rfc\n");
    }


    $service = createService($fiel);

    try {
        $verify = $service->verify($requestId);
    } catch (Throwable $e) {
        $db->prepare('UPDATE cf_solicitudes SET estado=?, ultima_verificacion=NOW() WHERE id_solicitud=?')
            ->execute(['error', $idLocal]);
        exit("Error al verificar: " . $e->getMessage());
    }

    $status = $verify->getStatus();
    $statusRequest = $verify->getStatusRequest();
    $estadoSAT = 'pendiente';

    if ($statusRequest->isFinished()) {
        $estadoSAT = 'terminada';
    } elseif ($statusRequest->isInProgress() || $statusRequest->isAccepted()) {
        $estadoSAT = 'aceptada';
    } elseif ($statusRequest->isRejected()) {
        $estadoSAT = 'rechazada';
    } elseif ($statusRequest->isFailure()) {
        $estadoSAT = 'error';
    } elseif ($statusRequest->isExpired()) {
        $estadoSAT = 'vencida';
    }

    $packageIds = $verify->getPackagesIds();
    $paquetesList = [];
    foreach ($packageIds as $pid) {
        $paquetesList[] = [
            'package_id' => $pid,
            'estado' => 'pendiente',
            'zip_path' => null,
            'fecha_descarga' => null,
            'num_cfdis' => 0,
            'mensaje_error' => null,
            'procesado' => 0
        ];
    }

    $upd = $db->prepare('UPDATE cf_solicitudes
        SET estado=?, total_paquetes=?, paquetes_json=?, ultima_verificacion=NOW(),
            fecha_terminada=CASE WHEN ?="terminada" THEN NOW() ELSE fecha_terminada END
        WHERE id_solicitud=?');
    $upd->execute([$estadoSAT, count($paquetesList), json_encode($paquetesList), $estadoSAT, $idLocal]);

    exit("Verificación completada ($estadoSAT) para solicitud $idLocal\n");
}
// --- FIN DE LA SECCIÓN DE EJECUCIÓN EN SEGUNDO PLANO ---


function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ¡NUEVA FUNCIÓN! (Ejemplo)
 * Debes implementar esta función para obtener la FIEL desde una fuente segura.
 * NO uses la sesión aquí.
 *
 * @param string $rfc
 * @return Fiel|null
 */
function getFielFromSource(string $rfc): ?Fiel
{
    // Ejemplo: Cargar la FIEL desde archivos guardados en una ubicación segura.
    // La ruta a estos archivos podría estar en tu archivo de configuración.
    $rutaBaseFiel = __DIR__ . '/../fieles/'; // ¡Asegúrate de que esta ruta sea correcta y segura!
    $cerPath = $rutaBaseFiel . $rfc . '.cer';
    $keyPath = $rutaBaseFiel . $rfc . '.key';
    $passphrase = 'tu_contraseña'; // ¡Esto debería obtenerse de forma segura! No la escribas directamente en el código.

    if (!file_exists($cerPath) || !file_exists($keyPath)) {
        return null; // O maneja el error como prefieras
    }

    try {
        $fiel = Fiel::create(
            file_get_contents($cerPath),
            file_get_contents($keyPath),
            $passphrase
        );

        if (!$fiel->isValid()) {
            return null; // O maneja el error de FIEL inválida/expirada
        }
        return $fiel;
    } catch (Throwable $e) {
        // Log del error si es necesario
        error_log("Error al crear la FIEL para $rfc: " . $e->getMessage());
        return null;
    }
}


function ensureFiel(): Fiel
{
    // Esta función sigue siendo útil para la parte web (no en segundo plano)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase'])) {
        respond(['success' => false, 'message' => 'Sesión no autenticada (FIEL)'], 401);
    }
    try {
        $fiel = Fiel::create($_SESSION['fiel_cer_content'], $_SESSION['fiel_key_content'], $_SESSION['fiel_passphrase']);
        if (!$fiel->isValid()) {
            respond(['success' => false, 'message' => 'FIEL inválida o expirada'], 401);
        }
        return $fiel;
    } catch (Throwable $e) {
        respond(['success' => false, 'message' => 'Error FIEL: ' . $e->getMessage()], 500);
    }
}

function createService(Fiel $fiel): Service
{
    $client = new Client(['timeout' => 90]);
    $webClient = new GuzzleWebClient($client);
    return new Service(new FielRequestBuilder($fiel), $webClient);
}


// --- INICIA SECCIÓN DE LA PETICIÓN POST ---
// Esta parte del código se ejecuta cuando un usuario hace una petición POST desde el navegador.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Sólo POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['success' => false, 'message' => 'JSON inválido'], 400);
}

$idLocal = (int)($input['id_solicitud'] ?? 0);
$requestId = trim($input['requestId'] ?? '');

if ($idLocal <= 0 && $requestId === '') {
    respond(['success' => false, 'message' => 'Debe enviar id_solicitud o requestId'], 400);
}

// Llama a ensureFiel() aquí para asegurarse de que el usuario está autenticado
// antes de iniciar el proceso en segundo plano.
ensureFiel();

respond([
    'success' => true,
    'message' => 'Verificación en proceso',
    'id_solicitud' => $idLocal,
    'requestId' => $requestId
]);

// Ejecutar en segundo plano la verificación
$phpPath = PHP_BINARY; // Usa la constante PHP_BINARY para encontrar la ruta de PHP
$scriptPath = __FILE__;
$cmd = sprintf(
    '%s %s --background %d %s > /dev/null 2>&1 &',
    escapeshellarg($phpPath),
    escapeshellarg($scriptPath),
    $idLocal,
    escapeshellarg($requestId)
);
exec($cmd);
exit;
// --- FIN DE LA SECCIÓN DE LA PETICIÓN POST ---