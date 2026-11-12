<?php
/** Valida la instalación de triggers dentro de una transacción que siempre se revierte. */
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/DataRevision.php';
$pdo=Database::getConnection(true);
if (!$pdo) {fwrite(STDERR,"SQL no disponible.\n");exit(1);}
if ($pdo->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3') exit(1);
$before=DataRevision::read($pdo);
$checks=0;
$pdo->beginTransaction();
try {
    $stmt=$pdo->query(file_get_contents(__DIR__.'/../database/habilitar_cache_rrhh_v3.sql'));
    while ($stmt->nextRowset()) {}
    $stmt->closeCursor();
    $revision=DataRevision::read($pdo);
    if ($revision===null) throw new RuntimeException('La cobertura no habilitó la revisión.');
    $checks++;
    foreach (['rrhh.Horario'=>'Activo','organizacion.Departamento'=>'Nombre','seguridad.UsuarioSistema'=>'Activo','auditoria.EventoAuditoria'=>'Descripcion'] as $table=>$column) {
        $affected=$pdo->exec("UPDATE TOP (1) $table SET [$column]=[$column]");
        if ($affected>0) {
            $changed=DataRevision::read($pdo);
            if ($changed===$revision || $changed===null) throw new RuntimeException('Cambio sin detectar en '.$table);
            $revision=$changed;
            $checks++;
        }
    }
    $pdo->exec('DISABLE TRIGGER rrhh.TR_SIGH_Revision_Personal ON rrhh.Personal');
    if (DataRevision::read($pdo)!==null) throw new RuntimeException('Se habilitó caché sin seguimiento de Personal.');
    $checks++;
    $pdo->exec('ENABLE TRIGGER rrhh.TR_SIGH_Revision_Personal ON rrhh.Personal');
    $pdo->exec('UPDATE rrhh.Personal SET Activo=Activo WHERE 1=0');
    if (DataRevision::read($pdo)!==$revision) throw new RuntimeException('Un UPDATE sin filas invalidó la caché.');
    $checks++;
    $pdo->rollBack();
    if (DataRevision::read($pdo)!==$before) throw new RuntimeException('No se restauró el estado original.');
    echo json_encode(['modo'=>'PRUEBA_CON_ROLLBACK','comprobaciones'=>$checks,'estado_original_restaurado'=>true]),"\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
