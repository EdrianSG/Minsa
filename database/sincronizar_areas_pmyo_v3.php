<?php
/**
 * Script de sincronización de Áreas, Departamentos, Cargos y Profesiones
 * según el Diccionario Institucional del Hospital Distrital de Pacasmayo (PMYO).
 * Base de Datos: BD_HOSPITAL_V3
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PmyoCatalog.php';

$pdo = Database::getConnection();
if (!$pdo) {
    echo "ERROR: No se pudo conectar a la base de datos SQL Server.\n";
    exit(1);
}

$dbName = $pdo->query('SELECT DB_NAME()')->fetchColumn();
echo "Base de datos conectada: $dbName\n";

$isDryRun = !in_array('--aplicar', $argv, true);
if ($isDryRun) {
    echo "MODO VISTA PREVIA (DRY RUN). Agregue --aplicar para ejecutar los cambios en la base de datos.\n\n";
} else {
    echo "MODO EJECUCIÓN (--aplicar). Iniciando transacción...\n\n";
    $pdo->beginTransaction();
}

try {
    // 1. Obtener SedeID del Hospital Pacasmayo
    $stmtSede = $pdo->query("SELECT TOP 1 SedeID FROM organizacion.Sede WHERE Activo = 1 ORDER BY SedeID");
    $sedeId = (int)$stmtSede->fetchColumn();
    if ($sedeId <= 0) {
        throw new RuntimeException("No se encontró una sede activa en organizacion.Sede.");
    }
    echo "Sede institucional detectada: SedeID = $sedeId\n";

    // 2. Sincronizar Departamentos (organizacion.Departamento)
    $departamentosDef = [
        'DEP_ADM'   => 'Oficina de Administración',
        'DEP_ENF'   => 'Departamento de Enfermería',
        'DEP_CIR'   => 'Departamento de Cirugía y Anestesiología',
        'DEP_GIN'   => 'Departamento de Gineco-Obstetricia',
        'DEP_MED'   => 'Departamento de Medicina y Consulta Externa',
        'DEP_EMERG' => 'Departamento de Emergencia',
        'DEP_AYUDA' => 'Departamento de Ayuda al Diagnóstico y Tratamiento',
    ];

    $depMap = []; // Codigo => DepartamentoID
    // Cargar existentes
    $stmtExistingDeps = $pdo->query("SELECT DepartamentoID, Codigo, Nombre FROM organizacion.Departamento WHERE SedeID = $sedeId");
    foreach ($stmtExistingDeps->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $depMap[$d['Codigo']] = (int)$d['DepartamentoID'];
    }

    echo "\n--- DEPARTAMENTOS ---\n";
    foreach ($departamentosDef as $cod => $nombre) {
        if (isset($depMap[$cod])) {
            echo " [EXISTE] $cod: $nombre (ID: {$depMap[$cod]})\n";
            continue;
        }
        echo " [NUEVO]  $cod: $nombre\n";
        if (!$isDryRun) {
            $stmtIns = $pdo->prepare("INSERT INTO organizacion.Departamento (SedeID, Codigo, Nombre, Activo) VALUES (?, ?, ?, 1)");
            $stmtIns->execute([$sedeId, $cod, $nombre]);
            $newId = (int)$pdo->lastInsertId();
            $depMap[$cod] = $newId;
            echo "          -> Creado con DepartamentoID = $newId\n";
        } else {
            $depMap[$cod] = 999; // Mock ID para dry run
        }
    }

    // 3. Mapeo de Área a Departamento
    $areaToDep = [
        // Administración
        'ADMINISTRATIVO'              => 'DEP_ADM',
        'RECURSOS HUMANOS'            => 'DEP_ADM',
        'ALMACEN Y PATRIMONIO'        => 'DEP_ADM',
        'DIGITADORA'                  => 'DEP_ADM',
        'GESTION DE LA INFORMACION'   => 'DEP_ADM',
        'SIS'                         => 'DEP_ADM',
        'SIS (SEGURO INTEGRAL DE SALUD)' => 'DEP_ADM',
        'MANTENIMIENTO'               => 'DEP_ADM',
        'PILOTO AMBULANCIA'           => 'DEP_ADM',
        'ASISTENTA SOCIAL'            => 'DEP_ADM',
        'SALUD AMBIENTAL'             => 'DEP_ADM',
        // Enfermería
        'ENFERMERA'                   => 'DEP_ENF',
        'ENFERMERIA'                  => 'DEP_ENF',
        'LIC ENFERMERIA'              => 'DEP_ENF',
        'TEC ENFERMERIA'              => 'DEP_ENF',
        // Cirugía
        'CIRUGIA'                     => 'DEP_CIR',
        'CIRUGIA GENERAL'             => 'DEP_CIR',
        'ANESTESIOLOGIA'              => 'DEP_CIR',
        'CENTRO QUIRURGICO'           => 'DEP_CIR',
        // Gineco-Obstetricia
        'GINECOLOGIA'                 => 'DEP_GIN',
        'OBSTETRICIA'                 => 'DEP_GIN',
        // Emergencia
        'EMERGENCIA'                  => 'DEP_EMERG',
        // Ayuda al diagnóstico
        'LABORATORIO'                 => 'DEP_AYUDA',
        'FARMACIA'                    => 'DEP_AYUDA',
        'RAYOS X'                     => 'DEP_AYUDA',
        // Medicina y Consultas
        'MEDICINA'                    => 'DEP_MED',
        'MEDICINA GENERAL'            => 'DEP_MED',
        'MEDICO AUDITO'               => 'DEP_MED',
        'TRAUMATOLOGIA'               => 'DEP_MED',
        'PSICOLOGIA'                  => 'DEP_MED',
        'NUTRICION'                   => 'DEP_MED',
        'NUTRICIONISTA'               => 'DEP_MED',
        'ODONTOLOGO'                  => 'DEP_MED',
        'TERAPIA FISICA'              => 'DEP_MED',
        'REHABILITACION'              => 'DEP_MED',
        'REHABILITACION FISICA'       => 'DEP_MED',
        'TELECONSULTAS'               => 'DEP_MED',
    ];

    // 4. Sincronizar Áreas (organizacion.Area)
    echo "\n--- ÁREAS ORGANIZACIONALES ---\n";
    $existingAreas = [];
    $stmtAreas = $pdo->query("SELECT AreaID, DepartamentoID, Codigo, UPPER(LTRIM(RTRIM(Nombre))) AS NombreUpper FROM organizacion.Area");
    foreach ($stmtAreas->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $existingAreas[$a['NombreUpper']] = (int)$a['AreaID'];
    }

    $areaCounter = 10;
    $areasCreadas = 0;
    foreach ($areaToDep as $areaNombre => $depCod) {
        $keyUpper = mb_strtoupper(trim($areaNombre), 'UTF-8');
        if (isset($existingAreas[$keyUpper])) {
            echo " [EXISTE] $areaNombre (AreaID: {$existingAreas[$keyUpper]})\n";
            continue;
        }

        $depId = $depMap[$depCod] ?? $depMap['DEP_MED'];
        $cleanCode = 'A_' . substr(preg_replace('/[^A-Z0-9]/', '', $keyUpper), 0, 10) . '_' . (++$areaCounter);
        if (strlen($cleanCode) > 20) $cleanCode = substr($cleanCode, 0, 20);

        echo " [NUEVA]  $areaNombre -> Dep: $depCod ($depId), Cod: $cleanCode\n";
        if (!$isDryRun) {
            $stmtInsArea = $pdo->prepare("INSERT INTO organizacion.Area (DepartamentoID, Codigo, Nombre, Descripcion, Activo) VALUES (?, ?, ?, ?, 1)");
            $stmtInsArea->execute([$depId, $cleanCode, $areaNombre, "Área funcional PMYO: $areaNombre"]);
            $newAreaId = (int)$pdo->lastInsertId();
            $existingAreas[$keyUpper] = $newAreaId;
            $areasCreadas++;
        }
    }
    echo "Total áreas a insertar: " . ($isDryRun ? count($areaToDep) - count($existingAreas) : $areasCreadas) . "\n";

    // 5. Sincronizar Profesiones (rrhh.Profesion)
    echo "\n--- PROFESIONES ---\n";
    $pmyoMap = PmyoCatalog::map();
    $profesionesNuevas = $pmyoMap['profesiones']; // [Codigo => Nombre]

    $existingProfs = [];
    $stmtProfs = $pdo->query("SELECT ProfesionID, Codigo, UPPER(LTRIM(RTRIM(Nombre))) AS NombreUpper FROM rrhh.Profesion");
    foreach ($stmtProfs->fetchAll(PDO::FETCH_ASSOC) as $pr) {
        $existingProfs[$pr['Codigo']] = (int)$pr['ProfesionID'];
        $existingProfs[$pr['NombreUpper']] = (int)$pr['ProfesionID'];
    }

    $profsCreadas = 0;
    foreach ($profesionesNuevas as $cod => $nombre) {
        $nomUpper = mb_strtoupper(trim($nombre), 'UTF-8');
        if (isset($existingProfs[$cod]) || isset($existingProfs[$nomUpper])) {
            $pid = $existingProfs[$cod] ?? $existingProfs[$nomUpper];
            echo " [EXISTE] $cod: $nombre (ID: $pid)\n";
            continue;
        }

        echo " [NUEVA]  $cod: $nombre\n";
        if (!$isDryRun) {
            $stmtInsProf = $pdo->prepare("INSERT INTO rrhh.Profesion (Codigo, Nombre, Activo) VALUES (?, ?, 1)");
            $stmtInsProf->execute([$cod, $nombre]);
            $newProfId = (int)$pdo->lastInsertId();
            $existingProfs[$cod] = $newProfId;
            $existingProfs[$nomUpper] = $newProfId;
            $profsCreadas++;
        }
    }

    // 6. Sincronizar Especialidades (rrhh.Especialidad)
    echo "\n--- ESPECIALIDADES MÉDICAS / PROFESIONALES ---\n";
    $specialtiesToSync = [
        ['MED', 'Anestesiología'],
        ['MED', 'Cirugía general'],
        ['MED', 'Ginecología'],
        ['MED', 'Ginecología y obstetricia'],
        ['MED', 'Medicina interna'],
        ['MED', 'Pediatría'],
        ['MED', 'Neumología'],
        ['MED', 'Traumatología'],
        ['ODO', 'Odontopediatría'],
        ['TM',  'Terapia física y rehabilitación'],
    ];

    $existingSpecs = [];
    $stmtSpecs = $pdo->query("SELECT EspecialidadID, ProfesionID, UPPER(LTRIM(RTRIM(Nombre))) AS NombreUpper FROM rrhh.Especialidad");
    foreach ($stmtSpecs->fetchAll(PDO::FETCH_ASSOC) as $sp) {
        $existingSpecs[$sp['ProfesionID'] . ':' . $sp['NombreUpper']] = (int)$sp['EspecialidadID'];
    }

    $specsCreadas = 0;
    foreach ($specialtiesToSync as [$profCod, $specNombre]) {
        // Obtener ProfesionID
        $profId = $existingProfs[$profCod] ?? null;
        if (!$profId) {
            $stmtGetP = $pdo->prepare("SELECT ProfesionID FROM rrhh.Profesion WHERE Codigo = ?");
            $stmtGetP->execute([$profCod]);
            $profId = (int)$stmtGetP->fetchColumn();
        }
        if (!$profId) {
            echo " [WARN] No se encontró ProfesionID para código $profCod\n";
            continue;
        }

        $specKey = $profId . ':' . mb_strtoupper(trim($specNombre), 'UTF-8');
        if (isset($existingSpecs[$specKey])) {
            echo " [EXISTE] $specNombre (Prof $profCod, ID: {$existingSpecs[$specKey]})\n";
            continue;
        }

        echo " [NUEVA]  $specNombre (Prof $profCod -> ID $profId)\n";
        if (!$isDryRun) {
            $stmtInsSpec = $pdo->prepare("INSERT INTO rrhh.Especialidad (ProfesionID, Nombre, Activo) VALUES (?, ?, 1)");
            $stmtInsSpec->execute([$profId, $specNombre]);
            $newSpecId = (int)$pdo->lastInsertId();
            $existingSpecs[$specKey] = $newSpecId;
            $specsCreadas++;
        }
    }

    // 7. Sincronizar Cargos / Perfiles (rrhh.Cargo)
    echo "\n--- CARGOS / PERFILES PMYO ---\n";
    $profiles = PmyoCatalog::profiles();

    $existingCargos = [];
    $stmtCargos = $pdo->query("SELECT CargoID, Codigo, UPPER(LTRIM(RTRIM(Nombre))) AS NombreUpper FROM rrhh.Cargo");
    foreach ($stmtCargos->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $existingCargos[$c['NombreUpper']] = (int)$c['CargoID'];
    }

    $cargoCounter = 20;
    $cargosCreados = 0;
    foreach ($profiles as $perfilNombre) {
        $perfUpper = mb_strtoupper(trim($perfilNombre), 'UTF-8');
        if (isset($existingCargos[$perfUpper])) {
            echo " [EXISTE] $perfilNombre (CargoID: {$existingCargos[$perfUpper]})\n";
            continue;
        }

        $cleanCargoCode = 'C_' . substr(preg_replace('/[^A-Z0-9]/', '', $perfUpper), 0, 10) . '_' . (++$cargoCounter);
        if (strlen($cleanCargoCode) > 20) $cleanCargoCode = substr($cleanCargoCode, 0, 20);

        echo " [NUEVO]  $perfilNombre -> Cod: $cleanCargoCode\n";
        if (!$isDryRun) {
            $stmtInsCargo = $pdo->prepare("INSERT INTO rrhh.Cargo (Codigo, Nombre, Descripcion, Activo) VALUES (?, ?, ?, 1)");
            $stmtInsCargo->execute([$cleanCargoCode, $perfilNombre, "Perfil PMYO: $perfilNombre"]);
            $newCargoId = (int)$pdo->lastInsertId();
            $existingCargos[$perfUpper] = $newCargoId;
            $cargosCreados++;
        }
    }

    if (!$isDryRun) {
        $pdo->commit();
        echo "\n¡ÉXITO! Transacción confirmada en BD_HOSPITAL_V3.\n";
        echo " - Áreas creadas: $areasCreadas\n";
        echo " - Profesiones creadas: $profsCreadas\n";
        echo " - Especialidades creadas: $specsCreadas\n";
        echo " - Cargos/Perfiles creados: $cargosCreados\n";
    } else {
        echo "\nVista previa completada con éxito. Ejecute con --aplicar para guardar.\n";
    }

} catch (Throwable $e) {
    if (!$isDryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
