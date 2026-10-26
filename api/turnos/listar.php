<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('horarios', ['GET'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/turnos/listar.php
 * Hospital Distrital de Pacasmayo - ETAPA 3
 * 
 * Entrega el catálogo parametrizado de turnos oficiales del hospital
 * Reutiliza y extiende: rrhh.Turno
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getConnection();
$dbConnected = ($pdo !== null);
$turnos = [];

if ($dbConnected) {
    try {
        // Consultar rrhh.Turno de la base institucional
        $stmt = $pdo->query("SELECT TurnoID, Nombre, HoraInicio, HoraFin, Estado FROM rrhh.Turno ORDER BY TurnoID");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            foreach ($rows as $r) {
                $inicio = substr($r['HoraInicio'], 0, 5);
                $fin = substr($r['HoraFin'], 0, 5);
                
                // Calcular horas y si cruza medianoche
                $time1 = strtotime($inicio);
                $time2 = strtotime($fin);
                $cruza = ($time2 < $time1);
                
                if ($cruza) {
                    $diffHours = (strtotime('24:00') - $time1 + $time2 - strtotime('00:00')) / 3600;
                } else {
                    $diffHours = ($time2 - $time1) / 3600;
                }

                if (stripos($r['Nombre'], 'Mañana') !== false) {
                    $codigo = 'M';
                } elseif (stripos($r['Nombre'], 'Tarde') !== false) {
                    $codigo = 'T';
                } elseif (stripos($r['Nombre'], 'Nocturna') !== false || stripos($r['Nombre'], 'Noche') !== false) {
                    $codigo = 'N';
                } elseif (stripos($r['Nombre'], 'Diurna') !== false) {
                    $codigo = 'G';
                } elseif (stripos($r['Nombre'], 'Admin') !== false) {
                    $codigo = 'ADM';
                } elseif (stripos($r['Nombre'], 'Retén') !== false) {
                    $codigo = 'RET';
                } else {
                    $codigo = strtoupper(mb_substr($r['Nombre'], 0, 3, 'UTF-8'));
                }
                $esGuardia = ($diffHours >= 11.5) ? 1 : 0;

                $turnos[] = [
                    'turno_id'          => intval($r['TurnoID']),
                    'codigo'            => $codigo,
                    'nombre'            => $r['Nombre'],
                    'descripcion'       => "Turno institucional de $inicio a $fin",
                    'hora_inicio'       => $inicio,
                    'hora_fin'          => $fin,
                    'horas_computables' => round($diffHours, 2),
                    'cruza_medianoche'  => $cruza ? 1 : 0,
                    'es_guardia'        => $esGuardia,
                    'color'             => $cruza ? '#7c3aed' : ($diffHours >= 11 ? '#0d9488' : '#0284c7'),
                    'estado'            => intval($r['Estado'])
                ];
            }
            if (!empty($turnos)) {
                require_once __DIR__ . '/../../includes/SnapshotStore.php';
                SnapshotStore::set('turnos_catalogo_oficial', $turnos, ['source' => 'SQL_SERVER_LIVE']);
            }
        }
    } catch (PDOException $e) {
        $dbConnected = false;
    }
}

// Resiliencia local: si la BD está offline o no retornó datos, usar el Snapshot de datos reales
if (empty($turnos)) {
    require_once __DIR__ . '/../../includes/SnapshotStore.php';
    if (SnapshotStore::has('turnos_catalogo_oficial')) {
        $snap = SnapshotStore::get('turnos_catalogo_oficial');
        if (!empty($snap['data']) && is_array($snap['data'])) {
            $turnos = $snap['data'];
        }
    }
}

// Catálogo institucional parametrizado oficial SOLO si nunca hubo conexión
if (empty($turnos)) {
    $turnos = [
        [
            'turno_id'          => 1,
            'codigo'            => 'M',
            'nombre'            => 'Mañana Asistencial',
            'descripcion'       => 'Turno rotativo diurno de 6 horas para personal médico y asistencial',
            'hora_inicio'       => '08:00',
            'hora_fin'          => '14:00',
            'horas_computables' => 6.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 0,
            'color'             => '#0284c7',
            'tipo_regimen'      => 'ASISTENCIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 2,
            'codigo'            => 'T',
            'nombre'            => 'Tarde Asistencial',
            'descripcion'       => 'Turno rotativo vespertino de 6 horas para personal médico y asistencial',
            'hora_inicio'       => '14:00',
            'hora_fin'          => '20:00',
            'horas_computables' => 6.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 0,
            'color'             => '#d97706',
            'tipo_regimen'      => 'ASISTENCIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 3,
            'codigo'            => 'N',
            'nombre'            => 'Noche Asistencial',
            'descripcion'       => 'Turno nocturno de 12 horas. Inicia a las 20:00 y finaliza a las 08:00 del día siguiente',
            'hora_inicio'       => '20:00',
            'hora_fin'          => '08:00',
            'horas_computables' => 12.00,
            'cruza_medianoche'  => 1,
            'es_guardia'        => 1,
            'color'             => '#7c3aed',
            'tipo_regimen'      => 'ASISTENCIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 4,
            'codigo'            => 'GD',
            'nombre'            => 'Guardia Diurna Hospitalaria',
            'descripcion'       => 'Guardia de 12 horas continuas para servicios críticos y emergencia (08:00 a 20:00)',
            'hora_inicio'       => '08:00',
            'hora_fin'          => '20:00',
            'horas_computables' => 12.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 1,
            'color'             => '#0d9488',
            'tipo_regimen'      => 'ASISTENCIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 5,
            'codigo'            => 'GN',
            'nombre'            => 'Guardia Nocturna Hospitalaria',
            'descripcion'       => 'Guardia nocturna continuada de 12 horas con cruce de medianoche',
            'hora_inicio'       => '20:00',
            'hora_fin'          => '08:00',
            'horas_computables' => 12.00,
            'cruza_medianoche'  => 1,
            'es_guardia'        => 1,
            'color'             => '#4f46e5',
            'tipo_regimen'      => 'ASISTENCIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 6,
            'codigo'            => 'ADM',
            'nombre'            => 'Jornada Administrativa Regular',
            'descripcion'       => 'Horario continuo fijo de lunes a viernes para personal administrativo',
            'hora_inicio'       => '07:30',
            'hora_fin'          => '15:30',
            'horas_computables' => 8.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 0,
            'color'             => '#475569',
            'tipo_regimen'      => 'ADMINISTRATIVO',
            'estado'            => 1
        ],
        [
            'turno_id'          => 7,
            'codigo'            => 'VAC',
            'nombre'            => 'Vacaciones Programadas',
            'descripcion'       => 'Día de descanso físico vacacional según rol aprobado (computa 6h referenciales)',
            'hora_inicio'       => '08:00',
            'hora_fin'          => '14:00',
            'horas_computables' => 6.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 0,
            'color'             => '#0891b2',
            'tipo_regimen'      => 'ESPECIAL',
            'estado'            => 1
        ],
        [
            'turno_id'          => 8,
            'codigo'            => 'LIB',
            'nombre'            => 'Día Libre / Descanso Semanal',
            'descripcion'       => 'Descanso post guardia o día no laborable programado',
            'hora_inicio'       => '00:00',
            'hora_fin'          => '00:00',
            'horas_computables' => 0.00,
            'cruza_medianoche'  => 0,
            'es_guardia'        => 0,
            'color'             => '#94a3b8',
            'tipo_regimen'      => 'ESPECIAL',
            'estado'            => 1
        ]
    ];
}

// Estadísticas de parametrización
$stats = [
    'total_turnos'     => count($turnos),
    'turnos_6h'        => count(array_filter($turnos, fn($t) => floatval($t['horas_computables']) === 6.00)),
    'guardias_12h'     => count(array_filter($turnos, fn($t) => floatval($t['horas_computables']) === 12.00 || intval($t['es_guardia']) === 1)),
    'cruza_medianoche' => count(array_filter($turnos, fn($t) => intval($t['cruza_medianoche']) === 1)),
    'activos'          => count(array_filter($turnos, fn($t) => intval($t['estado']) === 1))
];

echo json_encode([
    'ok'           => true,
    'estadisticas' => $stats,
    'data'         => $turnos,
    'conexion_sql' => $dbConnected
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
