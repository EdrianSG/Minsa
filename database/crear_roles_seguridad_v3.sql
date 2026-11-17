/* =========================================================================
   CREACIÓN Y SINCRONIZACIÓN DE ROLES Y PERMISOS INSTITUCIONALES (RBAC)
   Hospital Distrital de Pacasmayo - BD_HOSPITAL_V3 (Esquema seguridad)
   Ejecutar en SQL Server Management Studio (SSMS) o DBeaver sobre BD_HOSPITAL_V3.
   ========================================================================= */

USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT, CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;

IF DB_NAME() <> N'BD_HOSPITAL_V3'
    THROW 51200, N'Base de datos incorrecta: se requiere ejecutar sobre BD_HOSPITAL_V3.', 1;

DECLARE @SistemaID int;
BEGIN TRY
    BEGIN TRANSACTION;

    -- 1. Asegurar Sistema 'RRHH'
    SELECT @SistemaID = SistemaID FROM seguridad.Sistema WHERE Codigo = 'RRHH';
    IF @SistemaID IS NULL
    BEGIN
        INSERT INTO seguridad.Sistema (Codigo, Nombre, Descripcion, Activo)
        VALUES ('RRHH', N'Recursos Humanos', N'Sistema de Gestión de Personal, Asistencia y Roles', 1);
        SET @SistemaID = CONVERT(int, SCOPE_IDENTITY());
    END
    ELSE
    BEGIN
        UPDATE seguridad.Sistema SET Activo = 1 WHERE SistemaID = @SistemaID;
    END

    -- 2. Asegurar Módulos del Sistema RRHH
    DECLARE @Modulos TABLE (Codigo varchar(30) PRIMARY KEY, Nombre nvarchar(300), Ruta varchar(300));
    INSERT INTO @Modulos VALUES
        ('dashboard',      N'Panel Principal',          'index.php?page=dashboard'),
        ('personal',       N'Directorio de Personal',   'index.php?page=personal'),
        ('horarios',       N'Horarios y Turnos',        'index.php?page=horarios'),
        ('programacion',   N'Programación Mensual',     'index.php?page=programacion'),
        ('asistencia',     N'Control de Asistencia',    'index.php?page=asistencia'),
        ('ausencias',      N'Ausencias y CITT',         'index.php?page=ausencias'),
        ('reportes',       N'Reportes Oficiales MINSA', 'index.php?page=reportes'),
        ('administracion', N'Administración y Seguridad','index.php?page=administracion');

    INSERT INTO seguridad.Modulo (SistemaID, Codigo, Nombre, Ruta, Activo)
    SELECT @SistemaID, m.Codigo, m.Nombre, m.Ruta, 1
    FROM @Modulos m
    WHERE NOT EXISTS (SELECT 1 FROM seguridad.Modulo sm WHERE sm.SistemaID = @SistemaID AND sm.Codigo = m.Codigo);

    UPDATE sm
    SET sm.Nombre = m.Nombre, sm.Ruta = m.Ruta, sm.Activo = 1
    FROM seguridad.Modulo sm
    JOIN @Modulos m ON m.Codigo = sm.Codigo AND sm.SistemaID = @SistemaID;

    -- 3. Asegurar Permisos Granulares
    DECLARE @Permisos TABLE (Modulo varchar(30), Accion varchar(40), Codigo varchar(100) PRIMARY KEY);
    INSERT INTO @Permisos SELECT Codigo, 'ver', 'RRHH.' + Codigo + '.ver' FROM @Modulos;
    INSERT INTO @Permisos VALUES
        ('programacion', 'editar',         'RRHH.programacion.editar'),
        ('asistencia',   'regularizar',    'RRHH.asistencia.regularizar'),
        ('ausencias',    'dictaminar',     'RRHH.ausencias.dictaminar'),
        ('reportes',     'exportar_excel', 'RRHH.reportes.exportar_excel');

    INSERT INTO seguridad.Permiso (ModuloID, Codigo, Nombre, Activo)
    SELECT sm.ModuloID, p.Codigo, CONVERT(nvarchar(300), p.Modulo + N': ' + p.Accion), 1
    FROM @Permisos p
    JOIN seguridad.Modulo sm ON sm.Codigo = p.Modulo AND sm.SistemaID = @SistemaID
    WHERE NOT EXISTS (SELECT 1 FROM seguridad.Permiso sp WHERE sp.Codigo = p.Codigo);

    UPDATE sp
    SET sp.Activo = 1
    FROM seguridad.Permiso sp
    JOIN @Permisos p ON p.Codigo = sp.Codigo;

    -- 4. Catálogo de Roles Institucionales
    DECLARE @Roles TABLE (
        Codigo varchar(50) PRIMARY KEY,
        Nombre nvarchar(100),
        Descripcion nvarchar(300)
    );

    INSERT INTO @Roles VALUES
        ('RRHH_ADMIN',          N'Administrador de Recursos Humanos', N'Control total del sistema: gestión de cuentas, asignación de roles, configuración normativa, auditoría y catálogos.'),
        ('JEFE_RRHH',           N'Jefatura de Recursos Humanos',      N'Supervisión institucional global, aprobación final de roles mensuales, dictamen de licencias/CITT y reportes oficiales.'),
        ('JEFE_SERVICIO',       N'Jefe de Servicio / UPSS',           N'Elaboración y validación del rol mensual (regla 150h asistenciales) y reemplazos de urgencia de su servicio hospitalario.'),
        ('OPERADOR_ASISTENCIA', N'Operador de Asistencia',            N'Monitoreo diario de marcaciones, regularización de omisiones, registro de permisos/CITT e importación biométrica.'),
        ('AUDITOR_PLANILLAS',   N'Auditor de Planillas',              N'Auditoría de cumplimiento de carga horaria (150h), cómputo de tardanzas e inasistencias y exportación a planillas.'),
        ('SUPERVISOR',          N'Supervisor de Asistencia y Turnos', N'Supervisión de turnos programados, verificación de cobertura asistencial en tiempo real y apoyo en rol mensual.'),
        ('CONSULTA',            N'Usuario de Consulta',               N'Acceso de solo lectura a la asistencia diaria, programación mensual y generación de reportes generales.');

    -- Insertar roles que no existan
    INSERT INTO seguridad.Rol (SistemaID, Codigo, Nombre, Descripcion, Activo)
    SELECT @SistemaID, r.Codigo, r.Nombre, r.Descripcion, 1
    FROM @Roles r
    WHERE NOT EXISTS (SELECT 1 FROM seguridad.Rol sr WHERE sr.Codigo = r.Codigo);

    -- Actualizar roles existentes para asegurar nombre, descripción y pertenencia a RRHH
    UPDATE sr
    SET sr.SistemaID = @SistemaID, sr.Nombre = r.Nombre, sr.Descripcion = r.Descripcion, sr.Activo = 1
    FROM seguridad.Rol sr
    JOIN @Roles r ON r.Codigo = sr.Codigo;

    -- 5. Mapeo de Rol a Permisos (seguridad.RolPermiso)
    DECLARE @RolPermisoMap TABLE (
        RolCodigo varchar(50),
        PermisoCodigo varchar(100),
        PRIMARY KEY (RolCodigo, PermisoCodigo)
    );

    -- RRHH_ADMIN: Todos los 12 permisos
    INSERT INTO @RolPermisoMap (RolCodigo, PermisoCodigo)
    SELECT 'RRHH_ADMIN', Codigo FROM @Permisos;

    -- JEFE_RRHH: Todos excepto administración
    INSERT INTO @RolPermisoMap (RolCodigo, PermisoCodigo)
    SELECT 'JEFE_RRHH', Codigo FROM @Permisos WHERE Codigo <> 'RRHH.administracion.ver';

    -- JEFE_SERVICIO
    INSERT INTO @RolPermisoMap VALUES
        ('JEFE_SERVICIO', 'RRHH.dashboard.ver'),
        ('JEFE_SERVICIO', 'RRHH.personal.ver'),
        ('JEFE_SERVICIO', 'RRHH.horarios.ver'),
        ('JEFE_SERVICIO', 'RRHH.programacion.ver'),
        ('JEFE_SERVICIO', 'RRHH.programacion.editar'),
        ('JEFE_SERVICIO', 'RRHH.asistencia.ver'),
        ('JEFE_SERVICIO', 'RRHH.ausencias.ver'),
        ('JEFE_SERVICIO', 'RRHH.reportes.ver'),
        ('JEFE_SERVICIO', 'RRHH.reportes.exportar_excel');

    -- OPERADOR_ASISTENCIA
    INSERT INTO @RolPermisoMap VALUES
        ('OPERADOR_ASISTENCIA', 'RRHH.dashboard.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.personal.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.horarios.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.programacion.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.asistencia.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.asistencia.regularizar'),
        ('OPERADOR_ASISTENCIA', 'RRHH.ausencias.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.ausencias.dictaminar'),
        ('OPERADOR_ASISTENCIA', 'RRHH.reportes.ver'),
        ('OPERADOR_ASISTENCIA', 'RRHH.reportes.exportar_excel');

    -- AUDITOR_PLANILLAS
    INSERT INTO @RolPermisoMap VALUES
        ('AUDITOR_PLANILLAS', 'RRHH.dashboard.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.personal.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.horarios.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.programacion.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.asistencia.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.ausencias.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.reportes.ver'),
        ('AUDITOR_PLANILLAS', 'RRHH.reportes.exportar_excel'),
        ('AUDITOR_PLANILLAS', 'RRHH.administracion.ver');

    -- SUPERVISOR
    INSERT INTO @RolPermisoMap VALUES
        ('SUPERVISOR', 'RRHH.dashboard.ver'),
        ('SUPERVISOR', 'RRHH.horarios.ver'),
        ('SUPERVISOR', 'RRHH.programacion.ver'),
        ('SUPERVISOR', 'RRHH.programacion.editar'),
        ('SUPERVISOR', 'RRHH.asistencia.ver'),
        ('SUPERVISOR', 'RRHH.ausencias.ver');

    -- CONSULTA
    INSERT INTO @RolPermisoMap VALUES
        ('CONSULTA', 'RRHH.dashboard.ver'),
        ('CONSULTA', 'RRHH.asistencia.ver'),
        ('CONSULTA', 'RRHH.reportes.ver'),
        ('CONSULTA', 'RRHH.reportes.exportar_excel');

    -- Insertar permisos en seguridad.RolPermiso
    INSERT INTO seguridad.RolPermiso (RolID, PermisoID, FechaAsignacion, Activo)
    SELECT r.RolID, p.PermisoID, SYSDATETIME(), 1
    FROM @RolPermisoMap rpm
    JOIN seguridad.Rol r ON r.Codigo = rpm.RolCodigo
    JOIN seguridad.Permiso p ON p.Codigo = rpm.PermisoCodigo
    WHERE NOT EXISTS (
        SELECT 1 FROM seguridad.RolPermiso srp WHERE srp.RolID = r.RolID AND srp.PermisoID = p.PermisoID
    );

    UPDATE srp
    SET srp.Activo = 1
    FROM seguridad.RolPermiso srp
    JOIN seguridad.Rol r ON r.RolID = srp.RolID
    JOIN @RolPermisoMap rpm ON rpm.RolCodigo = r.Codigo
    JOIN seguridad.Permiso p ON p.PermisoID = srp.PermisoID AND p.Codigo = rpm.PermisoCodigo;

    -- Bitácora de Auditoría
    INSERT INTO auditoria.EventoAuditoria (
        UsuarioID, SistemaID, FechaHora, Modulo, Accion, TablaAfectada, RegistroID, Descripcion
    ) VALUES (
        1, @SistemaID, SYSDATETIME(), 'SEGURIDAD', 'INICIALIZAR_ROLES', 'seguridad.Rol', NULL,
        N'[SEGURIDAD] Catálogo oficial de 7 roles institucionales y permisos RBAC para RRHH sincronizados mediante script.'
    );

    COMMIT TRANSACTION;

    PRINT N'>>> SINCRONIZACIÓN COMPLETADA CON ÉXITO EN BD_HOSPITAL_V3 <<<';

    SELECT r.RolID, r.Codigo, r.Nombre, COUNT(rp.PermisoID) AS TotalPermisosAsignados, r.Activo
    FROM seguridad.Rol r
    LEFT JOIN seguridad.RolPermiso rp ON rp.RolID = r.RolID AND rp.Activo = 1
    WHERE r.SistemaID = @SistemaID
    GROUP BY r.RolID, r.Codigo, r.Nombre, r.Activo
    ORDER BY r.RolID;

END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
