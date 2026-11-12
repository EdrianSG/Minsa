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
    $baseline = ProgramacionStore::read($pdo, 2100, 12, 0);
    $pdo->beginTransaction();
    $original = ProgramacionStore::read($pdo, 2100, 12, 0);
    if (!$original['personal_servicio']) {
        $service = $pdo->query('SELECT TOP 1 ServicioID, AreaID FROM organizacion.Servicio WHERE Activo = 1 ORDER BY ServicioID')->fetch();
        $cargo = (int)$pdo->query('SELECT TOP 1 CargoID FROM rrhh.Cargo WHERE Activo = 1 ORDER BY CargoID')->fetchColumn();
        $document = (int)$pdo->query('SELECT TOP 1 TipoDocumentoID FROM catalogo.TipoDocumento ORDER BY TipoDocumentoID')->fetchColumn();
        $suffix = bin2hex(random_bytes(5));
        $insertId = static function(string $sql, array $params) use ($pdo): int {
            $stmt = $pdo->prepare('SET NOCOUNT ON; ' . $sql . '; SELECT CAST(SCOPE_IDENTITY() AS INT)');
            $stmt->execute($params); $id = (int)$stmt->fetchColumn(); $stmt->closeCursor(); return $id;
        };
        if (!$service) {
            $sede = $insertId('INSERT INTO organizacion.Sede (Codigo, Nombre) VALUES (?, ?)', ['T' . $suffix, 'Prueba reversible']);
            $department = $insertId('INSERT INTO organizacion.Departamento (SedeID, Codigo, Nombre) VALUES (?, ?, ?)', [$sede, 'T' . $suffix, 'Prueba reversible']);
            $area = $insertId('INSERT INTO organizacion.Area (DepartamentoID, Codigo, Nombre) VALUES (?, ?, ?)', [$department, 'T' . $suffix, 'Prueba reversible']);
            $serviceId = $insertId('INSERT INTO organizacion.Servicio (AreaID, Codigo, Nombre) VALUES (?, ?, ?)', [$area, 'T' . $suffix, 'Prueba reversible']);
            $service = ['ServicioID'=>$serviceId, 'AreaID'=>$area];
        }
        if (!$cargo) $cargo = $insertId('INSERT INTO rrhh.Cargo (Codigo, Nombre) VALUES (?, ?)', ['T' . $suffix, 'Prueba reversible']);
        if (!$document) $document = $insertId('INSERT INTO catalogo.TipoDocumento (Codigo, Nombre) VALUES (?, ?)', ['T' . $suffix, 'Prueba reversible']);
        $s = $pdo->prepare('SET NOCOUNT ON; INSERT INTO rrhh.Personal (CodigoEmpleado,TipoDocumentoID,NumeroDocumento,Nombres,ApellidoPaterno,FechaIngreso) VALUES (?, ?, ?, ?, ?, CAST(GETDATE() AS DATE)); SELECT CAST(SCOPE_IDENTITY() AS INT)');
        $s->execute(['ROL' . $suffix, $document, $suffix, 'Prueba', 'Reversible']);
        $personal = (int)$s->fetchColumn(); $s->closeCursor();
        $s = $pdo->prepare('INSERT INTO rrhh.AsignacionPersonal (PersonalID,AreaID,ServicioID,CargoID,FechaInicio,EsActual) VALUES (?, ?, ?, ?, CAST(GETDATE() AS DATE), 1)');
        $s->execute([$personal, $service['AreaID'], $service['ServicioID'], $cargo]);
        $original = ProgramacionStore::read($pdo, 2100, 12, 0);
    }
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
    if ($restored !== $baseline) throw new RuntimeException('La verificación no restauró el periodo de prueba.');
    require_once __DIR__ . '/../includes/AttendancePeriod.php';
    TurnoCatalog::load($pdo);
    AttendancePeriod::loadMedicalDays($pdo, (int)date('Y'), (int)date('n'));
    echo "OK: SQL real valida lectura, guardado, versión, conflicto global/servicio y revisión; todas las escrituras de prueba fueron revertidas.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Verificación SQL: " . $e->getMessage() . "\n");
    exit(1);
}
