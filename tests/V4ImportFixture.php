<?php
/** Simulación de persistencia para probar importador sin conexiones ni datos de producción. */
class V4ImportPDO extends PDO {
    public array $queries=[];
    public array $staffMap=['BIO-A'=>42]; public bool $failAudit=false; public bool $hasModulo=true;
    public array $imports=[]; public array $marks=[]; public array $incidents=[]; public bool $tx=false; public array $backup=[];
    public function __construct() {}
    public function beginTransaction(): bool { $this->backup=[$this->imports,$this->marks,$this->incidents]; return $this->tx=true; }
    public function commit(): bool { $this->tx=false; return true; }
    public function rollBack(): bool { [$this->imports,$this->marks,$this->incidents]=$this->backup; $this->tx=false; return true; }
    public function inTransaction(): bool { return $this->tx; }
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->queries[]=$query; return new V4ImportStatement($this,$query); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false { $s=$this->prepare($query); $s->execute(); return $s; }
}
class V4ImportStatement extends PDOStatement {
    private array $rows=[]; private int $count=0;
    public function __construct(private V4ImportPDO $db, private string $sql) {}
    public function execute(?array $params=null): bool {
        $p=$params??[]; $this->rows=[]; $this->count=0;
        if (str_contains($this->sql,'sp_getapplock')) $this->rows=[[0]];
        elseif (str_contains($this->sql,'INFORMATION_SCHEMA.COLUMNS')) $this->rows=$this->db->hasModulo ? [[1]] : [];
        elseif (str_contains($this->sql,'FROM seguridad.Sistema')) $this->rows=[[7]];
        elseif (str_contains($this->sql,'FROM rrhh.VinculacionBiometrica')) $this->rows=array_map(fn($bio,$id)=>['BiostarUserID'=>$bio,'PersonalID'=>$id],array_keys($this->db->staffMap),array_values($this->db->staffMap));
        elseif (str_contains($this->sql,'SELECT TOP 1') && str_contains($this->sql,'FROM rrhh.ImportacionBiometrica')) {
            foreach ($this->db->imports as $id=>$r) if ($r['hash']===reset($p)) $this->rows=[['ImportacionID'=>$id,'Estado'=>$r['state']]];
        } elseif (str_contains($this->sql,'INSERT INTO rrhh.ImportacionBiometrica')) {
            $id=count($this->db->imports)+1; $this->db->imports[$id]=['hash'=>$p[':hash'],'state'=>'EN_PROCESO']; $this->rows=[['ImportacionBiometricaID'=>$id]];
        } elseif (str_contains($this->sql,'INSERT INTO rrhh.MarcacionBiometrica')) {
            $key=$p[':bUser'].'|'.$p[':fec'].'|'.$p[':devId'].'|'.$p[':ev'];
            if (!isset($this->db->marks[$key])) { $this->db->marks[$key]=$p; $this->count=1; }
        } elseif (str_contains($this->sql,'INSERT INTO rrhh.IncidenciaBiometrica')) $this->db->incidents[]=$p;
        elseif (str_contains($this->sql,'UPDATE rrhh.ImportacionBiometrica')) $this->db->imports[$p[':id']]['state']='COMPLETADO';
        elseif (str_contains($this->sql,'INSERT INTO auditoria.EventoAuditoria')) { if($this->db->failAudit) throw new RuntimeException('Fallo de auditoría simulado.'); }
        else throw new RuntimeException('SQL inesperado en fixture: '.$this->sql);
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return array_shift($this->rows)??false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $mode===PDO::FETCH_COLUMN ? array_map(fn($r)=>array_values($r)[0],$this->rows) : $this->rows; }
    public function fetchColumn(int $column=0): mixed { return $this->rows ? array_values($this->rows[0])[$column] : false; }
    public function rowCount(): int { return $this->count; }
    public function closeCursor(): bool { return true; }
}
