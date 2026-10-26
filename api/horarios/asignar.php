<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['POST'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/horarios/asignar.php
 * Hospital Distrital de Pacasmayo - Control de Carga Horaria Laboral (MINSA)
 * 
 * Asigna la pauta de horario semanal validando:
 * 1. Existencia del trabajador y régimen (Asistencial vs Administrativo).
 * 2. Catálogo de turnos y duración real (incluye turnos nocturnos que cruzan medianoche).
 * 3. Cálculo de horas semanales y validación contra máximos laborales.
 * 4. Proyección mensual real por calendario (del día de vigencia al fin de mes respectivo: 28, 29, 30 o 31 días).
 * 5. Evaluación de estados: CORRECTO, ADVERTENCIA o BLOQUEO.
 * 6. Manejo histórico: FechaFin de pauta anterior = FechaInicio - 1 día.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/workload_rules.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$personalId  = intval($data['personal_id'] ?? 0);
$fechaInicio = trim($data['fecha_inicio'] ?? date('Y-m-01'));
$dias        = $data['dias'] ?? []; // Array [dia => turno_id]

if ($personalId <= 0 || empty($dias)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Trabajador y pauta horaria son requeridos.']);
    exit;
}

// Validar formato de fecha YYYY-MM-DD
$dateParts = explode('-', $fechaInicio);
if (!apiValidDate($fechaInicio)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'La fecha de inicio de vigencia no es válida.']);
    exit;
}

$year  = intval($dateParts[0]);
$month = intval($dateParts[1]);
$day   = intval($dateParts[2]);

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

// 1. Obtener información del trabajador para determinar su régimen
$cargoNombre = '';
$servicioNombre = '';
$areaNombre = '';
$trabajadorNombre = "Personal #$personalId";

if ($dbConnected) {
    try {
        $stmtWorker = $pdo->prepare("
            SELECT 
                p.PersonalID,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS NombreCompleto,
                ISNULL(c.Nombre, '') AS CargoNombre,
                ISNULL(s.Nombre, '') AS ServicioNombre,
                ISNULL(a.Nombre, '') AS AreaNombre
            FROM rrhh.Personal p
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN organizacion.Area a ON ap.AreaID = a.AreaID
            WHERE p.PersonalID = ?
        ");
        $stmtWorker->execute([$personalId]);
        $wRow = $stmtWorker->fetch(PDO::FETCH_ASSOC);
        if (!$wRow) apiError(404, 'Trabajador inexistente.');
        if ($wRow) {
            $trabajadorNombre = trim($wRow['NombreCompleto']);
            $cargoNombre      = $wRow['CargoNombre'];
            $servicioNombre   = $wRow['ServicioNombre'];
            $areaNombre       = $wRow['AreaNombre'];
        }
    } catch (PDOException $e) {
        apiError(500, 'No se pudo consultar al trabajador.');
    }
}

// Obtener reglas según régimen
$reglas = WorkloadRules::getRulesForWorker($cargoNombre, $servicioNombre, $areaNombre);
$maxSemanal      = $reglas['horas_semanales_maximas'];
$targetMensual   = $reglas['horas_mensuales_objetivo'];
$maxMensual      = $reglas['horas_mensuales_maximas'];
$tipoPersonal    = $reglas['tipo'];

// 2. Obtener duración de los turnos desde catálogo (soporta medianoche)
$turnosMap = [];
if ($dbConnected) {
    try {
        $stmtTurnos = $pdo->query("SELECT TurnoID, Nombre, HoraInicio, HoraFin FROM rrhh.Turno WHERE Estado = 1");
        $turnosRows = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);
        foreach ($turnosRows as $tr) {
            $inicio = substr($tr['HoraInicio'], 0, 5);
            $fin = substr($tr['HoraFin'], 0, 5);
            $t1 = strtotime($inicio);
            $t2 = strtotime($fin);
            $cruza = ($t2 < $t1);
            if ($cruza) {
                $dur = (strtotime('24:00') - $t1 + $t2 - strtotime('00:00')) / 3600;
            } else {
                $dur = ($t2 - $t1) / 3600;
            }
            $turnosMap[intval($tr['TurnoID'])] = round($dur, 2);
        }
    } catch (PDOException $e) {
        apiError(500, 'No se pudo consultar el catálogo de turnos.');
    }
} else {
    $turnosMap = [1 => 6.0, 2 => 6.0, 3 => 12.0, 4 => 12.0, 5 => 8.0];
}

// 3. Calcular Horas Semanales
$totalHorasSemana = 0;
$diasValidos = [];

for ($i = 1; $i <= 7; $i++) {
    $tid = isset($dias[$i]) ? intval($dias[$i]) : 0;
    if ($tid < 0 || ($tid > 0 && !isset($turnosMap[$tid]))) apiError(400, 'La pauta contiene un turno inexistente.');
    $diasValidos[$i] = $tid;
    if ($tid > 0 && isset($turnosMap[$tid])) {
        $totalHorasSemana += $turnosMap[$tid];
    }
}

// 4. Validar Máximo Semanal (BLOQUEO)
if ($maxSemanal !== null && $totalHorasSemana > $maxSemanal) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'message' => "No se puede asignar esta pauta porque genera {$totalHorasSemana} horas semanales y el límite configurado para {$reglas['nombre']} es de {$maxSemanal} horas.",
        'reglas'  => $reglas,
        'horas_semanales' => $totalHorasSemana
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Proyección Mensual Real por Calendario
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$startDay = min(max(1, $day), $daysInMonth);
$totalHorasMes = 0;

for ($d = $startDay; $d <= $daysInMonth; $d++) {
    $fechaDia = sprintf('%04d-%02d-%02d', $year, $month, $d);
    // date('N'): 1 (para lunes) hasta 7 (para domingo)
    $diaSemanaN = intval(date('N', strtotime($fechaDia)));
    $tid = $diasValidos[$diaSemanaN] ?? 0;
    if ($tid > 0 && isset($turnosMap[$tid])) {
        $totalHorasMes += $turnosMap[$tid];
    }
}

// 6. Validar Máximo Mensual si estuviese configurado (BLOQUEO)
if ($maxMensual !== null && $totalHorasMes > $maxMensual) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'message' => "No se puede asignar esta pauta porque la proyección mensual ({$totalHorasMes}h) supera el máximo mensual permitido de {$maxMensual} horas.",
        'reglas'  => $reglas,
        'horas_mes' => $totalHorasMes
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 7. Evaluar Advertencias
$advertencia = null;
if ($tipoPersonal === 'ASISTENCIAL' && $targetMensual !== null) {
    if ($totalHorasMes < $targetMensual) {
        $faltan = $targetMensual - $totalHorasMes;
        $advertencia = "La proyección mensual de {$totalHorasMes}h no alcanza la meta de {$targetMensual}h (faltan {$faltan}h).";
    }
}

// 8. Persistencia Segura y Manejo de Histórico en Base de Datos
if ($dbConnected) {
    try {
        $pdo->beginTransaction();

        $future = $pdo->prepare('SELECT COUNT(*) FROM rrhh.Horario WITH (UPDLOCK, HOLDLOCK) WHERE PersonalID = ? AND FechaInicio > ? AND Estado = 1');
        $future->execute([$personalId, $fechaInicio]);
        if ((int)$future->fetchColumn() > 0) {
            $pdo->rollBack();
            apiError(409, 'Existe una pauta posterior. Revise las vigencias antes de cambiar el histórico.');
        }
        // Cerrar vigencia de pautas activas anteriores exactamente un día antes de la nueva vigencia
        // FechaFin = DATEADD(DAY, -1, :nueva_fecha_inicio)
        $stmtCierre = $pdo->prepare("
            UPDATE rrhh.Horario 
            SET FechaFin = DATEADD(DAY, -1, CAST(? AS DATE))
            WHERE PersonalID = ? 
              AND Estado = 1
              AND FechaInicio < CAST(? AS DATE)
              AND (FechaFin IS NULL OR FechaFin >= CAST(? AS DATE))
        ");
        $stmtCierre->execute([
            $fechaInicio,
            $personalId,
            $fechaInicio,
            $fechaInicio
        ]);

        $sameDate = $pdo->prepare('DELETE FROM rrhh.Horario WHERE PersonalID = ? AND FechaInicio = ?');
        $sameDate->execute([$personalId, $fechaInicio]);
        // Insertar la nueva pauta semanal
        $stmtIns = $pdo->prepare("
            INSERT INTO rrhh.Horario (PersonalID, TurnoID, DiaSemana, FechaInicio, Estado)
            VALUES (?, ?, ?, ?, 1)
        ");

        foreach ($diasValidos as $diaNum => $tId) {
            if ($diaNum >= 1 && $diaNum <= 7 && $tId > 0) {
                $stmtIns->execute([
                    $personalId,
                    $tId,
                    $diaNum,
                    $fechaInicio
                ]);
            }
        }

        $pdo->commit();

        // Sincronizar de inmediato el snapshot local de resiliencia
        try {
            require_once __DIR__ . '/../../includes/SnapshotStore.php';
            SnapshotStore::syncAllFromDatabase($pdo);
        } catch (Throwable $t) {}
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'ok'      => false,
            'message' => 'Error al guardar la pauta en la base de datos: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Respuesta exitosa al cliente
echo json_encode([
    'ok'                 => true,
    'message'            => 'Pauta de horario semanal asignada y validada correctamente.',
    'advertencia'        => $advertencia,
    'resumen'            => [
        'personal_id'         => $personalId,
        'trabajador'          => $trabajadorNombre,
        'regimen'             => $tipoPersonal,
        'fecha_inicio'        => $fechaInicio,
        'horas_semanales'     => $totalHorasSemana,
        'horas_mes_proyect'   => $totalHorasMes,
        'meta_mensual'        => $targetMensual,
        'max_semanal'         => $maxSemanal,
        'max_mensual'         => $maxMensual,
        'dias_mes_calculados' => ($daysInMonth - $startDay + 1)
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
