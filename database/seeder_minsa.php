<?php
/**
 * Seeder Institucional Idempotente - Hospital Distrital de Pacasmayo
 * Pobla datos maestros en la base de datos oficial BD_HOSPITAL respetando
 * la totalidad de esquemas, tablas y restricciones existentes sin alterar ningún nombre.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

echo "=========================================================\n";
echo "   HOSPITAL DISTRITAL DE PACASMAYO - SEEDER INSTITUCIONAL\n";
echo "   Base de Datos: BD_HOSPITAL en 192.168.10.192:1433\n";
echo "=========================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "[ERROR FATAL] No se pudo conectar a SQL Server: " . Database::getLastError() . "\n";
    exit(1);
}

try {
    // -------------------------------------------------------------------------
    // 1. CATALOGOS (catalogo.TipoDocumento, catalogo.Sexo, catalogo.EstadoCivil)
    // -------------------------------------------------------------------------
    echo "1. Sembrando esquemas de catálogo...\n";

    // TipoDocumento
    $tiposDoc = [
        ['Codigo' => 'DNI', 'Nombre' => 'Documento Nacional de Identidad'],
        ['Codigo' => 'CE',  'Nombre' => 'Carné de Extranjería'],
        ['Codigo' => 'PAS', 'Nombre' => 'Pasaporte'],
    ];
    foreach ($tiposDoc as $td) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM catalogo.TipoDocumento WHERE Codigo = ?");
        $chk->execute([$td['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO catalogo.TipoDocumento (Codigo, Nombre, Estado) VALUES (?, ?, 1)");
            $ins->execute([$td['Codigo'], $td['Nombre']]);
        }
    }

    // Sexo
    $sexos = [
        ['Codigo' => 'M', 'Nombre' => 'Masculino'],
        ['Codigo' => 'F', 'Nombre' => 'Femenino'],
    ];
    foreach ($sexos as $sx) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM catalogo.Sexo WHERE Codigo = ?");
        $chk->execute([$sx['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO catalogo.Sexo (Codigo, Nombre, Estado) VALUES (?, ?, 1)");
            $ins->execute([$sx['Codigo'], $sx['Nombre']]);
        }
    }

    // EstadoCivil
    $estadosCiv = ['Soltero(a)', 'Casado(a)', 'Conviviente', 'Divorciado(a)', 'Viudo(a)'];
    foreach ($estadosCiv as $ec) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM catalogo.EstadoCivil WHERE Nombre = ?");
        $chk->execute([$ec]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO catalogo.EstadoCivil (Nombre, Estado) VALUES (?, 1)");
            $ins->execute([$ec]);
        }
    }
    echo "   [OK] Catálogos base listos.\n";

    // -------------------------------------------------------------------------
    // 2. ORGANIZACION (Sede, Departamento, Area, Servicio)
    // -------------------------------------------------------------------------
    echo "2. Sembrando estructura organizacional...\n";

    // Sede
    $chkSede = $pdo->prepare("SELECT COUNT(*) FROM organizacion.Sede WHERE Codigo = ?");
    $chkSede->execute(['HDP-01']);
    if ((int)$chkSede->fetchColumn() === 0) {
        $insSede = $pdo->prepare("INSERT INTO organizacion.Sede (Codigo, Nombre, Direccion, Telefono, Estado) VALUES (?, ?, ?, ?, 1)");
        $insSede->execute(['HDP-01', 'Hospital Distrital de Pacasmayo', 'Av. San Martín S/N, Pacasmayo, La Libertad', '(044) 521234']);
    }
    $sedeId = (int)$pdo->query("SELECT SedeID FROM organizacion.Sede WHERE Codigo = 'HDP-01'")->fetchColumn();

    // Departamentos
    $departamentos = [
        ['Codigo' => 'DEP-MED', 'Nombre' => 'Departamento de Medicina'],
        ['Codigo' => 'DEP-ENF', 'Nombre' => 'Departamento de Enfermería'],
        ['Codigo' => 'DEP-PED', 'Nombre' => 'Departamento de Pediatría'],
        ['Codigo' => 'DEP-GIN', 'Nombre' => 'Departamento de Gineco-Obstetricia'],
        ['Codigo' => 'DEP-AYU', 'Nombre' => 'Departamento de Apoyo al Diagnóstico'],
        ['Codigo' => 'DEP-ADM', 'Nombre' => 'Oficina de Administración'],
        ['Codigo' => 'DEP-DIR', 'Nombre' => 'Dirección Ejecutiva'],
    ];
    $depIds = [];
    foreach ($departamentos as $dep) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM organizacion.Departamento WHERE Codigo = ?");
        $chk->execute([$dep['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO organizacion.Departamento (SedeID, Codigo, Nombre, Estado) VALUES (?, ?, ?, 1)");
            $ins->execute([$sedeId, $dep['Codigo'], $dep['Nombre']]);
        }
        $depIds[$dep['Codigo']] = (int)$pdo->query("SELECT DepartamentoID FROM organizacion.Departamento WHERE Codigo = '{$dep['Codigo']}'")->fetchColumn();
    }

    // Areas
    $areas = [
        ['Codigo' => 'AREA-MED', 'Nombre' => 'Área de Hospitalización y Consulta Externa Médica', 'Dep' => 'DEP-MED'],
        ['Codigo' => 'AREA-ENF', 'Nombre' => 'Área Asistencial de Enfermería', 'Dep' => 'DEP-ENF'],
        ['Codigo' => 'AREA-PED', 'Nombre' => 'Área de Pediatría y Neonatología', 'Dep' => 'DEP-PED'],
        ['Codigo' => 'AREA-GIN', 'Nombre' => 'Área Materno Infantil', 'Dep' => 'DEP-GIN'],
        ['Codigo' => 'AREA-LAB', 'Nombre' => 'Área de Laboratorio Clínico y Patología', 'Dep' => 'DEP-AYU'],
        ['Codigo' => 'AREA-FAR', 'Nombre' => 'Área de Farmacia y Dosis Unitaria', 'Dep' => 'DEP-AYU'],
        ['Codigo' => 'AREA-PER', 'Nombre' => 'Unidad de Personal / RR.HH.', 'Dep' => 'DEP-ADM'],
        ['Codigo' => 'AREA-INF', 'Nombre' => 'Unidad de Informática y Estadística', 'Dep' => 'DEP-DIR'],
    ];
    $areaIds = [];
    foreach ($areas as $a) {
        $depId = $depIds[$a['Dep']];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM organizacion.Area WHERE Codigo = ?");
        $chk->execute([$a['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO organizacion.Area (DepartamentoID, Codigo, Nombre, Descripcion, Estado) VALUES (?, ?, ?, ?, 1)");
            $ins->execute([$depId, $a['Codigo'], $a['Nombre'], $a['Nombre']]);
        }
        $areaIds[$a['Codigo']] = (int)$pdo->query("SELECT AreaID FROM organizacion.Area WHERE Codigo = '{$a['Codigo']}'")->fetchColumn();
    }

    // Servicios
    $servicios = [
        ['Codigo' => 'SERV-EME',  'Nombre' => 'Emergencia y Cuidados Críticos',           'Area' => 'AREA-MED'],
        ['Codigo' => 'SERV-UCI',  'Nombre' => 'Unidad de Cuidados Intensivos',             'Area' => 'AREA-MED'],
        ['Codigo' => 'SERV-HOSP', 'Nombre' => 'Hospitalización Medicina',                  'Area' => 'AREA-MED'],
        ['Codigo' => 'SERV-EXT',  'Nombre' => 'Consulta Externa',                          'Area' => 'AREA-MED'],
        ['Codigo' => 'SERV-PED',  'Nombre' => 'Pediatría y Neonatología',                  'Area' => 'AREA-PED'],
        ['Codigo' => 'SERV-OBST', 'Nombre' => 'Centro Obstétrico',                         'Area' => 'AREA-GIN'],
        ['Codigo' => 'SERV-QX',   'Nombre' => 'Centro Quirúrgico',                         'Area' => 'AREA-ENF'],
        ['Codigo' => 'SERV-LAB',  'Nombre' => 'Laboratorio Clínico',                       'Area' => 'AREA-LAB'],
        ['Codigo' => 'SERV-FAR',  'Nombre' => 'Farmacia y Dosis Unitaria',                 'Area' => 'AREA-FAR'],
        ['Codigo' => 'SERV-PER',  'Nombre' => 'Unidad de Personal / RR.HH.',               'Area' => 'AREA-PER'],
        ['Codigo' => 'SERV-INF',  'Nombre' => 'Unidad de Informática y Estadística',       'Area' => 'AREA-INF'],
    ];
    $servicioIds = [];
    foreach ($servicios as $s) {
        $arId = $areaIds[$s['Area']];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM organizacion.Servicio WHERE Codigo = ?");
        $chk->execute([$s['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO organizacion.Servicio (AreaID, Codigo, Nombre, Descripcion, Estado) VALUES (?, ?, ?, ?, 1)");
            $ins->execute([$arId, $s['Codigo'], $s['Nombre'], $s['Nombre']]);
        }
        $servicioIds[$s['Codigo']] = (int)$pdo->query("SELECT ServicioID FROM organizacion.Servicio WHERE Codigo = '{$s['Codigo']}'")->fetchColumn();
    }
    echo "   [OK] Organización, Sedes, Departamentos, Áreas y Servicios listos.\n";

    // -------------------------------------------------------------------------
    // 3. RRHH PARAMETROS (Cargo, Profesion, Especialidad, TipoContrato, Turno, TipoPermisoLaboral)
    // -------------------------------------------------------------------------
    echo "3. Sembrando parámetros de RRHH...\n";

    // TipoContrato
    $tiposContrato = [
        ['Nombre' => 'CAS (D.L. 1057)', 'Descripcion' => 'Contrato Administrativo de Servicios'],
        ['Nombre' => 'NOMBRADO (D.L. 276)', 'Descripcion' => 'Carrera Administrativa y Asistencial del Sector Público'],
        ['Nombre' => 'REGIMEN 728', 'Descripcion' => 'Régimen de la Actividad Privada'],
    ];
    $tipoContratoIds = [];
    foreach ($tiposContrato as $tc) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.TipoContrato WHERE Nombre = ?");
        $chk->execute([$tc['Nombre']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.TipoContrato (Nombre, Descripcion, Estado) VALUES (?, ?, 1)");
            $ins->execute([$tc['Nombre'], $tc['Descripcion']]);
        }
        $tipoContratoIds[$tc['Nombre']] = (int)$pdo->query("SELECT TipoContratoID FROM rrhh.TipoContrato WHERE Nombre = '{$tc['Nombre']}'")->fetchColumn();
    }

    // Profesiones
    $profesiones = [
        ['Codigo' => 'MED', 'Nombre' => 'Medicina Humana'],
        ['Codigo' => 'ENF', 'Nombre' => 'Enfermería'],
        ['Codigo' => 'OBS', 'Nombre' => 'Obstetricia'],
        ['Codigo' => 'TEC-SAL', 'Nombre' => 'Técnico de Salud'],
        ['Codigo' => 'TEC-MED', 'Nombre' => 'Tecnología Médica'],
        ['Codigo' => 'FAR-TEC', 'Nombre' => 'Farmacia Técnica'],
        ['Codigo' => 'ADM', 'Nombre' => 'Administración'],
        ['Codigo' => 'ING-SIS', 'Nombre' => 'Ingeniería de Sistemas'],
    ];
    $profesionIds = [];
    foreach ($profesiones as $p) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Profesion WHERE Codigo = ?");
        $chk->execute([$p['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.Profesion (Codigo, Nombre, Estado) VALUES (?, ?, 1)");
            $ins->execute([$p['Codigo'], $p['Nombre']]);
        }
        $profesionIds[$p['Codigo']] = (int)$pdo->query("SELECT ProfesionID FROM rrhh.Profesion WHERE Codigo = '{$p['Codigo']}'")->fetchColumn();
    }

    // Especialidades
    $especialidades = [
        ['Nombre' => 'Medicina Intensiva', 'Prof' => 'MED'],
        ['Nombre' => 'Pediatría', 'Prof' => 'MED'],
        ['Nombre' => 'Cuidados Intensivos y Emergencia', 'Prof' => 'ENF'],
        ['Nombre' => 'Centro Quirúrgico', 'Prof' => 'ENF'],
        ['Nombre' => 'Monitoreo Materno Perinatal', 'Prof' => 'OBS'],
        ['Nombre' => 'Psicoprofilaxis Obstétrica', 'Prof' => 'OBS'],
        ['Nombre' => 'Laboratorio Clínico y Anatomía Patológica', 'Prof' => 'TEC-MED'],
        ['Nombre' => 'Tecnologías de la Información', 'Prof' => 'ING-SIS'],
    ];
    $especialidadIds = [];
    foreach ($especialidades as $e) {
        $pId = $profesionIds[$e['Prof']];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Especialidad WHERE Nombre = ? AND ProfesionID = ?");
        $chk->execute([$e['Nombre'], $pId]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.Especialidad (ProfesionID, Nombre, Estado) VALUES (?, ?, 1)");
            $ins->execute([$pId, $e['Nombre']]);
        }
        $especialidadIds[$e['Nombre']] = (int)$pdo->query("SELECT EspecialidadID FROM rrhh.Especialidad WHERE Nombre = '{$e['Nombre']}' AND ProfesionID = $pId")->fetchColumn();
    }

    // Cargos
    $cargos = [
        ['Codigo' => 'CAR-MED-INT', 'Nombre' => 'Médico Especialista', 'Nivel' => 1],
        ['Codigo' => 'CAR-MED-PED', 'Nombre' => 'Médico Pediatra', 'Nivel' => 1],
        ['Codigo' => 'CAR-MED-GEN', 'Nombre' => 'Médico General', 'Nivel' => 2],
        ['Codigo' => 'CAR-ENF-ASI', 'Nombre' => 'Enfermera Asistencial', 'Nivel' => 2],
        ['Codigo' => 'CAR-ENF-ESP', 'Nombre' => 'Enfermera Especialista', 'Nivel' => 1],
        ['Codigo' => 'CAR-TEC-ENF', 'Nombre' => 'Técnico en Enfermería', 'Nivel' => 3],
        ['Codigo' => 'CAR-OBS-ASI', 'Nombre' => 'Obstetra Asistencial', 'Nivel' => 2],
        ['Codigo' => 'CAR-TEC-MED', 'Nombre' => 'Tecnólogo Médico', 'Nivel' => 2],
        ['Codigo' => 'CAR-TEC-FAR', 'Nombre' => 'Técnica en Farmacia', 'Nivel' => 3],
        ['Codigo' => 'CAR-ESP-ADM', 'Nombre' => 'Especialista Administrativo', 'Nivel' => 2],
        ['Codigo' => 'CAR-JEF-INF', 'Nombre' => 'Jefe de Informática y Estadística', 'Nivel' => 1],
    ];
    $cargoIds = [];
    foreach ($cargos as $c) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Cargo WHERE Codigo = ?");
        $chk->execute([$c['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.Cargo (Codigo, Nombre, Descripcion, Nivel, Estado) VALUES (?, ?, ?, ?, 1)");
            $ins->execute([$c['Codigo'], $c['Nombre'], $c['Nombre'], $c['Nivel']]);
        }
        $cargoIds[$c['Codigo']] = (int)$pdo->query("SELECT CargoID FROM rrhh.Cargo WHERE Codigo = '{$c['Codigo']}'")->fetchColumn();
    }

    // Turnos oficiales del Hospital
    $turnos = [
        ['Nombre' => 'Mañana Ordinario (07:00 - 13:00)', 'Inicio' => '07:00:00', 'Fin' => '13:00:00'],
        ['Nombre' => 'Tarde Ordinario (13:00 - 19:00)',  'Inicio' => '13:00:00', 'Fin' => '19:00:00'],
        ['Nombre' => 'Guardia Nocturna (19:00 - 07:00)', 'Inicio' => '19:00:00', 'Fin' => '07:00:00'],
        ['Nombre' => 'Guardia Diurna (07:00 - 19:00)',   'Inicio' => '07:00:00', 'Fin' => '19:00:00'],
        ['Nombre' => 'Jornada Administrativa (08:00 - 16:45)', 'Inicio' => '08:00:00', 'Fin' => '16:45:00'],
        ['Nombre' => 'Retén de Emergencia (19:00 - 07:00)', 'Inicio' => '19:00:00', 'Fin' => '07:00:00'],
    ];
    $turnoIds = [];
    foreach ($turnos as $t) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Turno WHERE Nombre = ?");
        $chk->execute([$t['Nombre']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.Turno (Nombre, HoraInicio, HoraFin, Estado) VALUES (?, ?, ?, 1)");
            $ins->execute([$t['Nombre'], $t['Inicio'], $t['Fin']]);
        }
        $turnoIds[$t['Nombre']] = (int)$pdo->query("SELECT TurnoID FROM rrhh.Turno WHERE Nombre = '{$t['Nombre']}'")->fetchColumn();
    }

    // TipoPermisoLaboral
    $tiposPermiso = [
        ['Nombre' => 'Descanso Médico (CITT EsSalud)', 'Descripcion' => 'Incapacidad temporal para el trabajo certificada'],
        ['Nombre' => 'Permiso por Capacitación Oficial', 'Descripcion' => 'Eventos académicos aprobados por MINSA/DIRESA'],
        ['Nombre' => 'Permiso por Asuntos Particulares', 'Descripcion' => 'Permiso personal con o sin goce de haber'],
        ['Nombre' => 'Vacaciones Programadas', 'Descripcion' => 'Rol anual de descanso vacacional'],
        ['Nombre' => 'Compensación de Horas Extra', 'Descripcion' => 'Descanso compensatorio por sobretiempo'],
        ['Nombre' => 'Licencia por Maternidad / Paternidad', 'Descripcion' => 'Licencia legal por alumbramiento'],
        ['Nombre' => 'Licencia Sindical', 'Descripcion' => 'Representación gremial autorizada'],
    ];
    $tipoPermisoIds = [];
    foreach ($tiposPermiso as $tp) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM rrhh.TipoPermisoLaboral WHERE Nombre = ?");
        $chk->execute([$tp['Nombre']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO rrhh.TipoPermisoLaboral (Nombre, Descripcion, Estado) VALUES (?, ?, 1)");
            $ins->execute([$tp['Nombre'], $tp['Descripcion']]);
        }
        $tipoPermisoIds[$tp['Nombre']] = (int)$pdo->query("SELECT TipoPermisoLaboralID FROM rrhh.TipoPermisoLaboral WHERE Nombre = '{$tp['Nombre']}'")->fetchColumn();
    }
    echo "   [OK] Parámetros de RRHH, Turnos Oficiales y Tipos de Permisos listos.\n";

    // -------------------------------------------------------------------------
    // 4. SEGURIDAD (Sistema, Rol, Usuario, UsuarioRol)
    // -------------------------------------------------------------------------
    echo "4. Sembrando esquemas de seguridad y accesos...\n";

    // Sistema
    $chkSis = $pdo->prepare("SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo = ?");
    $chkSis->execute(['SIST_RRHH_HDP']);
    if ((int)$chkSis->fetchColumn() === 0) {
        $insSis = $pdo->prepare("INSERT INTO seguridad.Sistema (Codigo, Nombre, Descripcion, Version, URL, Estado) VALUES (?, ?, ?, ?, ?, 1)");
        $insSis->execute(['SIST_RRHH_HDP', 'Sistema Integral de Gestión de RR.HH.', 'Hospital Distrital de Pacasmayo - MINSA', '1.0', 'http://127.0.0.1:8080']);
    }
    $sistemaId = (int)$pdo->query("SELECT SistemaID FROM seguridad.Sistema WHERE Codigo = 'SIST_RRHH_HDP'")->fetchColumn();

    // Roles
    $roles = [
        ['Codigo' => 'ADMIN',      'Nombre' => 'Administrador del Sistema',   'Desc' => 'Acceso y configuración integral del sistema'],
        ['Codigo' => 'JEFE_RRHH',  'Nombre' => 'Jefatura de Personal',         'Desc' => 'Aprobación de roles, licencias y resoluciones'],
        ['Codigo' => 'SUPERVISOR', 'Nombre' => 'Supervisor Asistencial',      'Desc' => 'Supervisión de piso, cambios de guardia y regularizaciones'],
        ['Codigo' => 'CONSULTA',   'Nombre' => 'Auditor / Consulta',          'Desc' => 'Acceso de solo lectura a reportes e indicadores'],
    ];
    $rolIds = [];
    foreach ($roles as $r) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM seguridad.Rol WHERE Codigo = ?");
        $chk->execute([$r['Codigo']]);
        if ((int)$chk->fetchColumn() === 0) {
            $ins = $pdo->prepare("INSERT INTO seguridad.Rol (Codigo, Nombre, Descripcion, Estado) VALUES (?, ?, ?, 1)");
            $ins->execute([$r['Codigo'], $r['Nombre'], $r['Desc']]);
        }
        $rolIds[$r['Codigo']] = (int)$pdo->query("SELECT RolID FROM seguridad.Rol WHERE Codigo = '{$r['Codigo']}'")->fetchColumn();
    }
    echo "   [OK] Sistema y Roles de Seguridad listos.\n";

    // -------------------------------------------------------------------------
    // 5. PERSONAL Y PERSONAS (core.Persona, rrhh.Personal, Asignacion, Contrato)
    // -------------------------------------------------------------------------
    echo "5. Sembrando personal institucional oficial...\n";

    $dniTipoId = (int)$pdo->query("SELECT TipoDocumentoID FROM catalogo.TipoDocumento WHERE Codigo = 'DNI'")->fetchColumn();
    $sexoMId   = (int)$pdo->query("SELECT SexoID FROM catalogo.Sexo WHERE Codigo = 'M'")->fetchColumn();
    $sexoFId   = (int)$pdo->query("SELECT SexoID FROM catalogo.Sexo WHERE Codigo = 'F'")->fetchColumn();

    $staffData = [
        [
            'dni' => '45892104', 'nombres' => 'Ana María', 'ape_pat' => 'Torres', 'ape_mat' => 'Benites',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00101', 'fecha_ingreso' => '2021-03-15',
            'cargo' => 'CAR-ENF-ASI', 'prof' => 'ENF', 'esp' => 'Cuidados Intensivos y Emergencia',
            'area' => 'AREA-ENF', 'servicio' => 'SERV-EME', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '948123456', 'correo' => 'atorres@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '70445708', 'nombres' => 'Luis Alberto', 'ape_pat' => 'Díaz', 'ape_mat' => 'Rojas',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00102', 'fecha_ingreso' => '2016-07-01',
            'cargo' => 'CAR-MED-INT', 'prof' => 'MED', 'esp' => 'Medicina Intensiva',
            'area' => 'AREA-MED', 'servicio' => 'SERV-UCI', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '978541236', 'correo' => 'ldiaz@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '18942301', 'nombres' => 'Carlos Eduardo', 'ape_pat' => 'Vega', 'ape_mat' => 'Morales',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00103', 'fecha_ingreso' => '2022-01-10',
            'cargo' => 'CAR-TEC-ENF', 'prof' => 'TEC-SAL', 'esp' => null,
            'area' => 'AREA-ENF', 'servicio' => 'SERV-HOSP', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '944882109', 'correo' => 'cvega@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '41209845', 'nombres' => 'Rosa Patricia', 'ape_pat' => 'Mendoza', 'ape_mat' => 'Campos',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00104', 'fecha_ingreso' => '2014-05-18',
            'cargo' => 'CAR-MED-PED', 'prof' => 'MED', 'esp' => 'Pediatría',
            'area' => 'AREA-PED', 'servicio' => 'SERV-PED', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '949112233', 'correo' => 'rmendoza@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '43920194', 'nombres' => 'Elena Beatriz', 'ape_pat' => 'Ramos', 'ape_mat' => 'Quispe',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00105', 'fecha_ingreso' => '2020-09-01',
            'cargo' => 'CAR-OBS-ASI', 'prof' => 'OBS', 'esp' => 'Monitoreo Materno Perinatal',
            'area' => 'AREA-GIN', 'servicio' => 'SERV-OBST', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '948771122', 'correo' => 'eramos@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '47309642', 'nombres' => 'Carmen Rosa', 'ape_pat' => 'Urcia', 'ape_mat' => 'Ventura',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00106', 'fecha_ingreso' => '2012-02-01',
            'cargo' => 'CAR-ESP-ADM', 'prof' => 'ADM', 'esp' => null,
            'area' => 'AREA-PER', 'servicio' => 'SERV-PER', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '949887766', 'correo' => 'curcia@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '19204918', 'nombres' => 'Jorge Luis', 'ape_pat' => 'Saldaña', 'ape_mat' => 'Polo',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00107', 'fecha_ingreso' => '2019-11-15',
            'cargo' => 'CAR-TEC-MED', 'prof' => 'TEC-MED', 'esp' => 'Laboratorio Clínico y Anatomía Patológica',
            'area' => 'AREA-LAB', 'servicio' => 'SERV-LAB', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '965412890', 'correo' => 'jsaldana@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '71435865', 'nombres' => 'Víctor Raúl', 'ape_pat' => 'Morales', 'ape_mat' => 'Quispe',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00108', 'fecha_ingreso' => '2023-04-01',
            'cargo' => 'CAR-MED-GEN', 'prof' => 'MED', 'esp' => null,
            'area' => 'AREA-MED', 'servicio' => 'SERV-EXT', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '987321654', 'correo' => 'vmorales@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '42019485', 'nombres' => 'Claudia Elena', 'ape_pat' => 'Bazán', 'ape_mat' => 'Varas',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00109', 'fecha_ingreso' => '2015-08-20',
            'cargo' => 'CAR-TEC-FAR', 'prof' => 'FAR-TEC', 'esp' => null,
            'area' => 'AREA-FAR', 'servicio' => 'SERV-FAR', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '956789123', 'correo' => 'cbazan@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '72109483', 'nombres' => 'Manuel Alejandro', 'ape_pat' => 'Silva', 'ape_mat' => 'Paredes',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00110', 'fecha_ingreso' => '2017-06-12',
            'cargo' => 'CAR-OBS-ASI', 'prof' => 'OBS', 'esp' => 'Psicoprofilaxis Obstétrica',
            'area' => 'AREA-GIN', 'servicio' => 'SERV-OBST', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '981234567', 'correo' => 'msilva@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '40918234', 'nombres' => 'Dora Inés', 'ape_pat' => 'Vargas', 'ape_mat' => 'Cerna',
            'sexo' => $sexoFId, 'codigo_emp' => 'EMP-00111', 'fecha_ingreso' => '2013-10-01',
            'cargo' => 'CAR-ENF-ESP', 'prof' => 'ENF', 'esp' => 'Centro Quirúrgico',
            'area' => 'AREA-ENF', 'servicio' => 'SERV-QX', 'tipo_contrato' => 'NOMBRADO (D.L. 276)',
            'celular' => '974561238', 'correo' => 'dvargas@hospitalpacasmayo.gob.pe'
        ],
        [
            'dni' => '73291045', 'nombres' => 'Miguel Arturo', 'ape_pat' => 'Cortez', 'ape_mat' => 'García',
            'sexo' => $sexoMId, 'codigo_emp' => 'EMP-00112', 'fecha_ingreso' => '2018-03-01',
            'cargo' => 'CAR-JEF-INF', 'prof' => 'ING-SIS', 'esp' => 'Tecnologías de la Información',
            'area' => 'AREA-INF', 'servicio' => 'SERV-INF', 'tipo_contrato' => 'CAS (D.L. 1057)',
            'celular' => '944556677', 'correo' => 'mcortez@hospitalpacasmayo.gob.pe'
        ]
    ];

    $personalCreated = 0;
    foreach ($staffData as $s) {
        // 5.1 Persona
        $chkP = $pdo->prepare("SELECT PersonaID FROM core.Persona WHERE TipoDocumentoID = ? AND NumeroDocumento = ?");
        $chkP->execute([$dniTipoId, $s['dni']]);
        $personaId = $chkP->fetchColumn();

        if (!$personaId) {
            $insPer = $pdo->prepare("INSERT INTO core.Persona (TipoDocumentoID, NumeroDocumento, Nombres, ApellidoPaterno, ApellidoMaterno, SexoID, Celular, Correo, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $insPer->execute([$dniTipoId, $s['dni'], $s['nombres'], $s['ape_pat'], $s['ape_mat'], $s['sexo'], $s['celular'], $s['correo']]);
            $chkP->execute([$dniTipoId, $s['dni']]);
            $personaId = $chkP->fetchColumn();
        }
        $personaId = (int)$personaId;

        // 5.2 Personal
        $chkPers = $pdo->prepare("SELECT PersonalID FROM rrhh.Personal WHERE PersonaID = ?");
        $chkPers->execute([$personaId]);
        $personalId = $chkPers->fetchColumn();

        if (!$personalId) {
            $insPersonal = $pdo->prepare("INSERT INTO rrhh.Personal (PersonaID, CodigoEmpleado, FechaIngreso, Estado) VALUES (?, ?, ?, 1)");
            $insPersonal->execute([$personaId, $s['codigo_emp'], $s['fecha_ingreso']]);
            $chkPers->execute([$personaId]);
            $personalId = $chkPers->fetchColumn();
        }
        $personalId = (int)$personalId;

        // 5.3 AsignacionPersonal
        $arId = $areaIds[$s['area']];
        $svId = $servicioIds[$s['servicio']];
        $cgId = $cargoIds[$s['cargo']];
        $pfId = $profesionIds[$s['prof']];
        $esId = $s['esp'] ? ($especialidadIds[$s['esp']] ?? null) : null;

        $chkAsig = $pdo->prepare("SELECT COUNT(*) FROM rrhh.AsignacionPersonal WHERE PersonalID = ? AND EsActual = 1");
        $chkAsig->execute([$personalId]);
        if ((int)$chkAsig->fetchColumn() === 0) {
            $insAsig = $pdo->prepare("INSERT INTO rrhh.AsignacionPersonal (PersonalID, AreaID, ServicioID, CargoID, ProfesionID, EspecialidadID, FechaInicio, EsActual, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
            $insAsig->execute([$personalId, $arId, $svId, $cgId, $pfId, $esId, $s['fecha_ingreso']]);
        }

        // 5.4 Contrato
        $tcId = $tipoContratoIds[$s['tipo_contrato']];
        $chkCtr = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Contrato WHERE PersonalID = ? AND Estado = 1");
        $chkCtr->execute([$personalId]);
        if ((int)$chkCtr->fetchColumn() === 0) {
            $insCtr = $pdo->prepare("INSERT INTO rrhh.Contrato (PersonalID, TipoContratoID, NumeroContrato, FechaInicio, Estado) VALUES (?, ?, ?, ?, 1)");
            $insCtr->execute([$personalId, $tcId, 'CTR-' . date('Y') . '-' . substr($s['dni'], -4), $s['fecha_ingreso']]);
        }

        $personalCreated++;
    }
    echo "   [OK] Total de $personalCreated empleados vinculados e integrados.\n";

    // -------------------------------------------------------------------------
    // 6. USUARIOS Y ASIGNACION DE ROLES (seguridad.Usuario, seguridad.UsuarioRol)
    // -------------------------------------------------------------------------
    echo "6. Sembrando usuarios del sistema...\n";

    // Obtener personas clave para asignar cuentas
    $pCarmen = (int)$pdo->query("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = '47309642'")->fetchColumn(); // Carmen Urcia
    $pMiguel = (int)$pdo->query("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = '73291045'")->fetchColumn(); // Miguel Cortez
    $pAna    = (int)$pdo->query("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = '45892104'")->fetchColumn(); // Ana Torres
    $pLuis   = (int)$pdo->query("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = '70445708'")->fetchColumn(); // Luis Díaz
    $pRosa   = (int)$pdo->query("SELECT PersonaID FROM core.Persona WHERE NumeroDocumento = '41209845'")->fetchColumn(); // Rosa Mendoza

    $passHashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
    $passHashMinsa = password_hash('minsa2026', PASSWORD_BCRYPT);

    $usuariosData = [
        ['NombreUsuario' => 'admin',    'PersonaID' => $pMiguel, 'Password' => $passHashAdmin, 'Rol' => 'ADMIN'],
        ['NombreUsuario' => 'curcia',   'PersonaID' => $pCarmen, 'Password' => $passHashMinsa, 'Rol' => 'JEFE_RRHH'],
        ['NombreUsuario' => 'atorres',  'PersonaID' => $pAna,    'Password' => $passHashMinsa, 'Rol' => 'SUPERVISOR'],
        ['NombreUsuario' => 'ldiaz',    'PersonaID' => $pLuis,   'Password' => $passHashMinsa, 'Rol' => 'CONSULTA'],
        ['NombreUsuario' => 'rmendoza', 'PersonaID' => $pRosa,   'Password' => $passHashMinsa, 'Rol' => 'CONSULTA'],
    ];

    foreach ($usuariosData as $u) {
        $chkU = $pdo->prepare("SELECT UsuarioID FROM seguridad.Usuario WHERE NombreUsuario = ? OR PersonaID = ?");
        $chkU->execute([$u['NombreUsuario'], $u['PersonaID']]);
        $usrId = $chkU->fetchColumn();

        if (!$usrId) {
            $insU = $pdo->prepare("INSERT INTO seguridad.Usuario (PersonaID, NombreUsuario, PasswordHash, FechaCreacion, Bloqueado, Estado) VALUES (?, ?, ?, SYSDATETIME(), 0, 1)");
            $insU->execute([$u['PersonaID'], $u['NombreUsuario'], $u['Password']]);
            $chkU->execute([$u['NombreUsuario'], $u['PersonaID']]);
            $usrId = $chkU->fetchColumn();
        }
        $usrId = (int)$usrId;
        $rId = $rolIds[$u['Rol']];

        $chkUR = $pdo->prepare("SELECT COUNT(*) FROM seguridad.UsuarioRol WHERE UsuarioID = ? AND RolID = ?");
        $chkUR->execute([$usrId, $rId]);
        if ((int)$chkUR->fetchColumn() === 0) {
            $insUR = $pdo->prepare("INSERT INTO seguridad.UsuarioRol (UsuarioID, RolID, FechaAsignacion, FechaInicio, Estado) VALUES (?, ?, SYSDATETIME(), '2026-01-01', 1)");
            $insUR->execute([$usrId, $rId]);
        }
    }
    echo "   [OK] Usuarios y asignación de roles de seguridad listos.\n";

    // -------------------------------------------------------------------------
    // 7. HORARIOS SEMANALES INSTITUCIONALES (rrhh.Horario)
    // -------------------------------------------------------------------------
    echo "7. Sembrando horarios semanales recurrentes en rrhh.Horario...\n";

    $tMId   = $turnoIds['Mañana Ordinario (07:00 - 13:00)'];
    $tTId   = $turnoIds['Tarde Ordinario (13:00 - 19:00)'];

    // Para cada personal, registrar horario base
    $personales = $pdo->query("SELECT PersonalID FROM rrhh.Personal")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($personales as $pid) {
        $pIdInt = (int)$pid;
        for ($dia = 1; $dia <= 5; $dia++) {
            $tSel = ($pIdInt % 2 === 0) ? $tMId : $tTId;
            $chkH = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Horario WHERE PersonalID = ? AND DiaSemana = ? AND Estado = 1");
            $chkH->execute([$pIdInt, $dia]);
            if ((int)$chkH->fetchColumn() === 0) {
                $insH = $pdo->prepare("INSERT INTO rrhh.Horario (PersonalID, TurnoID, DiaSemana, FechaInicio, Estado) VALUES (?, ?, ?, '2026-01-01', 1)");
                $insH->execute([$pIdInt, $tSel, $dia]);
            }
        }
    }
    echo "   [OK] Horarios recurrentes de personal listos.\n";

    // -------------------------------------------------------------------------
    // 8. ASISTENCIAS BIOMETRICAS DEL MES (rrhh.Asistencia)
    // -------------------------------------------------------------------------
    echo "8. Sembrando marcaciones de asistencia en rrhh.Asistencia...\n";

    $mesActual = '2026-09-';
    $diasMes = min(22, (int)date('d'));
    $asistCount = 0;

    foreach ($personales as $pid) {
        $pIdInt = (int)$pid;
        for ($d = 1; $d <= $diasMes; $d++) {
            $diaStr = sprintf('%02d', $d);
            $fecha = $mesActual . $diaStr;
            $diaSem = (int)date('N', strtotime($fecha));

            if ($diaSem >= 6) {
                if ($pIdInt === 1 && $d === 6) {
                    $horaEnt = '07:02:10';
                    $horaSal = '19:05:00';
                    $estado = 'Puntual';
                    $obs = 'Guardia Diurna de Fin de Semana';
                } else {
                    continue;
                }
            } else {
                if ($pIdInt === 3 && $d === 15) {
                    $horaEnt = null;
                    $horaSal = null;
                    $estado = 'Falta Injustificada';
                    $obs = 'No registra marcación en lector biométrico';
                } elseif ($pIdInt === 1 && $d === 10) {
                    $horaEnt = '07:18:40';
                    $horaSal = '13:05:12';
                    $estado = 'Tardanza Leve';
                    $obs = 'Ingreso con 18 min de tardanza';
                } elseif ($pIdInt === 2 && $d >= 12 && $d <= 14) {
                    $horaEnt = null;
                    $horaSal = null;
                    $estado = 'Licencia Médica';
                    $obs = 'Licencia con CITT EsSalud A-025-458921-26';
                } else {
                    $minutos = 50 + (($pIdInt * 2 + $d) % 9);
                    $horaEnt = '06:' . $minutos . ':15';
                    $horaSal = '13:04:22';
                    $estado = 'Puntual';
                    $obs = 'Marcación biométrica conforme';
                }
            }

            $chkAs = $pdo->prepare("SELECT COUNT(*) FROM rrhh.Asistencia WHERE PersonalID = ? AND Fecha = ?");
            $chkAs->execute([$pIdInt, $fecha]);
            if ((int)$chkAs->fetchColumn() === 0) {
                $insAs = $pdo->prepare("INSERT INTO rrhh.Asistencia (PersonalID, Fecha, HoraEntrada, HoraSalida, Estado, Observacion) VALUES (?, ?, ?, ?, ?, ?)");
                $insAs->execute([$pIdInt, $fecha, $horaEnt, $horaSal, $estado, $obs]);
                $asistCount++;
            }
        }
    }
    echo "   [OK] Marcaciones de asistencia iniciales registradas en rrhh.Asistencia.\n";

    // -------------------------------------------------------------------------
    // 9. PERMISOS Y LICENCIAS (rrhh.PermisoLaboral)
    // -------------------------------------------------------------------------
    echo "9. Sembrando licencias y permisos en rrhh.PermisoLaboral...\n";

    $tpCittId = $tipoPermisoIds['Descanso Médico (CITT EsSalud)'];
    $tpPartId = $tipoPermisoIds['Permiso por Asuntos Particulares'];
    $tpCapId  = $tipoPermisoIds['Permiso por Capacitación Oficial'];

    $permisosData = [
        [
            'PersonalID' => (int)$personales[1], // Luis Díaz
            'TipoID'     => $tpCittId,
            'Inicio'     => '2026-09-12',
            'Fin'        => '2026-09-14',
            'Motivo'     => 'CITT: A-025-458921-26 - Descanso médico por lumbalgia aguda certificada por EsSalud Pacasmayo',
            'Estado'     => 'APROBADO'
        ],
        [
            'PersonalID' => (int)$personales[0], // Ana Torres
            'TipoID'     => $tpCapId,
            'Inicio'     => '2026-09-25',
            'Fin'        => '2026-09-26',
            'Motivo'     => 'Curso Taller Nacional de Reanimación Cardiopulmonar Avanzada - GERESA LL',
            'Estado'     => 'APROBADO'
        ],
        [
            'PersonalID' => (int)$personales[2], // Carlos Vega
            'TipoID'     => $tpPartId,
            'Inicio'     => '2026-09-24',
            'Fin'        => '2026-09-24',
            'Motivo'     => 'Trámite personal documentado en Trujillo',
            'Estado'     => 'PENDIENTE'
        ]
    ];

    foreach ($permisosData as $pm) {
        $chkPm = $pdo->prepare("SELECT COUNT(*) FROM rrhh.PermisoLaboral WHERE PersonalID = ? AND FechaInicio = ? AND TipoPermisoLaboralID = ?");
        $chkPm->execute([$pm['PersonalID'], $pm['Inicio'], $pm['TipoID']]);
        if ((int)$chkPm->fetchColumn() === 0) {
            $insPm = $pdo->prepare("INSERT INTO rrhh.PermisoLaboral (PersonalID, TipoPermisoLaboralID, FechaInicio, FechaFin, Motivo, Estado) VALUES (?, ?, ?, ?, ?, ?)");
            $insPm->execute([$pm['PersonalID'], $pm['TipoID'], $pm['Inicio'], $pm['Fin'], $pm['Motivo'], $pm['Estado']]);
        }
    }
    echo "   [OK] Permisos y licencias CITT listos en rrhh.PermisoLaboral.\n";

    // -------------------------------------------------------------------------
    // 10. BITACORA DE AUDITORIA (auditoria.EventoAuditoria)
    // -------------------------------------------------------------------------
    echo "10. Registrando evento de vinculación en auditoria.EventoAuditoria...\n";

    $adminUsrId = (int)$pdo->query("SELECT UsuarioID FROM seguridad.Usuario WHERE NombreUsuario = 'admin'")->fetchColumn();

    $insAud = $pdo->prepare("
        INSERT INTO auditoria.EventoAuditoria (
            UsuarioID, SistemaID, FechaHora, Modulo, Accion, TablaAfectada, RegistroID, DireccionIP, Descripcion
        ) VALUES (
            ?, ?, SYSDATETIME(), 'SISTEMA', 'VINCULAR_BD', 'BD_HOSPITAL', 'ALL', '127.0.0.1',
            'Vinculación exitosa e inicialización de datos maestros en el servidor SQL Server 2022'
        )
    ");
    $insAud->execute([$adminUsrId, $sistemaId]);
    echo "   [OK] Evento de auditoría transaccional registrado correctamente.\n\n";

    echo "=========================================================\n";
    echo "   ¡SEMILLERO COMPLETADO CON TOTAL ÉXITO!\n";
    echo "   Todas las tablas institucionales quedaron pobladas\n";
    echo "   y vinculadas sin modificar ningún nombre de tabla.\n";
    echo "=========================================================\n";

} catch (PDOException $e) {
    echo "\n[ERROR EN SQL SERVER]: " . $e->getMessage() . "\n";
    exit(1);
}
