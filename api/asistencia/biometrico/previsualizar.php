<?php
/**
 * API Endpoint: /api/asistencia/biometrico/previsualizar.php
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Recibe el archivo CSV subido por RR.HH. y retorna la previsualización estadística
 * y técnica sin escribir en la base de datos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../includes/BiostarCsvParser.php';

// Validar que se haya subido un archivo
if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['archivo_csv']['error'] ?? -1;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor.',
        UPLOAD_ERR_NO_FILE => 'Por favor seleccione un archivo CSV para cargar.',
        UPLOAD_ERR_PARTIAL => 'La carga del archivo se interrumpió.',
        default => 'Error al recibir el archivo en el servidor.'
    };
    apiError(400, $msg);
}

$uploadedFile = $_FILES['archivo_csv'];
$fileName = $uploadedFile['name'];
$tmpPath = $uploadedFile['tmp_name'];

// Validar extensión
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    apiError(400, "Formato no válido. Debe cargar un archivo con extensión .csv (se recibió .{$ext}).");
}

try {
    $resumen = BiostarCsvParser::previsualizar($tmpPath, 30);
    $resumen['nombre_archivo'] = $fileName;

    echo json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (InvalidArgumentException $e) {
    apiError(400, $e->getMessage());
} catch (Throwable $e) {
    error_log("Error en previsualizar BioStar CSV: " . $e->getMessage());
    apiError(500, "Error al procesar el archivo: " . $e->getMessage());
}
