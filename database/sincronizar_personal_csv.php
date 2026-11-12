<?php
/** Vincula marcas únicamente mediante el padrón explícito aprobado. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
if (!in_array('--aplicar-vinculos-verificados', $argv, true)) {
    fwrite(STDERR, "Solo se aplican vínculos ya verificados. Use --aplicar-vinculos-verificados. No crea trabajadores ni datos laborales.\n");
    exit(1);
}
$pdo=Database::getConnection();
if (!$pdo) { fwrite(STDERR,"SQL Server no disponible.\n"); exit(1); }
try {
    $pdo->beginTransaction();
    $conflicts=$pdo->query("SELECT COUNT(*) FROM rrhh.MarcacionBiometrica m JOIN rrhh.VinculacionBiometrica v ON v.BiostarUserID=m.BiostarUserID WHERE m.PersonalID IS NOT NULL AND v.PersonalID IS NOT NULL AND m.PersonalID<>v.PersonalID")->fetchColumn();
    if ($conflicts) throw new RuntimeException('Existen vínculos en conflicto; requiere conciliación humana.');
    $n=$pdo->exec("UPDATE m SET PersonalID=v.PersonalID, EstadoVinculacion='VINCULADO' FROM rrhh.MarcacionBiometrica m JOIN rrhh.VinculacionBiometrica v ON v.BiostarUserID=m.BiostarUserID JOIN rrhh.Personal p ON p.PersonalID=v.PersonalID WHERE m.PersonalID IS NULL");
    require_once __DIR__.'/../includes/auditoria.php';
    if (!Auditoria::registrar(null,'ASISTENCIA','VINCULAR','rrhh.MarcacionBiometrica',null,'Vínculos explícitos aplicados desde CLI: '.$n)) throw new RuntimeException('No se pudo auditar.');
    $pdo->commit(); echo "Marcaciones vinculadas: $n\n";
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
