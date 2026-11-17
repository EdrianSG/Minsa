/* HISTÓRICO, sustituido para la solicitud actual.
   El usuario ya corrigió las asignaciones y los cargos manualmente.
   Usar eliminar_area_enfermera_vacia_v3.sql para comprobar referencias y borrar
   únicamente el área ENFERMERA vacía. Este archivo consolida también cargos. */
/* Ejecutar completo en BD_HOSPITAL_V3.
   Área ENFERMERA -> ENFERMERIA. Cargo ENFERMERIA -> ENFERMERA.
   Conserva las asignaciones y los IDs de los catálogos de destino existentes.
   No modifica profesión, especialidad, técnicos, internos, licenciados ni Enfermero(a).
   La consolidación incluye asignaciones actuales e históricas del mismo concepto.
   Audita y elimina solo los dos registros redundantes de origen.
   Transacción única: cualquier conflicto revierte todos los cambios.
   Script preparado sin ejecución en SQL Server. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;

DECLARE @Origen int, @Destino int, @DeptOrigen int, @DeptDestino int;
DECLARE @Personal int=0, @Servicios int=0, @PuntosRed int=0, @SistemaID int;
DECLARE @AreaOriginal nvarchar(max), @AreaDestino nvarchar(max);
DECLARE @CargoOrigen int,@CargoDestino int,@AsignacionesCargo int=0;
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @Lock int;
    EXEC @Lock=sys.sp_getapplock @Resource=N'RRHH_CONSOLIDAR_AREA_ENFERMERA',
        @LockMode='Exclusive', @LockOwner='Transaction', @LockTimeout=5000;
    IF @Lock<0 THROW 51300,N'Hay otra consolidación en curso.',1;

    IF (SELECT COUNT(*) FROM organizacion.Area WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERIA')<>1
        THROW 51301,N'Debe existir una única área ENFERMERIA. No se crea ni se elige por aproximación.',1;
    SELECT @Destino=AreaID,@DeptDestino=DepartamentoID FROM organizacion.Area
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERIA';
    IF NOT EXISTS(SELECT 1 FROM organizacion.Area WHERE AreaID=@Destino AND Activo=1)
        THROW 51302,N'ENFERMERIA debe estar habilitada como destino.',1;

    IF (SELECT COUNT(*) FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA')>1
        THROW 51303,N'Hay varias áreas ENFERMERA. Resolver la ambigüedad antes de ejecutar.',1;
    SELECT @Origen=AreaID,@DeptOrigen=DepartamentoID FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA';
    IF @Origen IS NOT NULL
    BEGIN
        IF @DeptOrigen IS NOT NULL AND @DeptDestino IS NOT NULL AND @DeptOrigen<>@DeptDestino
            THROW 51304,N'Las áreas pertenecen a departamentos distintos. Revisar esa relación antes de consolidar.',1;

        SELECT @AreaOriginal=(SELECT * FROM organizacion.Area WHERE AreaID=@Origen FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        SELECT @AreaDestino=(SELECT * FROM organizacion.Area WHERE AreaID=@Destino FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);

        IF EXISTS (SELECT 1 FROM sys.foreign_key_columns fkc
            JOIN sys.columns rc ON rc.object_id=fkc.referenced_object_id AND rc.column_id=fkc.referenced_column_id
            WHERE fkc.referenced_object_id=OBJECT_ID('organizacion.Area') AND rc.name<>'AreaID')
            THROW 51310,N'Hay relaciones de Área mediante otra clave. Revisar ese esquema antes de consolidar.',1;

        -- Bloquear referencias desconocidas con datos, incluidas las que podrían borrarse en cascada.
        DECLARE @Schema sysname,@Table sysname,@Column sysname,@Sql nvarchar(max),@References bigint;
        DECLARE refs CURSOR LOCAL FAST_FORWARD FOR
            SELECT DISTINCT OBJECT_SCHEMA_NAME(fkc.parent_object_id),OBJECT_NAME(fkc.parent_object_id),c.name
            FROM sys.foreign_key_columns fkc
            JOIN sys.columns c ON c.object_id=fkc.parent_object_id AND c.column_id=fkc.parent_column_id
            JOIN sys.columns rc ON rc.object_id=fkc.referenced_object_id AND rc.column_id=fkc.referenced_column_id
            WHERE rc.name='AreaID' AND fkc.referenced_object_id IN (OBJECT_ID('organizacion.Area'),OBJECT_ID('organizacion.Servicio'))
            AND NOT (OBJECT_SCHEMA_NAME(fkc.parent_object_id)='rrhh' AND OBJECT_NAME(fkc.parent_object_id)='AsignacionPersonal' AND c.name='AreaID')
            AND NOT (fkc.referenced_object_id=OBJECT_ID('organizacion.Area') AND c.name='AreaID'
                AND ((OBJECT_SCHEMA_NAME(fkc.parent_object_id)='organizacion' AND OBJECT_NAME(fkc.parent_object_id)='Servicio')
                  OR (OBJECT_SCHEMA_NAME(fkc.parent_object_id)='redes' AND OBJECT_NAME(fkc.parent_object_id)='PuntoRed')));
        OPEN refs;
        FETCH NEXT FROM refs INTO @Schema,@Table,@Column;
        WHILE @@FETCH_STATUS=0
        BEGIN
            SET @References=0;
            SET @Sql=N'SELECT @n=COUNT_BIG(*) FROM '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)
                +N' WITH (UPDLOCK,HOLDLOCK) WHERE '+QUOTENAME(@Column)+N'=@id';
            EXEC sys.sp_executesql @Sql,N'@id int,@n bigint OUTPUT',@id=@Origen,@n=@References OUTPUT;
            IF @References>0
            BEGIN
                DECLARE @Message nvarchar(2048)=N'Hay referencias adicionales en '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N'. Revisar antes de eliminar ENFERMERA.';
                THROW 51305,@Message,1;
            END;
            FETCH NEXT FROM refs INTO @Schema,@Table,@Column;
        END;
        CLOSE refs;
        DEALLOCATE refs;

        -- La FK compuesta de AsignacionPersonal exige mantener juntos ServicioID y AreaID.
        IF EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WITH (UPDLOCK,HOLDLOCK)
            LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
            WHERE ap.AreaID=@Origen AND ap.ServicioID IS NOT NULL AND (s.ServicioID IS NULL OR s.AreaID<>@Origen OR s.AreaID IS NULL))
            THROW 51306,N'Hay asignaciones con servicio y área incompatibles. Corregirlas antes de consolidar.',1;
        IF EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WITH (UPDLOCK,HOLDLOCK)
            JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
            WHERE s.AreaID=@Origen AND (ap.AreaID<>@Origen OR ap.AreaID IS NULL))
            THROW 51307,N'Un servicio de ENFERMERA tiene asignaciones en otra área. Revisar antes de continuar.',1;

        DECLARE @Vinculos TABLE (AsignacionPersonalID int PRIMARY KEY,ServicioID int NOT NULL);
        INSERT @Vinculos SELECT AsignacionPersonalID,ServicioID FROM rrhh.AsignacionPersonal
            WHERE AreaID=@Origen AND ServicioID IS NOT NULL;
        UPDATE ap SET ServicioID=NULL FROM rrhh.AsignacionPersonal ap
            JOIN @Vinculos v ON v.AsignacionPersonalID=ap.AsignacionPersonalID;

        UPDATE organizacion.Servicio SET AreaID=@Destino WHERE AreaID=@Origen;
        SET @Servicios=@@ROWCOUNT;
        UPDATE ap SET AreaID=@Destino,ServicioID=COALESCE(v.ServicioID,ap.ServicioID)
            FROM rrhh.AsignacionPersonal ap LEFT JOIN @Vinculos v ON v.AsignacionPersonalID=ap.AsignacionPersonalID
            WHERE ap.AreaID=@Origen;
        SET @Personal=@@ROWCOUNT;
        IF OBJECT_ID(N'redes.PuntoRed',N'U') IS NOT NULL
        BEGIN
            EXEC sys.sp_executesql N'UPDATE redes.PuntoRed SET AreaID=@destino WHERE AreaID=@origen; SET @n=@@ROWCOUNT;',
                N'@origen int,@destino int,@n int OUTPUT',@origen=@Origen,@destino=@Destino,@n=@PuntosRed OUTPUT;
        END;

        IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo='RRHH')<>1
            THROW 51308,N'No existe un sistema RRHH único para auditar la operación.',1;
        SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';
        DECLARE @Descripcion nvarchar(max)=(SELECT JSON_QUERY(@AreaOriginal) AS area_eliminada,
            JSON_QUERY(@AreaDestino) AS area_destino,@Personal AS asignaciones_trasladadas,
            @Servicios AS servicios_trasladados,@PuntosRed AS puntos_red_trasladados FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'CONSOLIDAR_AREA','organizacion.Area',CONVERT(varchar(100),@Origen),@Descripcion);
        DELETE FROM organizacion.Area WHERE AreaID=@Origen;
        IF @@ROWCOUNT<>1 THROW 51309,N'No se eliminó exactamente un área. Se revierte la operación.',1;

    END; -- consolidación de área, si todavía existe ENFERMERA

    IF (SELECT COUNT(*) FROM rrhh.Cargo WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA')<>1
        THROW 51320,N'Debe existir un único cargo ENFERMERA como destino.',1;
    SELECT @CargoDestino=CargoID FROM rrhh.Cargo
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA';
    IF NOT EXISTS(SELECT 1 FROM rrhh.Cargo WHERE CargoID=@CargoDestino AND Activo=1)
        THROW 51321,N'El cargo ENFERMERA de destino debe estar habilitado.',1;
    IF (SELECT COUNT(*) FROM rrhh.Cargo WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERIA')>1
        THROW 51322,N'Hay varios cargos ENFERMERIA. Revisar antes de consolidar.',1;
    SELECT @CargoOrigen=CargoID FROM rrhh.Cargo WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERIA';
    IF @CargoOrigen IS NOT NULL
    BEGIN
        IF EXISTS(SELECT 1 FROM sys.foreign_key_columns fkc
            JOIN sys.columns rc ON rc.object_id=fkc.referenced_object_id AND rc.column_id=fkc.referenced_column_id
            WHERE fkc.referenced_object_id=OBJECT_ID('rrhh.Cargo') AND rc.name<>'CargoID')
            THROW 51323,N'El cargo tiene relaciones mediante otra clave. Revisar ese esquema.',1;
        DECLARE @CargoSchema sysname,@CargoTable sysname,@CargoColumn sysname,@CargoSql nvarchar(max),@CargoRefs bigint;
        DECLARE cargoRefs CURSOR LOCAL FAST_FORWARD FOR
            SELECT DISTINCT OBJECT_SCHEMA_NAME(fkc.parent_object_id),OBJECT_NAME(fkc.parent_object_id),c.name
            FROM sys.foreign_key_columns fkc
            JOIN sys.columns c ON c.object_id=fkc.parent_object_id AND c.column_id=fkc.parent_column_id
            WHERE fkc.referenced_object_id=OBJECT_ID('rrhh.Cargo')
            AND NOT (OBJECT_SCHEMA_NAME(fkc.parent_object_id)='rrhh' AND OBJECT_NAME(fkc.parent_object_id)='AsignacionPersonal' AND c.name='CargoID');
        OPEN cargoRefs;
        FETCH NEXT FROM cargoRefs INTO @CargoSchema,@CargoTable,@CargoColumn;
        WHILE @@FETCH_STATUS=0
        BEGIN
            SET @CargoRefs=0;
            SET @CargoSql=N'SELECT @n=COUNT_BIG(*) FROM '+QUOTENAME(@CargoSchema)+N'.'+QUOTENAME(@CargoTable)
                +N' WITH (UPDLOCK,HOLDLOCK) WHERE '+QUOTENAME(@CargoColumn)+N'=@id';
            EXEC sys.sp_executesql @CargoSql,N'@id int,@n bigint OUTPUT',@id=@CargoOrigen,@n=@CargoRefs OUTPUT;
            IF @CargoRefs>0
            BEGIN
                DECLARE @CargoMessage nvarchar(2048)=N'Hay referencias adicionales al cargo en '+QUOTENAME(@CargoSchema)+N'.'+QUOTENAME(@CargoTable)+N'. Revisar antes de consolidar.';
                THROW 51324,@CargoMessage,1;
            END;
            FETCH NEXT FROM cargoRefs INTO @CargoSchema,@CargoTable,@CargoColumn;
        END;
        CLOSE cargoRefs;
        DEALLOCATE cargoRefs;

        DECLARE @CargoOriginalJson nvarchar(max)=(SELECT CargoID,Codigo,Nombre,Descripcion,Nivel,Activo FROM rrhh.Cargo WHERE CargoID=@CargoOrigen FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        UPDATE rrhh.AsignacionPersonal SET CargoID=@CargoDestino WHERE CargoID=@CargoOrigen;
        SET @AsignacionesCargo=@@ROWCOUNT;
        IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo='RRHH')<>1
            THROW 51325,N'No existe un sistema RRHH único para registrar auditoría.',1;
        SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';
        DECLARE @CargoDescripcion nvarchar(max)=(SELECT JSON_QUERY(@CargoOriginalJson) AS cargo_eliminado,
            @CargoDestino AS cargo_destino_id,@AsignacionesCargo AS asignaciones_corregidas FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'CONSOLIDAR_CARGO','rrhh.Cargo',CONVERT(varchar(100),@CargoOrigen),@CargoDescripcion);
        DELETE FROM rrhh.Cargo WHERE CargoID=@CargoOrigen;
        IF @@ROWCOUNT<>1 THROW 51326,N'No se eliminó exactamente un cargo. Se revierte todo.',1;
    END;

    COMMIT TRANSACTION;
    SELECT N'Área y cargo de enfermería normalizados' AS Resultado,
        @Origen AS AreaOrigenID,@Destino AS AreaEnfermeriaID,@Personal AS AsignacionesAreaTrasladadas,
        @Servicios AS ServiciosTrasladados,@PuntosRed AS PuntosRedTrasladados,
        @CargoOrigen AS CargoOrigenID,@CargoDestino AS CargoEnfermeraID,@AsignacionesCargo AS AsignacionesCargoCorregidas;

END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
