/* Corrige ASISTENTA SOCIAL -> ASISTENTE SOCIAL conservando el mismo AreaID.
   Ejecutar completo en BD_HOSPITAL_V3. No traslada asignaciones ni modifica cargos.
   Script preparado sin ejecución en el servidor. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
DECLARE @AreaID int,@SistemaID int,@Original nvarchar(max),@Descripcion nvarchar(max);
BEGIN TRY
    BEGIN TRANSACTION;
    IF (SELECT COUNT(*) FROM organizacion.Area WITH(XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ASISTENTA SOCIAL')>1
        THROW 51600,N'Hay varias áreas ASISTENTA SOCIAL. Revisar antes de renombrar.',1;
    SELECT @AreaID=AreaID FROM organizacion.Area WITH(XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ASISTENTA SOCIAL';
    IF @AreaID IS NULL
    BEGIN
        IF (SELECT COUNT(*) FROM organizacion.Area WITH(UPDLOCK,HOLDLOCK)
            WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ASISTENTE SOCIAL')<>1
            THROW 51601,N'No se identificó un área única para corregir.',1;
        COMMIT TRANSACTION;
        SELECT N'El área ya figura como ASISTENTE SOCIAL.' AS Resultado;
        RETURN;
    END;
    IF EXISTS(SELECT 1 FROM organizacion.Area WITH(UPDLOCK,HOLDLOCK)
        WHERE AreaID<>@AreaID AND LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ASISTENTE SOCIAL')
        THROW 51602,N'Ya existe otra área ASISTENTE SOCIAL. Se necesita revisar la duplicidad.',1;
    IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo='RRHH')<>1
        THROW 51603,N'Se requiere un sistema RRHH único para auditoría.',1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';
    SET @Original=(SELECT AreaID,DepartamentoID,Codigo,Nombre,Descripcion,Activo FROM organizacion.Area WHERE AreaID=@AreaID FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    UPDATE organizacion.Area SET Nombre=N'ASISTENTE SOCIAL' WHERE AreaID=@AreaID;
    SET @Descripcion=(SELECT JSON_QUERY(@Original) AS original,N'ASISTENTE SOCIAL' AS nombre_final FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
        VALUES(NULL,@SistemaID,SYSDATETIME(),'RENOMBRAR_AREA','organizacion.Area',CONVERT(varchar(100),@AreaID),@Descripcion);
    COMMIT TRANSACTION;
    SELECT AreaID,Nombre FROM organizacion.Area WHERE AreaID=@AreaID;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
