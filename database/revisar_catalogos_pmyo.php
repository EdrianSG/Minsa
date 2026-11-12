<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../config/database.php';
$p=Database::getConnection(true); if (!$p) exit(1);
if ($p->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V4') exit(1);
$queries=[
 'areas'=>'SELECT a.AreaID,a.Codigo,a.Nombre,a.DepartamentoID,d.Nombre AS Departamento FROM organizacion.Area a LEFT JOIN organizacion.Departamento d ON d.DepartamentoID=a.DepartamentoID ORDER BY a.AreaID',
 'profesiones'=>'SELECT ProfesionID,Codigo,Nombre,Activo FROM rrhh.Profesion ORDER BY ProfesionID',
 'especialidades'=>'SELECT EspecialidadID,ProfesionID,Nombre FROM rrhh.Especialidad ORDER BY EspecialidadID',
 'cargos'=>'SELECT CargoID,Codigo,Nombre FROM rrhh.Cargo ORDER BY CargoID',
 'padron_operativo'=>"SELECT DepartamentoOriginal AS area,COUNT(*) AS total,COUNT(DISTINCT CargoOriginal) AS perfiles FROM rrhh.PadronHorarioOrigen GROUP BY DepartamentoOriginal ORDER BY DepartamentoOriginal",
 'perfiles_operativos'=>"SELECT CargoOriginal AS perfil,COUNT(*) AS total FROM rrhh.PadronHorarioOrigen GROUP BY CargoOriginal ORDER BY CargoOriginal",
 'padron_general'=>"SELECT COUNT(*) AS registros,COUNT(DISTINCT DepartamentoOriginal) AS areas,COUNT(DISTINCT CargoOriginal) AS perfiles FROM rrhh.PadronBiometricoOrigen",
];
$out=[]; foreach($queries as $key=>$sql) $out[$key]=$p->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
