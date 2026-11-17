<?php
/**
 * Helper de Auditoría Institucional - Hospital Distrital de Pacasmayo
 * Mapeado a la tabla auditoria.EventoAuditoria
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SystemContext.php';

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
        ?string $descripcion = null,
        ?PDO $pdo = null
    ): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if ($pdo === null) {
            $pdo = $connection ?? Database::getConnection();
        }
        if ($pdo !== null) {
            try {
                static $hasModuloCol = null;
                if ($hasModuloCol === null) {
                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                        $hasModuloCol = true;
                    } else {
                        $hasModuloCol = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'auditoria' AND TABLE_NAME = 'EventoAuditoria' AND COLUMN_NAME = 'Modulo'")->fetchColumn();
                    }
                }

                $detalleCompleto = $hasModuloCol ? ($descripcion ?? '') : ("[$modulo] " . ($descripcion ?? ''));
                if ($hasModuloCol) {
                    $stmt = $pdo->prepare("
                        INSERT INTO auditoria.EventoAuditoria (
                            UsuarioID, SistemaID, Modulo, FechaHora, Accion,
                            TablaAfectada, RegistroID, DireccionIP, Descripcion
                        ) VALUES (
                            :usuario_id, :sistema_id, :modulo, SYSDATETIME(), :accion,
                            :tabla, :registro_id, :ip, :descripcion
                        )
                    ");
                    return $stmt->execute([
                        ':usuario_id'   => ($usuarioId && $usuarioId > 0) ? $usuarioId : null,
                        ':sistema_id'   => SystemContext::id($pdo),
                        ':modulo'       => substr($modulo, 0, 100),
                        ':accion'       => substr($accion, 0, 50),
                        ':tabla'        => $tablaAfectada ? substr($tablaAfectada, 0, 150) : null,
                        ':registro_id'  => $registroId ? substr((string)$registroId, 0, 100) : null,
                        ':ip'           => substr($ip, 0, 45),
                        ':descripcion'  => mb_substr($detalleCompleto, 0, 1000, 'UTF-8')
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO auditoria.EventoAuditoria (
                            UsuarioID, SistemaID, FechaHora, Accion,
                            TablaAfectada, RegistroID, DireccionIP, Descripcion
                        ) VALUES (
                            :usuario_id, :sistema_id, SYSDATETIME(), :accion,
                            :tabla, :registro_id, :ip, :descripcion
                        )
                    ");
                    return $stmt->execute([
                        ':usuario_id'   => ($usuarioId && $usuarioId > 0) ? $usuarioId : null,
                        ':sistema_id'   => SystemContext::id($pdo),
                        ':accion'       => substr($accion, 0, 50),
                        ':tabla'        => $tablaAfectada ? substr($tablaAfectada, 0, 150) : null,
                        ':registro_id'  => $registroId ? substr((string)$registroId, 0, 100) : null,
                        ':ip'           => substr($ip, 0, 45),
                        ':descripcion'  => mb_substr($detalleCompleto, 0, 1000, 'UTF-8')
                    ]);
                }
            } catch (Exception $e) {
                error_log('Auditoría SQL: ' . $e->getMessage());
                // Una operación transaccional debe revertirse si falta su auditoría.
                if ($pdo->inTransaction()) return false;
            }
        }

        require_once __DIR__ . '/AuditFallback.php';
        $saved = AuditFallback::append([
            'usuario_id'=>$usuarioId, 'nombre_usuario'=>$_SESSION['user']['username'] ?? null,
            'fecha_hora'=>date('c'), 'modulo'=>$modulo, 'accion'=>$accion,
            'tabla_afectada'=>$tablaAfectada, 'registro_id'=>$registroId,
            'direccion_ip'=>$ip, 'descripcion'=>$descripcion
        ]);
        error_log($saved ? 'Auditoría guardada en archivo; pendiente de consolidación SQL.' : 'ERROR: no se pudo persistir la auditoría.');
        return (bool)$saved;
    }
}
