<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/SnapshotStore.php';
/** Los servicios y sus identificadores siempre proceden del catálogo V4. */
final class OrganizationCatalog {
    public static function services(): array {
        $pdo=Database::getConnection();
        if ($pdo) {
            $rows=$pdo->query('SELECT s.ServicioID AS id,s.Codigo AS codigo,s.Nombre AS nombre,s.AreaID AS area_id,a.DepartamentoID AS departamento_id FROM organizacion.Servicio s LEFT JOIN organizacion.Area a ON a.AreaID=s.AreaID WHERE s.Activo=1 ORDER BY s.Nombre,s.ServicioID')->fetchAll(PDO::FETCH_ASSOC);
            SnapshotStore::set('servicios_catalogo_v4',$rows,['source'=>'SQL_SERVER_LIVE']);
        } else $rows=SnapshotStore::get('servicios_catalogo_v4')['data']??[];
        $allowed=Auth::getAllowedServiceId();
        return $allowed===null ? $rows : array_values(array_filter($rows,fn($r)=>(int)$r['id']===$allowed));
    }
    /** Áreas reales, independientes de los servicios; respeta el alcance del usuario. */
    public static function areas(): array {
        $allowed = Auth::getAllowedServiceId();
        $key = 'areas_catalogo_personal' . ($allowed === null ? '' : '_serv_' . $allowed);
        $pdo = Database::getConnection();
        if ($pdo) {
            $sql = 'SELECT a.AreaID AS id,a.Nombre AS nombre FROM organizacion.Area a';
            $params = [];
            if ($allowed !== null) {
                $sql .= ' WHERE EXISTS (SELECT 1 FROM organizacion.Servicio s WHERE s.AreaID=a.AreaID AND s.ServicioID=?)';
                $params[] = $allowed;
            }
            $stmt = $pdo->prepare($sql . ' ORDER BY a.Nombre,a.AreaID');
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            SnapshotStore::set($key, $rows, ['source'=>'SQL_SERVER_LIVE']);
            return $rows;
        }
        return SnapshotStore::get($key)['data'] ?? [];
    }
    public static function areaOptions(): string {
        $html = '<option value="">Todas las áreas</option>';
        foreach (self::areas() as $row) {
            $html .= '<option value="' . (int)$row['id'] . '">' . htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html;
    }
    public static function options(string $allValue=''): string {
        $html=Auth::getAllowedServiceId()===null ? '<option value="'.htmlspecialchars($allValue,ENT_QUOTES,'UTF-8').'">Todos los servicios</option>' : '';
        foreach (self::services() as $r) $html.='<option value="'.(int)$r['id'].'">'.htmlspecialchars($r['nombre']??'',ENT_QUOTES,'UTF-8').'</option>';
        return $html;
    }
}
