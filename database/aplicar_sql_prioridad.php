<?php
/** Uso: php database/aplicar_sql_prioridad.php (solo CLI, migración aditiva). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection(true);
if (!$pdo) { fwrite(STDERR, "No se pudo conectar a SQL Server.\n"); exit(1); }
try {
    $pdo->beginTransaction();
    foreach (preg_split('/^GO\s*$/mi', file_get_contents(__DIR__ . '/migracion_sql_prioridad.sql')) as $batch) {
        if (trim($batch) !== '') $pdo->exec($batch);
    }
    $pdo->commit();
    echo "OK: programación mensual y control de versiones instalados en SQL Server.\n";
    if (in_array('--verificar', $argv, true)) require __DIR__ . '/../tests/sql_server_smoke.php';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Migración revertida: " . $e->getMessage() . "\n");
    exit(1);
}
