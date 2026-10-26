-- Ejecutar sobre la base configurada para la aplicación. Migración aditiva e idempotente.
SET XACT_ABORT ON;
IF OBJECT_ID(N'rrhh.ProgramacionMensual', N'U') IS NULL
BEGIN
    CREATE TABLE rrhh.ProgramacionMensual (
        Anio SMALLINT NOT NULL, Mes TINYINT NOT NULL,
        Version BIGINT NOT NULL DEFAULT 1,
        ActualizadoEn DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        UsuarioID INT NULL,
        CONSTRAINT PK_ProgramacionMensual PRIMARY KEY (Anio, Mes),
        CONSTRAINT CK_ProgramacionMensual_Periodo CHECK (Anio BETWEEN 2020 AND 2100 AND Mes BETWEEN 1 AND 12),
        CONSTRAINT FK_ProgramacionMensual_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario(UsuarioID)
    );
END;
IF OBJECT_ID(N'rrhh.ProgramacionMensualDetalle', N'U') IS NULL
BEGIN
    CREATE TABLE rrhh.ProgramacionMensualDetalle (
        Anio SMALLINT NOT NULL, Mes TINYINT NOT NULL,
        PersonalID INT NOT NULL, Dia TINYINT NOT NULL,
        CodigoTurno VARCHAR(16) NOT NULL,
        CONSTRAINT PK_ProgramacionMensualDetalle PRIMARY KEY (Anio, Mes, PersonalID, Dia),
        CONSTRAINT FK_ProgramacionDetalle_Periodo FOREIGN KEY (Anio, Mes) REFERENCES rrhh.ProgramacionMensual(Anio, Mes),
        CONSTRAINT FK_ProgramacionDetalle_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal(PersonalID),
        CONSTRAINT CK_ProgramacionDetalle_Dia CHECK (Dia BETWEEN 1 AND DAY(EOMONTH(DATEFROMPARTS(Anio, Mes, 1)))),
        CONSTRAINT CK_ProgramacionDetalle_Codigo CHECK (CodigoTurno IN
            ('M','1','T','2','N','3','GD','GN','1/2','2/3','1/2/3','G24','ADM','VAC','V',
             'VAC-1','VAC-2','VAC-3','V1','V2','V3','DESC','C','CITT','F','P','PER','LIB'))
    );
END;
IF OBJECT_ID(N'seguridad.VersionDatos', N'U') IS NULL
BEGIN
    CREATE TABLE seguridad.VersionDatos (
        ID TINYINT NOT NULL PRIMARY KEY CHECK (ID = 1),
        Revision BIGINT NOT NULL DEFAULT 1
    );
END;
IF NOT EXISTS (SELECT 1 FROM seguridad.VersionDatos WHERE ID = 1)
    INSERT INTO seguridad.VersionDatos (ID, Revision) VALUES (1, 1);
GO
-- También detecta modificaciones hechas desde otros equipos o directamente en SQL.
DECLARE @esquema SYSNAME, @tabla SYSNAME, @ddl NVARCHAR(MAX), @trigger SYSNAME;
DECLARE tablas CURSOR LOCAL FAST_FORWARD FOR
SELECT s.name, t.name FROM sys.tables t JOIN sys.schemas s ON s.schema_id = t.schema_id
WHERE (s.name = 'rrhh' AND t.name IN
    ('Personal','AsignacionPersonal','Contrato','Cargo','Profesion','Especialidad','TipoContrato',
     'Turno','Horario','Asistencia','PermisoLaboral','TipoPermisoLaboral','ProgramacionMensual',
     'ProgramacionMensualDetalle','MarcacionBiometrica','ImportacionBiometrica','IncidenciaBiometrica','VinculacionBiometrica'))
 OR (s.name = 'core' AND t.name = 'Persona')
 OR (s.name = 'catalogo' AND t.name IN ('TipoDocumento','Sexo'))
 OR (s.name = 'organizacion' AND t.name IN ('Servicio','Area'))
 OR (s.name = 'seguridad' AND t.name IN ('Usuario','UsuarioRol','Rol','RolPermiso','Permiso'));
OPEN tablas;
FETCH NEXT FROM tablas INTO @esquema, @tabla;
WHILE @@FETCH_STATUS = 0
BEGIN
    SET @trigger = N'TR_SIGH_Revision_' + @tabla;
    SET @ddl = N'CREATE OR ALTER TRIGGER ' + QUOTENAME(@esquema) + N'.' + QUOTENAME(@trigger)
        + N' ON ' + QUOTENAME(@esquema) + N'.' + QUOTENAME(@tabla)
        + N' AFTER INSERT, UPDATE, DELETE AS BEGIN SET NOCOUNT ON; '
        + N'IF EXISTS (SELECT 1 FROM inserted) OR EXISTS (SELECT 1 FROM deleted) '
        + N'UPDATE seguridad.VersionDatos SET Revision = Revision + 1 WHERE ID = 1; END;';
    EXEC sys.sp_executesql @ddl;
    FETCH NEXT FROM tablas INTO @esquema, @tabla;
END;
CLOSE tablas;
DEALLOCATE tablas;
