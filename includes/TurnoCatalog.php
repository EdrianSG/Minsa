<?php
require_once __DIR__ . '/SnapshotStore.php';
class TurnoCatalog {
    public static function code(string $name): string {
        $name = mb_strtolower($name, 'UTF-8');
        foreach (['mañana'=>'M', 'tarde'=>'T', 'nocturna'=>'N', 'noche'=>'N', 'diurna'=>'GD', 'admin'=>'ADM', 'retén'=>'RET'] as $part=>$code) {
            if (str_contains($name, $part)) return $code;
        }
        return mb_strtoupper(mb_substr($name, 0, 3, 'UTF-8'), 'UTF-8');
    }
    public static function load(PDO $pdo): array {
        $stmt = $pdo->prepare('SELECT TurnoID, Nombre, CONVERT(VARCHAR(8), HoraInicio, 108) AS HoraInicio,
            CONVERT(VARCHAR(8), HoraFin, 108) AS HoraFin, Estado FROM rrhh.Turno ORDER BY TurnoID');
        $stmt->execute();
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $first = strtotime('2000-01-01 ' . $row['HoraInicio']);
            $last = strtotime('2000-01-01 ' . $row['HoraFin']);
            if ($last <= $first) $last += 86400;
            $result[] = ['turno_id'=>(int)$row['TurnoID'], 'codigo'=>self::code($row['Nombre']),
                'nombre'=>$row['Nombre'], 'hora_inicio'=>$row['HoraInicio'], 'hora_fin'=>$row['HoraFin'],
                'horas_computables'=>($last-$first)/3600, 'estado'=>(int)$row['Estado'],
                'descripcion'=>'Turno institucional', 'cruza_medianoche'=>$row['HoraFin'] <= $row['HoraInicio'] ? 1 : 0,
                'es_guardia'=>($last-$first) >= 41400 ? 1 : 0, 'color'=>($last-$first) >= 41400 ? '#7c3aed' : '#0284c7'];
        }
        return $result;
    }
}
