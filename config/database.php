<?php
/**
 * Configuración centralizada de Base de Datos - Hospital Distrital de Pacasmayo
 * Motor: Microsoft SQL Server / SQL Server Express
 * 
 * Compatible con:
 * 1. PDO_SQLSRV (Recomendado oficial de Microsoft para PHP)
 * 2. sqlsrv_* (Driver procedimental alternativo)
 */

class Database
{
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    /**
     * Parámetros de conexión predeterminados (Servidor de pruebas)
     * Pueden sobreescribirse mediante variables de entorno en el servidor
     */
    public static function getConfig(): array
    {
        return [
            'host' => getenv('DB_HOST') ?: '192.168.11.20',
            'port' => getenv('DB_PORT') ?: '1433',
            'database' => getenv('DB_NAME') ?: 'BD_HOSPITAL',
            'username' => getenv('DB_USER') ?: 'eduardo_dev',
            'password' => getenv('DB_PASS') ?: '123456789',
            'charset' => 'UTF-8',
            'encrypt' => false, // En redes locales normalmente no se requiere forzar SSL
            'trust_server_certificate' => true,
            'timeout' => 3
        ];
    }

    /**
     * Verifica si alguno de los drivers de SQL Server está disponible en el entorno PHP
     */
    public static function hasDriver(): bool
    {
        return extension_loaded('pdo_sqlsrv') || extension_loaded('sqlsrv') || extension_loaded('pdo_odbc');
    }

    /**
     * Obtiene el nombre del driver activo
     */
    public static function getAvailableDriverName(): string
    {
        if (extension_loaded('pdo_sqlsrv'))
            return 'pdo_sqlsrv';
        if (extension_loaded('sqlsrv'))
            return 'sqlsrv';
        if (extension_loaded('pdo_odbc'))
            return 'pdo_odbc';
        return 'none';
    }

    /**
     * Retorna la conexión PDO única (Singleton) o null si el driver o servidor no está disponible
     */
    public static function getConnection(bool $forceFreshCheck = false): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = self::getConfig();

        if ($config['username'] === '' || $config['password'] === '') {
            self::$lastError = 'Configure DB_USER y DB_PASS en el entorno del servidor.';
            return null;
        }

        if (!extension_loaded('pdo_sqlsrv')) {
            self::$lastError = "El driver 'pdo_sqlsrv' no está instalado en este entorno PHP. Se utilizará modo desacoplado / mock.";
            return null;
        }

        // Circuit Breaker: Si recientemente falló la conexión (en los últimos 15 segundos),
        // no bloquear los hilos esperando timeouts de red; retornar null inmediatamente.
        $cacheFile = sys_get_temp_dir() . '/sgh_db_offline.json';
        if (!$forceFreshCheck && file_exists($cacheFile)) {
            $cached = @json_decode(file_get_contents($cacheFile), true);
            if ($cached && isset($cached['time']) && (time() - $cached['time']) < 15) {
                self::$lastError = $cached['error'] ?? "Base de datos temporalmente no disponible.";
                return null;
            }
        }

        // Verificación rápida de disponibilidad de socket (timeout 0.4s) para no bloquear requests
        $probe = @fsockopen($config['host'], (int) $config['port'], $errno, $errstr, 0.4);
        if (!$probe) {
            $msg = "El servidor SQL Server ({$config['host']}:{$config['port']}) no responde en la red.";
            self::$lastError = $msg;
            @file_put_contents($cacheFile, json_encode(['time' => time(), 'error' => $msg]));
            return null;
        }
        fclose($probe);

        // Si el socket respondió, borrar cualquier caché de error previo
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        try {
            $encryptStr = $config['encrypt'] ? 'yes' : 'no';
            $trustCertStr = $config['trust_server_certificate'] ? 'true' : 'false';

            $dsn = sprintf(
                "sqlsrv:Server=%s,%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=%d",
                $config['host'],
                $config['port'],
                $config['database'],
                $encryptStr,
                $trustCertStr,
                $config['timeout']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
            ];

            self::$instance = new PDO($dsn, $config['username'], $config['password'], $options);
            self::$lastError = null;
            return self::$instance;

        } catch (PDOException $e) {
            self::$lastError = "Error de conexión a SQL Server: " . $e->getMessage();
            @file_put_contents($cacheFile, json_encode(['time' => time(), 'error' => self::$lastError]));
            return null;
        }
    }

    /**
     * Retorna el último mensaje de error o estado
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Prueba rápida de conexión sin interrumpir la ejecución
     */
    public static function testConnection(): array
    {
        $config = self::getConfig();
        $driver = self::getAvailableDriverName();

        if ($driver === 'none') {
            return [
                'ok' => false,
                'driver' => 'none',
                'server' => $config['host'] . ':' . $config['port'],
                'message' => "Driver SQL Server no disponible en este runtime PHP. Sistema operando con datos de capa desacoplada."
            ];
        }

        $conn = self::getConnection(true);
        if ($conn) {
            return [
                'ok' => true,
                'driver' => $driver,
                'server' => $config['host'] . ':' . $config['port'],
                'database' => $config['database'],
                'message' => "Conexión exitosa con el servidor SQL Server."
            ];
        }

        return [
            'ok' => false,
            'driver' => $driver,
            'server' => $config['host'] . ':' . $config['port'],
            'message' => self::$lastError ?? "No se pudo establecer conexión con SQL Server."
        ];
    }
}
