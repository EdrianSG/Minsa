<?php
require_once __DIR__.'/../includes/PersonalSearch.php';
$person=['nombres'=>'José Luis','apellido_paterno'=>'Pérez','apellido_materno'=>'García',
    'nombre_completo'=>'Pérez García, José Luis','dni'=>'00123456','codigo_empleado'=>'EMP_12',
    'cargo_nombre'=>'ENFERMERO','profesion_nombre'=>null,'biostar_user_id'=>'bio-7'];
$matches=['Pérez','Pérez García','Perez Garcia Jose','José Pérez','Luis García','garcia,   jose',
    "  PÉREZ\tGARCIA  ",'00123456','EMP_12','bio-7','ENFERMERO José'];
$misses=['Pérez López','José Alberto','00123457','%','EMP%12'];
foreach ($matches as $query) {
    if (!PersonalSearch::matches($person,$query)) throw new RuntimeException('No encontró una búsqueda válida.');
}
foreach ($misses as $query) {
    if (PersonalSearch::matches($person,$query)) throw new RuntimeException('Aceptó un término ausente.');
}
if (!PersonalSearch::matches(['nombres'=>'Ana','apellido_paterno'=>null,'nombre_completo_original'=>'Ríos Torres Ana'],'ana rios torres')) {
    throw new RuntimeException('Se perdió el nombre original informado.');
}
if (PersonalSearch::matches(['nombres'=>null],'Ana')) throw new RuntimeException('Inventó una coincidencia sin nombre.');
echo "OK: 18 búsquedas compuestas, orden libre, acentos, identificadores y datos incompletos.\n";

require_once __DIR__.'/../includes/PersonalDirectory.php';
final class SearchStatement extends PDOStatement {
    public array $parameters=[];
    public function __construct() {}
    public function bindValue(string|int $param,mixed $value,int $type=PDO::PARAM_STR): bool { $this->parameters[$param]=$value; return true; }
    public function execute(?array $params=null): bool { return true; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {
        $row=['personal_id'=>null];
        foreach (['total_registros','total','asistenciales','administrativos','nombrados','cas','pendientes_validacion','activos'] as $key) $row['__'.$key]=0;
        return [$row];
    }
}
final class SearchPDO extends PDO {
    public string $sql='';
    public SearchStatement $statement;
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->sql=$query; return $this->statement=new SearchStatement(); }
}
$pdo=new SearchPDO();
PersonalDirectory::page($pdo,['q'=>"Pérez García O'NEILL",'servicio'=>'','areaFilter'=>'','condicion'=>'','regimen'=>'','estado'=>null,'page'=>1,'limit'=>10]);
foreach (['Pérez','García',"O'NEILL"] as $index=>$term) {
    if (($pdo->statement->parameters[':nameTerm'.$index]??null)!==$term || ($pdo->statement->parameters[':bioTerm'.$index]??null)!==$term) {
        throw new RuntimeException('La consulta SQL no recibió todos los términos separados.');
    }
}
if (str_contains($pdo->sql,"O'NEILL")) throw new RuntimeException('Un valor de búsqueda se interpoló en el SQL.');
echo "OK: SQL construido con tres términos independientes y parámetros protegidos.\n";
