<?php
require_once __DIR__.'/../includes/TurnoCatalog.php';
$rows=[
 ['turno_id'=>42,'codigo'=>'X42','nombre'=>'Turno real','tipo'=>'TURNO','activo'=>1,'horas_computables'=>7],
 ['turno_id'=>43,'codigo'=>'INACTIVO','tipo'=>'TURNO','activo'=>0],
 ['turno_id'=>44,'codigo'=>'VAC','tipo'=>'AUSENCIA','activo'=>1],
 ['turno_id'=>45,'codigo'=>'VAC-X42','tipo'=>'TURNO','activo'=>1],
];
$selected=TurnoCatalog::operational($rows);
if(count($selected)!==1 || $selected[0]['turno_id']!==42 || $selected[0]['codigo']!=='X42') throw new RuntimeException('Se ofrecieron turnos ajenos al catálogo operativo.');
if(TurnoCatalog::operational([])!==[]) throw new RuntimeException('Se inventaron turnos para un catálogo vacío.');
$historic=TurnoCatalog::programming([['codigo'=>'M','nombre'=>'Mañana','horas_computables'=>7]]);
if(!isset($historic['1'],$historic['VAC-M'])) throw new RuntimeException('Se perdieron equivalencias históricas.');
echo "OK: catálogo operativo compartido, activos, ausencias separadas, catálogo vacío e historial conservado.\n";
