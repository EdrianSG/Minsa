<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
apiGuard('dashboard', ['GET'], []);

/**
 * Endpoint de estado de conexión a la Base de Datos
 * Retorna estado en vivo / circuit breaker de SQL Server
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$isOnline = (Database::getConnection() !== null);
$config = Database::getConfig();

echo json_encode([
    'ok'      => true,
    'online'  => $isOnline,
    'status'  => $isOnline ? 'En línea' : 'Offline',
    'driver'  => Database::getAvailableDriverName(),
    'server'  => $config['host'] . ':' . $config['port'],
    'message' => $isOnline ? 'Conectado a Microsoft SQL Server' : Database::getLastError()
], JSON_UNESCAPED_UNICODE);
