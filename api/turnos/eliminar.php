<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['POST', 'DELETE'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/turnos/eliminar.php
 * Hospital Distrital de Pacasmayo - ETAPA 3
 * 
 * Permite eliminar o desactivar un turno del catálogo oficial
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auditoria.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$turnoId = intval($data['turno_id'] ?? 0);

if ($turnoId <= 0) {
    apiError(400, 'Identificador de turno inválido.');
}

$pdo = apiRequireDatabase();

try {
    // 1. Obtener los datos del turno a eliminar
    $stmt = $pdo->prepare("SELECT TurnoID, Codigo, Nombre, Tipo, Activo FROM rrhh.Turno WHERE TurnoID = :id");
    $stmt->execute([':id' => $turnoId]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        apiError(404, 'El turno seleccionado no existe o ya fue eliminado.');
    }

    $codigo = trim($turno['Codigo'] ?? '');
    $nombre = trim($turno['Nombre'] ?? '');

    // 2. Verificar referencias en rrhh.ProgramacionMensualDetalle
    $stmtProg = $pdo->prepare("SELECT COUNT(*) FROM rrhh.ProgramacionMensualDetalle WHERE CodigoTurno = :codigo");
    $stmtProg->execute([':codigo' => $codigo]);
    $enUsoProg = (int)$stmtProg->fetchColumn();

    // 3. Verificar referencias en rrhh.Horario si la tabla existe
    $enUsoHorario = 0;
    $hasHorario = (bool)$pdo->query("SELECT 1 FROM sys.tables WHERE object_id = OBJECT_ID('rrhh.Horario')")->fetchColumn();
    if ($hasHorario) {
        $stmtHor = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Horario WHERE TurnoID = :id AND Activo = 1");
        $stmtHor->execute([':id' => $turnoId]);
        $enUsoHorario = (int)$stmtHor->fetchColumn();
    }

    $accionAuditoria = 'DELETE';
    $mensaje = '';

    if ($enUsoProg > 0 || $enUsoHorario > 0) {
        // En uso histórico o actual: desactivar (soft-delete) para preservar integridad referencial y auditoría
        $stmtDesact = $pdo->prepare("UPDATE rrhh.Turno SET Activo = 0 WHERE TurnoID = :id");
        $stmtDesact->execute([':id' => $turnoId]);
        $accionAuditoria = 'DEACTIVATE';
        $mensaje = "El turno {$codigo} ({$nombre}) posee registros asociados y ha sido retirado del catálogo activo.";
    } else {
        // Sin dependencias activas: eliminación física con fallback seguro a desactivación
        try {
            $stmtDel = $pdo->prepare("DELETE FROM rrhh.Turno WHERE TurnoID = :id");
            $stmtDel->execute([':id' => $turnoId]);
            $accionAuditoria = 'DELETE';
            $mensaje = "Turno {$codigo} ({$nombre}) eliminado definitivamente del catálogo oficial.";
        } catch (PDOException $e) {
            $stmtDesact = $pdo->prepare("UPDATE rrhh.Turno SET Activo = 0 WHERE TurnoID = :id");
            $stmtDesact->execute([':id' => $turnoId]);
            $accionAuditoria = 'DEACTIVATE';
            $mensaje = "El turno {$codigo} ({$nombre}) fue retirado del catálogo activo.";
        }
    }

    // 4. Registro de auditoría
    $currentUser = Auth::getCurrentUser();
    Auditoria::registrar(
        $currentUser['usuario_id'] ?? 1,
        'TURNOS',
        $accionAuditoria,
        'rrhh.Turno',
        (string)$turnoId,
        "Eliminación de turno oficial: {$codigo} - {$nombre} ({$mensaje})"
    );

    // 5. Sincronizar de inmediato el snapshot local de resiliencia
    try {
        require_once __DIR__ . '/../../includes/SnapshotStore.php';
        SnapshotStore::syncAllFromDatabase($pdo);
    } catch (Throwable $t) {}

    echo json_encode([
        'ok'      => true,
        'success' => true,
        'message' => $mensaje,
        'mensaje' => $mensaje,
        'turno_id'=> $turnoId
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (PDOException $e) {
    error_log('Eliminar turno error: ' . $e->getMessage());
    apiError(500, 'Error interno al procesar la eliminación del turno.');
}
