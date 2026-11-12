<?php
require_once __DIR__ . '/../config/database.php';
/** Contador compartido entre sesiones, con bloqueo por archivo y ventana de 15 minutos. */
class LoginThrottle {
    public static function allow(string $identifier): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local-cli';
        return self::reserve('ip:' . $ip, 100) && self::reserve('user:' . $ip . ':' . mb_strtolower($identifier, 'UTF-8'), 10);
    }
    private static function reserve(string $key, int $limit): bool {
        $dir = getenv('AUTH_RATE_PATH') ?: sys_get_temp_dir() . '/sigh-login-' . Database::sourceId();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('No se pudo verificar el límite de acceso.');
        $handle = @fopen($dir . '/' . hash('sha256', $key) . '.json', 'c+');
        if (!$handle) throw new RuntimeException('No se pudo verificar el límite de acceso.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('No se pudo verificar el límite de acceso.');
            $state = json_decode(stream_get_contents($handle), true);
            if (!is_array($state) || time() - ($state['start'] ?? 0) >= 900) $state = ['start'=>time(), 'count'=>0];
            if ($state['count'] >= $limit) return false;
            $state['count']++;
            rewind($handle); ftruncate($handle, 0);
            if (fwrite($handle, json_encode($state)) === false) throw new RuntimeException('No se pudo verificar el límite de acceso.');
            return true;
        } finally {
            flock($handle, LOCK_UN); fclose($handle);
        }
    }
}
