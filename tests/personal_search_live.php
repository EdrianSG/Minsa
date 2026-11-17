<?php
/** Solo lectura de datos existentes. No imprime identidades ni crea trabajadores. */
if (PHP_SAPI!=='cli') exit(1);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/PersonalDirectory.php';
$pdo=Database::getConnection(true);
if (!$pdo) {fwrite(STDERR,"SQL no disponible.\n");exit(1);}
if ($pdo->query('SELECT DB_NAME()')->fetchColumn()!=='BD_HOSPITAL_V3') exit(1);
$person=$pdo->query("SELECT TOP 1 PersonalID,Nombres,ApellidoPaterno,ApellidoMaterno
    FROM rrhh.Personal WHERE NULLIF(ApellidoPaterno,'') IS NOT NULL AND NULLIF(ApellidoMaterno,'') IS NOT NULL
    AND NULLIF(Nombres,'') IS NOT NULL ORDER BY PersonalID")->fetch(PDO::FETCH_ASSOC);
if (!$person) throw new RuntimeException('Falta un registro existente con los campos necesarios para la prueba.');
$cases=[
 $person['ApellidoPaterno'].' '.$person['ApellidoMaterno'],
 $person['ApellidoPaterno'].' '.$person['ApellidoMaterno'].' '.$person['Nombres'],
 $person['Nombres'].' '.$person['ApellidoPaterno'],
 $person['ApellidoMaterno'].', '.$person['Nombres'],
];
foreach ($cases as $query) {
    $filters=['q'=>$query,'servicio'=>'','areaFilter'=>'','condicion'=>'','regimen'=>'','estado'=>null,'page'=>1,'limit'=>50];
    $result=PersonalDirectory::page($pdo,$filters);
    if (!in_array((string)$person['PersonalID'],array_map('strval',array_column($result['data'],'personal_id')),true)) {
        throw new RuntimeException('Una búsqueda compuesta no devolvió al trabajador existente.');
    }
}
echo "OK: 4 combinaciones de apellidos/nombres contra V3, sin escritura de registros.\n";
