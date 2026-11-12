<?php
/** El filtro consulta asignaciones reales; no modifica el alcance de autorización. */
final class ReportAreaFilter {
    public static function id(): ?int {
        $value = trim((string)($_GET['area'] ?? ''));
        if ($value === '') return null;
        if (!ctype_digit($value) || (int)$value < 1) apiError(400, 'El área debe ser un identificador válido.');
        return (int)$value;
    }
    public static function requirePersonal(PDO $pdo, int $personalId, ?int $areaId): void {
        if ($areaId === null) return;
        $stmt = $pdo->prepare('SELECT 1 FROM rrhh.AsignacionPersonal WHERE PersonalID=? AND AreaID=? AND EsActual=1 AND Activo=1');
        $stmt->execute([$personalId, $areaId]);
        if (!$stmt->fetchColumn()) apiError(404, 'El trabajador no tiene una asignación actual en el área seleccionada.');
    }
}
