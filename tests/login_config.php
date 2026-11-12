<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/PasswordHashFormat.php';
$count=0;
function loginCheck(bool $ok,string $why): void { global $count; ++$count; if (!$ok) throw new RuntimeException($why); }
$h=password_hash('solo-prueba-local',PASSWORD_BCRYPT,['cost'=>4]);
foreach (['2y','2a','2b'] as $format) {
    $candidate=substr_replace($h,$format,1,2);
    loginCheck(PasswordHashFormat::supported($candidate),'Variante bcrypt válida rechazada: '.$format);
    loginCheck(password_verify('solo-prueba-local',$candidate),'Contraseña de bcrypt no verificable.');
}
loginCheck(!PasswordHashFormat::supported('texto sin hash'),'Texto plano admitido para recuperación.');
$oldUser=getenv('DB_USER'); $oldPass=getenv('DB_PASS');
try {
    // Variables explícitas vacías prevalecen sobre archivo local, sin intentar conectar.
    putenv('DB_USER='); putenv('DB_PASS=');
    loginCheck(Database::getConfig()['username']==='' && Database::getConfig()['password']==='','Configuración local ignoró las variables de entorno.');
    $method=new ReflectionMethod(Auth::class,'performLogin');
    $result=$method->invoke(null,'usuario-prueba','clave-prueba');
    loginCheck(!$result['ok'] && $result['status']===503,'Fallo de conexión se presentó como credenciales incorrectas.');
} finally {
    $oldUser===false ? putenv('DB_USER') : putenv('DB_USER='.$oldUser);
    $oldPass===false ? putenv('DB_PASS') : putenv('DB_PASS='.$oldPass);
}
echo "OK: $count verificaciones de hashes, configuración y error de conexión sin acceso a SQL Server.\n";
