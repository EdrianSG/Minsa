<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/BiostarCsvParser.php';
require_once __DIR__.'/V4ImportFixture.php';
$pdo=new V4ImportPDO(); $prop=new ReflectionProperty(Database::class,'instance'); $prop->setValue(null,$pdo);
$file=tempnam(sys_get_temp_dir(),'v4-csv-');
function importCheck(bool $ok,string $why): void { if (!$ok) throw new RuntimeException($why); }
try {
    $header='Fecha,Puerta,ID Dispositivo,Dispositivo,Grupo Usuario,Usuario,Evento'."\n";
    $event='Autenticación 1:N correcta (Huella)';
    $a="2026-01-01 08:00:00,P,1,D,G,BIO-A(Nombre),$event\n";
    $b="2026-01-01 08:00:20,P,1,D,G,BIO-A(Nombre),$event\n";
    $c="2026-01-01 08:01:00,P,1,D,G,000053(Nombre),$event\n";
    file_put_contents($file,$header.$a.$a.$b.$c);
    $r=BiostarCsvParser::importar($file,'fixture.csv',null);
    importCheck($r['marcaciones_insertadas']===3 && $r['marcaciones_duplicadas']===1,'Doble marca idéntica no deduplicada.');
    importCheck($r['marcaciones_consecutivas']===1,'Se perdió ráfaga válida.');
    $marks=array_values($pdo->marks);
    importCheck($marks[0][':pid']===42 && $marks[2][':pid']===null,'Se vinculó un ID sin mapeo explícito.');
    importCheck($marks[2][':bUser']==='000053','Se alteró ID de origen.');
    $r=BiostarCsvParser::importar($file,'fixture.csv',null);
    importCheck($r['ya_importado'] && count($pdo->imports)===1 && count($pdo->marks)===3,'Hash repetido duplicó importación.');
    file_put_contents($file,$header.$a.$b.$c);
    $r=BiostarCsvParser::importar($file,'otro.csv',null);
    importCheck($r['marcaciones_insertadas']===0 && $r['marcaciones_duplicadas']===3,'Otro archivo duplicó marcas existentes.');
    importCheck(!$pdo->inTransaction(),'Transacción abierta después de importar.');
    echo "OK: 7 verificaciones de importación, hash repetido, marcas idénticas, ráfagas y vínculos explícitos (PDO simulado).\n";
} finally { unlink($file); $prop->setValue(null,null); }
