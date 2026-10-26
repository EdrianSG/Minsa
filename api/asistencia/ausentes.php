<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/asistencia/ausentes.php
 * Hospital Distrital de Pacasmayo
 * 
 * Endpoint para la consulta "¿Quién faltó hoy?":
 * - Identifica al personal que no ha registrado ingreso en el biométrico o incurrió en falta administrativa (>30m) desde rrhh.Asistencia.
 * - Clasifica la criticidad de la ausencia según el servicio asistencial (Emergencia, UCI, Centro Obstétrico, etc.).
 * - Entrega los datos directos de contacto para coordinación de reemplazo.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!apiValidDate($fecha)) apiError(400, 'Fecha inválida. Formato YYYY-MM-DD requerido.');

$servicioId = isset($_GET['servicio_id']) && $_GET['servicio_id'] !== '' ? intval($_GET['servicio_id']) : null;

// Restricción por servicio según el rol del usuario
$userAllowedServ = Auth::getAllowedServiceId();
if ($userAllowedServ !== null) {
    if ($servicioId === null) {
        $servicioId = $userAllowedServ;
    } elseif ($servicioId !== $userAllowedServ) {
        apiError(403, 'No tiene autorización para consultar ausencias de otro servicio.');
    }
}

// Servicios críticos que demandan reemplazo asistencial urgente
$serviciosCriticos = [
    'EMERGENCIA Y CUIDADOS CRÍTICOS',
    'UNIDAD DE CUIDADOS INTENSIVOS',
    'UNIDAD DE CUIDADOS INTENSIVOS (UCI)',
    'HOSPITALIZACIÓN MEDICINA Y CIRUGÍA',
    'HOSPITALIZACIÓN MEDICINA',
    'CENTRO QUIRÚRGICO',
    'CENTRO OBSTÉTRICO',
    'PEDIATRÍA Y NEONATOLOGÍA'
];

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$ausentes = [];

if ($dbConnected) {
    try {
        $diaSemana = (int)date('N', strtotime($fecha)); // 1=Lunes..7=Domingo
        $whereSql = "WHERE CAST(a.Fecha AS DATE) = :fecha AND a.Estado LIKE '%Falta%'";
        $params = [
            ':fecha' => $fecha,
            ':diaSemana' => $diaSemana
        ];

        if ($servicioId !== null && $servicioId > 0) {
            $whereSql .= " AND s.ServicioID = :servId";
            $params[':servId'] = $servicioId;
        }

        $sql = "
            SELECT 
                p.PersonalID AS personal_id,
                per.NumeroDocumento AS dni,
                CONCAT(per.ApellidoPaterno, ' ', ISNULL(per.ApellidoMaterno, ''), ', ', per.Nombres) AS nombre_completo,
                ISNULL(per.Celular, per.Telefono) AS telefono,
                per.Correo AS correo,
                ISNULL(c.Nombre, 'Personal Hospitalario') AS cargo,
                ISNULL(s.ServicioID, 7) AS servicio_id,
                ISNULL(s.Nombre, 'Servicio Hospitalario') AS servicio_nombre,
                ISNULL(tc.Nombre, 'CAS') AS condicion_laboral,
                ISNULL(t.Nombre, 'Turno Asignado') AS turno_nombre,
                CONVERT(VARCHAR(5), t.HoraInicio, 108) AS hora_prog_inicio,
                CONVERT(VARCHAR(5), t.HoraFin, 108) AS hora_prog_fin,
                a.Estado AS tipo_ausencia,
                CONVERT(VARCHAR(5), a.HoraEntrada, 108) AS hora_marcada,
                'SIN_JUSTIFICAR' AS justificacion_estado,
                a.Observacion AS motivo
            FROM rrhh.Asistencia a
            INNER JOIN rrhh.Personal p ON a.PersonalID = p.PersonalID
            INNER JOIN core.Persona per ON p.PersonaID = per.PersonaID
            LEFT JOIN rrhh.AsignacionPersonal ap ON p.PersonalID = ap.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1
            LEFT JOIN organizacion.Servicio s ON ap.ServicioID = s.ServicioID
            LEFT JOIN rrhh.Cargo c ON ap.CargoID = c.CargoID
            LEFT JOIN rrhh.Contrato con ON p.PersonalID = con.PersonalID AND con.Estado = 1
            LEFT JOIN rrhh.TipoContrato tc ON con.TipoContratoID = tc.TipoContratoID
            LEFT JOIN rrhh.Horario h ON p.PersonalID = h.PersonalID AND h.DiaSemana = :diaSemana AND h.Estado = 1
            LEFT JOIN rrhh.Turno t ON h.TurnoID = t.TurnoID
            {$whereSql}
            ORDER BY per.ApellidoPaterno, per.ApellidoMaterno, per.Nombres
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $servUpper = strtoupper(trim((string)$r['servicio_nombre']));
            $esCritico = false;
            foreach ($serviciosCriticos as $crit) {
                if (strpos($servUpper, $crit) !== false) {
                    $esCritico = true;
                    break;
                }
            }

            $stUpper = strtoupper(trim((string)$r['tipo_ausencia']));
            $esFaltaAdmin = (strpos($stUpper, 'ADMINISTRATIVA') !== false);

            $ausentes[] = [
                'personal_id'          => intval($r['personal_id']),
                'dni'                  => $r['dni'],
                'nombre_completo'      => $r['nombre_completo'],
                'telefono'             => $r['telefono'] ?: 'No registrado',
                'correo'               => $r['correo'] ?: 'No registrado',
                'cargo'                => $r['cargo'],
                'servicio_id'          => intval($r['servicio_id']),
                'servicio_nombre'      => $r['servicio_nombre'],
                'condicion_laboral'    => (stripos($r['condicion_laboral'], 'Nombrad') !== false ? 'NOMBRADO' : 'CAS'),
                'turno_nombre'         => $r['turno_nombre'],
                'hora_prog_inicio'     => $r['hora_prog_inicio'] ?: '08:00',
                'hora_prog_fin'        => $r['hora_prog_fin'] ?: '14:00',
                'tipo_ausencia'        => $esFaltaAdmin ? 'FALTA_ADMINISTRATIVA' : 'FALTA_TOTAL',
                'hora_marcada'         => $r['hora_marcada'],
                'justificacion_estado' => 'SIN_JUSTIFICAR',
                'motivo'               => $r['motivo'] ?: 'Inasistencia sin marcación en biométrico.',
                'criticidad'           => $esCritico ? 'ALTA_URGENTE' : ($esFaltaAdmin ? 'MEDIA' : 'BAJA'),
                'requiere_reemplazo'   => $esCritico
            ];
        }
    } catch (Exception $e) {
        error_log("Error en api/asistencia/ausentes.php: " . $e->getMessage());
    }
}

$totalAltaCriticidad = count(array_filter($ausentes, function($a) { return $a['criticidad'] === 'ALTA_URGENTE'; }));
$totalRequierenReemplazo = count(array_filter($ausentes, function($a) { return $a['requiere_reemplazo']; }));

echo json_encode([
    'ok'                      => true,
    'fecha'                   => $fecha,
    'total_ausentes'          => count($ausentes),
    'ausencias_criticas'      => $totalAltaCriticidad,
    'requieren_reemplazo_urg' => $totalRequierenReemplazo,
    'ausentes'                => $ausentes
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
