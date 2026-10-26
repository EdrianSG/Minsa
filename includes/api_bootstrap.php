<?php
/** Controles comunes para todos los puntos de entrada de la API. */
require_once __DIR__ . '/auth.php';
date_default_timezone_set('America/Lima');

function apiError(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'success' => false, 'message' => $message,
        'mensaje' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function apiGuard(string $module, array $methods = ['GET'], array $writeRoles = []): void {
    if (!Auth::isAuthenticated()) apiError(401, 'Debe iniciar sesión.');
    if (!Auth::checkPermission($module)) apiError(403, 'No tiene permiso para este módulo.');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        apiError(405, 'Método no permitido.');
    }
    if ($method !== 'GET') {
        if (!Auth::hasRole('ADMIN', 'ADMIN_RRHH', ...$writeRoles))
            apiError(403, 'No tiene permiso para realizar esta operación.');
        // Rechazar envíos desde otros sitios; el cliente JSON existente no necesita cambios.
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site')
            apiError(403, 'Origen de solicitud no permitido.');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $originPort = parse_url($origin, PHP_URL_PORT);
            $authority = $originHost . ($originPort ? ':' . $originPort : '');
            if (strcasecmp($authority, $_SERVER['HTTP_HOST'] ?? '') !== 0)
                apiError(403, 'Origen de solicitud no permitido.');
        }
    }
    header('Cache-Control: no-store');
}

function apiRequireServiceAccess(?int $servicioId): void {
    if (!Auth::checkServiceAccess($servicioId)) {
        apiError(403, 'No tiene autorización para acceder o modificar los datos de este servicio.');
    }
}

function apiRequirePersonalAccess(int $personalId): void {
    if (!Auth::checkPersonalAccess($personalId)) {
        apiError(403, 'No tiene autorización para acceder o modificar los datos de este trabajador.');
    }
}

function apiRequireDatabase(): PDO {
    $pdo = Database::getConnection();
    if (!$pdo) apiError(503, 'SQL Server no está disponible. No se guardaron cambios; vuelva a intentar al recuperar la conexión.');
    return $pdo;
}

function apiValidDate(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function apiValidTime(string $time): bool {
    return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time);
}
