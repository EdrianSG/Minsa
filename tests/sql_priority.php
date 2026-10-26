<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/ProgramacionStore.php';
class StorePDO extends PDO {
    public array $periods = [], $details = [];
    public bool $active = false, $emptyRoster = false, $fail = false;
    private array $saved = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool { $this->saved = [$this->periods,$this->details]; $this->active=true; return true; }
    public function commit(): bool { $this->active=false; return true; }
    public function rollBack(): bool { [$this->periods,$this->details]=$this->saved; $this->active=false; return true; }
    public function prepare(string $query, array $options=[]): PDOStatement|false { return new StoreStatement($this,$query); }
}
class StoreStatement extends PDOStatement {
    private array $rows = [];
    private int $affected = 0;
    public function __construct(private StorePDO $db, private string $sql) {}
    public function execute(?array $params=null): bool {
        $p=$params ?? []; $sql=$this->sql; $this->rows=[]; $this->affected=0;
        if (str_starts_with($sql,'SELECT Version')) {
            $key=$p[':anio'].'-'.$p[':mes'];
            if (isset($this->db->periods[$key])) $this->rows=[['Version'=>$this->db->periods[$key]]];
        } elseif (str_contains($sql,'FROM rrhh.Personal p')) {
            if (!$this->db->emptyRoster) $this->rows=[['personal_id'=>1,'servicio_id'=>7,'nombre_completo'=>'Prueba','regimen_laboral'=>'ASISTENCIAL']];
        } elseif (str_starts_with($sql,'INSERT INTO rrhh.ProgramacionMensual (')) {
            $this->db->periods[$p[0].'-'.$p[1]]=1;
        } elseif (str_starts_with($sql,'UPDATE rrhh.ProgramacionMensual SET')) {
            $this->db->periods[$p[1].'-'.$p[2]]++;
        } elseif (str_starts_with($sql,'UPDATE rrhh.ProgramacionMensualDetalle')) {
            if ($this->db->fail && $p[4]===2) throw new PDOException('Fallo de disco SQL simulado');
            $key=implode('-',array_slice($p,1));
            if (isset($this->db->details[$key])) { $this->db->details[$key]=$p[0]; $this->affected=1; }
        } elseif (str_starts_with($sql,'INSERT INTO rrhh.ProgramacionMensualDetalle')) {
            if ($this->db->fail && $p[3]===2) throw new PDOException('Fallo de disco SQL simulado');
            $this->db->details[implode('-',array_slice($p,0,4))]=$p[4];
        } elseif (str_contains($sql,'SELECT PersonalID, Dia')) {
            foreach ($this->db->details as $key=>$code) {
                [$year,$month,$pid,$day]=explode('-',$key);
                if ((int)$year===$p[':anio'] && (int)$month===$p[':mes']) $this->rows[]=['PersonalID'=>(int)$pid,'Dia'=>(int)$day,'CodigoTurno'=>$code];
            }
        }
        return true;
    }
    public function fetchColumn(int $column=0): mixed { return $this->rows ? array_values($this->rows[0])[$column] : false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function rowCount(): int { return $this->affected; }
}
$count=0;
function verifySQL(bool $ok,string $label): void { global $count; if (!$ok) throw new RuntimeException($label); $count++; }
$db=new StorePDO();
$change=[['personal_id'=>1,'dia'=>1,'codigo_turno'=>'N']];
$version=ProgramacionStore::save($db,2026,9,7,1,false,$change,1);
verifySQL($version===2 && !$db->inTransaction(),'Confirma commit y versión SQL');
$data=ProgramacionStore::read($db,2026,9,7);
verifySQL($data['personal_servicio'][0]['patron'][0]==='N','Lectura conserva el turno SQL');
try { ProgramacionStore::save($db,2026,9,0,1,false,$change,1); throw new Exception('No detectó conflicto'); }
catch (ProgramacionConflict $e) { verifySQL($e->version===2 && !$db->inTransaction(),'Vista global y servicio comparten control de versiones'); }
$invalid=[['personal_id'=>99,'dia'=>1,'codigo_turno'=>'M']];
try { ProgramacionStore::save($db,2026,9,7,2,false,$invalid,1); throw new Exception('Aceptó trabajador ajeno'); }
catch (InvalidArgumentException $e) { verifySQL(count($db->details)===1 && $db->periods['2026-9']===2,'Trabajador ajeno no modifica SQL'); }
$db->fail=true;
try { ProgramacionStore::save($db,2026,9,7,2,false,[['personal_id'=>1,'dia'=>1,'codigo_turno'=>'M'],['personal_id'=>1,'dia'=>2,'codigo_turno'=>'T']],1); throw new Exception('No simuló fallo'); }
catch (PDOException $e) { verifySQL($db->details['2026-9-1-1']==='N' && $db->periods['2026-9']===2,'Fallo intermedio revierte todas las celdas y la versión'); }
$db->fail=false; $db->emptyRoster=true;
verifySQL(ProgramacionStore::read($db,2026,9,7)['personal_servicio']===[],'SQL vacío conserva vacío sin datos locales o ficticios');
echo "OK: $count comprobaciones de persistencia SQL, rollback y conflictos.\n";
