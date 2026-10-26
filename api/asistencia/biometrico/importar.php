<?php
/**
 * API Endpoint: /api/asistencia/biometrico/importar.php
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Procesa y almacena de forma definitiva e idempotente las marcaciones del CSV en SQL Server.
 */

declare(strict_types=1);

// Ajustar límites de tiempo para procesamiento de lotes grandes
@set_time_limit(180);
@ini_set('memory_limit', '256M');

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['POST'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../includes/BiostarCsvParser.php';
require_once __DIR__ . '/../../../includes/auth.php';

// Validar archivo subido
if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['archivo_csv']['error'] ?? -1;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
        UPLOAD_ERR_NO_FILE => 'Por favor seleccione un archivo CSV para importar.',
        default => 'Error al recibir el archivo en el servidor.'
    };
    apiError(400, $msg);
}

$uploadedFile = $_FILES['archivo_csv'];
$fileName = $uploadedFile['name'];
$tmpPath = $uploadedFile['tmp_name'];
$observaciones = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : null;

// Validar extensión
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    apiError(400, "Formato no válido. Debe cargar un archivo .csv");
}

$usuario = Auth::getCurrentUser();
require_once __DIR__ . '/../../../includes/AttendanceReconciler.php';

try {
    $resultado = BiostarCsvParser::importar($tmpPath, $fileName, $usuarioId, $observaciones);

    // Auto-conciliación de fechas involucradas en la importación
    if ($resultado['ok'] && ($resultado['marcaciones_insertadas'] ?? 0) > 0) {
        $importacionId = $resultado['importacion_id'] ?? 0;
        $pdo = Database::getConnection();
        if ($pdo && $importacionId > 0) {
            $stmtFechas = $pdo->prepare("SELECT DISTINCT CAST(FechaHora AS DATE) as Fecha FROM rrhh.MarcacionBiometrica WHERE ImportacionID = ?");
            $stmtFechas->execute([$importacionId]);
            $fechas = $stmtFechas->fetchAll(PDO::FETCH_COLUMN);
            $reconciliadas = 0;
            foreach ($fechas as $f) {
                if ($f) {
                    AttendanceReconciler::conciliarFecha((string)$f, $pdo);
                    $reconciliadas++;
                }
            }
            $resultado['fechas_conciliadas'] = $reconciliadas;
            $resultado['mensaje'] .= " Se concilio la asistencia para {$reconciliadas} días con marcaciones.";
        }
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (InvalidArgumentException $e) {
    apiError(400, $e->getMessage());
} catch (Throwable $e) {
    error_log("Error en importar BioStar CSV: " . $e->getMessage());
    apiError(500, "No se pudo completar la importación: " . $e->getMessage());
}
