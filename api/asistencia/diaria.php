<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

/**
 * API Endpoint: /api/asistencia/diaria.php
 * Hospital Distrital de Pacasmayo - ETAPA 5
 * 
 * Evalúa la asistencia diaria cruzando el turno programado vs. las marcaciones biométricas
 * según las reglas institucionales de Recursos Humanos y MINSA:
 * - Tolerancia de entrada: hasta +5 minutos de la HoraInicio (Puntual).
 * - Tardanza: entre +6 y +30 minutos de la HoraInicio (Cómputo exacto de minutos).
 * - Falta Administrativa: más de +30 minutos de la HoraInicio o sin marcación.
 * - Salida anticipada: antes de la HoraFin programada.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/attendance_evaluator.php';
require_once __DIR__ . '/../../includes/AttendanceReconciler.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida.');
$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : null;
$filtroEstado = isset($_GET['estado']) && $_GET['estado'] !== '' ? strtoupper(trim($_GET['estado'])) : null;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$pdo = Database::getConnection();
$asistencias = [];
$dbConnected = ($pdo !== null);

if ($dbConnected) {
    try {
        // Auto-conciliación si rrhh.Asistencia no tiene registros pero el biométrico sí
        $stmtChk = $pdo->prepare("SELECT COUNT(*) as cnt FROM rrhh.Asistencia WHERE Fecha = ?");
        $stmtChk->execute([$fecha]);
        $cntAsist = (int)($stmtChk->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        if ($cntAsist === 0) {
            $stmtBio = $pdo->prepare("SELECT COUNT(*) as cnt FROM rrhh.MarcacionBiometrica WHERE CAST(FechaHora AS DATE) = ?");
            $stmtBio->execute([$fecha]);
            $cntBio = (int)($stmtBio->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

            if ($cntBio > 0) {
                AttendanceReconciler::conciliarFecha($fecha, $pdo);
            }
        }
        $sql = "
            SELECT 
                p.PersonalID,
                per.NumeroDocumento AS DNI,
                per.Nombres,
                per.ApellidoPaterno,
                per.ApellidoMaterno,
                per.Telefono,
                per.Correo AS CorreoElectronico,
                ISNULL(c.Nombre, 'Servidor Público') AS NombreCargo,
                ISNULL(prof.Nombre, 'General') AS NombreProfesion,
                s.ServicioID,
                ISNULL(s.Nombre, 'Hospital Distrital de Pacasmayo') AS NombreServicio,
                'ASISTENCIAL' AS RegimenLaboral,
                ISNULL(tc.Nombre, 'CAS') AS CondicionLaboral,
                t.TurnoID,
                ISNULL(t.Nombre, 'Mañana Ordinario') AS NombreTurno,
                ISNULL(CONVERT(VARCHAR(8), t.HoraInicio, 108), '07:00:00') AS HoraProgInicio,
                ISNULL(CONVERT(VARCHAR(8), t.HoraFin, 108), '13:00:00') AS HoraProgFin,
                a.AsistenciaID,
                CONVERT(VARCHAR(8), a.HoraEntrada, 108) AS HoraEntrada,
                CONVERT(VARCHAR(8), a.HoraSalida, 108) AS HoraSalida,
                a.Estado AS EstadoAsistencia,
                a.Observacion
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Profesion prof ON ap.ProfesionID = prof.ProfesionID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            LEFT JOIN rrhh.Horario h ON p.PersonalID = h.PersonalID AND h.DiaSemana = :diaSemana AND h.Estado = 1 AND h.FechaInicio <= :fechaInicio AND (h.FechaFin IS NULL OR h.FechaFin >= :fechaFin)
            LEFT JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            LEFT JOIN rrhh.Asistencia a ON p.PersonalID = a.PersonalID AND a.Fecha = :fechaAsist
            WHERE p.Estado = 1
        ";

        $params = [
            ':diaSemana' => (int)date('N', strtotime($fecha)),
            ':fechaInicio' => $fecha,
            ':fechaFin' => $fecha,
            ':fechaAsist' => $fecha
        ];

        if ($servicioId !== null) {
            $sql .= " AND s.ServicioID = :servicioId";
            $params[':servicioId'] = $servicioId;
        }

        $sql .= " ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawRecords as $row) {
            $nombreCompleto = trim("{$row['ApellidoPaterno']} {$row['ApellidoMaterno']} {$row['Nombres']}");
            $asistencias[] = procesarFilaAsistencia($row, $nombreCompleto, $fecha);
        }
        if (!empty($asistencias)) {
            require_once __DIR__ . '/../../includes/SnapshotStore.php';
            SnapshotStore::set('asistencia_diaria_' . $fecha, $asistencias, ['source' => 'SQL_SERVER_LIVE', 'fecha' => $fecha]);
        }
    } catch (Exception $e) {
        $dbConnected = false;
    }
}

// Resiliencia local: si la BD está offline, intentar cargar el Snapshot de asistencia de esa fecha o la más reciente
if (empty($asistencias)) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    if (SnapshotStore::has('asistencia_diaria_' . $fecha)) {
        $snap = SnapshotStore::get('asistencia_diaria_' . $fecha);
        if (!empty($snap['data']) && is_array($snap['data'])) {
            $asistencias = $snap['data'];
        }
    }
}

if (empty($asistencias)) {
    echo json_encode([
        'ok'              => true,
        'fecha'           => $fecha,
        'conexion_sql'    => $dbConnected,
        'resumen'         => [
            'total_evaluados'        => 0,
            'total_programados'      => 0,
            'asistio_puntual'        => 0,
            'tardanzas'              => 0,
            'minutos_tardanza_total' => 0,
            'faltas'                 => 0,
            'permisos'               => 0,
            'descansos'              => 0,
            'porcentaje_asistencia'  => 100.0
        ],
        'total_registros' => 0,
        'asistencias'     => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Filtros en memoria para búsqueda de texto, servicio y estado
$filtrados = array_values(array_filter($asistencias, function ($item) use ($servicioId, $filtroEstado, $busqueda) {
    if ($servicioId !== null && intval($item['servicio_id']) !== $servicioId) {
        return false;
    }

    if ($filtroEstado !== null && $filtroEstado !== 'TODOS') {
        if ($filtroEstado === 'AUSENTES' || $filtroEstado === 'FALTA') {
            if (!in_array($item['estado_evaluado'], ['FALTA', 'FALTA_ADMINISTRATIVA'])) {
                return false;
            }
        } elseif ($item['estado_evaluado'] !== $filtroEstado) {
            return false;
        }
    }

    if ($busqueda !== '') {
        $term = mb_strtolower($busqueda, 'UTF-8');
        $encontrado = (
            strpos(mb_strtolower($item['nombre_completo'], 'UTF-8'), $term) !== false ||
            strpos($item['dni'], $term) !== false ||
            strpos(mb_strtolower($item['cargo'], 'UTF-8'), $term) !== false ||
            strpos(mb_strtolower($item['servicio_nombre'], 'UTF-8'), $term) !== false
        );
        if (!$encontrado) return false;
    }

    return true;
}));

// Resumen estadístico del día
$totalProgramados = 0;
$asistioPuntual = 0;
$tardanzas = 0;
$minutosTardanzaTotal = 0;
$faltas = 0;
$permisos = 0;
$descansos = 0;

foreach ($asistencias as $item) {
    if ($item['turno_codigo'] !== 'LIB' && $item['turno_codigo'] !== 'DESC') {
        $totalProgramados++;
    }

    switch ($item['estado_evaluado']) {
        case 'ASISTIO':
            $asistioPuntual++;
            break;
        case 'TARDANZA':
            $tardanzas++;
            $minutosTardanzaTotal += intval($item['minutos_tardanza']);
            break;
        case 'FALTA':
        case 'FALTA_ADMINISTRATIVA':
            $faltas++;
            break;
        case 'PERMISO':
        case 'LICENCIA':
            $permisos++;
            break;
        case 'DESCANSO':
            $descansos++;
            break;
    }
}

$porcentajeAsistencia = ($totalProgramados > 0) 
    ? round((($asistioPuntual + $tardanzas) / $totalProgramados) * 100, 1) 
    : 100.0;

echo json_encode([
    'ok'                   => true,
    'fecha'                => $fecha,
    'conexion_sql'         => $dbConnected,
    'resumen'              => [
        'total_evaluados'        => count($asistencias),
        'total_programados'      => $totalProgramados,
        'asistio_puntual'        => $asistioPuntual,
        'tardanzas'              => $tardanzas,
        'minutos_tardanza_total' => $minutosTardanzaTotal,
        'faltas'                 => $faltas,
        'permisos'               => $permisos,
        'descansos'              => $descansos,
        'porcentaje_asistencia'  => $porcentajeAsistencia
    ],
    'total_registros'      => count($filtrados),
    'asistencias'          => $filtrados
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);




if (!function_exists('generarAsistenciasInstitucionales')) {
function generarAsistenciasInstitucionales($fecha) {
    $personalInstitucional = [
        // Emergencia y Cuidados Críticos
        [1, '45871234', 'Dra. Carmen Rosa Benites Silva', '944812301', 'Médico Cirujano Especialista', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'NOMBRADO', 'GD', '08:00', '20:00', '07:54', '20:05', 'ASISTIO', 0, 'Marcación biométrica puntual.'],
        [2, '41239876', 'Dr. Manuel Jesús Alayo Vargas', '944812302', 'Médico Emergencista', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'NOMBRADO', 'GD', '08:00', '20:00', '08:18', '20:00', 'TARDANZA', 18, 'Tardanza de 18 min en biométrico.'],
        [3, '70123456', 'Lic. Sofia Elena Paredes Quiroz', '944812303', 'Enfermera Especialista en Emergencias', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:03', '14:02', 'ASISTIO', 0, 'Dentro de tolerancia de 5 min.'],
        [4, '46985214', 'Lic. Patricia Noelia Ríos Mendoza', '944812304', 'Enfermera Asistencial', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', null, null, 'FALTA', 0, 'Ausencia total. Sin marcación en biométrico.'],
        [5, '47896325', 'Lic. Katherine Melissa Castro Ruiz', '944812305', 'Licenciada en Enfermería', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:14', '14:00', 'TARDANZA', 14, 'Tardanza de 14 min en biométrico.'],
        [6, '43216549', 'Tec. Jorge Luis Morales Chávez', '944812306', 'Técnico de Enfermería', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'NOMBRADO', 'GD', '08:00', '20:00', '07:48', '20:10', 'ASISTIO', 0, 'Ingreso puntual anticipado.'],
        [7, '48963214', 'Tec. Rosa María Vásquez León', '944812307', 'Técnica en Enfermería', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'CAS', 'GD', '08:00', '20:00', null, null, 'PERMISO', 0, 'Papeleta de Permiso por Comisión Oficial.'],
        [8, '45632147', 'Tec. Carlos Alberto Sánchez Flores', '944812308', 'Técnico de Enfermería', 1, 'Emergencia y Cuidados Críticos', 'ASISTENCIAL', 'NOMBRADO', 'GN', '20:00', '08:00', null, null, 'DESCANSO', 0, 'Programado para turno nocturno de hoy.'],

        // Hospitalización
        [9, '42589631', 'Dra. Ana Isabel Gutiérrez Torres', '944812309', 'Médico Internista', 2, 'Hospitalización Medicina y Cirugía', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '07:58', '14:05', 'ASISTIO', 0, 'Ingreso conforme.'],
        [10, '46321789', 'Lic. Claudia Milagros Cruzado Vera', '944812310', 'Enfermera Coordinadora de Sala', 2, 'Hospitalización Medicina y Cirugía', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '08:42', '14:00', 'FALTA_ADMINISTRATIVA', 42, 'Ingreso a las 08:42 (+42 min). Falta administrativa.'],
        [11, '71236548', 'Lic. Diana Carolina Gómez Rázuri', '944812311', 'Enfermera Asistencial', 2, 'Hospitalización Medicina y Cirugía', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:00', '14:00', 'ASISTIO', 0, 'Puntual exacto.'],
        [12, '48521479', 'Tec. Walter Andrés Gil Valdivia', '944812312', 'Técnico en Enfermería', 2, 'Hospitalización Medicina y Cirugía', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', null, null, 'FALTA', 0, 'Inasistencia sin justificar.'],

        // Gineco-Obstetricia
        [13, '40125896', 'Obs. María Elena Salazar Romero', '944812313', 'Obstetra Especialista', 3, 'Gineco-Obstetricia y Centro Obstétrico', 'ASISTENCIAL', 'NOMBRADO', 'GD', '08:00', '20:00', '07:50', '20:00', 'ASISTIO', 0, 'Puntual.'],
        [14, '47852369', 'Obs. Jessica Paola Chinchay Vega', '944812314', 'Obstetra Asistencial', 3, 'Gineco-Obstetricia y Centro Obstétrico', 'ASISTENCIAL', 'CAS', 'GD', '08:00', '20:00', '08:22', '20:00', 'TARDANZA', 22, 'Tardanza de 22 min en biométrico.'],

        // Pediatría y Neonatología
        [15, '41589632', 'Dr. Roberto Carlos Miranda Zavaleta', '944812315', 'Médico Pediatra', 4, 'Pediatría y Neonatología', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '08:02', '14:00', 'ASISTIO', 0, 'Puntual dentro de tolerancia.'],
        [16, '72365412', 'Lic. Gladys Verónica Polo Becerra', '944812316', 'Enfermera Neonatóloga', 4, 'Pediatría y Neonatología', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:04', '14:01', 'ASISTIO', 0, 'Puntual.'],

        // Cirugía y Centro Quirúrgico
        [17, '43652148', 'Dr. Víctor Hugo Castañeda Prado', '944812317', 'Cirujano General', 5, 'Cirugía y Centro Quirúrgico', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '07:45', '14:15', 'ASISTIO', 0, 'Puntual.'],
        [18, '46987412', 'Lic. Maritza Isabel Tejada Correa', '944812318', 'Enfermera Instrumentista', 5, 'Cirugía y Centro Quirúrgico', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:05', '14:00', 'ASISTIO', 0, 'Puntual en límite de tolerancia (5 min).'],

        // Consulta Externa
        [19, '42145896', 'Dr. Fernando Javier Reyes Silva', '944812319', 'Médico Cardiólogo', 6, 'Consulta Externa y Especialidades', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '08:00', '14:00', 'ASISTIO', 0, 'Puntual.'],
        [20, '45963214', 'Dr. Segundo Aurelio Vallejos Luna', '944812320', 'Médico Neumólogo', 6, 'Consulta Externa y Especialidades', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', '08:15', '14:00', 'TARDANZA', 15, 'Tardanza de 15 min.'],

        // Farmacia
        [21, '43258961', 'Q.F. Liliana Beatriz Cáceres Díaz', '944812321', 'Química Farmacéutica Jefa', 7, 'Farmacia Hospitalaria', 'ASISTENCIAL', 'NOMBRADO', 'M', '08:00', '14:00', '07:55', '14:00', 'ASISTIO', 0, 'Puntual.'],
        [22, '48965214', 'Tec. Nelson David Ortiz Mendoza', '944812322', 'Técnico de Farmacia', 7, 'Farmacia Hospitalaria', 'ASISTENCIAL', 'CAS', 'M', '08:00', '14:00', null, null, 'FALTA', 0, 'Falta no justificada. No asistió.'],

        // Laboratorio Clínico
        [23, '44589632', 'Lic. César Augusto Palacios Vega', '944812323', 'Biólogo - Tecnólogo Médico', 8, 'Laboratorio Clínico y Banco de Sangre', 'ASISTENCIAL', 'NOMBRADO', 'GD', '08:00', '20:00', '07:58', '20:00', 'ASISTIO', 0, 'Puntual.'],

        // Administración y RR.HH.
        [24, '41852963', 'Lic. Eduardo Francisco Díaz Saldaña', '944812324', 'Jefe de Recursos Humanos', 9, 'Dirección y Recursos Humanos', 'ADMINISTRATIVO', 'NOMBRADO', 'ADM', '07:30', '16:30', '07:28', '16:35', 'ASISTIO', 0, 'Puntual.'],
        [25, '46325874', 'Abog. Mónica Patricia Arana Vigo', '944812325', 'Especialista en Control de Asistencia', 9, 'Dirección y Recursos Humanos', 'ADMINISTRATIVO', 'CAS', 'ADM', '07:30', '16:30', '07:34', '16:30', 'ASISTIO', 0, 'Dentro de tolerancia (4 min).'],
        [26, '47896541', 'Bach. Gonzalo Enrique Medina Solís', '944812326', 'Asistente de Planillas y Escalafón', 9, 'Dirección y Recursos Humanos', 'ADMINISTRATIVO', 'CAS', 'ADM', '07:30', '16:30', '07:46', '16:30', 'TARDANZA', 16, 'Tardanza administrativa de 16 min.']
    ];

    $resultado = [];
    foreach ($personalInstitucional as $p) {
        $resultado[] = [
            'personal_id'              => $p[0],
            'dni'                      => $p[1],
            'nombre_completo'          => $p[2],
            'telefono'                 => $p[3],
            'correo'                   => 'trabajador.' . $p[1] . '@hospitalpacasmayo.gob.pe',
            'cargo'                    => $p[4],
            'profesion'                => 'Salud / Administración',
            'servicio_id'              => $p[5],
            'servicio_nombre'          => $p[6],
            'regimen_laboral'          => $p[7],
            'condicion_laboral'        => $p[8],
            'turno_id'                 => 1,
            'turno_nombre'             => ($p[9] === 'GD') ? 'Guardia Diurna' : (($p[9] === 'GN') ? 'Guardia Nocturna' : (($p[9] === 'ADM') ? 'Administrativo' : 'Turno Mañana')),
            'turno_codigo'             => $p[9],
            'hora_prog_inicio'         => $p[10],
            'hora_prog_fin'            => $p[11],
            'asistencia_id'            => $p[0],
            'hora_entrada'             => $p[12],
            'hora_salida'              => $p[13],
            'minutos_tardanza'         => $p[15],
            'minutos_salida_anticipada'=> 0,
            'estado_evaluado'          => $p[14],
            'observacion'              => $p[16],
            'observacion_regla'        => $p[16],
            'es_ausente'               => in_array($p[14], ['FALTA', 'FALTA_ADMINISTRATIVA'])
        ];
    }

    return $resultado;
}
}

