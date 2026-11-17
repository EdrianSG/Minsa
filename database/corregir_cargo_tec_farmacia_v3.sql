/* Corrección ortográfica puntual: TEC FARCACIA -> TEC FARMACIA.
   Ejecutar completo en BD_HOSPITAL_V3. Conserva CargoID, código, nivel,
   estado y todas las asignaciones actuales e históricas.
   No modifica profesiones, áreas ni trabajadores. No elimina registros.
   Si existen ambas denominaciones, aborta para revisar sus IDs y relaciones.
   Preparado sin ejecución en SQL Server. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
DECLARE @CargoID int, @SistemaID int, @Original nvarchar(max), @Descripcion nvarchar(max);
BEGIN TRY
    BEGIN TRANSACTION;
    IF (SELECT COUNT(*) FROM rrhh.Cargo WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'TEC FARCACIA')>1
        THROW 51700,N'Hay varios cargos TEC FARCACIA. Revisar sus IDs antes de corregir.',1;
    SELECT @CargoID=CargoID FROM rrhh.Cargo WITH (UPDLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'TEC FARCACIA';
    IF @CargoID IS NULL
    BEGIN
        IF (SELECT COUNT(*) FROM rrhh.Cargo WITH (UPDLOCK,HOLDLOCK)
            WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'TEC FARMACIA')<>1
            THROW 51701,N'No se encontró un cargo único para esta corrección. Revisar catálogo.',1;
        COMMIT TRANSACTION;
        SELECT N'El cargo ya figura como TEC FARMACIA; no se modificó ningún registro.' AS Resultado;
        RETURN;
    END;
    IF EXISTS (SELECT 1 FROM rrhh.Cargo WITH (UPDLOCK,HOLDLOCK)
        WHERE CargoID<>@CargoID AND LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'TEC FARMACIA')
        THROW 51702,N'Ya existe TEC FARMACIA con otro CargoID. Se revierte todo; revisar antes de consolidar.',1;
    IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo=N'RRHH' AND Activo=1)<>1
        THROW 51703,N'Se requiere un sistema RRHH activo único para auditar.',1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo=N'RRHH' AND Activo=1;
    SET @Original=(SELECT CargoID,Codigo,Nombre,Descripcion,Nivel,Activo FROM rrhh.Cargo
        WHERE CargoID=@CargoID FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    SET @Descripcion=(SELECT JSON_QUERY(@Original) AS original,N'TEC FARMACIA' AS nombre_final
        FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    IF DATALENGTH(@Descripcion)>2000
        THROW 51704,N'La auditoría supera el tamaño del campo; se requiere revisión sin truncar datos.',1;
    UPDATE rrhh.Cargo SET Nombre=N'TEC FARMACIA' WHERE CargoID=@CargoID;
    IF @@ROWCOUNT<>1 THROW 51705,N'No se actualizó exactamente un cargo. Se revierte todo.',1;
    INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
        VALUES(NULL,@SistemaID,SYSDATETIME(),'RENOMBRAR_CARGO','rrhh.Cargo',CONVERT(varchar(100),@CargoID),@Descripcion);
    COMMIT TRANSACTION;
    SELECT CargoID,Codigo,Nombre,Nivel,Activo FROM rrhh.Cargo WHERE CargoID=@CargoID;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
