/* Ejecutar completo sobre BD_HOSPITAL_V3.
   Consolida CIRUGIA en CIRUGIA GENERAL y elimina únicamente el área de origen.
   Conserva personal, servicios y puntos de red trasladando sus referencias.
   Valida los nombres completos y las FK reales. Cualquier error revierte todo.
   No ejecutado por el asistente. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;

DECLARE @Origen int, @Destino int, @DeptOrigen int, @DeptDestino int;
DECLARE @Personal int=0, @Servicios int=0, @PuntosRed int=0, @SistemaID int;
DECLARE @AreaOriginal nvarchar(max), @AreaDestino nvarchar(max);
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @Lock int;
    EXEC @Lock=sys.sp_getapplock @Resource=N'RRHH_CONSOLIDAR_AREA_CIRUGIA',
        @LockMode='Exclusive', @LockOwner='Transaction', @LockTimeout=5000;
    IF @Lock<0 THROW 51300,N'Hay otra consolidación en curso.',1;

    IF (SELECT COUNT(*) FROM organizacion.Area WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'CIRUGIA GENERAL')<>1
        THROW 51301,N'Debe existir una única área CIRUGIA GENERAL. No se crea ni se elige por aproximación.',1;
    SELECT @Destino=AreaID,@DeptDestino=DepartamentoID FROM organizacion.Area
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'CIRUGIA GENERAL';
    IF NOT EXISTS(SELECT 1 FROM organizacion.Area WHERE AreaID=@Destino AND Activo=1)
        THROW 51302,N'CIRUGIA GENERAL debe estar habilitada como destino.',1;

    IF (SELECT COUNT(*) FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'CIRUGIA')>1
        THROW 51303,N'Hay varias áreas CIRUGIA. Resolver la ambigüedad antes de ejecutar.',1;
    SELECT @Origen=AreaID,@DeptOrigen=DepartamentoID FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'CIRUGIA';
    IF @Origen IS NULL
    BEGIN
        COMMIT TRANSACTION;
        SELECT N'CIRUGIA ya no existe. CIRUGIA GENERAL permanece disponible.' AS Resultado;
        RETURN;
    END;
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
            DECLARE @Message nvarchar(2048)=N'Hay referencias adicionales en '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N'. Revisar antes de eliminar CIRUGIA.';
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
        THROW 51307,N'Un servicio de CIRUGIA tiene asignaciones en otra área. Revisar antes de continuar.',1;

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

    COMMIT TRANSACTION;
    SELECT N'CIRUGIA consolidada en CIRUGIA GENERAL' AS Resultado,@Origen AS AreaEliminadaID,
        @Destino AS AreaDestinoID,@Personal AS AsignacionesTrasladadas,@Servicios AS ServiciosTrasladados,@PuntosRed AS PuntosRedTrasladados;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
