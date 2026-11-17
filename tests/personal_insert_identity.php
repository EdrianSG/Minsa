<?php
require_once __DIR__.'/../includes/PersonalRepository.php';
final class IdentityStatement extends PDOStatement {
    public int $index=0;
    public bool $closed=false;
    public array $params=[];
    public function __construct(public array $results) {}
    public function execute(?array $params=null): bool { $this->params=$params??[]; return true; }
    public function columnCount(): int { return $this->results[$this->index]===null?0:1; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->results[$this->index]; }
    public function nextRowset(): bool { return ++$this->index<count($this->results); }
    public function closeCursor(): bool { $this->closed=true; return true; }
}
final class IdentityPDO extends PDO {
    public string $sql='';
    public function __construct(public IdentityStatement $statement) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->sql=$query; return $this->statement; }
    public function lastInsertId(?string $name=null): string|false { throw new RuntimeException('Controlador sin lastInsertId.'); }
}
$method=new ReflectionMethod(PersonalRepository::class,'insertIdentity');
foreach ([[null,['inserted_id'=>'42']],[['trigger_result'=>999],null,['inserted_id'=>42]]] as $results) {
    $stmt=new IdentityStatement($results); $pdo=new IdentityPDO($stmt);
    $id=$method->invoke(null,$pdo,'INSERT INTO rrhh.Personal (Nombres) VALUES (?)',['Nombre de prueba']);
    if ($id!==42 || !$stmt->closed || $stmt->params!==['Nombre de prueba'] || !str_contains($pdo->sql,'SCOPE_IDENTITY()')) throw new RuntimeException('Identidad o parámetros incorrectos.');
}
foreach ([[null], [['inserted_id'=>null]], [['inserted_id'=>0]]] as $results) {
    $stmt=new IdentityStatement($results); $pdo=new IdentityPDO($stmt); $rejected=false;
    try { $method->invoke(null,$pdo,'INSERT INTO rrhh.Personal DEFAULT VALUES',[]); } catch (RuntimeException $e) { $rejected=true; }
    if (!$rejected || !$stmt->closed) throw new RuntimeException('Se aceptó identidad ausente o quedó cursor abierto.');
}
echo "OK: identidad sin lastInsertId, resultados intermedios, parámetros e identidades inválidas (5 casos; sin conexión SQL).\n";
