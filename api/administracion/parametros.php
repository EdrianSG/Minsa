<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET', 'POST']);
require_once __DIR__ . '/../../includes/SystemSettings.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = apiRequireDatabase();
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        if (!is_array($input)) apiError(400, 'Solicitud inválida.');
        $settings = SystemSettings::save($pdo, $input, (int)Auth::getCurrentUser()['usuario_id']);
    } else {
        $settings = SystemSettings::read(null, $pdo);
    }
    echo json_encode(['ok'=>true, 'parametros'=>$settings,
        'proxima_configuracion'=>SystemSettings::read(date('Y-m-d', strtotime('tomorrow')), $pdo),
        'editable'=>true, 'mensaje'=>'Reglas guardadas con historial en V4. Los cambios se aplican desde el día siguiente.'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    apiError(400, $e->getMessage());
} catch (Throwable $e) {
    error_log('Parámetros: ' . $e->getMessage());
    apiError(503, 'No se pudieron consultar o guardar los parámetros institucionales.');
}
