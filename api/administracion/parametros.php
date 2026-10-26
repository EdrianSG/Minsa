<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET', 'POST'], []);

/**
 * API Endpoint: /api/administracion/parametros.php
 * Hospital Distrital de Pacasmayo - ETAPA 8
 * 
 * Parámetros Normativos del Sistema Hospitalario y Políticas de Asistencia
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/auditoria.php';

$method = $_SERVER['REQUEST_METHOD'];
$currentUser = Auth::getCurrentUser();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['sistema_parametros'])) {
    $_SESSION['sistema_parametros'] = [
        'tolerancia_entrada_minutos' => 5,
        'tardanza_maxima_minutos'    => 30,
        'regla_asistencial_horas'    => 150,
        'dias_maximos_regularizar'   => 3,
        'citt_obligatorio_salud'     => true,
        'biometrico_modo'            => 'IMPORTACION_INDEPENDIENTE',
        'hospital_nombre'            => 'Hospital Distrital de Pacasmayo',
        'red_salud'                  => 'Red Integrada de Salud Pacasmayo',
        'gerencia_salud'             => 'Gerencia Regional de Salud La Libertad',
        'actualizado_por'            => 'Lic. María Rodríguez Santillán',
        'fecha_actualizacion'        => '2026-09-21 08:00:00'
    ];
}

if ($method === 'GET') {
    echo json_encode([
        'ok'         => true,
        'parametros' => $_SESSION['sistema_parametros']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $tolerancia = max(0, min(60, intval($input['tolerancia_entrada_minutos'] ?? 5)));
    $tardanzaMax = max(15, min(120, intval($input['tardanza_maxima_minutos'] ?? 30)));
    $reglaHoras = max(100, min(200, intval($input['regla_asistencial_horas'] ?? 150)));
    $diasReg = max(1, min(30, intval($input['dias_maximos_regularizar'] ?? 3)));
    $cittOblig = isset($input['citt_obligatorio_salud']) ? (bool)$input['citt_obligatorio_salud'] : true;
    $biometrico = trim($input['biometrico_modo'] ?? 'IMPORTACION_INDEPENDIENTE');

    $_SESSION['sistema_parametros'] = array_merge($_SESSION['sistema_parametros'], [
        'tolerancia_entrada_minutos' => $tolerancia,
        'tardanza_maxima_minutos'    => $tardanzaMax,
        'regla_asistencial_horas'    => $reglaHoras,
        'dias_maximos_regularizar'   => $diasReg,
        'citt_obligatorio_salud'     => $cittOblig,
        'biometrico_modo'            => $biometrico,
        'actualizado_por'            => $currentUser['nombre_completo'] ?? 'Administrador',
        'fecha_actualizacion'        => date('Y-m-d H:i:s')
    ]);

    // Registrar en auditoría
    Auditoria::registrar(
        $currentUser['usuario_id'],
        'ADMINISTRACION',
        'UPDATE',
        'config.ParametrosSistema',
        '1',
        "Actualización de políticas institucionales: Tolerancia = {$tolerancia}m, Tardanza máx = {$tardanzaMax}m, Regla asistencial = {$reglaHoras}h"
    );

    echo json_encode([
        'ok'         => true,
        'mensaje'    => 'Parámetros institucionales guardados correctamente.',
        'parametros' => $_SESSION['sistema_parametros']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
