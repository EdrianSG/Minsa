<?php
final class DataRevision {
    /** Tablas de las que dependen las lecturas y reglas del sistema RRHH. */
    public const TABLES = [
        'rrhh.Personal','rrhh.AsignacionPersonal','rrhh.Contrato','rrhh.Cargo','rrhh.Profesion',
        'rrhh.Especialidad','rrhh.TipoContrato','rrhh.Turno','rrhh.Horario','rrhh.Asistencia',
        'rrhh.PermisoLaboral','rrhh.TipoPermisoLaboral','rrhh.ProgramacionMensual',
        'rrhh.ProgramacionMensualDetalle','rrhh.MarcacionBiometrica','rrhh.ImportacionBiometrica',
        'rrhh.IncidenciaBiometrica','rrhh.VinculacionBiometrica','rrhh.PadronBiometricoOrigen',
        'organizacion.Area','organizacion.Servicio','organizacion.Departamento','organizacion.Sede',
        'catalogo.TipoDocumento','catalogo.Sexo','seguridad.ParametrosRRHH','seguridad.Sistema',
        'seguridad.Usuario','seguridad.UsuarioRol','seguridad.UsuarioSistema','seguridad.Rol',
        'seguridad.RolPermiso','seguridad.Permiso','seguridad.Modulo','auditoria.EventoAuditoria'
    ];

    public static function read(PDO $pdo): ?string {
        // Una revisión estática o una tabla sin seguimiento nunca habilita la caché.
        $values = implode(',', array_fill(0,count(self::TABLES),'(?)'));
        $stmt = $pdo->prepare("SELECT required.tabla FROM (VALUES $values) required(tabla)
            WHERE OBJECT_ID(required.tabla,'U') IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM sys.triggers tr JOIN sys.sql_modules sm ON sm.object_id=tr.object_id
                WHERE tr.parent_id=OBJECT_ID(required.tabla) AND tr.is_disabled=0
                AND tr.is_instead_of_trigger=0
                AND EXISTS (SELECT 1 FROM sys.trigger_events e WHERE e.object_id=tr.object_id AND e.type_desc='INSERT')
                AND EXISTS (SELECT 1 FROM sys.trigger_events e WHERE e.object_id=tr.object_id AND e.type_desc='UPDATE')
                AND EXISTS (SELECT 1 FROM sys.trigger_events e WHERE e.object_id=tr.object_id AND e.type_desc='DELETE')
                AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(sm.definition),' ',''),CHAR(9),''),CHAR(10),''),CHAR(13),''),'[',''),']','')
                    LIKE '%updateseguridad.versiondatossetrevision=revision+1%'
            )");
        $stmt->execute(self::TABLES);
        if ($stmt->fetchColumn() !== false) return null;
        if (!$pdo->query("SELECT OBJECT_ID('seguridad.VersionDatos','U')")->fetchColumn()) return null;
        $rows = $pdo->query('SELECT CONVERT(varchar(30),Revision) AS revision FROM seguridad.VersionDatos')->fetchAll(PDO::FETCH_COLUMN);
        if (count($rows)!==1 || !ctype_digit((string)$rows[0])) return null;
        return 'rrhh-cache-1:'.$rows[0].':'.date('Y-m-d');
    }
}
