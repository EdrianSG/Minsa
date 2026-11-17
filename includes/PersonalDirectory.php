<?php
require_once __DIR__ . '/PersonalSearch.php';
require_once __DIR__ . '/WorkloadPolicy.php';
/** Lectura paginada de personal. Conserva los criterios de presentación existentes. */
final class PersonalDirectory {
    public static function page(PDO $pdo, array $filters): array {
        $workloadSql=WorkloadPolicy::sql('p.PersonalID',null,$pdo);
        $q = $filters['q'];
        $servicio = $filters['servicio'];
        $areaFilter = $filters['areaFilter'];
        $condicion = $filters['condicion'];
        $regimen = $filters['regimen'];
        $estado = $filters['estado'];
        $page = $filters['page'];
        $limit = $filters['limit'];
        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                ap.AsignacionPersonalID AS asignacion_id,
                p.CodigoEmpleado AS codigo_empleado,
                p.FechaIngreso AS fecha_ingreso,
                p.FechaCese AS fecha_cese,
                p.Activo AS estado,
                p.Activo AS activo,
                p.NumeroDocumento AS dni,
                CAST(NULL AS NVARCHAR(150)) AS departamento_origen,
                CAST(NULL AS NVARCHAR(150)) AS cargo_origen,
                CAST(NULL AS DATETIME2) AS fecha_alta_biometrica,
                (SELECT TOP 1 vb.BiostarUserID FROM rrhh.VinculacionBiometrica vb WHERE vb.PersonalID=p.PersonalID ORDER BY vb.VinculacionBiometricaID) AS biostar_user_id,
                p.RegimenLaboral AS regimen_laboral,
                {$workloadSql} AS tipo_personal_confirmado,
                p.GrupoOcupacional AS grupo_ocupacional,
                p.NombreCompletoOriginal AS nombre_completo_original,
                p.Nombres AS nombres,
                p.ApellidoPaterno AS apellido_paterno,
                p.ApellidoMaterno AS apellido_materno,
                LTRIM(RTRIM(CONCAT(p.ApellidoPaterno, ' ', ISNULL(p.ApellidoMaterno, ''), ', ', p.Nombres))) AS nombre_completo,
                p.Celular AS celular,
                p.Correo AS correo,
                p.Telefono AS telefono,
                
                c.CargoID AS cargo_id,
                ISNULL(c.Nombre, 'Sin Cargo') AS cargo_nombre,
                
                prof.ProfesionID AS profesion_id,
                ISNULL(prof.Nombre, 'General') AS profesion_nombre,
                
                esp.EspecialidadID AS especialidad_id,
                esp.Nombre AS especialidad_nombre,
                
                a.AreaID AS area_id,
                ISNULL(a.Nombre, 'General') AS area_nombre,
                
                s.ServicioID AS servicio_id,
                ISNULL(s.Nombre, 'Servicio General') AS servicio_nombre,
                
                tc.TipoContratoID AS tipo_contrato_id,
                p.CondicionLaboral AS condicion_laboral,
                con.NumeroContrato AS numero_contrato
            FROM rrhh.Personal p
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Activo = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON p.ProfesionID = prof.ProfesionID
            LEFT JOIN rrhh.Especialidad esp ON p.ProfesionID = esp.ProfesionID AND p.EspecialidadID = esp.EspecialidadID
            LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            OUTER APPLY (SELECT TOP 1 cx.* FROM rrhh.Contrato cx WHERE cx.PersonalID = p.PersonalID AND cx.Activo = 1 ORDER BY cx.FechaInicio DESC, cx.ContratoID DESC) con
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            WHERE 1=1
        ";

        $detailsSql = $sql;
        $params = [];

        if ($estado !== null) {
            $sql .= " AND p.Activo = :estado";
            $params[':estado'] = $estado;
        }

        if ($condicion !== '') {
            $sql .= " AND p.CondicionLaboral = :condicion";
            $params[':condicion'] = $condicion;
        }

        if ($regimen !== '') {
            $regUpper = strtoupper(trim($regimen));
            if (!in_array($regUpper,['ADMINISTRATIVO','ASISTENCIAL'],true)) {
                $sql .= " AND (p.RegimenLaboral LIKE :regimen OR p.RegimenLaboral = :regimenExact)";
                $params[':regimen'] = "%$regimen%";
                $params[':regimenExact'] = $regimen;
            }
        }

        if ($areaFilter !== '') {
            $sql .= " AND ap.AreaID = :areaId";
            $params[':areaId'] = (int)$areaFilter;
        }

        if ($servicio !== '') {
            if (is_numeric($servicio)) {
                $sql .= " AND s.ServicioID = :servId";
                $params[':servId'] = intval($servicio);
            } else {
                $sql .= " AND s.Nombre LIKE :servNombre";
                $params[':servNombre'] = "%$servicio%";
            }
        }

        $identitySql = "CONCAT(p.Nombres,N' ',p.ApellidoPaterno,N' ',p.ApellidoMaterno,N' ',
            p.NombreCompletoOriginal,N' ',p.NumeroDocumento,N' ',p.CodigoEmpleado,N' ',c.Nombre,N' ',prof.Nombre)";
        foreach (PersonalSearch::terms($q) as $index=>$term) {
            $sql .= " AND (CHARINDEX(:nameTerm$index,$identitySql COLLATE Latin1_General_100_CI_AI)>0
                OR EXISTS(SELECT 1 FROM rrhh.VinculacionBiometrica vb WHERE vb.PersonalID=p.PersonalID
                    AND CHARINDEX(:bioTerm$index,vb.BiostarUserID COLLATE Latin1_General_100_CI_AI)>0))";
            $params[':nameTerm'.$index] = $term;
            $params[':bioTerm'.$index] = $term;
        }

        $conditionSql = self::upperSql('condicion_laboral');
        $cte = "WITH Base AS ($sql), Normalized AS (SELECT Base.*,tipo_personal_confirmado AS tipo_calculado FROM Base)";
        $postFilters = [];
        if (in_array(strtoupper(trim($regimen)),['ASISTENCIAL','ADMINISTRATIVO'],true)) {
            $postFilters[]='tipo_calculado=:tipoFiltro';
            $params[':tipoFiltro']=strtoupper(trim($regimen));
        }
        $matches = $postFilters ? implode(' AND ',$postFilters) : '1=1';
        // Materializar solo claves y contadores durante esta consulta evita repetir
        // las uniones y la clasificación al calcular totales y recuperar la página.
        $cte = "SET NOCOUNT ON;
            DECLARE @Directory TABLE (
                ordinal bigint PRIMARY KEY, personal_id int, asignacion_id int NULL,
                tipo_calculado nvarchar(300) NULL, condicion_laboral nvarchar(100) NULL,
                estado bit NULL, coincide bit NOT NULL
            );
            $cte
            INSERT @Directory
            SELECT ROW_NUMBER() OVER (ORDER BY apellido_paterno,apellido_materno,nombres,personal_id,asignacion_id),
                personal_id,asignacion_id,tipo_calculado,condicion_laboral,estado,
                CASE WHEN $matches THEN 1 ELSE 0 END FROM Normalized;
            SELECT Details.*,PageIDs.tipo_calculado,Totals.* FROM (
                SELECT COALESCE(SUM(CAST(coincide AS int)),0) AS __total_registros,COUNT(*) AS __total,
                    COALESCE(SUM(CASE WHEN tipo_calculado='ASISTENCIAL' THEN 1 ELSE 0 END),0) AS __asistenciales,
                    COALESCE(SUM(CASE WHEN tipo_calculado='ADMINISTRATIVO' THEN 1 ELSE 0 END),0) AS __administrativos,
                    COALESCE(SUM(CASE WHEN $conditionSql='NOMBRADO' THEN 1 ELSE 0 END),0) AS __nombrados,
                    COALESCE(SUM(CASE WHEN $conditionSql='CAS' THEN 1 ELSE 0 END),0) AS __cas,
                    COALESCE(SUM(CASE WHEN estado IS NULL THEN 1 ELSE 0 END),0) AS __pendientes_validacion,
                    COALESCE(SUM(CASE WHEN estado=1 THEN 1 ELSE 0 END),0) AS __activos FROM @Directory
            ) Totals OUTER APPLY (
                SELECT * FROM @Directory WHERE coincide=1 ORDER BY ordinal
                OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
            ) PageIDs OUTER APPLY (
                $detailsSql AND p.PersonalID=PageIDs.personal_id
                AND (ap.AsignacionPersonalID=PageIDs.asignacion_id OR (ap.AsignacionPersonalID IS NULL AND PageIDs.asignacion_id IS NULL))
            ) Details ORDER BY PageIDs.ordinal";
        $stmt = $pdo->prepare($cte);
        foreach ($params as $key=>$value) $stmt->bindValue($key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $offset = min($page - 1, intdiv(PHP_INT_MAX,$limit)) * $limit;
        $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = (int)$result[0]['__total_registros'];
        $stats = [];
        foreach (['total','asistenciales','administrativos','nombrados','cas','pendientes_validacion','activos'] as $field) {
            $stats[$field] = (int)$result[0]['__'.$field];
        }
        $rows = [];
        foreach ($result as $row) {
            if ($row['personal_id'] === null) continue;
            $row['tipo_personal'] = $row['tipo_calculado'];
            $row['aplica_seti'] = $row['tipo_calculado'] === 'ASISTENCIAL' ? 1 : null;
            unset($row['tipo_calculado'],$row['__total_registros'],$row['asignacion_id']);
            foreach (array_keys($stats) as $field) unset($row['__'.$field]);
            $rows[] = $row;
        }
        return ['ok'=>true,'total_registros'=>$count,'pagina_actual'=>$page,
            'total_paginas'=>max(1,(int)ceil($count/$limit)),'limite'=>$limit,
            'estadisticas'=>$stats,'data'=>$rows,'conexion_sql'=>true];
    }

    private static function upperSql(string $expression): string {
        return "(TRANSLATE($expression,'abcdefghijklmnopqrstuvwxyz','ABCDEFGHIJKLMNOPQRSTUVWXYZ') COLLATE Latin1_General_100_BIN2)";
    }
}
