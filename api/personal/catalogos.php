<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('personal', ['GET'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/personal/catalogos.php
 * Provee las opciones de catálogo activas para el formulario de registro de personal:
 * - Tipos de documento
 * - Sexos
 * - Áreas y Servicios organizacionales
 * - Cargos y Profesiones
 * - Tipos de Contrato
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__.'/../../includes/PmyoCatalog.php';

$pdo = Database::getConnection();

if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay conexión con la base de datos SQL Server.'
    ]);
    exit;
}

try {
    // 1. Tipos de Documento
    $stmtDoc = $pdo->query("SELECT TipoDocumentoID AS id, Codigo AS codigo, Nombre AS nombre, Activo AS activo FROM catalogo.TipoDocumento ORDER BY TipoDocumentoID");
    $tiposDocumento = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // 2. Sexos
    $stmtSexo = $pdo->query("SELECT SexoID AS id, Codigo AS codigo, Nombre AS nombre, Activo AS activo FROM catalogo.Sexo ORDER BY SexoID");
    $sexos = $stmtSexo->fetchAll(PDO::FETCH_ASSOC);

    // 3. Áreas
    $stmtAreas = $pdo->query("SELECT AreaID AS id, DepartamentoID AS departamento_id, Nombre AS nombre, Activo AS activo FROM organizacion.Area ORDER BY Nombre");
    $areas = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Servicios
    $stmtServ = $pdo->query("SELECT ServicioID AS id, Nombre AS nombre, AreaID AS area_id, Activo AS activo FROM organizacion.Servicio ORDER BY Nombre");
    $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);

    // 5. Cargos
    $stmtCargos = $pdo->query("SELECT CargoID AS id, Nombre AS nombre, Activo AS activo FROM rrhh.Cargo ORDER BY Nombre");
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

    // 6. Profesiones
    $stmtProf = $pdo->query("SELECT ProfesionID AS id, Codigo AS codigo, Nombre AS nombre, Activo AS activo FROM rrhh.Profesion ORDER BY Nombre");
    $profesiones = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    // 7. Tipos de Contrato
    $stmtCont = $pdo->query("SELECT TipoContratoID AS id, Nombre AS nombre, Activo AS activo FROM rrhh.TipoContrato ORDER BY TipoContratoID");
    $tiposContrato = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    $especialidades = $pdo->query("SELECT EspecialidadID AS id, ProfesionID AS profesion_id, Nombre AS nombre, Activo AS activo FROM rrhh.Especialidad ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);
    $departamentos = $pdo->query("SELECT DepartamentoID AS id, SedeID AS sede_id, Nombre AS nombre, Activo AS activo FROM organizacion.Departamento ORDER BY Nombre")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => PmyoCatalog::enrich([
            'tipos_documento' => $tiposDocumento,
            'sexos'           => $sexos,
            'areas'           => $areas,
            'servicios'       => $servicios,
            'cargos'          => $cargos,
            'profesiones'     => $profesiones,
            'tipos_contrato'  => $tiposContrato,
            'especialidades' => $especialidades,
            'departamentos' => $departamentos
        ])
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar catálogos: ' . $e->getMessage()
    ]);
}
