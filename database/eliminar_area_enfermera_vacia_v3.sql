/* Ejecutar completo en BD_HOSPITAL_V3.
   Elimina únicamente el área ENFERMERA cuando no tiene ninguna referencia.
   El usuario ya trasladó el personal a ENFERMERIA y corrigió sus cargos.
   Conserva todos los cargos y las asignaciones existentes.
   Si hay referencias actuales o históricas, revierte y muestra la tabla involucrada.
   Script preparado sin ejecución en SQL Server. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;

DECLARE @AreaID int,@SistemaID int,@Original nvarchar(max);
BEGIN TRY
    BEGIN TRANSACTION;
    IF (SELECT COUNT(*) FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA')>1
        THROW 51400,N'Hay varias áreas ENFERMERA. Identificar el registro correcto antes de eliminar.',1;
    SELECT @AreaID=AreaID FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI=N'ENFERMERA';
    IF @AreaID IS NULL
    BEGIN
        COMMIT TRANSACTION;
        SELECT N'El área ENFERMERA ya no existe.' AS Resultado;
        RETURN;
    END;

    -- Verifica todas las FK, incluidas claves compuestas y borrados en cascada.
    DECLARE @ForeignKey int,@Schema sysname,@Table sysname,@Join nvarchar(max),@Sql nvarchar(max),@Count bigint;
    DECLARE areaRefs CURSOR LOCAL FAST_FORWARD FOR
        SELECT fk.object_id,OBJECT_SCHEMA_NAME(fk.parent_object_id),OBJECT_NAME(fk.parent_object_id)
        FROM sys.foreign_keys fk WHERE fk.referenced_object_id=OBJECT_ID('organizacion.Area');
    OPEN areaRefs;
    FETCH NEXT FROM areaRefs INTO @ForeignKey,@Schema,@Table;
    WHILE @@FETCH_STATUS=0
    BEGIN
        SELECT @Join=STRING_AGG(CONVERT(nvarchar(max),N'p.'+QUOTENAME(pc.name)+N'=a.'+QUOTENAME(rc.name)),N' AND ')
            WITHIN GROUP (ORDER BY fkc.constraint_column_id)
        FROM sys.foreign_key_columns fkc
        JOIN sys.columns pc ON pc.object_id=fkc.parent_object_id AND pc.column_id=fkc.parent_column_id
        JOIN sys.columns rc ON rc.object_id=fkc.referenced_object_id AND rc.column_id=fkc.referenced_column_id
        WHERE fkc.constraint_object_id=@ForeignKey;
        SET @Count=0;
        SET @Sql=N'SELECT @n=COUNT_BIG(*) FROM '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)
            +N' p WITH (UPDLOCK,HOLDLOCK) JOIN organizacion.Area a ON '+@Join+N' WHERE a.AreaID=@id';
        EXEC sys.sp_executesql @Sql,N'@id int,@n bigint OUTPUT',@id=@AreaID,@n=@Count OUTPUT;
        IF @Count>0
        BEGIN
            DECLARE @Message nvarchar(2048)=N'El área ENFERMERA tiene '+CONVERT(nvarchar(30),@Count)
                +N' referencias en '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N'. Se conserva el área y se revierte la operación.';
            THROW 51401,@Message,1;
        END;
        FETCH NEXT FROM areaRefs INTO @ForeignKey,@Schema,@Table;
    END;
    CLOSE areaRefs;
    DEALLOCATE areaRefs;

    IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo='RRHH')<>1
        THROW 51402,N'Se requiere un sistema RRHH único para auditar la eliminación.',1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';
    SELECT @Original=(SELECT AreaID,DepartamentoID,Codigo,Nombre,Descripcion,Activo
        FROM organizacion.Area WHERE AreaID=@AreaID FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
    INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
        VALUES(NULL,@SistemaID,SYSDATETIME(),'ELIMINAR_AREA_VACIA','organizacion.Area',CONVERT(varchar(100),@AreaID),@Original);
    DELETE FROM organizacion.Area WHERE AreaID=@AreaID;
    IF @@ROWCOUNT<>1 THROW 51403,N'No se eliminó exactamente un área. Se revierte todo.',1;
    COMMIT TRANSACTION;
    SELECT N'Área ENFERMERA vacía eliminada' AS Resultado,@AreaID AS AreaEliminadaID;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
