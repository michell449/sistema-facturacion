<?php
// // app-m/core/descargar-sat.php
// // Forzar la respuesta JSON
// header('Content-Type: application/json; charset=utf-t');
// // Incluir el autoloader de Composer
// require_once __DIR__ . '/../vendor/autoload.php';
// // Incluir tu script de parsing para reutilizar la función
// require_once __DIR__ . '/cargar-xml.php'; 

// // Usar las clases de la biblioteca
// use PhpCfdi\SatWsDescargaMasiva\Service;
// use PhpCfdi\SatWsDescargaMasiva\Shared\ServiceEndpoints;
// use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
// use PhpCfdi\Credentials\Credential;
// use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\QueryParameters;
// Directorio temporal para guardar archivos (e.firma, zips, etc.)
// ¡Asegúrate de que este directorio exista y tenga permisos de escritura!
// $tempDir = __DIR__ . "/../uploads/tmp_sat/";
// if (!is_dir($tempDir)) {
//     mkdir($tempDir, 0755, true);
// }

// // --- INICIO DE LA LÓGICA ---

// try {
//     // 1. Validar la petición (debe ser POST y con los archivos de la e.firma)
//     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//         throw new \Exception('Método no permitido.');
//     }

//     if (
//         !isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK ||
//         !isset($_FILES['llave']) || $_FILES['llave']['error'] !== UPLOAD_ERR_OK ||
//         !isset($_POST['passwordFiel'])
//     ) {
//         throw new \Exception('Faltan los archivos de la e.firma o la contraseña.');
//     }

//     // 2. Mover archivos de e.firma a una ubicación temporal y segura
//     $cerPath = $tempDir . basename($_FILES['certificado']['name']);
//     $keyPath = $tempDir . basename($_FILES['llave']['name']);
    
//     if (!move_uploaded_file($_FILES['certificado']['tmp_name'], $cerPath) || !move_uploaded_file($_FILES['llave']['tmp_name'], $keyPath)) {
//         throw new \Exception('Error al guardar los archivos de la e.firma.');
//     }
    
//     $password = $_POST['passwordFiel'];

//     // 3. Crear el objeto Fiel y validar que la contraseña y los archivos son correctos
//     $fiel = Credential::create(
//         file_get_contents($cerPath),
//         file_get_contents($keyPath),
//         $password
//     );

//     if (!$fiel->isValid()) {
//         throw new \Exception('La e.firma es inválida o la contraseña es incorrecta.');
//     }
    
//     // Limpiar archivos temporales de la e.firma
//     unlink($cerPath);
//     unlink($keyPath);

    // 4. Crear el servicio de conexión con el SAT
    // Usamos GuzzleHttp, que la biblioteca recomienda y Composer instala
   // 4. Crear el RequestBuilder y el Service de conexión con el SAT
//    Esta es la sección corregida.

// // Primero, se crea un RequestBuilder usando la Fiel.
// $requestBuilder = new \PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder($fiel);

// // Luego, se crea el cliente web.
// $webClient = new \PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient();

// // Finalmente, el Service se crea con el RequestBuilder y el WebClient.
// $service = new Service($requestBuilder, $webClient);


// // 5. Crear los parámetros de la consulta
// $startDateStr = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 day'));
// $endDateStr = $_POST['fecha_fin'] ?? date('Y-m-d');

// // Validamos que las fechas sean correctas para evitar errores
// try {
//     $startDate = new DateTimeImmutable($startDateStr);
//     $endDate = new DateTimeImmutable($endDateStr . ' 23:59:59');
// } catch (Exception $e) {
//     throw new \Exception('El formato de las fechas de inicio o fin no es válido.');
// }

// $queryParameters = \PhpCfdi\SatWsDescargaMasiva\Shared\QueryParameters::create(
//     new \PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod($startDate, $endDate)
// );

// // Tipo de descarga (Emitidos o Recibidos)
// if (($_POST['tipo_facturas'] ?? 'recibidas') === 'emitidas') {
//     $queryParameters = $queryParameters->withDownloadType(\PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType::issued());
// } else {
//     $queryParameters = $queryParameters->withDownloadType(\PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType::received());
// }

// // Tipo de solicitud (CFDI o Metadata)
// if (($_POST['tipo_solicitud'] ?? 'cfdi') === 'metadata') {
//     $queryParameters = $queryParameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::metadata());
// } else {
//     $queryParameters = $queryParameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::cfdi());
// }
    
//     // Tipo de solicitud (CFDI o Metadata)
//     if (($_POST['tipo_solicitud'] ?? 'cfdi') === 'metadata') {
//         $queryParameters = $queryParameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::metadata());
//     } else {
//         $queryParameters = $queryParameters->withRequestType(\PhpCfdi\SatWsDescargaMasiva\Shared\RequestType::cfdi());
//     }

//     // 6. Enviar la solicitud de consulta
//     $queryResult = $service->query($queryParameters);

//     if (!$queryResult->getStatus()->isAccepted()) {
//         throw new \Exception("La solicitud fue rechazada por el SAT: " . $queryResult->getStatus()->getMessage());
//     }
    
//     $requestId = $queryResult->getRequestId();

//     // 7. Verificar el estado de la solicitud hasta que esté completa (esto puede tardar)
//     $maxAttempts = 10; // Intentar 10 veces (aprox 2 minutos)
//     $attempt = 0;
    // do {
    //     $attempt++;
    //     // Esperar 10 segundos entre cada verificación
    //     sleep(10); 
    //     $verifyResult = $service->verify($requestId);

    //     if ($verifyResult->getStatus()->isFinished()) {
    //         break; // Salir del bucle si ya terminó
    //     }

    //     if ($attempt >= $maxAttempts) {
    //         throw new \Exception('La solicitud al SAT está tardando demasiado. Intente más tarde con el ID: ' . $requestId);
    //     }

    // } while (true);
    
    // // 8. Descargar los paquetes
    // $downloadResult = $service->download($verifyResult->getPackagesIds()[0]);
    // if (!$downloadResult->getStatus()->isAccepted()) {
    //     throw new \Exception("No se pudo descargar el paquete: " . $downloadResult->getStatus()->getMessage());
    // }
    
    // // Guardar el paquete ZIP
    // $zipPath = $tempDir . $requestId . '.zip';
    // file_put_contents($zipPath, $downloadResult->getPackageContent());

    // // 9. Descomprimir y procesar los XML
    // $zip = new ZipArchive();
    // $results = ['success' => true, 'parsed' => [], 'errors' => []];

    // if ($zip->open($zipPath) === TRUE) {
    //     for ($i = 0; $i < $zip->numFiles; $i++) {
    //         $filename = $zip->getNameIndex($i);
    //         if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xml') {
    //             continue;
    //         }

    //         $xmlContents = $zip->getFromIndex($i);
    //         $parsedData = parseCfdiXmlString($xmlContents); // Reutilizamos tu función

    //         if ($parsedData) {
    //             // Guardar el XML en temporal para el siguiente paso (guardar en BD)
    //             $tmpFilename = uniqid('cfdi_') . '.xml';
    //             file_put_contents($tempDir . $tmpFilename, $xmlContents);
    //             $parsedData['_tmp_file'] = $tmpFilename;
    //             $results['parsed'][] = $parsedData;
    //         } else {
    //             $results['errors'][] = "XML no válido dentro del paquete: $filename";
    //         }
    //     }
    //     $zip->close();
    // } else {
    //     throw new \Exception('No se pudo abrir el paquete ZIP descargado.');
    // }
    
    // // Limpiar el archivo zip
    // unlink($zipPath);

    // echo json_encode($results, JSON_UNESCAPED_UNICODE);

// } catch (\Throwable $e) {
//     // Capturar cualquier error y devolverlo como JSON
//     http_response_code(400); // Bad Request
//     echo json_encode([
//         'success' => false,
//         'message' => $e->getMessage()
//     ]);
// }
// exit;