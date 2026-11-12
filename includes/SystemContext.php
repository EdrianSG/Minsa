<?php
/** Identidad del sistema independiente; nunca presupone un SistemaID numérico. */
class SystemContext {
    public static function code(): string { return getenv('APP_SYSTEM_CODE') ?: 'RRHH'; }
    public static function id(PDO $pdo): int {
        $s = $pdo->prepare('SELECT SistemaID FROM seguridad.Sistema WHERE Codigo = ? AND Activo = 1');
        $s->execute([self::code()]);
        $ids = $s->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) !== 1) throw new RuntimeException('Configure un único sistema activo para APP_SYSTEM_CODE.');
        return (int)$ids[0];
    }
    public static function membershipSql(string $user = 'u'): string {
        return "EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us WHERE us.UsuarioID = {$user}.UsuarioID
            AND us.SistemaID = r.SistemaID AND us.Activo = 1
            AND (us.FechaInicio IS NULL OR us.FechaInicio <= CAST(GETDATE() AS DATE))
            AND (us.FechaFin IS NULL OR us.FechaFin >= CAST(GETDATE() AS DATE)))";
    }
}
