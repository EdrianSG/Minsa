<?php
require_once __DIR__.'/../../includes/api_bootstrap.php';
apiGuard('personal',['POST'],['JEFE_RRHH']);
$data=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$id=filter_var($data['personal_id']??null,FILTER_VALIDATE_INT);
$bio=trim((string)($data['biostar_user_id']??''));
$reason=trim((string)($data['observacion']??''));
if (!$id || $id<1 || $bio==='' || mb_strlen($bio)>30 || $reason==='' || mb_strlen($reason)>200 || ($data['verificado']??false)!==true) apiError(400,'Indique el trabajador, el ID BioStar exacto, la verificación y su sustento.');
apiRequirePersonalAccess($id);
$pdo=apiRequireDatabase();
try {
    $pdo->beginTransaction();
    $s=$pdo->prepare('SELECT PersonalID FROM rrhh.Personal WHERE PersonalID=?'); $s->execute([$id]);
    if ($s->fetchColumn()===false) throw new InvalidArgumentException('Trabajador inexistente.');
    static $hasPadron = null;
    if ($hasPadron === null) {
        $hasPadron = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'rrhh' AND TABLE_NAME = 'PadronBiometricoOrigen'")->fetchColumn();
    }
    if ($hasPadron) {
        $s=$pdo->prepare('SELECT BiostarUserID FROM rrhh.PadronBiometricoOrigen WHERE BiostarUserID=? UNION SELECT BiostarUserID FROM rrhh.MarcacionBiometrica WHERE BiostarUserID=?');
        $s->execute([$bio,$bio]);
    } else {
        $s=$pdo->prepare('SELECT BiostarUserID FROM rrhh.MarcacionBiometrica WHERE BiostarUserID=?');
        $s->execute([$bio]);
    }
    if ($s->fetchColumn()===false) throw new InvalidArgumentException('El ID no existe en el padrón ni en las marcas de origen.');
    $s->closeCursor();
    $s=$pdo->prepare('SELECT PersonalID FROM rrhh.VinculacionBiometrica WITH (UPDLOCK,HOLDLOCK) WHERE BiostarUserID=?'); $s->execute([$bio]); $old=$s->fetch(PDO::FETCH_ASSOC);
    if ($old && $old['PersonalID']!==null && (int)$old['PersonalID']!==$id) throw new InvalidArgumentException('Este ID ya tiene otro vínculo; requiere conciliación, no se sobrescribe.');
    $s=$pdo->prepare('SELECT COUNT(*) FROM rrhh.MarcacionBiometrica WITH (UPDLOCK,HOLDLOCK) WHERE BiostarUserID=? AND PersonalID IS NOT NULL AND PersonalID<>?'); $s->execute([$bio,$id]);
    if ((int)$s->fetchColumn()>0) throw new InvalidArgumentException('Existen marcas ligadas a otro trabajador; requiere conciliación.');
    $actor=(int)Auth::getCurrentUser()['usuario_id'];
    if ($old && $old['PersonalID']===null) {
        $s=$pdo->prepare("UPDATE rrhh.VinculacionBiometrica SET PersonalID=?,Observacion=?,TipoVinculacion='MANUAL',UsuarioID=? WHERE BiostarUserID=? AND PersonalID IS NULL");
        $s->execute([$id,$reason,$actor,$bio]);
    } elseif (!$old) {
        $s=$pdo->prepare("INSERT INTO rrhh.VinculacionBiometrica (BiostarUserID,PersonalID,Observacion,FechaCreacion,TipoVinculacion,UsuarioID) VALUES (?,?,?,SYSDATETIME(),'MANUAL',?)");
        $s->execute([$bio,$id,$reason,$actor]);
    }
    $s=$pdo->prepare("UPDATE rrhh.MarcacionBiometrica SET PersonalID=?,EstadoVinculacion='VINCULADO',EstadoProcesamiento='PENDIENTE' WHERE BiostarUserID=? AND PersonalID IS NULL"); $s->execute([$id,$bio]); $count=$s->rowCount();
    if (!Auditoria::registrar($actor,'PERSONAL','VINCULAR_BIOSTAR','rrhh.Personal',(string)$id,'ID BioStar verificado: '.$bio.'. Sustento: '.$reason)) throw new RuntimeException('Auditoría no disponible.');
    $pdo->commit();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'message'=>'Vínculo verificado guardado. La identidad documental y los datos laborales se conservan.','marcas_vinculadas'=>$count],JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); apiError(409,$e->getMessage()); }
catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); apiError(500,'No se pudo guardar el vínculo; cambios revertidos.'); }
