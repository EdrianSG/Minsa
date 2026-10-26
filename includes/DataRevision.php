<?php
class DataRevision {
    public static function read(PDO $pdo): ?string {
        try {
            $stmt = $pdo->prepare('SELECT Revision FROM seguridad.VersionDatos WHERE ID = 1');
            $stmt->execute();
            $value = $stmt->fetchColumn();
            return $value === false ? null : (string)$value;
        } catch (PDOException $e) {
            // Antes de instalar la migración se consulta SQL en cada petición, sin reutilizar caché.
            error_log('Control de versiones SQL no disponible: ' . $e->getMessage());
            return null;
        }
    }
}
