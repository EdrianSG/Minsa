<?php
/**
 * Script de limpieza de personal - Hospital Distrital de Pacasmayo
 * Base de datos: BD_HOSPITAL_V3
 * 
 * Elimina los 451 trabajadores importados/conflictivos, conservando ÚNICAMENTE:
 * - Cuenta de Administrador General en seguridad.Usuario (admin)
 * - Ficha técnica de Administrador en rrhh.Personal (PersonalID = 1, SYS-RRHH-ADMIN)
 * - Marcaciones biométricas en crudo (desvinculadas como PENDIENTE_VINCULACION)
 */

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SnapshotStore.php';

echo "=========================================================\n";
echo "   HOSPITAL DISTRITAL DE PACASMAYO - SIGH MINSA\n";
echo "   Limpieza en BD_HOSPITAL_V3 (Preservando Admin)\n";
echo "=========================================================\n\n";

$pdo = Database::getConnection(true);
if (!$pdo) {
    fwrite(STDERR, "[ERROR] No hay conexión con SQL Server: " . Database::getLastError() . "\n");
    exit(1);
}

$dbName = $pdo->query("SELECT DB_NAME()")->fetchColumn();
echo "Base de datos activa: {$dbName}\n";

// 1. Verificar admin
$stmtAdmin = $pdo->query("SELECT UsuarioID, NombreUsuario, PersonalID, Activo, Bloqueado FROM seguridad.Usuario WHERE NombreUsuario = 'admin'");
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    fwrite(STDERR, "[ERROR FATAL] No se encontró la cuenta 'admin' en seguridad.Usuario.\n");
    exit(1);
}

$stmtP1 = $pdo->query("SELECT PersonalID, CodigoEmpleado, Nombres, ApellidoPaterno FROM rrhh.Personal WHERE PersonalID = 1");
$p1 = $stmtP1->fetch(PDO::FETCH_ASSOC);

if (!$p1) {
    fwrite(STDERR, "[ERROR FATAL] No se encontró el registro de PersonalID = 1 (SYS-RRHH-ADMIN).\n");
    exit(1);
}

echo "Admin verificado: {$admin['NombreUsuario']} (UsuarioID: {$admin['UsuarioID']}, PersonalID: {$admin['PersonalID']})\n";
echo "Ficha técnica admin verificada: {$p1['CodigoEmpleado']} - {$p1['Nombres']} {$p1['ApellidoPaterno']}\n\n";

$totalInicial = (int)$pdo->query("SELECT COUNT(*) FROM rrhh.Personal")->fetchColumn();
echo "Total de trabajadores antes de la limpieza: {$totalInicial}\n";

try {
    $pdo->beginTransaction();

    // A. Actualizar referencias en laboratorio y clínico para apuntar al admin (PersonalID = 1)
    try {
        $pdo->exec("UPDATE laboratorio.Solicitud SET SolicitantePersonalID = 1 WHERE SolicitantePersonalID IS NOT NULL AND SolicitantePersonalID <> 1");
        $pdo->exec("UPDATE laboratorio.Resultado SET ResponsablePersonalID = 1 WHERE ResponsablePersonalID IS NOT NULL AND ResponsablePersonalID <> 1");
        $pdo->exec("UPDATE laboratorio.Resultado SET ValidadorPersonalID = 1 WHERE ValidadorPersonalID IS NOT NULL AND ValidadorPersonalID <> 1");
    } catch (Throwable $t) {
        // Ignorar si no aplican
    }

    try {
        $pdo->exec("UPDATE clinico.Diagnostico SET PersonalID = 1 WHERE PersonalID IS NOT NULL AND PersonalID <> 1");
    } catch (Throwable $t) {
        // Ignorar si no aplican
    }

    // B. Desvincular marcaciones biométricas que apunten a otros trabajadores
    try {
        $pdo->exec("UPDATE rrhh.MarcacionBiometrica SET PersonalID = NULL, EstadoVinculacion = 'PENDIENTE_VINCULACION' WHERE PersonalID IS NOT NULL AND PersonalID <> 1");
    } catch (Throwable $t) {}

    // C. Eliminar vinculaciones biométricas ajenas
    try {
        $pdo->exec("DELETE FROM rrhh.VinculacionBiometrica WHERE PersonalID <> 1");
    } catch (Throwable $t) {}

    // D. Eliminar asignaciones de personal ajenas
    $asigEliminadas = $pdo->exec("DELETE FROM rrhh.AsignacionPersonal WHERE PersonalID <> 1");
    echo "- Asignaciones de personal eliminadas: {$asigEliminadas}\n";

    // E. Eliminar contratos ajenos
    try {
        $pdo->exec("DELETE FROM rrhh.Contrato WHERE PersonalID <> 1");
    } catch (Throwable $t) {}

    // F. Eliminar asistencias y permisos ajenos
    try {
        $pdo->exec("DELETE FROM rrhh.Asistencia WHERE PersonalID <> 1");
        $pdo->exec("DELETE FROM rrhh.PermisoLaboral WHERE PersonalID <> 1");
        $pdo->exec("DELETE FROM rrhh.ProgramacionMensualDetalle WHERE PersonalID <> 1");
    } catch (Throwable $t) {}

    // G. Eliminar los 451 trabajadores restantes de rrhh.Personal (conservando PersonalID = 1)
    $personalEliminado = $pdo->exec("DELETE FROM rrhh.Personal WHERE PersonalID <> 1");
    echo "- Trabajadores eliminados de rrhh.Personal: {$personalEliminado}\n";

    // H. Asegurar cuenta admin activa y vinculada a PersonalID = 1
    $pdo->exec("UPDATE seguridad.Usuario SET PersonalID = 1, Activo = 1, Bloqueado = 0 WHERE UsuarioID = {$admin['UsuarioID']}");
    $pdo->exec("UPDATE rrhh.Personal SET Activo = 1 WHERE PersonalID = 1");

    // I. Incrementar versión de datos
    try {
        $pdo->exec("UPDATE seguridad.VersionDatos SET Revision = Revision + 1 WHERE VersionDatosID = 1");
    } catch (Throwable $t) {}

    $pdo->commit();
    echo "\n>>> LIMPIEZA CONFIRMADA EXITOSAMENTE EN BD_HOSPITAL_V3 <<<\n\n";

    // Sincronizar snapshots
    echo "Sincronizando snapshots locales...\n";
    try {
        SnapshotStore::syncAllFromDatabase($pdo);
        echo "Snapshots sincronizados OK.\n";
    } catch (Throwable $t) {
        echo "Aviso snapshots: " . $t->getMessage() . "\n";
    }

    $totalFinal = (int)$pdo->query("SELECT COUNT(*) FROM rrhh.Personal")->fetchColumn();
    $adminFinal = $pdo->query("SELECT u.NombreUsuario, u.Activo, p.CodigoEmpleado, p.Nombres FROM seguridad.Usuario u JOIN rrhh.Personal p ON u.PersonalID = p.PersonalID WHERE u.UsuarioID = {$admin['UsuarioID']}")->fetch(PDO::FETCH_ASSOC);

    echo "\n=========================================================\n";
    echo "ESTADO FINAL EN BD_HOSPITAL_V3:\n";
    echo " - Total en rrhh.Personal: {$totalFinal} (Únicamente la cuenta de Administrador)\n";
    echo " - Usuario de acceso: {$adminFinal['NombreUsuario']} ({$adminFinal['CodigoEmpleado']} - {$adminFinal['Nombres']})\n";
    echo " - Base de datos lista para ingresar el personal mediante el sistema.\n";
    echo "=========================================================\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "\n[ERROR CRÍTICO] La operación fue revertida: " . $e->getMessage() . "\n");
    exit(1);
}
