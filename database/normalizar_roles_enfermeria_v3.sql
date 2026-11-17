/* Ejecutar completo en BD_HOSPITAL_V3.
   1. Elimina el área LIC ENFERMERIA / LICENCIADO EN ENFERMERIA solo si está vacía.
   2. Consolida ENFERMERIA, ENFERMERA y Enfermero(a) como cargo ENFERMERO.
   3. Corrige LIC.ENFEMERIA / LIC ENFEMERIA a LIC ENFERMERIA.
   Reutiliza IDs existentes, traslada asignaciones actuales e históricas y audita.
   Las coincidencias son de nombre completo, ignorando espacios, puntos y acentos.
   Conserva áreas ENFERMERIA, técnicos, internos, profesiones y especialidades.
   Script preparado sin ejecución. Cualquier conflicto revierte todo. */
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS, ANSI_PADDING, ANSI_WARNINGS, ARITHABORT,
    CONCAT_NULL_YIELDS_NULL, QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;

DECLARE @AreaID int,@SistemaID int;
DECLARE @Aliases TABLE(Grupo varchar(20),Clave nvarchar(150),Prioridad int,PRIMARY KEY(Grupo,Clave));
INSERT @Aliases VALUES
    ('GENERAL',N'ENFERMERO',0),('GENERAL',N'ENFERMERA',1),
    ('GENERAL',N'ENFERMERIA',2),('GENERAL',N'ENFERMERO(A)',3),
    ('LICENCIADO',N'LICENFERMERIA',0),('LICENCIADO',N'LICENFEMERIA',1);
DECLARE @Members TABLE(CargoID int PRIMARY KEY,Grupo varchar(20),Prioridad int,Nombre nvarchar(150),Activo bit,Nivel int NULL,Original nvarchar(max));
DECLARE @Targets TABLE(Grupo varchar(20) PRIMARY KEY,CargoID int,NombreFinal nvarchar(150),Asignaciones int DEFAULT 0,CargosConsolidados int DEFAULT 0);
DECLARE @Removals TABLE(Objeto int,Clave sysname,ID int,Tipo varchar(20));
BEGIN TRY
    BEGIN TRANSACTION;
    DECLARE @Lock int;
    EXEC @Lock=sys.sp_getapplock @Resource=N'RRHH_NORMALIZAR_ROLES_ENFERMERIA',
        @LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=5000;
    IF @Lock<0 THROW 51500,N'Otra normalización está en curso.',1;
    IF (SELECT COUNT(*) FROM seguridad.Sistema WHERE Codigo='RRHH')<>1
        THROW 51501,N'Se requiere un sistema RRHH único para auditoría.',1;
    SELECT @SistemaID=SistemaID FROM seguridad.Sistema WHERE Codigo='RRHH';

    DECLARE @Areas TABLE(AreaID int PRIMARY KEY);
    INSERT @Areas SELECT AreaID FROM organizacion.Area WITH (XLOCK,HOLDLOCK)
        WHERE REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(Nombre)),N' ',N''),N'.',N''),CHAR(9),N'')
        COLLATE Latin1_General_100_CI_AI IN (N'LICENFERMERIA',N'LICENCIADOENENFERMERIA',N'LICENCIADAENENFERMERIA');
    IF (SELECT COUNT(*) FROM @Areas)>1
        THROW 51502,N'Hay varias áreas de licenciados en enfermería. Identificar la que debe eliminarse.',1;
    SELECT @AreaID=AreaID FROM @Areas;
    IF @AreaID IS NOT NULL INSERT @Removals VALUES(OBJECT_ID('organizacion.Area'),'AreaID',@AreaID,'AREA');

    INSERT @Members(CargoID,Grupo,Prioridad,Nombre,Activo,Nivel,Original)
        SELECT c.CargoID,a.Grupo,a.Prioridad,c.Nombre,c.Activo,c.Nivel,
            (SELECT c.CargoID,c.Codigo,c.Nombre,c.Descripcion,c.Nivel,c.Activo FOR JSON PATH,WITHOUT_ARRAY_WRAPPER)
        FROM rrhh.Cargo c WITH (XLOCK,HOLDLOCK)
        JOIN @Aliases a ON a.Clave=REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(c.Nombre)),N' ',N''),N'.',N''),CHAR(9),N'') COLLATE Latin1_General_100_CI_AI;
    IF EXISTS(SELECT Grupo,Prioridad FROM @Members GROUP BY Grupo,Prioridad HAVING COUNT(*)>1)
        THROW 51503,N'Hay cargos duplicados para una misma denominación. Revisar sus IDs antes de consolidar.',1;
    INSERT @Targets(Grupo,CargoID,NombreFinal)
        SELECT Grupo,CargoID,CASE WHEN Grupo='GENERAL' THEN N'ENFERMERO' ELSE N'LIC ENFERMERIA' END
        FROM (SELECT Grupo,CargoID,ROW_NUMBER() OVER(PARTITION BY Grupo ORDER BY Prioridad,CargoID) AS Orden FROM @Members) candidatos
        WHERE Orden=1;
    IF EXISTS(SELECT 1 FROM @Targets t JOIN @Members m ON m.CargoID=t.CargoID WHERE m.Activo<>1 OR m.Activo IS NULL)
        THROW 51504,N'Un cargo de destino está inactivo. Revisar su vigencia antes de normalizar.',1;
    IF EXISTS(SELECT 1 FROM @Members m JOIN @Targets t ON t.Grupo=m.Grupo
        JOIN @Members destino ON destino.CargoID=t.CargoID
        WHERE m.CargoID<>t.CargoID AND m.Nivel IS NOT NULL AND (destino.Nivel IS NULL OR m.Nivel<>destino.Nivel))
        THROW 51508,N'Los cargos tienen niveles diferentes o un nivel informado solo en el origen. Revisar antes de consolidar.',1;
    INSERT @Removals SELECT OBJECT_ID('rrhh.Cargo'),'CargoID',m.CargoID,'CARGO'
        FROM @Members m JOIN @Targets t ON t.Grupo=m.Grupo WHERE m.CargoID<>t.CargoID;

    -- Cada área debe estar realmente vacía. Para cargos se permite trasladar
    -- únicamente la FK conocida de AsignacionPersonal; las demás se revisan.
    DECLARE @Tipo varchar(20),@ID int,@Objeto int,@Clave sysname,@FK int,@Schema sysname,@Table sysname;
    DECLARE @Join nvarchar(max),@Sql nvarchar(max),@Count bigint;
    DECLARE refs CURSOR LOCAL FAST_FORWARD FOR
        SELECT r.Tipo,r.ID,r.Objeto,r.Clave,fk.object_id,OBJECT_SCHEMA_NAME(fk.parent_object_id),OBJECT_NAME(fk.parent_object_id)
        FROM @Removals r JOIN sys.foreign_keys fk ON fk.referenced_object_id=r.Objeto
        WHERE NOT (r.Tipo='CARGO' AND fk.parent_object_id=OBJECT_ID('rrhh.AsignacionPersonal')
            AND (SELECT COUNT(*) FROM sys.foreign_key_columns f WHERE f.constraint_object_id=fk.object_id)=1
            AND EXISTS(SELECT 1 FROM sys.foreign_key_columns f
                JOIN sys.columns pc ON pc.object_id=f.parent_object_id AND pc.column_id=f.parent_column_id
                JOIN sys.columns rc ON rc.object_id=f.referenced_object_id AND rc.column_id=f.referenced_column_id
                WHERE f.constraint_object_id=fk.object_id AND pc.name='CargoID' AND rc.name='CargoID'));
    OPEN refs;
    FETCH NEXT FROM refs INTO @Tipo,@ID,@Objeto,@Clave,@FK,@Schema,@Table;
    WHILE @@FETCH_STATUS=0
    BEGIN
        SELECT @Join=STRING_AGG(CONVERT(nvarchar(max),N'p.'+QUOTENAME(pc.name)+N'=r.'+QUOTENAME(rc.name)),N' AND ')
            WITHIN GROUP(ORDER BY f.constraint_column_id)
        FROM sys.foreign_key_columns f
        JOIN sys.columns pc ON pc.object_id=f.parent_object_id AND pc.column_id=f.parent_column_id
        JOIN sys.columns rc ON rc.object_id=f.referenced_object_id AND rc.column_id=f.referenced_column_id
        WHERE f.constraint_object_id=@FK;
        SET @Count=0;
        SET @Sql=N'SELECT @n=COUNT_BIG(*) FROM '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N' p WITH (UPDLOCK,HOLDLOCK) JOIN '
            +QUOTENAME(OBJECT_SCHEMA_NAME(@Objeto))+N'.'+QUOTENAME(OBJECT_NAME(@Objeto))+N' r ON '+@Join
            +N' WHERE r.'+QUOTENAME(@Clave)+N'=@id';
        EXEC sys.sp_executesql @Sql,N'@id int,@n bigint OUTPUT',@id=@ID,@n=@Count OUTPUT;
        IF @Count>0
        BEGIN
            DECLARE @Message nvarchar(2048)=N'El registro '+@Tipo+N' ID '+CONVERT(nvarchar(20),@ID)
                +N' tiene referencias en '+QUOTENAME(@Schema)+N'.'+QUOTENAME(@Table)+N'. Se revierte todo para revisarlas.';
            THROW 51505,@Message,1;
        END;
        FETCH NEXT FROM refs INTO @Tipo,@ID,@Objeto,@Clave,@FK,@Schema,@Table;
    END;
    CLOSE refs;
    DEALLOCATE refs;

    -- Renombrar el destino elegido conserva su ID y su código técnico.
    DECLARE @CargoID int,@DestinoID int,@Grupo varchar(20),@NombreFinal nvarchar(150),@Original nvarchar(max),@Descripcion nvarchar(max),@Filas int;
    DECLARE targets CURSOR LOCAL FAST_FORWARD FOR SELECT t.CargoID,t.NombreFinal,m.Original
        FROM @Targets t JOIN @Members m ON m.CargoID=t.CargoID
        WHERE m.Nombre COLLATE Latin1_General_100_BIN2<>t.NombreFinal COLLATE Latin1_General_100_BIN2;
    OPEN targets;
    FETCH NEXT FROM targets INTO @CargoID,@NombreFinal,@Original;
    WHILE @@FETCH_STATUS=0
    BEGIN
        UPDATE rrhh.Cargo SET Nombre=@NombreFinal WHERE CargoID=@CargoID;
        SET @Descripcion=(SELECT JSON_QUERY(@Original) AS original,@NombreFinal AS nombre_final FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'RENOMBRAR_CARGO','rrhh.Cargo',CONVERT(varchar(100),@CargoID),@Descripcion);
        FETCH NEXT FROM targets INTO @CargoID,@NombreFinal,@Original;
    END;
    CLOSE targets;
    DEALLOCATE targets;

    DECLARE cargos CURSOR LOCAL FAST_FORWARD FOR SELECT m.CargoID,t.CargoID,m.Grupo,m.Original
        FROM @Members m JOIN @Targets t ON t.Grupo=m.Grupo WHERE m.CargoID<>t.CargoID;
    OPEN cargos;
    FETCH NEXT FROM cargos INTO @CargoID,@DestinoID,@Grupo,@Original;
    WHILE @@FETCH_STATUS=0
    BEGIN
        UPDATE rrhh.AsignacionPersonal SET CargoID=@DestinoID WHERE CargoID=@CargoID;
        SET @Filas=@@ROWCOUNT;
        UPDATE @Targets SET Asignaciones=Asignaciones+@Filas,CargosConsolidados=CargosConsolidados+1 WHERE Grupo=@Grupo;
        SET @Descripcion=(SELECT JSON_QUERY(@Original) AS cargo_origen,@DestinoID AS destino_id,@Filas AS asignaciones_actualizadas FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'CONSOLIDAR_CARGO','rrhh.Cargo',CONVERT(varchar(100),@CargoID),@Descripcion);
        DELETE FROM rrhh.Cargo WHERE CargoID=@CargoID;
        IF @@ROWCOUNT<>1 THROW 51506,N'No se eliminó exactamente un cargo de origen.',1;
        FETCH NEXT FROM cargos INTO @CargoID,@DestinoID,@Grupo,@Original;
    END;
    CLOSE cargos;
    DEALLOCATE cargos;

    IF @AreaID IS NOT NULL
    BEGIN
        SET @Original=(SELECT AreaID,DepartamentoID,Codigo,Nombre,Descripcion,Activo FROM organizacion.Area WHERE AreaID=@AreaID FOR JSON PATH,WITHOUT_ARRAY_WRAPPER);
        INSERT auditoria.EventoAuditoria(UsuarioID,SistemaID,FechaHora,Accion,TablaAfectada,RegistroID,Descripcion)
            VALUES(NULL,@SistemaID,SYSDATETIME(),'ELIMINAR_AREA_VACIA','organizacion.Area',CONVERT(varchar(100),@AreaID),@Original);
        DELETE FROM organizacion.Area WHERE AreaID=@AreaID;
        IF @@ROWCOUNT<>1 THROW 51507,N'No se eliminó exactamente el área vacía.',1;
    END;
    COMMIT TRANSACTION;
    SELECT @AreaID AS AreaVaciaEliminadaID;
    SELECT Grupo,CargoID,NombreFinal,Asignaciones AS AsignacionesActualizadas,CargosConsolidados FROM @Targets;
END TRY
BEGIN CATCH
    IF XACT_STATE()<>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
