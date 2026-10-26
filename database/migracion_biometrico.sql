-- =============================================================================
-- MIGRACIÓN INSTITUCIONAL: IMPORTACIÓN BIOMÉTRICA BIOSTAR 2
-- Hospital Distrital de Pacasmayo - Fase 2
-- Base de Datos: BD_HOSPITAL (SQL Server 2017/2019/2022)
-- Esquema: rrhh
--
-- Estructuras aditivas no destructivas para almacenar y auditar:
-- 1. Historial de Cargas CSV (rrhh.ImportacionBiometrica)
-- 2. Marcaciones Crudas de Biometría (rrhh.MarcacionBiometrica)
-- 3. Incidencias y Anomalías Temporales (rrhh.IncidenciaBiometrica)
-- 4. Vinculación de Alias / IDs no DNI (rrhh.VinculacionBiometrica)
-- =============================================================================

USE BD_HOSPITAL;
GO

-- 1. Control e Historial de Importaciones
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'ImportacionBiometrica' AND schema_id = SCHEMA_ID('rrhh'))
BEGIN
    CREATE TABLE rrhh.ImportacionBiometrica (
        ImportacionID            INT IDENTITY(1,1) PRIMARY KEY,
        NombreArchivo            NVARCHAR(255) NOT NULL,
        HashSHA256               VARCHAR(64) NOT NULL,
        FechaHoraCarga           DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        UsuarioID                INT NULL,
        TotalRegistros           INT NOT NULL DEFAULT 0,
        MarcacionesValidas       INT NOT NULL DEFAULT 0,
        MarcacionesOmitidas      INT NOT NULL DEFAULT 0,
        MarcacionesDuplicadas    INT NOT NULL DEFAULT 0,
        PendientesVinculacion    INT NOT NULL DEFAULT 0,
        MarcacionesConsecutivas  INT NOT NULL DEFAULT 0,
        VinculadosDirectos       INT NOT NULL DEFAULT 0,
        Estado                   NVARCHAR(30) NOT NULL DEFAULT 'COMPLETADO', -- 'EN_PROCESO', 'COMPLETADO', 'FALLIDO'
        MensajeError             NVARCHAR(1000) NULL,
        Observaciones            NVARCHAR(500) NULL,

        CONSTRAINT FK_Importacion_Usuario 
            FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario(UsuarioID)
    );
    CREATE INDEX IX_Importacion_Hash ON rrhh.ImportacionBiometrica(HashSHA256);
    CREATE INDEX IX_Importacion_Fecha ON rrhh.ImportacionBiometrica(FechaHoraCarga DESC);
END;
GO

-- 2. Almacén de Marcaciones Biométricas Crudas (Staging Pericial)
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'MarcacionBiometrica' AND schema_id = SCHEMA_ID('rrhh'))
BEGIN
    CREATE TABLE rrhh.MarcacionBiometrica (
        MarcacionID              BIGINT IDENTITY(1,1) PRIMARY KEY,
        ImportacionID            INT NOT NULL,
        BiostarUserID            VARCHAR(30) NOT NULL,
        BiostarUserName          NVARCHAR(150) NULL,
        FechaHora                DATETIME2 NOT NULL,
        DispositivoID            VARCHAR(50) NOT NULL,
        DispositivoNombre        NVARCHAR(150) NULL,
        Evento                   NVARCHAR(100) NOT NULL,
        PersonalID               INT NULL,
        EstadoProcesamiento      NVARCHAR(30) NOT NULL DEFAULT 'PENDIENTE', -- 'PENDIENTE', 'PROCESADO', 'IGNORADO'
        EstadoVinculacion        NVARCHAR(30) NOT NULL DEFAULT 'PENDIENTE_VINCULACION', -- 'VINCULADO', 'PENDIENTE_VINCULACION'
        EsConsecutiva            BIT NOT NULL DEFAULT 0,
        SegundosDiferencia       INT NULL,
        Advertencia              NVARCHAR(200) NULL,

        CONSTRAINT FK_Marcacion_Importacion 
            FOREIGN KEY (ImportacionID) REFERENCES rrhh.ImportacionBiometrica(ImportacionID),
        CONSTRAINT FK_Marcacion_Personal 
            FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal(PersonalID),
        CONSTRAINT UQ_Marcacion_Evento 
            UNIQUE (BiostarUserID, FechaHora, DispositivoID, Evento)
    );
    CREATE INDEX IX_Marcacion_Personal_Fecha ON rrhh.MarcacionBiometrica(PersonalID, FechaHora);
    CREATE INDEX IX_Marcacion_BiostarUser ON rrhh.MarcacionBiometrica(BiostarUserID);
    CREATE INDEX IX_Marcacion_Importacion ON rrhh.MarcacionBiometrica(ImportacionID);
END;
GO

-- 3. Incidencias y Anomalías Biométricas (Revisión de RR.HH.)
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'IncidenciaBiometrica' AND schema_id = SCHEMA_ID('rrhh'))
BEGIN
    CREATE TABLE rrhh.IncidenciaBiometrica (
        IncidenciaID             INT IDENTITY(1,1) PRIMARY KEY,
        ImportacionID            INT NOT NULL,
        MarcacionID              BIGINT NULL,
        BiostarUserID            VARCHAR(30) NULL,
        TipoIncidencia           NVARCHAR(50) NOT NULL, -- 'USUARIO_DESCONOCIDO', 'AUTENTICACION_ERRONEA', 'CAMBIO_HORA_DISPOSITIVO', 'FORMATO_INVALIDO'
        Nivel                    NVARCHAR(20) NOT NULL DEFAULT 'INFO', -- 'INFO', 'ADVERTENCIA', 'ERROR'
        Detalle                  NVARCHAR(500) NOT NULL,
        FechaHoraEvento          DATETIME2 NULL,
        FechaHoraRegistro        DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

        CONSTRAINT FK_Incidencia_Importacion 
            FOREIGN KEY (ImportacionID) REFERENCES rrhh.ImportacionBiometrica(ImportacionID),
        CONSTRAINT FK_Incidencia_Marcacion 
            FOREIGN KEY (MarcacionID) REFERENCES rrhh.MarcacionBiometrica(MarcacionID)
    );
    CREATE INDEX IX_Incidencia_Importacion ON rrhh.IncidenciaBiometrica(ImportacionID);
    CREATE INDEX IX_Incidencia_Tipo ON rrhh.IncidenciaBiometrica(TipoIncidencia);
END;
GO

-- 4. Mapeo Manual de Identificadores Especiales (Alias no DNI)
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'VinculacionBiometrica' AND schema_id = SCHEMA_ID('rrhh'))
BEGIN
    CREATE TABLE rrhh.VinculacionBiometrica (
        VinculacionID            INT IDENTITY(1,1) PRIMARY KEY,
        BiostarUserID            VARCHAR(30) NOT NULL UNIQUE,
        PersonalID               INT NOT NULL,
        Observacion              NVARCHAR(200) NULL,
        FechaCreacion            DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
        TipoVinculacion          NVARCHAR(30) NOT NULL DEFAULT 'MANUAL', -- 'MANUAL', 'AUTOMATICA_DNI'
        UsuarioID                INT NULL,

        CONSTRAINT FK_Vinculacion_Personal 
            FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal(PersonalID),
        CONSTRAINT FK_Vinculacion_Usuario 
            FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario(UsuarioID)
    );
END;
GO
