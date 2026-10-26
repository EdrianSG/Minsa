<?php
/** Solo CLI. Verifica el modelo real con todas las escrituras de prueba revertidas. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ProgramacionStore.php';
require_once __DIR__ . '/../includes/DataRevision.php';
$pdo = Database::getConnection(true);
if (!$pdo) { fwrite(STDERR, "SQL Server no disponible.\n"); exit(1); }
try {
    $revision = DataRevision::read($pdo);
    if ($revision === null) throw new RuntimeException('No está instalado el control de revisiones.');
    $pdo->beginTransaction();
    $original = ProgramacionStore::read($pdo, 2100, 12, 0);
    if (!$original['personal_servicio']) throw new RuntimeException('No hay trabajadores para verificar el modelo.');
    $worker = $original['personal_servicio'][0];
    $user = (int)$pdo->query('SELECT TOP 1 UsuarioID FROM seguridad.Usuario ORDER BY UsuarioID')->fetchColumn();
    $version = ProgramacionStore::save($pdo, 2100, 12, (int)$worker['servicio_id'], $original['version'], false,
        [['personal_id'=>(int)$worker['personal_id'], 'dia'=>1, 'codigo_turno'=>'M']], $user);
    $saved = ProgramacionStore::read($pdo, 2100, 12, (int)$worker['servicio_id']);
    $found = false;
    foreach ($saved['personal_servicio'] as $person) {
        if ((int)$person['personal_id'] === (int)$worker['personal_id']) $found = $person['patron'][0] === 'M';
    }
    $physical = $pdo->prepare('SELECT CodigoTurno FROM rrhh.ProgramacionMensualDetalle WHERE Anio = 2100 AND Mes = 12 AND PersonalID = ? AND Dia = 1');
    $physical->execute([(int)$worker['personal_id']]);
    if ($physical->fetchColumn() !== 'M') throw new RuntimeException('La celda no fue almacenada físicamente.');
    if (!$found || $version !== $original['version'] + 1) throw new RuntimeException('La programación no se conservó en SQL.');
    try {
        ProgramacionStore::save($pdo, 2100, 12, 0, $original['version'], false,
            [['personal_id'=>(int)$worker['personal_id'],'dia'=>1,'codigo_turno'=>'T']], $user);
        throw new RuntimeException('No se detectó el conflicto de versión.');
    } catch (ProgramacionConflict $e) { /* Esperado: se conserva la escritura anterior dentro de la transacción. */ }
    $inside = DataRevision::read($pdo);
    if ($inside === null || (int)$inside <= (int)$revision) throw new RuntimeException('Los triggers no actualizaron la revisión.');
    $pdo->rollBack();
    $restored = ProgramacionStore::read($pdo, 2100, 12, 0);
    if ($restored !== $original) throw new RuntimeException('La verificación no restauró el periodo de prueba.');
    require_once __DIR__ . '/../includes/AttendancePeriod.php';
    TurnoCatalog::load($pdo);
    AttendancePeriod::loadMedicalDays($pdo, (int)date('Y'), (int)date('n'));
    echo "OK: SQL real valida lectura, guardado, versión, conflicto global/servicio y revisión; todas las escrituras de prueba fueron revertidas.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Verificación SQL: " . $e->getMessage() . "\n");
    exit(1);
}
