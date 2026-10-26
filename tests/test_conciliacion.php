<?php
/**
 * Test de Integración: Motor de Conciliación Biométrica MINSA
 * Hospital Distrital de Pacasmayo
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/AttendanceReconciler.php';
require_once __DIR__ . '/../includes/AttendanceRules.php';

echo "=========================================================\n";
echo "   TEST: CONCILIACION BIOMETRICA MINSA E IDEMPOTENCIA    \n";
echo "=========================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "[FAIL] No hay conexión a SQL Server.\n";
    exit(1);
}

// 1. Probar conciliación de fecha real 2026-10-15
echo "1. Probando AttendanceReconciler::conciliarFecha('2026-10-15')...\n";
$t0 = microtime(true);
$res = AttendanceReconciler::conciliarFecha('2026-10-15', $pdo);
$elapsed = round(microtime(true) - $t0, 3);

echo "   [OK] Conciliación completada en {$elapsed}s\n";
echo "   - Total evaluados: {$res['total_evaluados']}\n";
echo "   - Con marcación biométrica: {$res['con_marcacion']}\n";
echo "   - Asistencias puntuales (<=5 min): {$res['puntuales']}\n";
echo "   - Tardanzas (6-30 min): {$res['tardanzas']}\n";
echo "   - Faltas administrativas (>30 min): {$res['faltas_administrativas']}\n";
echo "   - Inasistencias sin marcación: {$res['faltas_sin_marcacion']}\n";

assert($res['total_evaluados'] > 200, "Debe haber más de 200 servidores evaluados");
assert($res['con_marcacion'] === 101, "Deben haber 101 trabajadores con marcaciones el 2026-10-15");
assert($res['puntuales'] === 98, "Deben haber 98 puntuales el 2026-10-15");
assert($res['tardanzas'] === 3, "Deben haber 3 tardanzas el 2026-10-15");

// 2. Probar preservación pericial de regularizaciones manuales
echo "\n2. Probando preservación de regularizaciones manuales (No Sobrescritura)...\n";
// Insertar o actualizar un registro con sustento manual de prueba
$stmtFindAny = $pdo->query("SELECT TOP 1 PersonalID FROM rrhh.Personal WHERE Estado = 1 ORDER BY PersonalID DESC");
$testPersonalId = (int)$stmtFindAny->fetch(PDO::FETCH_ASSOC)['PersonalID'];
$testFecha = '2026-10-15';

// Guardar como permiso justificado manual
$stmtSetManual = $pdo->prepare("
    UPDATE rrhh.Asistencia
    SET Estado = 'Permiso',
        Observacion = 'Regularizado mediante Memo N° 452-2026-DIR-HDP (Comisión de servicio)',
        HoraEntrada = NULL,
        HoraSalida = NULL
    WHERE PersonalID = :pid AND Fecha = :fec
");
$stmtSetManual->execute([':pid' => $testPersonalId, ':fec' => $testFecha]);

// Re-ejecutar conciliación
$res2 = AttendanceReconciler::conciliarFecha($testFecha, $pdo);
echo "   - Regularizaciones manuales preservadas: {$res2['preservados_manuales']}\n";

$stmtVerify = $pdo->prepare("SELECT Estado, Observacion FROM rrhh.Asistencia WHERE PersonalID = ? AND Fecha = ?");
$stmtVerify->execute([$testPersonalId, $testFecha]);
$rowVerify = $stmtVerify->fetch(PDO::FETCH_ASSOC);

assert($rowVerify['Estado'] === 'Permiso', "El estado no debió ser sobrescrito y debe seguir siendo 'Permiso'");
assert(strpos($rowVerify['Observacion'], 'Memo N° 452') !== false, "La observación con memo debe conservarse intacta");
echo "   [OK] Prueba de preservación superada exitosamente.\n";

// Restaurar registro
$res3 = AttendanceReconciler::conciliarFecha($testFecha, $pdo);

echo "\n=========================================================\n";
echo "   TODOS LOS TESTS DE CONCILIACION PASARON CON EXITO [OK]\n";
echo "=========================================================\n";
