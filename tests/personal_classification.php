<?php
require_once __DIR__.'/../includes/PersonalRepository.php';
require_once __DIR__.'/../includes/WorkloadPolicy.php';
function checkClassification(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$legacy=PersonalRepository::normalize(['regimen_laboral'=>'ASISTENCIAL']);
checkClassification($legacy['tipo_personal']==='ASISTENCIAL' && !isset($legacy['regimen_laboral']) && !isset($legacy['grupo_ocupacional']),'Se inventó régimen legal o grupo.');
$explicit=PersonalRepository::normalize(['regimen_laboral'=>'DL 276','tipo_personal'=>'ASISTENCIAL']);
checkClassification($explicit['regimen_laboral']==='DL 276' && $explicit['tipo_personal']==='ASISTENCIAL','Se mezcló clasificación con régimen legal.');
$unknown=PersonalRepository::normalize(['tipo_personal'=>'','regimen_laboral'=>'']);
checkClassification(array_key_exists('tipo_personal',$unknown) && $unknown['tipo_personal']===null && $unknown['regimen_laboral']===null,'No permite datos sin confirmar.');
foreach (['[PERSONAL] ','[HORARIOS] ',''] as $prefix) {
    checkClassification(WorkloadPolicy::fromAudit($prefix.'{"tipo_personal":"ASISTENCIAL"}')==='ASISTENCIAL','No se reconoce la clasificación auditada.');
}
checkClassification(WorkloadPolicy::fromAudit('{"tipo_personal":null}')===null,'Se inventó una clasificación.');
try { PersonalRepository::normalize(['tipo_personal'=>'SIS']); throw new RuntimeException('Clasificación inválida aceptada.'); } catch (InvalidArgumentException $e) {}
require __DIR__.'/personal_search.php';
checkClassification(!str_contains($pdo->sql,"CHARINDEX('SIS'"),'Se sigue clasificando Asistente Social por coincidencia SIS.');
checkClassification(str_contains($pdo->sql,"tipo_personal_confirmado") && str_contains($pdo->sql,"ws.Codigo="),'No se consulta la confirmación aislada por sistema.');
echo "OK: clasificación explícita independiente del régimen, datos desconocidos y auditoría por sistema.\n";
