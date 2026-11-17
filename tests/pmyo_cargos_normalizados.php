<?php
require_once __DIR__.'/../includes/PmyoCatalog.php';
$data=PmyoCatalog::enrich([
    'areas'=>[['id'=>18,'nombre'=>'ENFERMERIA'],['id'=>15,'nombre'=>'ASISTENTE SOCIAL'],['id'=>20,'nombre'=>'FARMACIA'],['id'=>40,'nombre'=>'REHABILITACIÓN'],['id'=>12,'nombre'=>'SIS (SEGURO INTEGRAL DE SALUD)']],
    'cargos'=>[['id'=>18,'nombre'=>'ENFERMERO'],['id'=>33,'nombre'=>'LIC ENFERMERIA'],['id'=>65,'nombre'=>'TEC ENFERMERIA'],['id'=>35,'nombre'=>'LIC TRABAJO SOCIAL'],['id'=>36,'nombre'=>'TEC FARMACIA'],['id'=>37,'nombre'=>'TECNICA FISIOTERAPEUTA'],['id'=>38,'nombre'=>'TEC ADMINISTRATIVO']],
    'profesiones'=>[['id'=>7,'codigo'=>'ENF'],['id'=>8,'codigo'=>'TENF'],['id'=>9,'codigo'=>'TSOC'],['id'=>10,'codigo'=>'TFAR']],
    'especialidades'=>[]
]);
$general=$data['cargos'][0];
$licensed=$data['cargos'][1];
$technical=$data['cargos'][2];
$checks=[
    $general['nombre']==='ENFERMERO',
    $general['perfil_pmyo']===true,
    $general['areas_pmyo']===[18],
    $general['profesion_propuesta_id']===7,
    $licensed['nombre']==='LIC ENFERMERIA' && $licensed['areas_pmyo']===[18],
    $technical['nombre']==='TEC ENFERMERIA' && $technical['profesion_propuesta_id']===8,
    $data['areas'][1]['nombre']==='ASISTENTE SOCIAL' && $data['areas'][1]['pmyo']===true,
    $data['cargos'][3]['areas_pmyo']===[15] && $data['cargos'][3]['profesion_propuesta_id']===9,
    $data['cargos'][4]['nombre']==='TEC FARMACIA' && $data['cargos'][4]['perfil_pmyo']===true,
    $data['cargos'][4]['areas_pmyo']===[20] && $data['cargos'][4]['profesion_propuesta_id']===10,
    $data['areas'][3]['pmyo']===true && $data['cargos'][5]['areas_pmyo']===[40],
    $data['areas'][4]['pmyo']===true && $data['cargos'][6]['areas_pmyo']===[12]
];
if (in_array(false,$checks,true)) throw new RuntimeException('Falló la compatibilidad de los cargos normalizados con el selector PMYO.');
echo "OK: 12 comprobaciones de cargos, rehabilitación y SIS normalizados.\n";
