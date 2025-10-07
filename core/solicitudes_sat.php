<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;

function create_sat_service(Fiel $fiel): Service {
    return new Service(new FielRequestBuilder($fiel), new GuzzleWebClient());
}

$fiel = Fiel::create(
    $_SESSION['fiel_cer_content'],
    $_SESSION['fiel_key_content'],
    $_SESSION['fiel_passphrase']
);

$service = create_sat_service($fiel);
$res = $conn->query("SELECT * FROM cf_solicitudes WHERE estado='pendiente'");

while ($row = $res->fetch_assoc()) {
    $verify = $service->verify($row['request_id']);
    if ($verify->getStatus()->isAccepted() && count($verify->getPackagesIds()) > 0) {
        foreach ($verify->getPackagesIds() as $pid) {
            $conn->query("INSERT INTO cf_solicitudes (id_solicitud, package_id, estado) VALUES ('{$row['request_id']}', '$pid', 'listo')");
        }
        $conn->query("UPDATE sat_solicitudes SET estado='completado' WHERE request_id='{$row['request_id']}'");
    }
}
