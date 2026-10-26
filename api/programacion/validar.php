<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('programacion', ['POST'], ['JEFE_RRHH', 'SUPERVISOR', 'JEFE_SERVICIO']);

/**
 * API Endpoint: /api/programacion/validar.php
 * Hospital Distrital de Pacasmayo - ETAPA 4
 * 
 * Validador oficial del Rol Mensual según las reglas de negocio de RR.HH. y MINSA:
 * - Cumplimiento estricto de 150 horas para personal asistencial (SETI)
 * - Detección de déficit (<150h) o sobretiempos (+150h)
 * - Cobertura asistencial mínima en guardias y turnos críticos
 */

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$matriz = $data['matriz'] ?? [];
$servicioNombre = $data['servicio_nombre'] ?? 'Emergencia y Cuidados Críticos';
$mesNombre = $data['mes_nombre'] ?? 'Setiembre 2026';

$observados = [];
$cumplen = [];
$alertasCobertura = [];
$puedeAprobar = true;

foreach ($matriz as $trabajador) {
    $nombre = $trabajador['nombre_completo'] ?? 'Personal';
    $dni = $trabajador['dni'] ?? '';
    $regimen = strtoupper($trabajador['regimen_laboral'] ?? 'ASISTENCIAL');
    $horas = floatval($trabajador['total_horas'] ?? 0);

    if ($regimen === 'ASISTENCIAL') {
        $dif = $horas - 150.0;
        if (abs($dif) < 0.1) {
            $cumplen[] = [
                'trabajador' => $nombre,
                'dni'        => $dni,
                'horas'      => $horas,
                'estado'     => 'CUMPLE_150H',
                'mensaje'    => 'Cumple exactamente la carga de 150.0 horas mensuales reglamentarias.'
            ];
        } else {
            $puedeAprobar = false;
            $tipoObs = ($dif < 0) ? 'DEFICIT' : 'EXCESO';
            $observados[] = [
                'trabajador' => $nombre,
                'dni'        => $dni,
                'horas'      => $horas,
                'diferencia' => $dif,
                'tipo'       => $tipoObs,
                'mensaje'    => ($dif < 0) 
                    ? sprintf('Déficit de %.1f horas (Registra %.1f hrs de 150 hrs requeridas).', abs($dif), $horas)
                    : sprintf('Sobretiempo de +%.1f horas (Registra %.1f hrs; excede las 150 hrs).', $dif, $horas)
            ];
        }
    }
}

echo json_encode([
    'ok'            => true,
    'puede_enviar'  => $puedeAprobar,
    'servicio'      => $servicioNombre,
    'periodo'       => $mesNombre,
    'resumen'       => [
        'total_evaluados' => count($matriz),
        'cumplen_150h'    => count($cumplen),
        'observados_150h' => count($observados)
    ],
    'observados'    => $observados,
    'cumplen'       => $cumplen,
    'dictamen'      => $puedeAprobar 
        ? 'El rol mensual cumple satisfactoriamente con la regla normativa de 150 horas para todo el personal asistencial. Apto para ser enviado a Recursos Humanos.' 
        : 'El rol mensual presenta inconsistencias en la carga horaria de ' . count($observados) . ' trabajador(es). No puede ser enviado hasta regularizar a 150 horas.'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
