<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
if (Database::getConfig()['database'] === 'BD_HOSPITAL_V4') {
    fwrite(STDERR, "Proceso histórico incompatible con V4. No se ejecutó ninguna consulta.\n");
    exit(1);
}
$pdo = Database::getConnection(true);
if (!$pdo) { fwrite(STDERR, "SQL Server no está disponible.\n"); exit(1); }
try {
    $pdo->beginTransaction();
    foreach (preg_split('/^GO\s*$/mi', file_get_contents(__DIR__ . '/migracion_administracion.sql')) as $batch) {
        if (trim($batch) !== '') $pdo->exec($batch);
    }
    $pdo->commit();
    echo "OK: historial de parámetros RR.HH. instalado; sin modificar datos operativos.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Migración revertida: ' . $e->getMessage() . "\n"); exit(1);
}
