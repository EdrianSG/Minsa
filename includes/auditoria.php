<?php
/**
 * Helper de Auditoría Institucional - Hospital Distrital de Pacasmayo
 * Mapeado a la tabla auditoria.EventoAuditoria
 */

require_once __DIR__ . '/../config/database.php';

class Auditoria {
    /**
     * Registra un evento en la bitácora de auditoría
     * 
     * @param int|null    $usuarioId      ID del usuario que ejecuta la acción
     * @param string      $modulo         Módulo del sistema (ej: 'PROGRAMACION', 'ASISTENCIA', 'AUSENCIAS')
     * @param string      $accion         Tipo de acción (ej: 'LOGIN', 'INSERT', 'UPDATE', 'DELETE', 'APROBAR')
     * @param string|null $tablaAfectada  Tabla del esquema afectada (ej: 'rrhh.Asistencia', 'seguridad.Usuario')
     * @param string|null $registroId     ID o identificador del registro afectado
     * @param string|null $descripcion    Detalle descriptivo legible del evento
     * @return bool
     */
    public static function registrar(
        ?int $usuarioId,
        string $modulo,
        string $accion,
        ?string $tablaAfectada = null,
        ?string $registroId = null,
        ?string $descripcion = null
    ): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        $pdo = Database::getConnection();
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO auditoria.EventoAuditoria (
                        UsuarioID, SistemaID, FechaHora, Modulo, Accion,
                        TablaAfectada, RegistroID, DireccionIP, Descripcion
                    ) VALUES (
                        :usuario_id, 1, SYSDATETIME(), :modulo, :accion,
                        :tabla, :registro_id, :ip, :descripcion
                    )
                ");
                return $stmt->execute([
                    ':usuario_id'   => $usuarioId,
                    ':modulo'       => $modulo,
                    ':accion'       => $accion,
                    ':tabla'        => $tablaAfectada,
                    ':registro_id'  => $registroId,
                    ':ip'           => substr($ip, 0, 45),
                    ':descripcion'  => $descripcion
                ]);
            } catch (Exception $e) {
                // Si falla la BD, cae en el fallback en sesión
            }
        }

        // Almacenamiento en buffer de sesión para entorno local/offline
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['auditoria_log'])) {
            $_SESSION['auditoria_log'] = [];
        }

        array_unshift($_SESSION['auditoria_log'], [
            'auditoria_id'   => count($_SESSION['auditoria_log']) + 1001,
            'usuario_id'     => $usuarioId ?: 1,
            'nombre_usuario' => 'mrodriguez',
            'fecha_hora'     => date('Y-m-d H:i:s'),
            'modulo'         => $modulo,
            'accion'         => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id'    => $registroId,
            'direccion_ip'   => $ip,
            'descripcion'    => $descripcion
        ]);

        // Limitar buffer en sesión a los últimos 100 eventos
        if (count($_SESSION['auditoria_log']) > 100) {
            array_pop($_SESSION['auditoria_log']);
        }

        return true;
    }
}
