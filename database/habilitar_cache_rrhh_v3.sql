/* Ejecutar una vez en la V3 del ingeniero para habilitar la caché verificable.
   Completa triggers sobre tablas existentes. No crea tablas ni modifica personal.
   Cualquier escritura en las tablas supervisadas incrementa la revisión global.
   Repetir el script permite actualizar su cobertura. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
IF OBJECT_ID(N'seguridad.VersionDatos',N'U') IS NULL
    THROW 51200,N'Falta seguridad.VersionDatos en la base restaurada.',1;
IF (SELECT COUNT(*) FROM seguridad.VersionDatos)<>1
    THROW 51201,N'Se requiere una única fila existente en seguridad.VersionDatos.',1;
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @lock int;
    EXEC @lock=sys.sp_getapplock @Resource=N'RRHH_CONFIGURAR_CACHE',@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=5000;
    IF @lock<0 THROW 51202,N'Hay otra configuración de caché en curso.',1;
    DECLARE @tables TABLE (Esquema sysname,Tabla sysname);
    INSERT @tables VALUES
    (N'rrhh',N'Personal'),
    (N'rrhh',N'AsignacionPersonal'),
    (N'rrhh',N'Contrato'),
    (N'rrhh',N'Cargo'),
    (N'rrhh',N'Profesion'),
    (N'rrhh',N'Especialidad'),
    (N'rrhh',N'TipoContrato'),
    (N'rrhh',N'Turno'),
    (N'rrhh',N'Horario'),
    (N'rrhh',N'Asistencia'),
    (N'rrhh',N'PermisoLaboral'),
    (N'rrhh',N'TipoPermisoLaboral'),
    (N'rrhh',N'ProgramacionMensual'),
    (N'rrhh',N'ProgramacionMensualDetalle'),
    (N'rrhh',N'MarcacionBiometrica'),
    (N'rrhh',N'ImportacionBiometrica'),
    (N'rrhh',N'IncidenciaBiometrica'),
    (N'rrhh',N'VinculacionBiometrica'),
    (N'rrhh',N'PadronBiometricoOrigen'),
    (N'organizacion',N'Area'),
    (N'organizacion',N'Servicio'),
    (N'organizacion',N'Departamento'),
    (N'organizacion',N'Sede'),
    (N'catalogo',N'TipoDocumento'),
    (N'catalogo',N'Sexo'),
    (N'seguridad',N'ParametrosRRHH'),
    (N'seguridad',N'Sistema'),
    (N'seguridad',N'Usuario'),
    (N'seguridad',N'UsuarioRol'),
    (N'seguridad',N'UsuarioSistema'),
    (N'seguridad',N'Rol'),
    (N'seguridad',N'RolPermiso'),
    (N'seguridad',N'Permiso'),
    (N'seguridad',N'Modulo'),
    (N'auditoria',N'EventoAuditoria');
    DECLARE @schema sysname,@table sysname,@ddl nvarchar(max);
    DECLARE tracked CURSOR LOCAL FAST_FORWARD FOR
        SELECT Esquema,Tabla FROM @tables WHERE OBJECT_ID(QUOTENAME(Esquema)+'.'+QUOTENAME(Tabla),'U') IS NOT NULL;
    OPEN tracked;
    FETCH NEXT FROM tracked INTO @schema,@table;
    WHILE @@FETCH_STATUS=0
    BEGIN
        SET @ddl=N'CREATE OR ALTER TRIGGER '+QUOTENAME(@schema)+N'.'+QUOTENAME(N'TR_SIGH_Revision_'+@table)
          +N' ON '+QUOTENAME(@schema)+N'.'+QUOTENAME(@table)+N' AFTER INSERT,UPDATE,DELETE AS
            BEGIN SET NOCOUNT ON;
              IF EXISTS(SELECT 1 FROM inserted) OR EXISTS(SELECT 1 FROM deleted)
                UPDATE seguridad.VersionDatos SET Revision = Revision + 1;
            END;';
        EXEC sys.sp_executesql @ddl;
        FETCH NEXT FROM tracked INTO @schema,@table;
    END;
    CLOSE tracked;
    DEALLOCATE tracked;
    UPDATE seguridad.VersionDatos SET Revision=Revision+1;
    COMMIT TRANSACTION;
    SELECT N'Seguimiento de cambios para caché RRHH configurado' AS Resultado,Revision FROM seguridad.VersionDatos;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
