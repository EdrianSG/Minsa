<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('personal', ['GET'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/personal/detalle.php
 * Hospital Distrital de Pacasmayo - ETAPA 2
 * 
 * Retorna la ficha integral del trabajador consultando el modelo institucional:
 * rrhh.Personal, rrhh.AsignacionPersonal, rrhh.Cargo,
 * rrhh.Profesion, rrhh.Especialidad, organizacion.Area, organizacion.Servicio, rrhh.Contrato
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
$personalId = intval($_GET['id'] ?? 0);

if ($personalId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Identificador de personal no válido.']);
    exit;
}

apiRequirePersonalAccess($personalId);

$pdo = Database::getConnection();
require_once __DIR__.'/../../includes/WorkloadPolicy.php';
$workloadSql=WorkloadPolicy::sql();
$dbConnected = ($pdo !== null);
$detalle = null;

if ($dbConnected) {
    try {
        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                (SELECT COALESCE(MAX(ev.EventoAuditoriaID),0) FROM auditoria.EventoAuditoria ev JOIN seguridad.Sistema sis ON sis.SistemaID=ev.SistemaID WHERE sis.Codigo=:sistema_codigo AND sis.Activo=1 AND ev.TablaAfectada=N'rrhh.Personal' AND ev.RegistroID=CAST(p.PersonalID AS VARCHAR(100)) AND ev.Accion IN ('INSERT','UPDATE')) AS version_ficha,
                p.CodigoEmpleado AS codigo_empleado,
                p.FechaIngreso AS fecha_ingreso,
                p.FechaCese AS fecha_cese,
                p.Activo AS estado,
                
                p.NumeroDocumento AS dni,
                p.Activo AS activo,
                CAST(NULL AS NVARCHAR(150)) AS departamento_origen,
                CAST(NULL AS NVARCHAR(150)) AS cargo_origen,
                CAST(NULL AS DATETIME2) AS fecha_alta_biometrica,
                (SELECT TOP 1 vb.BiostarUserID FROM rrhh.VinculacionBiometrica vb WHERE vb.PersonalID=p.PersonalID ORDER BY vb.VinculacionBiometricaID) AS biostar_user_id,
                p.TipoDocumentoID AS tipo_documento_id,
                CAST(NULL AS NVARCHAR(250)) AS nombre_completo_original,
                sx.Nombre AS sexo_nombre,
                p.Nombres AS nombres,
                p.ApellidoPaterno AS apellido_paterno,
                p.ApellidoMaterno AS apellido_materno,
                LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ', ', p.Nombres))) AS nombre_completo,
                p.FechaNacimiento AS fecha_nacimiento,
                p.SexoID AS sexo_id,
                p.EstadoCivilID AS estado_civil_id,
                p.Telefono AS telefono,
                p.Celular AS celular,
                p.Correo AS correo,
                p.ColegioProfesional AS colegio_profesional,
                p.NumeroColegiatura AS numero_colegiatura,
                p.NumeroRNE AS numero_rne,
                p.RegimenLaboral AS regimen_laboral,
                p.GrupoOcupacional AS grupo_ocupacional,
                {$workloadSql} AS tipo_personal,
                
                c.CargoID AS cargo_id,
                c.Nombre AS cargo_nombre,
                c.Codigo AS cargo_codigo,
                c.Nivel AS cargo_nivel,
                
                prof.ProfesionID AS profesion_id,
                prof.Nombre AS profesion_nombre,
                
                esp.EspecialidadID AS especialidad_id,
                esp.Nombre AS especialidad_nombre,
                
                ap.AsignacionPersonalID AS asignacion_actual_id,
                ap.FechaFin AS fecha_fin_asignacion,
                a.AreaID AS area_id,
                a.Nombre AS area_nombre,
                dep.DepartamentoID AS departamento_id,
                dep.Nombre AS departamento_nombre,
                
                s.ServicioID AS servicio_id,
                s.Nombre AS servicio_nombre,
                
                tc.TipoContratoID AS tipo_contrato_id,
                p.CondicionLaboral AS condicion_laboral,
                con.ContratoID AS contrato_id,
                ap.FechaInicio AS fecha_asignacion,
                con.NumeroContrato AS numero_contrato,
                con.FechaInicio AS contrato_inicio,
                con.FechaFin AS contrato_fin
            FROM rrhh.Personal p
            LEFT JOIN catalogo.Sexo sx ON sx.SexoID=p.SexoID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON p.ProfesionID = prof.ProfesionID
            LEFT JOIN rrhh.Especialidad esp ON p.ProfesionID = esp.ProfesionID AND p.EspecialidadID = esp.EspecialidadID
            LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
            LEFT JOIN organizacion.Departamento dep ON dep.DepartamentoID=a.DepartamentoID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            WHERE p.PersonalID = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $personalId, ':sistema_codigo'=>SystemContext::code()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $detalle = $row;
        }
    } catch (PDOException $e) {
        $dbConnected = false;
    }
}

if (!$dbConnected) apiError(503, 'No se pudo consultar la ficha en SQL Server.');
if (!$detalle) apiError(404, 'Trabajador inexistente.');
$detalle['nombre_completo'] = trim(implode(' ', array_filter([$detalle['apellido_paterno'], $detalle['apellido_materno'], $detalle['nombres']]))) ?: $detalle['nombre_completo_original'];
$detalle['horas_mensuales'] = strtoupper($detalle['tipo_personal'] ?? '') === 'ASISTENCIAL' ? 150 : null;

echo json_encode([
    'ok'   => true,
    'data' => $detalle
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
