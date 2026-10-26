<?php
/**
 * Test de Integración: Importación Biométrica BioStar 2
 * Hospital Distrital de Pacasmayo
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/BiostarCsvParser.php';

echo "=========================================================\n";
echo "   TEST DE INTEGRACION: IMPORTACION BIOMETRICA BIOSTAR 2\n";
echo "=========================================================\n\n";

$realCsv = '/home/edrian/Escritorio/Proyecto Minsa/Report_20261015T070613/Report_20261015T070613_1.csv';
if (!file_exists($realCsv)) {
    echo "[FAIL] No se encuentra el archivo CSV real en: $realCsv\n";
    exit(1);
}

// 1. Probar Previsualización en Streaming
echo "1. Probando BiostarCsvParser::previsualizar() con 50,000 filas...\n";
$t0 = microtime(true);
$prev = BiostarCsvParser::previsualizar($realCsv, 10);
$elapsed = round(microtime(true) - $t0, 3);

echo "   [OK] Previsualización completada en {$elapsed}s\n";
echo "   - Total filas: {$prev['total_filas']}\n";
echo "   - Marcaciones válidas (huella): {$prev['marcaciones_validas']}\n";
echo "   - Eventos omitidos: {$prev['marcaciones_omitidas']}\n";
echo "   - Marcaciones consecutivas (<=60s): {$prev['marcaciones_consecutivas']}\n";
echo "   - Total usuarios únicos: {$prev['total_usuarios_unicos']}\n";
echo "   - Dispositivos detectados: " . count($prev['dispositivos']) . "\n";
echo "   - Ya importado previamente: " . ($prev['ya_importado'] ? 'SI' : 'NO') . "\n";

assert($prev['total_filas'] === 50000, "Total filas debe ser 50000");
assert($prev['marcaciones_validas'] === 41382, "Marcaciones válidas debe ser 41382");
assert($prev['marcaciones_omitidas'] === 8618, "Omitidas debe ser 8618");
assert(count($prev['dispositivos']) === 4, "Dispositivos debe ser 4");

// 2. Probar Reimportación Idempotente
echo "\n2. Probando BiostarCsvParser::importar() para verificar deduplicación atómica...\n";
$t1 = microtime(true);
$pdo = Database::getConnection();
$impResult = BiostarCsvParser::importar(
    $realCsv,
    'Report_20261015T070613_1.csv',
    1,
    'Test automatizado de verificacion de idempotencia'
);
$elapsedImp = round(microtime(true) - $t1, 3);

echo "   [OK] Proceso de importación completado en {$elapsedImp}s\n";
echo "   - Importación ID: #{$impResult['importacion_id']}\n";
echo "   - Total procesadas: {$impResult['total_filas']}\n";
echo "   - Nuevas insertadas: {$impResult['marcaciones_insertadas']}\n";
echo "   - Duplicadas omitidas: {$impResult['marcaciones_duplicadas']}\n";
echo "   - Consecutivas marcadas: {$impResult['marcaciones_consecutivas']}\n";

// Dado que las 41,382 ya existen en BD, todas deben ser marcadas como duplicadas e insertadas 0
assert($impResult['marcaciones_insertadas'] === 0, "No debe insertar registros repetidos");
assert($impResult['marcaciones_duplicadas'] === 41382, "Todas deben detectarse como duplicadas");

// 3. Probar Endpoints de Historial e Incidencias
echo "\n3. Probando consultas de historial e incidencias...\n";
$stmtHist = $pdo->query("SELECT COUNT(*) FROM rrhh.ImportacionBiometrica WHERE Estado = 'COMPLETADO'");
$totalCompletadas = $stmtHist->fetchColumn();
echo "   [OK] Total de cargas completadas en BD: {$totalCompletadas}\n";

$stmtInc = $pdo->query("SELECT COUNT(*) FROM rrhh.IncidenciaBiometrica");
$totalIncidencias = $stmtInc->fetchColumn();
echo "   [OK] Total de incidencias registradas en BD: {$totalIncidencias}\n";

echo "\n>>> TODOS LOS TESTS FUNCIONALES PASARON CON EXITO <<<\n";
