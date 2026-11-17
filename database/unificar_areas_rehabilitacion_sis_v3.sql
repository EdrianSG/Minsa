/* Unificaciones confirmadas por RRHH: REHABILITACION FISICA -> REHABILITACIÓN;
   SIS -> SIS (SEGURO INTEGRAL DE SALUD).
   MEDICINA y MEDICINA GENERAL permanecen separadas y no se modifican.
   Conserva AreaID de destino y todos los ServicioID. Traslada asignaciones actuales
   e históricas, servicios y puntos de red. Conserva las áreas de origen INACTIVAS
   y sus códigos como trazabilidad. No elimina filas ni crea áreas/servicios nuevos.
   Ambas consolidaciones son una única transacción; cualquier conflicto revierte todo.
   Preparado sin ejecución. Requiere desplegar también los cambios de selectores. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
DECLARE @Origen int,@Destino int,@DeptOrigen int,@DeptDestino int,@SistemaID int;
DECLARE @Personal int,@Servicios int,@PuntosRed int;
DECLARE @NombreOrigen nvarchar(150),@NombreDestino nvarchar(150);
DECLARE @AreaOriginal nvarchar(max),@AreaDestino nvarchar(max),@Descripcion nvarchar(max);
DECLARE @Schema sysname,@Table sysname,@Column sysname,@Sql nvarchar(max),@References bigint,@Message nvarchar(2048);
DECLARE @Vinculos TABLE(AsignacionPersonalID int PRIMARY KEY,ServicioID int NOT NULL);
DECLARE @Pares TABLE(Orden int PRIMARY KEY,Origen nvarchar(150),Destino nvarchar(150));
INSERT @Pares VALUES (1,N'REHABILITACION FISICA',N'REHABILITACIÓN'),(2,N'SIS',N'SIS (SEGURO INTEGRAL DE SALUD)');
DECLARE @Resultados TABLE(Origen nvarchar(150),Destino nvarchar(150),AreaOrigenInactivaID int,AreaDestinoID int,
    AsignacionesTrasladadas int,ServiciosTrasladados int,PuntosRedTrasladados int);
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @Lock int;
    EXEC @Lock=sys.sp_getapplock @Resource=N'RRHH_CONSOLIDAR_AREAS_CONFIRMADAS',
        @LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=5000;
    IF @Lock<0 THROW 51800,N'Otra consolidación está en curso.',1;
    IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo=N'RRHH' AND Activo=1)<>1
        THROW 51804,N'Se requiere un sistema RRHH activo único para auditoría.',1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo=N'RRHH' AND Activo=1;
    DECLARE pares CURSOR LOCAL FAST_FORWARD FOR SELECT Origen,Destino FROM @Pares ORDER BY Orden;
    OPEN pares;
    FETCH NEXT FROM pares INTO @NombreOrigen,@NombreDestino;
    WHILE @@FETCH_STATUS=0
    BEGIN
        SELECT @Origen=NULL,@Destino=NULL,@DeptOrigen=NULL,@DeptDestino=NULL,
            @Personal=0,@Servicios=0,@PuntosRed=0;
    IF (SELECT COUNT(*) FROM organizacion.Area WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=@NombreDestino)<>1
        THROW 51301,N'Debe existir una única área de destino. No se crea ni se elige por aproximación.',1;
    SELECT @Destino=AreaID,@DeptDestino=DepartamentoID FROM organizacion.Area
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=@NombreDestino;
    IF NOT EXISTS(SELECT 1 FROM organizacion.Area WHERE AreaID=@Destino AND Activo=1)
        THROW 51302,N'El área de destino debe estar habilitada como destino.',1;

    IF (SELECT COUNT(*) FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=@NombreOrigen)>1
        THROW 51303,N'Hay varias áreas de origen. Resolver la ambigüedad antes de ejecutar.',1;
    SELECT @Origen=AreaID,@DeptOrigen=DepartamentoID FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=@NombreOrigen;
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

    -- Detenerse ante referencias adicionales con datos que requieren revisión explícita.
    
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
            SET @Message=N'Hay referencias adicionales en '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N'. Revisar antes de consolidar estas áreas.';
            THROW 51305,@Message,1;
        END;
        FETCH NEXT FROM refs INTO @Schema,@Table,@Column;
    END;
    CLOSE refs;
    DEALLOCATE refs;

    IF EXISTS (SELECT 1 FROM organizacion.Servicio s WITH (UPDLOCK,HOLDLOCK)
        JOIN organizacion.Servicio d WITH (UPDLOCK,HOLDLOCK) ON d.AreaID=@Destino AND d.Codigo=s.Codigo
        WHERE s.AreaID=@Origen)
        THROW 51803,N'Existen códigos de servicio coincidentes entre origen y destino. Revisarlos sin fusionar sus IDs.',1;

    -- La FK compuesta de AsignacionPersonal exige mantener juntos ServicioID y AreaID.
    IF EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WITH (UPDLOCK,HOLDLOCK)
        LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
        WHERE ap.AreaID=@Origen AND ap.ServicioID IS NOT NULL AND (s.ServicioID IS NULL OR s.AreaID<>@Origen OR s.AreaID IS NULL))
        THROW 51306,N'Hay asignaciones con servicio y área incompatibles. Corregirlas antes de consolidar.',1;
    IF EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WITH (UPDLOCK,HOLDLOCK)
        JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
        WHERE s.AreaID=@Origen AND (ap.AreaID<>@Origen OR ap.AreaID IS NULL))
        THROW 51307,N'Un servicio del área de origen tiene asignaciones en otra área. Revisar antes de continuar.',1;

    DELETE FROM @Vinculos;
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


    SET @Descripcion=(SELECT JSON_QUERY(@AreaOriginal) AS area_origen_conservada,
        JSON_QUERY(@AreaDestino) AS area_destino,@Personal AS asignaciones_trasladadas,
        @Servicios AS servicios_trasladados,@PuntosRed AS puntos_red_trasladados FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    IF DATALENGTH(@Descripcion)>2000 THROW 51802,N'La auditoría excede el tamaño del campo. Se revierte sin truncar.',1;
    INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
        VALUES(NULL,@SistemaID,SYSDATETIME(),'CONSOLIDAR_AREA','organizacion.Area',CONVERT(varchar(100),@Origen),@Descripcion);
    UPDATE organizacion.Area SET Activo=0 WHERE AreaID=@Origen;
    END;
    -- Normalizar solo la denominación expresamente confirmada; conservar ID y código.
    IF EXISTS (SELECT 1 FROM organizacion.Area WHERE AreaID=@Destino
        AND Nombre COLLATE Latin1_General_100_BIN2<>@NombreDestino COLLATE Latin1_General_100_BIN2)
    BEGIN
        SET @AreaDestino=(SELECT * FROM organizacion.Area WHERE AreaID=@Destino FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        UPDATE organizacion.Area SET Nombre=@NombreDestino WHERE AreaID=@Destino;
        SET @Descripcion=(SELECT JSON_QUERY(@AreaDestino) AS original,@NombreDestino AS nombre_final FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        IF DATALENGTH(@Descripcion)>2000 THROW 51801,N'La auditoría del nombre excede el tamaño del campo.',1;
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'RENOMBRAR_AREA','organizacion.Area',CONVERT(varchar(100),@Destino),@Descripcion);
    END;
    INSERT @Resultados VALUES(@NombreOrigen,@NombreDestino,@Origen,@Destino,@Personal,@Servicios,@PuntosRed);

        FETCH NEXT FROM pares INTO @NombreOrigen,@NombreDestino;
    END;
    CLOSE pares;
    DEALLOCATE pares;
    COMMIT TRANSACTION;
    SELECT * FROM @Resultados;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
