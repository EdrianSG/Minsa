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
    $stmtDoc = $pdo->query("SELECT TipoDocumentoID AS id, Codigo AS codigo, Nombre AS nombre FROM catalogo.TipoDocumento WHERE Estado = 1 ORDER BY TipoDocumentoID");
    $tiposDocumento = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // 2. Sexos
    $stmtSexo = $pdo->query("SELECT SexoID AS id, Codigo AS codigo, Nombre AS nombre FROM catalogo.Sexo WHERE Estado = 1 ORDER BY SexoID");
    $sexos = $stmtSexo->fetchAll(PDO::FETCH_ASSOC);

    // 3. Áreas
    $stmtAreas = $pdo->query("SELECT AreaID AS id, Nombre AS nombre FROM organizacion.Area WHERE Estado = 1 ORDER BY Nombre");
    $areas = $stmtAreas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Servicios
    $stmtServ = $pdo->query("SELECT ServicioID AS id, Nombre AS nombre, AreaID AS area_id FROM organizacion.Servicio WHERE Estado = 1 ORDER BY Nombre");
    $servicios = $stmtServ->fetchAll(PDO::FETCH_ASSOC);

    // 5. Cargos
    $stmtCargos = $pdo->query("SELECT CargoID AS id, Nombre AS nombre FROM rrhh.Cargo WHERE Estado = 1 ORDER BY Nombre");
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

    // 6. Profesiones
    $stmtProf = $pdo->query("SELECT ProfesionID AS id, Nombre AS nombre FROM rrhh.Profesion WHERE Estado = 1 ORDER BY Nombre");
    $profesiones = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

    // 7. Tipos de Contrato
    $stmtCont = $pdo->query("SELECT TipoContratoID AS id, Nombre AS nombre FROM rrhh.TipoContrato WHERE Estado = 1 ORDER BY TipoContratoID");
    $tiposContrato = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    // 8. Sugerir siguiente código de empleado
    $stmtMaxEmp = $pdo->query("SELECT MAX(CAST(SUBSTRING(CodigoEmpleado, 5, 10) AS INT)) FROM rrhh.Personal WHERE CodigoEmpleado LIKE 'EMP-%'");
    $maxNum = $stmtMaxEmp->fetchColumn();
    $nextNum = ($maxNum ? intval($maxNum) + 1 : 101);
    $suggestedCode = 'EMP-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'data' => [
            'tipos_documento' => $tiposDocumento,
            'sexos'           => $sexos,
            'areas'           => $areas,
            'servicios'       => $servicios,
            'cargos'          => $cargos,
            'profesiones'     => $profesiones,
            'tipos_contrato'  => $tiposContrato,
            'suggested_code'  => $suggestedCode
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar catálogos: ' . $e->getMessage()
    ]);
}
