<?php
/** Fixture exclusivo para el contexto CLI simulado de integration.py. */
class ReportFixturePDO extends PDO {
    public function __construct(private string $scenario) {}
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false { $s=$this->prepare($query); $s->execute(); return $s; }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $rows = [];
        if (str_contains($query,'FROM organizacion.Servicio')) return new ReportFixtureStatement([['id'=>7,'nombre'=>'Servicio de prueba','area_id'=>null]]);
        if (str_contains($query,'FROM seguridad.Sistema')) return new ReportFixtureStatement([['SistemaID'=>1]]);
        if (str_contains($query, 'FROM seguridad.Usuario u')) return new ReportFixtureStatement([['UsuarioID'=>1, 'PasswordHash'=>'fixture']]);
        if ($this->scenario === 'sql_empty') return new ReportFixtureStatement([]);
        if (str_contains($query, 'FROM rrhh.Personal p') && $this->scenario !== 'missing_worker') {
            $rows = [['PersonalID'=>1, 'DNI'=>'00000001', 'Nombres'=>'Prueba', 'ApellidoPaterno'=>'Trabajador',
                'ApellidoMaterno'=>'', 'NombreCargo'=>'Enfermería', 'ServicioID'=>7, 'NombreServicio'=>'Pruebas',
                'RegimenLaboral'=>'ASISTENCIAL', 'CondicionLaboral'=>'CAS']];
        }
        if (str_contains($query, 'FROM rrhh.PermisoLaboral pl') && $this->scenario === 'medical') {
            $rows = [['FechaInicio'=>'2019-12-20', 'FechaFin'=>'2020-02-10', 'TipoNombre'=>'CITT', 'Motivo'=>'Prueba']];
        }
        if (str_contains($query, 'FROM rrhh.Asistencia a') && $this->scenario === 'attendance_failure') {
            throw new PDOException('Fallo de consulta simulado');
        }
        if (str_contains($query, 'FROM rrhh.Personal p') && str_contains($query, 'AS personal_id')) {
            $rows = [['personal_id'=>1,'codigo_empleado'=>'TEST','dni'=>'00000001','nombre_completo'=>'Prueba', 'cargo_nombre'=>'Enfermería', 'servicio_id'=>7, 'servicio_nombre'=>'Pruebas', 'condicion_laboral'=>'CAS','regimen_laboral'=>'ASISTENCIAL','aplica_seti'=>1]];
        }
        if (str_starts_with($query, 'SELECT Version')) $rows = [['Version'=>7]];
        if (str_contains($query, 'FROM rrhh.ProgramacionMensualDetalle')) {
            for ($day=1; $day<=31; $day++) $rows[] = ['PersonalID'=>1,'Dia'=>$day,'CodigoTurno'=>$this->scenario === 'sql_primary' ? 'N' : 'M'];
        }
        if (str_contains($query, 'FROM rrhh.Turno')) {
            $rows = [
                ['TurnoID'=>1,'Codigo'=>'M','Nombre'=>'Mañana','Tipo'=>'TURNO','HoraInicio'=>'08:00:00','HoraFin'=>'14:00:00','Horas'=>6.0,'Activo'=>1,'Estado'=>1],
                ['TurnoID'=>3,'Codigo'=>'N','Nombre'=>'Guardia Nocturna','Tipo'=>'TURNO','HoraInicio'=>'20:00:00','HoraFin'=>'08:00:00','Horas'=>12.0,'Activo'=>1,'Estado'=>1]
            ];
        }
        return new ReportFixtureStatement($rows);
    }
}
class ReportFixtureStatement extends PDOStatement {
    private int $position = 0;
    public function __construct(private array $rows) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->rows[$this->position++] ?? false;
    }
    public function fetchColumn(int $column=0): mixed { return $this->rows ? array_values($this->rows[0])[$column] : false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $mode===PDO::FETCH_COLUMN ? array_map(fn($r)=>array_values($r)[0],$this->rows) : $this->rows; }
}
