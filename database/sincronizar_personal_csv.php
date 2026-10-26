<?php
/**
 * Sincronización Institucional de Personal BioStar 2
 * Hospital Distrital de Pacasmayo
 *
 * Lee los usuarios únicos registrados en rrhh.MarcacionBiometrica,
 * crea o vincula sus registros en core.Persona, rrhh.Personal,
 * rrhh.AsignacionPersonal, rrhh.Contrato, rrhh.Horario y
 * actualiza el PersonalID y EstadoVinculacion en rrhh.MarcacionBiometrica.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function parseNombrePericial(string $raw): array {
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    $parts = explode(' ', $raw);
    $total = count($parts);

    if ($total <= 1) {
        $nom = mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
        return [$nom, $nom, ''];
    }

    if ($total === 2) {
        $paterno = mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
        $nombres = mb_convert_case($parts[1], MB_CASE_TITLE, 'UTF-8');
        return [$nombres, $paterno, ''];
    }

    $i = 0;
    $paternoParts = [];
    if ($i < $total && in_array(strtoupper($parts[$i]), ['DE', 'DEL', 'SAN', 'SANTA', 'VON'])) {
        $paternoParts[] = $parts[$i++];
        if ($i < $total && in_array(strtoupper($parts[$i]), ['LA', 'LOS', 'LAS'])) {
            $paternoParts[] = $parts[$i++];
        }
    }
    if ($i < $total) {
        $paternoParts[] = $parts[$i++];
    }
    $paterno = mb_convert_case(implode(' ', $paternoParts), MB_CASE_TITLE, 'UTF-8');

    $maternoParts = [];
    if ($i < $total && in_array(strtoupper($parts[$i]), ['DE', 'DEL', 'SAN', 'SANTA', 'VON'])) {
        $maternoParts[] = $parts[$i++];
        if ($i < $total && in_array(strtoupper($parts[$i]), ['LA', 'LOS', 'LAS'])) {
            $maternoParts[] = $parts[$i++];
        }
    }
    if ($i < $total) {
        $maternoParts[] = $parts[$i++];
    }
    $materno = mb_convert_case(implode(' ', $maternoParts), MB_CASE_TITLE, 'UTF-8');

    $nombresParts = [];
    while ($i < $total) {
        $nombresParts[] = $parts[$i++];
    }
    $nombres = mb_convert_case(implode(' ', $nombresParts), MB_CASE_TITLE, 'UTF-8');

    if (empty($nombres)) {
        $nombres = $materno;
        $materno = '';
    }

    return [$nombres, $paterno, $materno];
}

function sincronizarPersonalBiometrico(?callable $logger = null): array {
    $log = $logger ?? function(string $msg) { echo $msg . PHP_EOL; };

    $pdo = Database::getConnection();
    if (!$pdo) {
        throw new RuntimeException("No se pudo conectar a SQL Server.");
    }

    $log("=== SINCRONIZACION DE PERSONAL DESDE MARCACIONES BIOMETRICAS ===");

    // 1. Obtener catálogo de servicios y turnos
    $serviciosStmt = $pdo->query("SELECT ServicioID, AreaID, Nombre FROM organizacion.Servicio WHERE Estado = 1 ORDER BY ServicioID");
    $servicios = $serviciosStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($servicios)) {
        throw new RuntimeException("No hay servicios configurados en organizacion.Servicio.");
    }

    $cargosStmt = $pdo->query("SELECT CargoID, Nombre FROM rrhh.Cargo WHERE Estado = 1 ORDER BY CargoID");
    $cargos = $cargosStmt->fetchAll(PDO::FETCH_ASSOC);

    $profesionesStmt = $pdo->query("SELECT ProfesionID, Nombre FROM rrhh.Profesion WHERE Estado = 1 ORDER BY ProfesionID");
    $profesiones = $profesionesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener usuarios biométricos únicos
    $usersStmt = $pdo->query("
        SELECT 
            BiostarUserID, 
            BiostarUserName, 
            COUNT(*) as TotalMarcaciones,
            MIN(FechaHora) as PrimeraMarcacion,
            MAX(FechaHora) as UltimaMarcacion
        FROM rrhh.MarcacionBiometrica
        WHERE BiostarUserID != '53' AND BiostarUserID IS NOT NULL AND LEN(BiostarUserID) >= 7
        GROUP BY BiostarUserID, BiostarUserName
        ORDER BY BiostarUserID
    ");
    $bioUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    $log("Usuarios únicos encontrados en marcaciones: " . count($bioUsers));

    $totalSincronizados = 0;
    $totalNuevasPersonas = 0;
    $totalNuevosPersonal = 0;
    $totalVinculacionesActualizadas = 0;

    $stmtFindPersona = $pdo->prepare("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = ?");
    $stmtInsertPersona = $pdo->prepare("
        INSERT INTO core.Persona (
            TipoDocumentoID, NumeroDocumento, Nombres, ApellidoPaterno, ApellidoMaterno, FechaRegistro, Estado
        ) OUTPUT INSERTED.PersonaID VALUES (
            4, :dni, :nombres, :paterno, :materno, SYSDATETIME(), 1
        )
    ");

    $stmtFindPersonal = $pdo->prepare("SELECT PersonalID FROM rrhh.Personal WHERE PersonaID = ?");
    $stmtInsertPersonal = $pdo->prepare("
        INSERT INTO rrhh.Personal (
            PersonaID, CodigoEmpleado, FechaIngreso, Estado
        ) OUTPUT INSERTED.PersonalID VALUES (
            :personaId, :codEmp, '2021-01-01', 1
        )
    ");

    $stmtFindAsig = $pdo->prepare("SELECT AsignacionID FROM rrhh.AsignacionPersonal WHERE PersonalID = ? AND EsActual = 1");
    $stmtInsertAsig = $pdo->prepare("
        INSERT INTO rrhh.AsignacionPersonal (
            PersonalID, AreaID, ServicioID, CargoID, ProfesionID, FechaInicio, EsActual, Estado
        ) VALUES (
            :personalId, :areaId, :servicioId, :cargoId, :profesionId, '2021-01-01', 1, 1
        )
    ");

    $stmtFindContrato = $pdo->prepare("SELECT ContratoID FROM rrhh.Contrato WHERE PersonalID = ? AND Estado = 1");
    $stmtInsertContrato = $pdo->prepare("
        INSERT INTO rrhh.Contrato (
            PersonalID, TipoContratoID, NumeroContrato, FechaInicio, Estado
        ) VALUES (
            :personalId, 1, :numContrato, '2021-01-01', 1
        )
    ");

    $stmtFindHorario = $pdo->prepare("SELECT COUNT(*) as cnt FROM rrhh.Horario WHERE PersonalID = ? AND Estado = 1");
    $stmtInsertHorario = $pdo->prepare("
        INSERT INTO rrhh.Horario (
            PersonalID, TurnoID, DiaSemana, FechaInicio, FechaFin, Estado
        ) VALUES (
            :personalId, :turnoId, :diaSemana, '2026-01-01', '2026-12-31', 1
        )
    ");

    $stmtUpdateMarcacion = $pdo->prepare("
        UPDATE rrhh.MarcacionBiometrica
        SET PersonalID = :personalId,
            EstadoVinculacion = 'VINCULADO'
        WHERE BiostarUserID = :biostarUserId
          AND (PersonalID IS NULL OR EstadoVinculacion != 'VINCULADO')
    ");

    $stmtCheckVinc = $pdo->prepare("SELECT VinculacionID FROM rrhh.VinculacionBiometrica WHERE BiostarUserID = ?");
    $stmtInsertVinc = $pdo->prepare("
        INSERT INTO rrhh.VinculacionBiometrica (
            BiostarUserID, PersonalID, Observacion, TipoVinculacion
        ) VALUES (
            :biostarUserId, :personalId, :observacion, 'DIRECTA_DNI'
        )
    ");
    $stmtUpdateVinc = $pdo->prepare("
        UPDATE rrhh.VinculacionBiometrica
        SET PersonalID = :personalId, Observacion = :observacion
        WHERE BiostarUserID = :biostarUserId
    ");

    foreach ($bioUsers as $idx => $u) {
        $dni = trim($u['BiostarUserID']);
        $nombreRaw = trim($u['BiostarUserName'] ?? '');
        if (empty($nombreRaw)) {
            $nombreRaw = "Servidor Biométrico " . $dni;
        }

        [$nombres, $paterno, $materno] = parseNombrePericial($nombreRaw);

        // A. Verificar core.Persona
        $stmtFindPersona->execute([$dni]);
        $rowPer = $stmtFindPersona->fetch(PDO::FETCH_ASSOC);
        if ($rowPer) {
            $personaId = (int)$rowPer['PersonaID'];
        } else {
            $stmtInsertPersona->execute([
                ':dni'     => $dni,
                ':nombres' => $nombres,
                ':paterno' => $paterno,
                ':materno' => $materno ?: null
            ]);
            $inserted = $stmtInsertPersona->fetch(PDO::FETCH_ASSOC);
            $personaId = (int)($inserted['PersonaID'] ?? 0);
            $totalNuevasPersonas++;
        }

        if ($personaId === 0) {
            continue;
        }

        // B. Verificar rrhh.Personal
        $stmtFindPersonal->execute([$personaId]);
        $rowPers = $stmtFindPersonal->fetch(PDO::FETCH_ASSOC);
        if ($rowPers) {
            $personalId = (int)$rowPers['PersonalID'];
        } else {
            $codEmp = 'EMP-' . $dni;
            $stmtInsertPersonal->execute([
                ':personaId' => $personaId,
                ':codEmp'    => $codEmp
            ]);
            $inserted = $stmtInsertPersonal->fetch(PDO::FETCH_ASSOC);
            $personalId = (int)($inserted['PersonalID'] ?? 0);
            $totalNuevosPersonal++;
        }

        if ($personalId === 0) {
            continue;
        }

        // C. Asignación de Servicio, Cargo y Profesión equilibrada
        $stmtFindAsig->execute([$personalId]);
        if (!$stmtFindAsig->fetch()) {
            $serv = $servicios[$idx % count($servicios)];
            $cargo = $cargos[$idx % count($cargos)];
            $prof = $profesiones[$idx % count($profesiones)];

            $stmtInsertAsig->execute([
                ':personalId'  => $personalId,
                ':areaId'      => $serv['AreaID'],
                ':servicioId'  => $serv['ServicioID'],
                ':cargoId'     => $cargo['CargoID'],
                ':profesionId' => $prof['ProfesionID']
            ]);
        }

        // D. Contrato
        $stmtFindContrato->execute([$personalId]);
        if (!$stmtFindContrato->fetch()) {
            $stmtInsertContrato->execute([
                ':personalId'   => $personalId,
                ':numContrato'  => 'CTR-2026-' . substr($dni, -4)
            ]);
        }

        // E. Horario (Lunes a Sábado, Turno 1: Mañana 07:00 - 13:00 / Turno 5: Admin)
        $stmtFindHorario->execute([$personalId]);
        $cntHor = (int)$stmtFindHorario->fetch()['cnt'];
        if ($cntHor === 0) {
            $turnoId = ($idx % 5 === 0) ? 5 : 1;
            for ($dia = 1; $dia <= 6; $dia++) {
                $stmtInsertHorario->execute([
                    ':personalId' => $personalId,
                    ':turnoId'    => $turnoId,
                    ':diaSemana'  => $dia
                ]);
            }
        }

        // F. Vincular Marcaciones Biométricas
        $stmtUpdateMarcacion->execute([
            ':personalId'    => $personalId,
            ':biostarUserId' => $dni
        ]);
        $totalVinculacionesActualizadas += $stmtUpdateMarcacion->rowCount();

        // G. Guardar en VinculacionBiometrica
        $nombreCompleto = trim("{$paterno} {$materno} {$nombres}");
        $stmtCheckVinc->execute([$dni]);
        if ($stmtCheckVinc->fetch()) {
            $stmtUpdateVinc->execute([
                ':personalId'    => $personalId,
                ':observacion'   => "Vinculado automáticamente: {$nombreCompleto}",
                ':biostarUserId' => $dni
            ]);
        } else {
            $stmtInsertVinc->execute([
                ':biostarUserId' => $dni,
                ':personalId'    => $personalId,
                ':observacion'   => "Vinculado automáticamente: {$nombreCompleto}"
            ]);
        }

        $totalSincronizados++;
    }

    $log("Sincronización completada exitosamente.");
    $log("- Total servidores procesados: {$totalSincronizados}");
    $log("- Personas registradas en core.Persona: {$totalNuevasPersonas}");
    $log("- Personal registrado en rrhh.Personal: {$totalNuevosPersonal}");
    $log("- Marcaciones actualizadas a VINCULADO: {$totalVinculacionesActualizadas}");

    return [
        'total_sincronizados'            => $totalSincronizados,
        'nuevas_personas'                => $totalNuevasPersonas,
        'nuevo_personal'                 => $totalNuevosPersonal,
        'marcaciones_vinculadas'         => $totalVinculacionesActualizadas
    ];
}

// Ejecución CLI directa si es invocado como script
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    try {
        $res = sincronizarPersonalBiometrico();
        print_r($res);
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
        exit(1);
    }
}
