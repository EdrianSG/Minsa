<?php
/** Fixture exclusivo para el contexto CLI simulado de integration.py. */
class ReportFixturePDO extends PDO {
    public function __construct(private string $scenario) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $rows = [];
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
        if (str_contains($query, 'FROM rrhh.Turno ORDER')) {
            $rows = [['TurnoID'=>1,'Nombre'=>'Mañana','HoraInicio'=>'08:00','HoraFin'=>'14:00','Estado'=>1], ['TurnoID'=>3,'Nombre'=>'Guardia Nocturna','HoraInicio'=>'20:00','HoraFin'=>'08:00','Estado'=>1]];
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
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
