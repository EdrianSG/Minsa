<?php
require_once __DIR__.'/../../includes/api_bootstrap.php';
apiGuard('personal',['GET'],['JEFE_RRHH']);
if (!Auth::hasRole('ADMIN','ADMIN_RRHH','JEFE_RRHH')) apiError(403,'El padrón sin homologar requiere autorización de Recursos Humanos.');
$pdo=apiRequireDatabase();
try {
    $hasPadron = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'rrhh' AND TABLE_NAME = 'PadronBiometricoOrigen'")->fetchColumn();
    if ($hasPadron) {
        $rows=$pdo->query("WITH pendientes AS (
            SELECT b.BiostarUserID AS biostar_user_id,b.NombreOriginal AS nombre_original,
                b.DepartamentoOriginal AS departamento_original,b.CargoOriginal AS cargo_original,'PADRON' AS fuente
            FROM rrhh.PadronBiometricoOrigen b WHERE NOT EXISTS(SELECT 1 FROM rrhh.VinculacionBiometrica v WHERE v.BiostarUserID=b.BiostarUserID AND v.PersonalID IS NOT NULL)
            UNION ALL
            SELECT m.BiostarUserID,CASE WHEN COUNT(DISTINCT m.BiostarUserName)=1 THEN MAX(m.BiostarUserName) ELSE NULL END,NULL,NULL,'MARCACIONES'
            FROM rrhh.MarcacionBiometrica m WHERE m.PersonalID IS NULL AND m.BiostarUserID IS NOT NULL
                AND NOT EXISTS(SELECT 1 FROM rrhh.PadronBiometricoOrigen b WHERE b.BiostarUserID=m.BiostarUserID)
                AND NOT EXISTS(SELECT 1 FROM rrhh.VinculacionBiometrica v WHERE v.BiostarUserID=m.BiostarUserID AND v.PersonalID IS NOT NULL)
            GROUP BY m.BiostarUserID)
            SELECT p.*,(SELECT COUNT(*) FROM rrhh.MarcacionBiometrica m WHERE m.BiostarUserID=p.biostar_user_id AND m.PersonalID IS NULL) AS marcas_pendientes
            FROM pendientes p ORDER BY p.biostar_user_id")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows=$pdo->query("SELECT 
            m.BiostarUserID AS biostar_user_id,
            CASE WHEN COUNT(DISTINCT m.BiostarUserName)=1 THEN MAX(m.BiostarUserName) ELSE NULL END AS nombre_original,
            CAST(NULL AS NVARCHAR(150)) AS departamento_original,
            CAST(NULL AS NVARCHAR(150)) AS cargo_original,
            'MARCACIONES' AS fuente,
            COUNT(*) AS marcas_pendientes
        FROM rrhh.MarcacionBiometrica m 
        WHERE m.PersonalID IS NULL AND m.BiostarUserID IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM rrhh.VinculacionBiometrica v WHERE v.BiostarUserID=m.BiostarUserID AND v.PersonalID IS NOT NULL)
        GROUP BY m.BiostarUserID
        ORDER BY m.BiostarUserID")->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'data'=>$rows,'total'=>count($rows),'conexion_sql'=>true],JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) { error_log($e->getMessage()); apiError(503,'No se pudo consultar el padrón pendiente.'); }
