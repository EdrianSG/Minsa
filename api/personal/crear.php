<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('personal', ['POST'], ['JEFE_RRHH']);

/**
 * API Endpoint: /api/personal/crear.php
 * Registra un nuevo trabajador en el Hospital Distrital de Pacasmayo
 * 
 * Flujo Transaccional en SQL Server:
 * 1. Inserta en core.Persona (identidad, datos de contacto)
 * 2. Inserta en rrhh.Personal (código empleado, fecha ingreso)
 * 3. Inserta en rrhh.AsignacionPersonal (área, servicio, cargo, profesión)
 * 4. Inserta en rrhh.Contrato (tipo de contrato, número, fechas)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

// Validaciones requeridas mínimas
$nombres         = trim($data['nombres'] ?? '');
$apellidoPaterno = trim($data['apellido_paterno'] ?? '');
$apellidoMaterno = trim($data['apellido_materno'] ?? '');
$dni             = trim($data['dni'] ?? '');
$tipoDocumentoID = intval($data['tipo_documento_id'] ?? 4); // 4 = DNI
$sexoID          = !empty($data['sexo_id']) ? intval($data['sexo_id']) : null;
$fechaNacimiento = !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null;
$celular         = trim($data['celular'] ?? '');
$correo          = trim($data['correo'] ?? '');
$telefono        = trim($data['telefono'] ?? '');

$codigoEmpleado  = trim($data['codigo_empleado'] ?? '');
$fechaIngreso    = !empty($data['fecha_ingreso']) ? $data['fecha_ingreso'] : date('Y-m-d');

$areaID          = intval($data['area_id'] ?? 0);
$servicioID      = !empty($data['servicio_id']) ? intval($data['servicio_id']) : null;
$cargoID         = intval($data['cargo_id'] ?? 0);
$profesionID     = !empty($data['profesion_id']) ? intval($data['profesion_id']) : null;

$tipoContratoID  = intval($data['tipo_contrato_id'] ?? 1); // 1 = CAS
$numeroContrato  = trim($data['numero_contrato'] ?? '');
$contratoInicio  = !empty($data['contrato_inicio']) ? $data['contrato_inicio'] : $fechaIngreso;
$contratoFin     = !empty($data['contrato_fin']) ? $data['contrato_fin'] : null;

// Validación de campos obligatorios
if ($nombres === '' || $apellidoPaterno === '' || $dni === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Los nombres, apellido paterno y número de documento son obligatorios.'
    ]);
    exit;
}

if ($areaID <= 0 || $cargoID <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Debe seleccionar un Área y un Cargo institucional para el trabajador.'
    ]);
    exit;
}

$pdo = Database::getConnection();

if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay conexión activa con la base de datos SQL Server.'
    ]);
    exit;
}

try {
    // 1. Verificar si ya existe el DNI
    $stmtCheckDni = $pdo->prepare("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = ?");
    $stmtCheckDni->execute([$dni]);
    if ($stmtCheckDni->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => "El número de documento '$dni' ya se encuentra registrado en el sistema."
        ]);
        exit;
    }

    // 2. Si no se especificó código de empleado, generar el correlativo
    if ($codigoEmpleado === '') {
        $stmtMax = $pdo->query("SELECT MAX(CAST(SUBSTRING(CodigoEmpleado, 5, 10) AS INT)) FROM rrhh.Personal WHERE CodigoEmpleado LIKE 'EMP-%'");
        $maxNum = $stmtMax->fetchColumn();
        $nextNum = ($maxNum ? intval($maxNum) + 1 : 101);
        $codigoEmpleado = 'EMP-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    } else {
        // Verificar unicidad de código de empleado
        $stmtCheckCod = $pdo->prepare("SELECT PersonalID FROM rrhh.Personal WHERE CodigoEmpleado = ?");
        $stmtCheckCod->execute([$codigoEmpleado]);
        if ($stmtCheckCod->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => "El código de empleado '$codigoEmpleado' ya está en uso."
            ]);
            exit;
        }
    }

    if ($numeroContrato === '') {
        $numeroContrato = 'CONTRATO-' . date('Y') . '-' . substr($dni, -4);
    }

    // Iniciar transacción atómica
    $pdo->beginTransaction();

    // Paso A: Insertar en core.Persona
    $sqlPersona = "
        INSERT INTO core.Persona (
            TipoDocumentoID, NumeroDocumento, Nombres, ApellidoPaterno, ApellidoMaterno,
            FechaNacimiento, SexoID, Celular, Correo, Telefono,
            FechaRegistro, Estado
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            GETDATE(), 1
        )
    ";
    $stmtP = $pdo->prepare($sqlPersona);
    $stmtP->execute([
        $tipoDocumentoID,
        $dni,
        $nombres,
        $apellidoPaterno,
        $apellidoMaterno !== '' ? $apellidoMaterno : null,
        $fechaNacimiento,
        $sexoID,
        $celular !== '' ? $celular : null,
        $correo !== '' ? $correo : null,
        $telefono !== '' ? $telefono : null
    ]);
    $personaID = $pdo->lastInsertId();

    // Paso B: Insertar en rrhh.Personal
    $sqlPersonal = "
        INSERT INTO rrhh.Personal (
            PersonaID, CodigoEmpleado, FechaIngreso, FechaCese, Estado
        ) VALUES (
            ?, ?, ?, NULL, 1
        )
    ";
    $stmtPer = $pdo->prepare($sqlPersonal);
    $stmtPer->execute([
        $personaID,
        $codigoEmpleado,
        $fechaIngreso
    ]);
    $personalID = $pdo->lastInsertId();

    // Paso C: Insertar en rrhh.AsignacionPersonal
    $sqlAsig = "
        INSERT INTO rrhh.AsignacionPersonal (
            PersonalID, AreaID, ServicioID, CargoID, ProfesionID,
            FechaInicio, EsActual, Estado
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, 1, 1
        )
    ";
    $stmtAsig = $pdo->prepare($sqlAsig);
    $stmtAsig->execute([
        $personalID,
        $areaID,
        $servicioID,
        $cargoID,
        $profesionID,
        $fechaIngreso
    ]);

    // Paso D: Insertar en rrhh.Contrato
    $sqlContrato = "
        INSERT INTO rrhh.Contrato (
            PersonalID, TipoContratoID, NumeroContrato, FechaInicio, FechaFin, Estado
        ) VALUES (
            ?, ?, ?, ?, ?, 1
        )
    ";
    $stmtCont = $pdo->prepare($sqlContrato);
    $stmtCont->execute([
        $personalID,
        $tipoContratoID,
        $numeroContrato,
        $contratoInicio,
        $contratoFin
    ]);

    // Confirmar transacción
    $pdo->commit();

    // Sincronizar de inmediato el snapshot local de resiliencia
    try {
        require_once __DIR__ . '/../../includes/SnapshotStore.php';
        SnapshotStore::syncAllFromDatabase($pdo);
    } catch (Throwable $t) {}

    echo json_encode([
        'success' => true,
        'message' => '¡Trabajador registrado exitosamente en el sistema!',
        'data' => [
            'personal_id'     => $personalID,
            'persona_id'      => $personaID,
            'codigo_empleado' => $codigoEmpleado,
            'nombre_completo' => "$apellidoPaterno " . ($apellidoMaterno ? "$apellidoMaterno, " : ", ") . "$nombres"
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el nuevo trabajador: ' . $e->getMessage()
    ]);
}
