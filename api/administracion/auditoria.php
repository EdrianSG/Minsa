<?php
require_once __DIR__ . '/../../includes/api_bootstrap.php';
apiGuard('administracion', ['GET'], []);

/**
 * API Endpoint: /api/administracion/auditoria.php
 * Hospital Distrital de Pacasmayo - ETAPA 8
 *
 * Bitácora y Registro de Trazabilidad de Eventos:
 * Mapeado a la tabla auditoria.EventoAuditoria
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$moduloFiltro  = trim($_GET['modulo'] ?? '');
$accionFiltro  = trim($_GET['accion'] ?? '');
$usuarioFiltro = isset($_GET['usuario_id']) && $_GET['usuario_id'] !== '' ? intval($_GET['usuario_id']) : null;
$busqueda      = trim($_GET['q'] ?? '');
$limite        = max(10, min(200, intval($_GET['limit'] ?? 50)));

$pdo = apiRequireDatabase();
$dbConnected = ($pdo !== null);

$eventos = [];

if ($dbConnected) {
    try {
        $sql = "
            SELECT TOP ($limite)
                ea.AuditoriaID,
                ea.UsuarioID,
                u.NombreUsuario,
                CONVERT(VARCHAR(19), ea.FechaHora, 120) AS FechaHora,
                ea.Modulo AS ModuloCodigo,
                ea.Accion,
                ea.TablaAfectada,
                ea.RegistroID,
                ea.DireccionIP,
                ea.Descripcion
            FROM auditoria.EventoAuditoria ea
            LEFT JOIN seguridad.Usuario u ON ea.UsuarioID = u.UsuarioID

            WHERE ea.SistemaID = :sistema_id
        ";
        $params = [':sistema_id' => SystemContext::id($pdo)];

        if ($moduloFiltro !== '') {
            $sql .= " AND ea.Modulo = :modulo";
            $params[':modulo'] = $moduloFiltro;
        }
        if ($accionFiltro !== '') {
            $sql .= " AND ea.Accion = :accion";
            $params[':accion'] = $accionFiltro;
        }
        if ($usuarioFiltro !== null) {
            $sql .= " AND ea.UsuarioID = :uid";
            $params[':uid'] = $usuarioFiltro;
        }
        if ($busqueda !== '') {
            $sql .= " AND (ea.Descripcion LIKE :q1 OR ea.TablaAfectada LIKE :q2 OR ea.DireccionIP LIKE :q3)";
            foreach (range(1,3) as $n) $params[':q'.$n] = "%$busqueda%";
        }

        $sql .= " ORDER BY ea.FechaHora DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $f) {
            // Extraer módulo de la descripción si fue prefijado como [MODULO]
            $moduloNom = $f['ModuloCodigo'];
            if (preg_match('/^\[([A-Z_]+)\]\s*(.*)$/', (string)$f['Descripcion'], $matches)) {
                $moduloNom = $matches[1];
            }

            $eventos[] = [
                'auditoria_id'   => intval($f['AuditoriaID']),
                'usuario_id'     => $f['UsuarioID'] ? intval($f['UsuarioID']) : null,
                'nombre_usuario' => $f['NombreUsuario'] ?? 'SISTEMA',
                'fecha_hora'     => $f['FechaHora'],
                'modulo'         => $moduloNom,
                'accion'         => $f['Accion'],
                'tabla_afectada' => $f['TablaAfectada'],
                'registro_id'    => $f['RegistroID'],
                'direccion_ip'   => $f['DireccionIP'],
                'descripcion'    => $f['Descripcion']
            ];
        }
    } catch (Exception $e) {
        error_log('Consulta de auditoría: ' . $e->getMessage());
        apiError(503, 'No se pudo consultar la auditoría de SQL Server.');
    }
}

// Estadísticas rápidas para la cabecera
$totales = [
    'total_registros' => count($eventos),
    'acciones_hoy'    => count(array_filter($eventos, fn($e) => substr($e['fecha_hora'], 0, 10) === date('Y-m-d'))),
    'modificaciones'  => count(array_filter($eventos, fn($e) => in_array($e['accion'], ['INSERT', 'UPDATE', 'DELETE', 'REGULARIZAR']))),
    'seguridad'       => count(array_filter($eventos, fn($e) => $e['modulo'] === 'SEGURIDAD'))
];

echo json_encode([
    'ok'      => true,
    'totales' => $totales,
    'eventos' => $eventos
], JSON_UNESCAPED_UNICODE);
