<?php
require_once __DIR__.'/../../includes/api_bootstrap.php';
apiGuard('dashboard', ['GET']);

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida.');
$filters = [];
foreach (['area','servicio_id'] as $key) {
    $value = trim((string)($_GET[$key] ?? ''));
    if ($key === 'servicio_id' && $value === '0') $value = '';
    if ($value !== '' && (!ctype_digit($value) || (int)$value < 1)) apiError(400, 'Filtro organizacional inválido.');
    $filters[$key] = $value === '' ? null : (int)$value;
}
$allowed = Auth::getAllowedServiceId();
if ($allowed !== null) {
    if ($filters['servicio_id'] !== null && $filters['servicio_id'] !== $allowed) apiError(403, 'Servicio no autorizado.');
    $filters['servicio_id'] = $allowed;
}
$pdo = apiRequireDatabase();
$scope = 'p.Activo=1';
$params = [];
if ($filters['area'] !== null || $filters['servicio_id'] !== null) {
    $scope .= ' AND EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID=p.PersonalID AND ap.EsActual=1 AND ap.Activo=1';
    if ($filters['area'] !== null) { $scope .= ' AND ap.AreaID=?'; $params[] = $filters['area']; }
    if ($filters['servicio_id'] !== null) { $scope .= ' AND ap.ServicioID=?'; $params[] = $filters['servicio_id']; }
    $scope .= ')';
}
$result = ['ok'=>true, 'fecha'=>$fecha, 'consulta'=>date(DATE_ATOM), 'calidad'=>null, 'pendientes'=>null, 'biometrico'=>null];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN NULLIF(LTRIM(RTRIM(p.NumeroDocumento)),'') IS NULL THEN 1 ELSE 0 END),0) AS sin_documento,
        COALESCE(SUM(CASE WHEN NOT_EXISTS_AREA=1 THEN 1 ELSE 0 END),0) AS sin_area,
        COALESCE(SUM(CASE WHEN NOT_EXISTS_CARGO=1 THEN 1 ELSE 0 END),0) AS sin_cargo,
        COALESCE(SUM(CASE WHEN NOT_EXISTS_BIO=1 THEN 1 ELSE 0 END),0) AS sin_vinculo
        FROM (SELECT p.PersonalID,p.NumeroDocumento,
          CASE WHEN EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal a WHERE a.PersonalID=p.PersonalID AND a.EsActual=1 AND a.Activo=1 AND a.AreaID IS NOT NULL) THEN 0 ELSE 1 END AS NOT_EXISTS_AREA,
          CASE WHEN EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal a WHERE a.PersonalID=p.PersonalID AND a.EsActual=1 AND a.Activo=1 AND a.CargoID IS NOT NULL) THEN 0 ELSE 1 END AS NOT_EXISTS_CARGO,
          CASE WHEN EXISTS (SELECT 1 FROM rrhh.VinculacionBiometrica v WHERE v.PersonalID=p.PersonalID) THEN 0 ELSE 1 END AS NOT_EXISTS_BIO
          FROM rrhh.Personal p WHERE $scope) p");
    $stmt->execute($params);
    $result['calidad'] = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC));
} catch (Throwable $e) { error_log('Dashboard calidad: '.$e->getMessage()); }

try {
    $stmt = $pdo->prepare("SELECT
          (SELECT COUNT(*) FROM rrhh.Contrato c WHERE c.Activo=1 AND c.FechaFin>=CAST(? AS date) AND c.FechaFin<=DATEADD(day,30,CAST(? AS date))
            AND EXISTS (SELECT 1 FROM rrhh.Personal p WHERE p.PersonalID=c.PersonalID AND $scope)) AS contratos_por_vencer");
    $stmt->execute(array_merge([$fecha,$fecha], $params));
    $result['pendientes'] = array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rrhh.PermisoLaboral pl
        WHERE pl.Estado='PENDIENTE' AND EXISTS (SELECT 1 FROM rrhh.Personal p WHERE p.PersonalID=pl.PersonalID AND $scope)");
    $stmt->execute($params);
    $result['pendientes']['solicitudes'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) { error_log('Dashboard pendientes: '.$e->getMessage()); }

try {
    $global = $filters['area'] === null && $filters['servicio_id'] === null;
    $markScope = $global ? '1=1' : "EXISTS (SELECT 1 FROM rrhh.Personal p WHERE p.PersonalID=m.PersonalID AND $scope)";
    $stmt = $pdo->prepare("SELECT TOP 1 i.ImportacionBiometricaID AS id,
        CONVERT(varchar(19),i.FechaHoraCarga,120) AS fecha_carga,i.Estado AS estado
        FROM rrhh.ImportacionBiometrica i".
        ($global ? '' : " WHERE EXISTS (SELECT 1 FROM rrhh.MarcacionBiometrica m WHERE m.ImportacionBiometricaID=i.ImportacionBiometricaID AND $markScope)").
        ' ORDER BY i.FechaHoraCarga DESC,i.ImportacionBiometricaID DESC');
    $stmt->execute($global ? [] : $params);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $bio = ['ultima_carga'=>$last ?: null, 'marcaciones_fecha'=>0, 'ultima_marcacion'=>null, 'pendientes_vinculacion'=>null, 'incidencias_ultima_carga'=>null];
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total,CONVERT(varchar(19),MAX(m.FechaHora),120) AS ultima
        FROM rrhh.MarcacionBiometrica m WHERE m.FechaHora>=CAST(? AS date) AND m.FechaHora<DATEADD(day,1,CAST(? AS date)) AND $markScope");
    $stmt->execute(array_merge([$fecha,$fecha], $global ? [] : $params));
    $marks = $stmt->fetch(PDO::FETCH_ASSOC);
    $bio['marcaciones_fecha'] = (int)$marks['total'];
    $bio['ultima_marcacion'] = $marks['ultima'];
    if ($last) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
            CONVERT(varchar(19),MIN(m.FechaHora),120) AS desde,CONVERT(varchar(19),MAX(m.FechaHora),120) AS hasta,
            COALESCE(SUM(CASE WHEN m.PersonalID IS NULL THEN 1 ELSE 0 END),0) AS pendientes
            FROM rrhh.MarcacionBiometrica m WHERE m.ImportacionBiometricaID=? AND $markScope");
        $stmt->execute(array_merge([(int)$last['id']], $global ? [] : $params));
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        $bio['ultima_carga']['marcaciones'] = (int)$period['total'];
        $bio['ultima_carga']['desde'] = $period['desde'];
        $bio['ultima_carga']['hasta'] = $period['hasta'];
        if ($global) {
            $bio['pendientes_vinculacion'] = (int)$period['pendientes'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM rrhh.IncidenciaBiometrica WHERE ImportacionBiometricaID=?');
            $stmt->execute([(int)$last['id']]);
            $bio['incidencias_ultima_carga'] = (int)$stmt->fetchColumn();
        }
    }
    $result['biometrico'] = $bio;
} catch (Throwable $e) { error_log('Dashboard biométrico: '.$e->getMessage()); }

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
