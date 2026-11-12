<?php
require_once __DIR__ . '/SnapshotStore.php';
class TurnoCatalog {
    /** Mismo catálogo operativo para Horarios y para seleccionar turnos en Programación. */
    public static function operational(array $shifts): array {
        return array_values(array_filter($shifts, static fn($s) =>
            (int)($s['activo'] ?? $s['estado'] ?? 0) === 1
            && ($s['tipo'] ?? 'TURNO') === 'TURNO'
            && !empty($s['codigo'])
            && !str_starts_with((string)$s['codigo'], 'VAC-')));
    }
    public static function code(string $name): string {
        $name = mb_strtolower($name, 'UTF-8');
        foreach (['mañana'=>'M', 'tarde'=>'T', 'nocturna'=>'N', 'noche'=>'N', 'diurna'=>'GD', 'admin'=>'ADM', 'retén'=>'RET'] as $part=>$code) {
            if (str_contains($name, $part)) return $code;
        }
        return mb_strtoupper(mb_substr($name, 0, 3, 'UTF-8'), 'UTF-8');
    }
    public static function load(PDO $pdo): array {
        static $hasColor = null;
        if ($hasColor === null) {
            $hasColor = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'rrhh' AND TABLE_NAME = 'Turno' AND COLUMN_NAME = 'Color'")->fetchColumn();
        }
        $colorField = $hasColor ? 'Color' : 'CAST(NULL AS VARCHAR(20)) AS Color';
        $stmt = $pdo->prepare("SELECT TurnoID, Codigo, Nombre, Tipo, CONVERT(VARCHAR(8), HoraInicio, 108) AS HoraInicio,
            CONVERT(VARCHAR(8), HoraFin, 108) AS HoraFin, Horas, Activo, $colorField FROM rrhh.Turno WHERE Activo = 1 ORDER BY TurnoID");
        $stmt->execute();
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $horaInicio = $row['HoraInicio'];
            $horaFin = $row['HoraFin'];
            $first = strtotime('2000-01-01 ' . $horaInicio);
            $last = strtotime('2000-01-01 ' . $horaFin);
            if ($last <= $first) $last += 86400;
            $calcHours = ($horaInicio !== null && $horaFin !== null) ? ($last - $first) / 3600 : null;
            $horasComputables = isset($row['Horas']) ? (float)$row['Horas'] : $calcHours;
            $codigo = !empty($row['Codigo']) ? trim($row['Codigo']) : null;

            $defaultColor = $horasComputables >= 11.5 ? '#7c3aed' : '#0284c7';
            $savedColor = !empty($row['Color']) ? trim($row['Color']) : null;
            $color = $savedColor ?: $defaultColor;

            $result[] = [
                'turno_id'          => (int)$row['TurnoID'],
                'codigo'            => $codigo,
                'nombre'            => $row['Nombre'],
                'tipo'              => $row['Tipo'] ?? 'TURNO',
                'hora_inicio'       => $row['HoraInicio'],
                'hora_fin'          => $row['HoraFin'],
                'horas_computables' => $horasComputables,
                'estado'            => (int)$row['Activo'],
                'activo'            => (int)$row['Activo'],
                'descripcion'       => 'Turno institucional oficial',
                'cruza_medianoche'  => ($horaFin && $horaInicio && $horaFin <= $horaInicio) ? 1 : 0,
                'es_guardia'        => $horasComputables >= 11.5 ? 1 : 0,
                'color'             => $color
            ];
        }
        return $result;
    }
    /** Catálogo de programación: duración laboral desde SQL; equivalencias institucionales explícitas y bidireccionales. */
    public static function programming(array $shifts): array {
        $result = [];
        foreach ($shifts as $shift) {
            if (empty($shift['codigo'])) continue;
            $code = (string)$shift['codigo'];
            $result[$code] = [
                'codigo' => $code,
                'tecla'  => $code,
                'nombre' => $shift['nombre'],
                'horas'  => isset($shift['horas_computables']) ? (float)$shift['horas_computables'] : null,
                'color'  => $shift['color'] ?? '#0284c7'
            ];
        }

        // Equivalencias institucionales bidireccionales (códigos numéricos y letras MINSA)
        $equivMap = [
            '1'     => 'M',
            '2'     => 'T',
            '3'     => 'N',
            '1/2'   => 'GD',
            '2/3'   => 'GN',
            '1/2/3' => 'G24',
            'AD'    => 'ADM'
        ];
        foreach ($equivMap as $c1 => $c2) {
            if (isset($result[$c1]) && !isset($result[$c2])) {
                $result[$c2] = array_replace($result[$c1], ['codigo' => $c2, 'tecla' => $c2]);
            } elseif (isset($result[$c2]) && !isset($result[$c1])) {
                $result[$c1] = array_replace($result[$c2], ['codigo' => $c1, 'tecla' => $c1]);
            }
        }

        // Aliases complementarios
        foreach (['GN' => 'N', 'V1' => 'M', 'V2' => 'T', 'V3' => 'N'] as $alias => $target) {
            if (!isset($result[$alias]) && isset($result[$target])) {
                $result[$alias] = array_replace($result[$target], ['codigo' => $alias, 'tecla' => $alias]);
            }
        }

        // Turnos de emergencia asumidos en vacaciones (con crédito al trabajador)
        foreach (['VAC-1' => '1', 'VAC-2' => '2', 'VAC-3' => '3', 'VAC-M' => 'M', 'VAC-T' => 'T', 'VAC-N' => 'N'] as $vCode => $baseCode) {
            if (!isset($result[$vCode]) && isset($result[$baseCode])) {
                $result[$vCode] = [
                    'codigo' => $vCode,
                    'tecla'  => $vCode,
                    'nombre' => 'Emergencia: ' . $result[$baseCode]['nombre'],
                    'horas'  => $result[$baseCode]['horas'],
                    'color'  => '#e11d48'
                ];
            }
        }

        // El crédito vacacional de 6 horas es una regla institucional de la aplicación
        foreach ([
            'LIB'  => ['Descanso / Libre', 0.0, '#94a3b8'],
            'VAC'  => ['Vacaciones', 6.0, '#0891b2'],
            'V'    => ['Vacaciones', 6.0, '#0891b2'],
            'DESC' => ['Descanso médico', 0.0, '#94a3b8'],
            'C'    => ['Descanso médico', 0.0, '#94a3b8'],
            'CITT' => ['CITT', 0.0, '#94a3b8'],
            'F'    => ['Feriado', 0.0, '#94a3b8'],
            'P'    => ['Permiso', 0.0, '#94a3b8'],
            'PER'  => ['Permiso', 0.0, '#94a3b8']
        ] as $code => [$name, $hours, $color]) {
            if (!isset($result[$code])) {
                $result[$code] = ['codigo' => $code, 'tecla' => $code, 'nombre' => $name, 'horas' => $hours, 'color' => $color];
            }
        }

        return $result;
    }

}
