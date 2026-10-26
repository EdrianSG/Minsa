<?php
/**
 * API Endpoint: /api/asistencia/biometrico/marcaciones.php
 * Hospital Distrital de Pacasmayo - Fase 2
 * 
 * Permite explorar y consultar las marcaciones biométricas importadas con filtros y paginación.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/api_bootstrap.php';
apiGuard('asistencia', ['GET'], ['JEFE_RRHH', 'OPERADOR_ASISTENCIA']);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';

$pdo = Database::getConnection();
if ($pdo === null) {
    apiError(503, "SQL Server no disponible.");
}

$importacionId = isset($_GET['importacion_id']) && $_GET['importacion_id'] !== '' ? intval($_GET['importacion_id']) : null;
$filtroVinculacion = isset($_GET['estado_vinculacion']) ? trim($_GET['estado_vinculacion']) : '';
$filtroConsecutiva = isset($_GET['solo_consecutivas']) ? intval($_GET['solo_consecutivas']) : 0;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(10, min(100, intval($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

try {
    $where = ["1=1"];
    $params = [];
    $allowedService = Auth::getAllowedServiceId();
    if ($allowedService !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID = m.PersonalID AND ap.EsActual = 1 AND ap.Estado = 1 AND ap.ServicioID = :servicioAutorizado)';
        $params[':servicioAutorizado'] = $allowedService;
    }

    if ($importacionId !== null && $importacionId > 0) {
        $where[] = "m.ImportacionID = :impId";
        $params[':impId'] = $importacionId;
    }

    if ($filtroVinculacion !== '' && $filtroVinculacion !== 'TODOS') {
        $where[] = "m.EstadoVinculacion = :estVinc";
        $params[':estVinc'] = $filtroVinculacion;
    }

    if ($filtroConsecutiva === 1) {
        $where[] = "m.EsConsecutiva = 1";
    }

    if ($busqueda !== '') {
        $where[] = "(m.BiostarUserID LIKE :busq OR m.BiostarUserName LIKE :busq OR m.DispositivoNombre LIKE :busq)";
        $params[':busq'] = "%{$busqueda}%";
    }

    $whereSql = implode(' AND ', $where);

    // Conteo total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM rrhh.MarcacionBiometrica m WHERE {$whereSql}");
    $stmtCount->execute($params);
    $total = intval($stmtCount->fetchColumn());

    // Registros paginados
    $sqlData = "
        SELECT 
            m.MarcacionID,
            m.ImportacionID,
            m.BiostarUserID,
            ISNULL(m.BiostarUserName, '(Sin nombre)') AS BiostarUserName,
            CONVERT(VARCHAR(19), m.FechaHora, 120) AS FechaHora,
            m.DispositivoID,
            m.DispositivoNombre,
            m.Evento,
            m.PersonalID,
            m.EstadoProcesamiento,
            m.EstadoVinculacion,
            m.EsConsecutiva,
            m.SegundosDiferencia,
            m.Advertencia
        FROM rrhh.MarcacionBiometrica m
        WHERE {$whereSql}
        ORDER BY m.FechaHora DESC, m.MarcacionID DESC
        OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY
    ";

    $stmtData = $pdo->prepare($sqlData);
    $stmtData->execute($params);
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'          => true,
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_paginas' => (int)ceil($total / $limit),
        'marcaciones' => $rows
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("Error al consultar marcaciones biométricas: " . $e->getMessage());
    apiError(500, "Error al consultar marcaciones: " . $e->getMessage());
}
