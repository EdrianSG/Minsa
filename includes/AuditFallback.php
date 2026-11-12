<?php
require_once __DIR__ . '/../config/database.php';
/** Respaldo durable de eventos cuando no se puede escribir en SQL. No simula éxito SQL. */
class AuditFallback {
    public static function append(array $event): bool {
        $directory = getenv('AUDIT_PATH') ?: __DIR__ . '/../storage/audit';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true)) return false;
        $file = $directory . '/' . Database::sourceId() . '-' . date('Y-m') . '.jsonl';
        $data = json_encode($event + ['database_source'=>Database::sourceId(), 'persistencia'=>'ARCHIVO_PENDIENTE_SQL'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $data !== false && @file_put_contents($file, $data . "\n", FILE_APPEND | LOCK_EX) === strlen($data) + 1;
    }
}
