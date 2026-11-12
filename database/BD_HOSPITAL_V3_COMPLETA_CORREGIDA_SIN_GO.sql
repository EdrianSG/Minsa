/* ======================================================================
   BD_HOSPITAL_V3 COMPLETA - INSTALADOR PARA SQL SERVER + DBEAVER
   Sin GO, sin migracion y sin compartir la tabla persona.Persona.

   1) La BD_HOSPITAL_V2 original NUNCA se modifica.
   2) Crea BD_HOSPITAL_V3 si no existe.
   3) Crea todas las tablas, indices y relaciones SOLO SI V3 esta vacia.
   4) Incluye los campos de RRHH solicitados y catalogos basicos.
   5) NO incluye registros ficticios de trabajadores o pacientes.
   6) En DBeaver usar AUTOCOMMIT y Ejecutar script SQL (Alt+X),
      o seleccionar TODO el texto y ejecutarlo como un bloque.

   ADVERTENCIA: Requiere SQL SERVER 2016+ y permisos CREATE DATABASE,
   CREATE TABLE, CREATE SCHEMA, CREATE ROLE, etc.
   La creacion de una base de datos no funciona en una transaccion
   explicita; activar autocommit si DBeaver usa transacciones manuales.
   NO ejecutar en una V3 ya llena de tablas.
   ====================================================================== */
/* PASO 1: SQL Server exige que CREATE DATABASE ocupe su propio lote.
   Ejecutarlo dinamicamente evita GO y preserva las demás instrucciones. */
EXEC(N'IF DB_ID(N''BD_HOSPITAL_V3'') IS NULL EXEC(N''CREATE DATABASE [BD_HOSPITAL_V3]'');');

/* PASO 2: ejecucion aislada en la base V3, aunque DBeaver tenga otra seleccionada. */
EXEC(N'
USE [BD_HOSPITAL_V3];
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET QUOTED_IDENTIFIER ON;
SET NUMERIC_ROUNDABORT OFF;
SET XACT_ABORT ON;
SET NOCOUNT ON;

IF EXISTS (SELECT 1 FROM sys.tables WHERE is_ms_shipped = 0)
BEGIN
    ;THROW 51011, N''La base BD_HOSPITAL_V3 ya contiene tablas. No se modifico nada: utiliza una base vacia.'', 1;
END;

/* Los esquemas se crean con SQL dinámico porque CREATE SCHEMA exige
   ser la primera instrucción de un lote T-SQL. */
IF SCHEMA_ID(N''catalogo'') IS NULL EXEC(N''CREATE SCHEMA catalogo'');
IF SCHEMA_ID(N''organizacion'') IS NULL EXEC(N''CREATE SCHEMA organizacion'');
IF SCHEMA_ID(N''rrhh'') IS NULL EXEC(N''CREATE SCHEMA rrhh'');
IF SCHEMA_ID(N''seguridad'') IS NULL EXEC(N''CREATE SCHEMA seguridad'');
IF SCHEMA_ID(N''clinico'') IS NULL EXEC(N''CREATE SCHEMA clinico'');
IF SCHEMA_ID(N''laboratorio'') IS NULL EXEC(N''CREATE SCHEMA laboratorio'');
IF SCHEMA_ID(N''etl'') IS NULL EXEC(N''CREATE SCHEMA etl'');
IF SCHEMA_ID(N''hospitalizacion'') IS NULL EXEC(N''CREATE SCHEMA hospitalizacion'');
IF SCHEMA_ID(N''redes'') IS NULL EXEC(N''CREATE SCHEMA redes'');
IF SCHEMA_ID(N''auditoria'') IS NULL EXEC(N''CREATE SCHEMA auditoria'');

/* =========================== CATALOGO =========================== */
CREATE TABLE catalogo.TipoDocumento (
    TipoDocumentoID INT IDENTITY(1,1) NOT NULL,
    Codigo          VARCHAR(20)   NOT NULL,
    Nombre          NVARCHAR(100) NOT NULL,
    Activo          BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoDocumento PRIMARY KEY (TipoDocumentoID),
    CONSTRAINT UQ_TipoDocumento_Codigo UNIQUE (Codigo)
);

CREATE TABLE catalogo.Sexo (
    SexoID  INT IDENTITY(1,1) NOT NULL,
    Codigo  VARCHAR(10)  NOT NULL,
    Nombre  NVARCHAR(50) NOT NULL,
    Activo  BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_Sexo PRIMARY KEY (SexoID),
    CONSTRAINT UQ_Sexo_Codigo UNIQUE (Codigo)
);

CREATE TABLE catalogo.EstadoCivil (
    EstadoCivilID INT IDENTITY(1,1) NOT NULL,
    Nombre        NVARCHAR(50) NOT NULL,
    Activo        BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_EstadoCivil PRIMARY KEY (EstadoCivilID),
    CONSTRAINT UQ_EstadoCivil_Nombre UNIQUE (Nombre)
);

-- Único periodo (año-mes) para TODO el sistema.
-- Reemplaza a dbo.Periodo y hospitalizacion.dim_periodo.
CREATE TABLE catalogo.Periodo (
    PeriodoID INT IDENTITY(1,1) NOT NULL,
    Anio      SMALLINT     NOT NULL,
    Mes       TINYINT      NOT NULL,
    NombreMes NVARCHAR(20) NOT NULL,
    CONSTRAINT PK_Periodo PRIMARY KEY (PeriodoID),
    CONSTRAINT UQ_Periodo_AnioMes UNIQUE (Anio, Mes),
    CONSTRAINT CK_Periodo_Anio CHECK (Anio BETWEEN 2000 AND 2100),
    CONSTRAINT CK_Periodo_Mes  CHECK (Mes BETWEEN 1 AND 12)
);

-- Catálogo CIE-10 (antes CodigoCIE era texto libre sin validar).
-- Clave natural: el propio código CIE.
CREATE TABLE catalogo.CIE10 (
    CodigoCIE   VARCHAR(10)   NOT NULL,
    Descripcion NVARCHAR(500) NOT NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_CIE10 PRIMARY KEY (CodigoCIE)
);
/* La antigua persona.Persona se elimina: identidad separada por rol. */

/* ========================= ORGANIZACION ========================= */
-- Sede = hospital / establecimiento. Unifica organizacion.Sede y
-- hospitalizacion.dim_hospital (antes había dos IDs para el mismo hospital).
CREATE TABLE organizacion.Sede (
    SedeID        INT IDENTITY(1,1) NOT NULL,
    Codigo        VARCHAR(20)   NOT NULL,     -- código RENIPRESS / código hospital
    Nombre        NVARCHAR(200) NOT NULL,
    RedSalud      NVARCHAR(200) NULL,
    NivelAtencion VARCHAR(20)   NULL,
    Direccion     NVARCHAR(250) NULL,
    Telefono      VARCHAR(30)   NULL,
    Activo        BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Sede PRIMARY KEY (SedeID),
    CONSTRAINT UQ_Sede_Codigo UNIQUE (Codigo)
);

CREATE TABLE organizacion.Departamento (
    DepartamentoID INT IDENTITY(1,1) NOT NULL,
    SedeID         INT           NOT NULL,
    Codigo         VARCHAR(20)   NOT NULL,
    Nombre         NVARCHAR(150) NOT NULL,
    Activo         BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Departamento PRIMARY KEY (DepartamentoID),
    CONSTRAINT UQ_Departamento_Codigo UNIQUE (SedeID, Codigo),
    CONSTRAINT FK_Departamento_Sede FOREIGN KEY (SedeID) REFERENCES organizacion.Sede (SedeID)
);

CREATE TABLE organizacion.Area (
    AreaID         INT IDENTITY(1,1) NOT NULL,
    DepartamentoID INT           NOT NULL,
    Codigo         VARCHAR(20)   NOT NULL,
    Nombre         NVARCHAR(150) NOT NULL,
    Descripcion    NVARCHAR(300) NULL,
    Activo         BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Area PRIMARY KEY (AreaID),
    CONSTRAINT UQ_Area_Codigo UNIQUE (DepartamentoID, Codigo),
    CONSTRAINT FK_Area_Departamento FOREIGN KEY (DepartamentoID) REFERENCES organizacion.Departamento (DepartamentoID)
);

-- Único catálogo de servicios del hospital (reemplaza a dbo.Servicio).
CREATE TABLE organizacion.Servicio (
    ServicioID  INT IDENTITY(1,1) NOT NULL,
    AreaID      INT           NOT NULL,
    Codigo      VARCHAR(20)   NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Servicio PRIMARY KEY (ServicioID),
    CONSTRAINT UQ_Servicio_Codigo UNIQUE (AreaID, Codigo),
    -- permite FK compuestas (AreaID, ServicioID) para que un servicio
    -- nunca quede asignado a un área que no es la suya
    CONSTRAINT UQ_Servicio_AreaServicio UNIQUE (AreaID, ServicioID),
    CONSTRAINT FK_Servicio_Area FOREIGN KEY (AreaID) REFERENCES organizacion.Area (AreaID)
);
/* ===================== RRHH: catálogos y Personal ===================== */
CREATE TABLE rrhh.Profesion (
    ProfesionID INT IDENTITY(1,1) NOT NULL,
    Codigo      VARCHAR(20)   NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Profesion PRIMARY KEY (ProfesionID),
    CONSTRAINT UQ_Profesion_Codigo UNIQUE (Codigo)
);

CREATE TABLE rrhh.Especialidad (
    EspecialidadID INT IDENTITY(1,1) NOT NULL,
    ProfesionID    INT           NOT NULL,
    Nombre         NVARCHAR(150) NOT NULL,
    Activo         BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Especialidad PRIMARY KEY (EspecialidadID),
    CONSTRAINT UQ_Especialidad_Nombre UNIQUE (ProfesionID, Nombre),
    CONSTRAINT UQ_Especialidad_ProfesionEspecialidad UNIQUE (ProfesionID, EspecialidadID),
    CONSTRAINT FK_Especialidad_Profesion FOREIGN KEY (ProfesionID) REFERENCES rrhh.Profesion (ProfesionID)
);

CREATE TABLE rrhh.Cargo (
    CargoID     INT IDENTITY(1,1) NOT NULL,
    Codigo      VARCHAR(20)   NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Nivel       INT           NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Cargo PRIMARY KEY (CargoID),
    CONSTRAINT UQ_Cargo_Codigo UNIQUE (Codigo)
);

CREATE TABLE rrhh.TipoContrato (
    TipoContratoID INT IDENTITY(1,1) NOT NULL,
    Nombre         NVARCHAR(100) NOT NULL,
    Descripcion    NVARCHAR(250) NULL,
    Activo         BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoContrato PRIMARY KEY (TipoContratoID),
    CONSTRAINT UQ_TipoContrato_Nombre UNIQUE (Nombre)
);

CREATE TABLE rrhh.TipoPermisoLaboral (
    TipoPermisoLaboralID INT IDENTITY(1,1) NOT NULL,
    Nombre               NVARCHAR(100) NOT NULL,
    Descripcion          NVARCHAR(250) NULL,
    Activo               BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoPermisoLaboral PRIMARY KEY (TipoPermisoLaboralID),
    CONSTRAINT UQ_TipoPermisoLaboral_Nombre UNIQUE (Nombre)
);

-- Catálogo único de códigos de turno/ausencia. Antes los códigos estaban
-- "quemados" en un CHECK de ProgramacionMensualDetalle y no tenían relación
-- con la tabla Turno. Ahora la programación referencia Turno.Codigo.
CREATE TABLE rrhh.Turno (
    TurnoID    INT IDENTITY(1,1) NOT NULL,
    Codigo     VARCHAR(16)   NOT NULL,
    Nombre     NVARCHAR(100) NOT NULL,
    Tipo       VARCHAR(20)   NOT NULL,          -- TURNO | AUSENCIA
    HoraInicio TIME(0)       NULL,              -- NULL para ausencias
    HoraFin    TIME(0)       NULL,
    Horas      DECIMAL(4,1)  NULL,
    Activo     BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Turno PRIMARY KEY (TurnoID),
    CONSTRAINT UQ_Turno_Codigo UNIQUE (Codigo),
    CONSTRAINT CK_Turno_Tipo CHECK (Tipo IN (''TURNO'', ''AUSENCIA''))
);

/* ===================== RRHH: PERSONAL INDEPENDIENTE ===================== */
-- NO existe PersonaID: todos los datos de identidad y laborales del trabajador
-- permanecen exclusivamente en rrhh.Personal.
CREATE TABLE rrhh.Personal (
    PersonalID                 INT IDENTITY(1,1) NOT NULL,
    CodigoEmpleado             VARCHAR(30)    NOT NULL,
    -- Identidad y contacto personal (independientes del paciente)
    TipoDocumentoID           INT            NOT NULL,
    NumeroDocumento           VARCHAR(20)    NOT NULL,
    Nombres                   NVARCHAR(100)  NOT NULL,
    ApellidoPaterno           NVARCHAR(100)  NOT NULL,
    ApellidoMaterno           NVARCHAR(100)  NULL,
    FechaNacimiento           DATE           NULL,
    SexoID                    INT            NULL,
    EstadoCivilID             INT            NULL,
    Telefono                  VARCHAR(30)    NULL,
    Celular                   VARCHAR(30)    NULL,
    Correo                    VARCHAR(150)   NULL,
    -- Dependencia laboral
    SedeID                     INT            NULL,
    ProfesionID                INT            NULL,
    EspecialidadID             INT            NULL,
    GradoAcademico             VARCHAR(30)    NULL,
    ColegioProfesional         VARCHAR(20)    NULL,
    NumeroColegiatura          VARCHAR(20)    NULL,
    FechaColegiatura           DATE           NULL,
    FechaVencimientoHabilidad  DATE           NULL,
    NumeroRNE                  VARCHAR(20)    NULL,
    RegimenLaboral             VARCHAR(20)    NULL,
    CondicionLaboral           VARCHAR(20)    NULL,
    GrupoOcupacional           VARCHAR(20)    NULL,
    NivelRemunerativo          VARCHAR(20)    NULL,
    FechaIngreso               DATE           NOT NULL,
    FechaCese                  DATE           NULL,
    MotivoCese                 NVARCHAR(200)  NULL,
    -- Contacto institucional
    CorreoInstitucional        VARCHAR(150)   NULL,
    TelefonoLaboral            VARCHAR(30)    NULL,
    Anexo                      VARCHAR(10)    NULL,
    -- Datos administrativos, tributarios y previsionales
    RUC                        CHAR(11)       NULL,
    SistemaPensionario         VARCHAR(10)    NULL,
    NombreAFP                  NVARCHAR(50)   NULL,
    CUSPP                      VARCHAR(20)    NULL,
    Banco                      NVARCHAR(100)  NULL,
    NumeroCuenta               VARCHAR(30)    NULL,
    CCI                        CHAR(20)       NULL,
    -- Emergencia
    ContactoEmergenciaNombre     NVARCHAR(150) NULL,
    ContactoEmergenciaParentesco NVARCHAR(50)  NULL,
    ContactoEmergenciaTelefono   VARCHAR(30)   NULL,
    -- Control
    FotoUrl                    VARCHAR(300)   NULL,
    Observacion                NVARCHAR(500)  NULL,
    FechaRegistro              DATETIME2(0)   NOT NULL DEFAULT (SYSDATETIME()),
    FechaActualizacion         DATETIME2(0)   NULL,
    Activo                     BIT            NOT NULL DEFAULT (1),
    CONSTRAINT PK_Personal PRIMARY KEY (PersonalID),
    CONSTRAINT UQ_Personal_Codigo UNIQUE (CodigoEmpleado),
    CONSTRAINT UQ_Personal_Documento UNIQUE (TipoDocumentoID, NumeroDocumento),
    CONSTRAINT FK_Personal_TipoDocumento FOREIGN KEY (TipoDocumentoID) REFERENCES catalogo.TipoDocumento (TipoDocumentoID),
    CONSTRAINT FK_Personal_Sexo FOREIGN KEY (SexoID) REFERENCES catalogo.Sexo (SexoID),
    CONSTRAINT FK_Personal_EstadoCivil FOREIGN KEY (EstadoCivilID) REFERENCES catalogo.EstadoCivil (EstadoCivilID),
    CONSTRAINT FK_Personal_Sede FOREIGN KEY (SedeID) REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_Personal_Profesion FOREIGN KEY (ProfesionID) REFERENCES rrhh.Profesion (ProfesionID),
    CONSTRAINT FK_Personal_Especialidad FOREIGN KEY (ProfesionID, EspecialidadID)
        REFERENCES rrhh.Especialidad (ProfesionID, EspecialidadID),
    CONSTRAINT CK_Personal_Nacimiento CHECK (FechaNacimiento IS NULL OR FechaNacimiento <= CAST(SYSDATETIME() AS DATE)),
    CONSTRAINT CK_Personal_Especialidad CHECK (EspecialidadID IS NULL OR ProfesionID IS NOT NULL),
    CONSTRAINT CK_Personal_RNE CHECK (NumeroRNE IS NULL OR EspecialidadID IS NOT NULL),
    CONSTRAINT CK_Personal_Colegiatura CHECK (NumeroColegiatura IS NULL OR ColegioProfesional IS NOT NULL),
    CONSTRAINT CK_Personal_Fechas CHECK (FechaCese IS NULL OR FechaCese >= FechaIngreso),
    CONSTRAINT CK_Personal_MotivoCese CHECK (FechaCese IS NOT NULL OR MotivoCese IS NULL),
    CONSTRAINT CK_Personal_GradoAcademico CHECK (GradoAcademico IS NULL OR GradoAcademico IN
        (''SECUNDARIA'', ''TECNICO'', ''BACHILLER'', ''TITULADO'', ''MAESTRIA'', ''DOCTORADO'')),
    CONSTRAINT CK_Personal_Regimen CHECK (RegimenLaboral IS NULL OR RegimenLaboral IN
        (''DL 276'', ''DL 728'', ''DL 1057 CAS'', ''DL 1153'', ''LEY 30057'', ''LOCACION'', ''OTRO'')),
    CONSTRAINT CK_Personal_Condicion CHECK (CondicionLaboral IS NULL OR CondicionLaboral IN
        (''NOMBRADO'', ''CONTRATADO'', ''CAS'', ''DESTACADO'', ''DESIGNADO'', ''RESIDENTE'', ''SERUMS'',
         ''INTERNO'', ''PRACTICANTE'', ''TERCERO'')),
    CONSTRAINT CK_Personal_Grupo CHECK (GrupoOcupacional IS NULL OR GrupoOcupacional IN
        (''FUNCIONARIO'', ''PROFESIONAL'', ''TECNICO'', ''AUXILIAR'', ''ADMINISTRATIVO'')),
    CONSTRAINT CK_Personal_Pension CHECK (SistemaPensionario IS NULL OR SistemaPensionario IN (''ONP'', ''AFP'', ''NINGUNO'')),
    CONSTRAINT CK_Personal_AFP CHECK (SistemaPensionario <> ''AFP'' OR (NombreAFP IS NOT NULL AND CUSPP IS NOT NULL)),
    CONSTRAINT CK_Personal_RUC CHECK (RUC IS NULL OR (LEN(RUC) = 11 AND RUC NOT LIKE ''%[^0-9]%'')),
    CONSTRAINT CK_Personal_CCI CHECK (CCI IS NULL OR (LEN(CCI) = 20 AND CCI NOT LIKE ''%[^0-9]%'')),
    CONSTRAINT CK_Personal_Habilidad CHECK (FechaVencimientoHabilidad IS NULL OR FechaColegiatura IS NULL
        OR FechaVencimientoHabilidad >= FechaColegiatura)
);
CREATE UNIQUE INDEX UX_Personal_CorreoInstitucional ON rrhh.Personal (CorreoInstitucional)
    WHERE CorreoInstitucional IS NOT NULL;
CREATE UNIQUE INDEX UX_Personal_Colegiatura ON rrhh.Personal (ColegioProfesional, NumeroColegiatura)
    WHERE NumeroColegiatura IS NOT NULL;
CREATE UNIQUE INDEX UX_Personal_RUC ON rrhh.Personal (RUC)
    WHERE RUC IS NOT NULL;
CREATE INDEX IX_Personal_Sede ON rrhh.Personal (SedeID);
CREATE INDEX IX_Personal_Profesion ON rrhh.Personal (ProfesionID, EspecialidadID);
CREATE INDEX IX_Personal_Apellidos ON rrhh.Personal (ApellidoPaterno, ApellidoMaterno);
CREATE INDEX IX_Personal_Nombres ON rrhh.Personal (Nombres);

/* =========================== SEGURIDAD =========================== */
CREATE TABLE seguridad.Sistema (
    SistemaID   INT IDENTITY(1,1) NOT NULL,
    Codigo      VARCHAR(30)   NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Version     VARCHAR(30)   NULL,
    URL         VARCHAR(300)  NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Sistema PRIMARY KEY (SistemaID),
    CONSTRAINT UQ_Sistema_Codigo UNIQUE (Codigo)
);

CREATE TABLE seguridad.Modulo (
    ModuloID    INT IDENTITY(1,1) NOT NULL,
    SistemaID   INT           NOT NULL,
    Codigo      VARCHAR(30)   NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Ruta        VARCHAR(300)  NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Modulo PRIMARY KEY (ModuloID),
    CONSTRAINT UQ_Modulo_Codigo UNIQUE (SistemaID, Codigo),
    CONSTRAINT FK_Modulo_Sistema FOREIGN KEY (SistemaID) REFERENCES seguridad.Sistema (SistemaID)
);

-- Antes Permiso no se relacionaba con ningún módulo.
CREATE TABLE seguridad.Permiso (
    PermisoID   INT IDENTITY(1,1) NOT NULL,
    ModuloID    INT           NULL,
    Codigo      VARCHAR(100)  NOT NULL,
    Nombre      NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Permiso PRIMARY KEY (PermisoID),
    CONSTRAINT UQ_Permiso_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_Permiso_Modulo FOREIGN KEY (ModuloID) REFERENCES seguridad.Modulo (ModuloID)
);

CREATE TABLE seguridad.Rol (
    RolID       INT IDENTITY(1,1) NOT NULL,
    SistemaID   INT           NULL,            -- NULL = rol transversal
    Codigo      VARCHAR(50)   NOT NULL,
    Nombre      NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Rol PRIMARY KEY (RolID),
    CONSTRAINT UQ_Rol_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_Rol_Sistema FOREIGN KEY (SistemaID) REFERENCES seguridad.Sistema (SistemaID)
);

CREATE TABLE seguridad.RolPermiso (
    RolID           INT          NOT NULL,
    PermisoID       INT          NOT NULL,
    FechaAsignacion DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Activo          BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_RolPermiso PRIMARY KEY (RolID, PermisoID),
    CONSTRAINT FK_RolPermiso_Rol     FOREIGN KEY (RolID)     REFERENCES seguridad.Rol (RolID),
    CONSTRAINT FK_RolPermiso_Permiso FOREIGN KEY (PermisoID) REFERENCES seguridad.Permiso (PermisoID)
);

-- Los usuarios del sistema son PERSONAL del hospital (antes apuntaba a
-- Persona, lo que permitía crear usuarios a pacientes).
CREATE TABLE seguridad.Usuario (
    UsuarioID     INT IDENTITY(1,1) NOT NULL,
    PersonalID    INT          NOT NULL,
    NombreUsuario VARCHAR(100) NOT NULL,
    PasswordHash  VARCHAR(500) NOT NULL,
    FechaCreacion DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    UltimoAcceso  DATETIME2(0) NULL,
    Bloqueado     BIT          NOT NULL DEFAULT (0),
    Activo        BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_Usuario PRIMARY KEY (UsuarioID),
    CONSTRAINT UQ_Usuario_Nombre   UNIQUE (NombreUsuario),
    CONSTRAINT UQ_Usuario_Personal UNIQUE (PersonalID),
    CONSTRAINT FK_Usuario_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID)
);

CREATE TABLE seguridad.UsuarioRol (
    UsuarioID       INT          NOT NULL,
    RolID           INT          NOT NULL,
    FechaAsignacion DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    FechaInicio     DATE         NOT NULL,
    FechaFin        DATE         NULL,
    Activo          BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_UsuarioRol PRIMARY KEY (UsuarioID, RolID),
    CONSTRAINT FK_UsuarioRol_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT FK_UsuarioRol_Rol     FOREIGN KEY (RolID)     REFERENCES seguridad.Rol (RolID),
    CONSTRAINT CK_UsuarioRol_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);

CREATE TABLE seguridad.UsuarioSistema (
    UsuarioID   INT  NOT NULL,
    SistemaID   INT  NOT NULL,
    FechaInicio DATE NOT NULL,
    FechaFin    DATE NULL,
    Activo      BIT  NOT NULL DEFAULT (1),
    CONSTRAINT PK_UsuarioSistema PRIMARY KEY (UsuarioID, SistemaID),
    CONSTRAINT FK_UsuarioSistema_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT FK_UsuarioSistema_Sistema FOREIGN KEY (SistemaID) REFERENCES seguridad.Sistema (SistemaID),
    CONSTRAINT CK_UsuarioSistema_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);

CREATE TABLE seguridad.VersionDatos (
    VersionDatosID TINYINT NOT NULL,
    Revision       BIGINT  NOT NULL DEFAULT (1),
    CONSTRAINT PK_VersionDatos PRIMARY KEY (VersionDatosID),
    CONSTRAINT CK_VersionDatos_Unica CHECK (VersionDatosID = 1)
);
/* ====================== RRHH: operación del personal ====================== */
-- Dónde trabaja el personal. Profesión/especialidad ya no van aquí.
CREATE TABLE rrhh.AsignacionPersonal (
    AsignacionPersonalID INT IDENTITY(1,1) NOT NULL,
    PersonalID  INT  NOT NULL,
    AreaID      INT  NOT NULL,
    ServicioID  INT  NULL,
    CargoID     INT  NOT NULL,
    FechaInicio DATE NOT NULL,
    FechaFin    DATE NULL,
    EsActual    BIT  NOT NULL DEFAULT (0),
    Activo      BIT  NOT NULL DEFAULT (1),
    CONSTRAINT PK_AsignacionPersonal PRIMARY KEY (AsignacionPersonalID),
    CONSTRAINT FK_AsignacionPersonal_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_AsignacionPersonal_Area     FOREIGN KEY (AreaID)     REFERENCES organizacion.Area (AreaID),
    -- el servicio debe pertenecer al área indicada
    CONSTRAINT FK_AsignacionPersonal_Servicio FOREIGN KEY (AreaID, ServicioID)
        REFERENCES organizacion.Servicio (AreaID, ServicioID),
    CONSTRAINT FK_AsignacionPersonal_Cargo    FOREIGN KEY (CargoID)    REFERENCES rrhh.Cargo (CargoID),
    CONSTRAINT CK_AsignacionPersonal_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);
CREATE UNIQUE INDEX UX_AsignacionPersonal_Actual ON rrhh.AsignacionPersonal (PersonalID) WHERE EsActual = 1;

CREATE TABLE rrhh.Contrato (
    ContratoID     INT IDENTITY(1,1) NOT NULL,
    PersonalID     INT         NOT NULL,
    TipoContratoID INT         NOT NULL,
    NumeroContrato VARCHAR(50) NULL,
    FechaInicio    DATE        NOT NULL,
    FechaFin       DATE        NULL,
    Activo         BIT         NOT NULL DEFAULT (1),
    CONSTRAINT PK_Contrato PRIMARY KEY (ContratoID),
    CONSTRAINT FK_Contrato_Personal     FOREIGN KEY (PersonalID)     REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_Contrato_TipoContrato FOREIGN KEY (TipoContratoID) REFERENCES rrhh.TipoContrato (TipoContratoID),
    CONSTRAINT CK_Contrato_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);
CREATE UNIQUE INDEX UX_Contrato_Numero ON rrhh.Contrato (NumeroContrato) WHERE NumeroContrato IS NOT NULL;

CREATE TABLE rrhh.PermisoLaboral (
    PermisoLaboralID     INT IDENTITY(1,1) NOT NULL,
    PersonalID           INT           NOT NULL,
    TipoPermisoLaboralID INT           NOT NULL,
    FechaInicio          DATE          NOT NULL,
    FechaFin             DATE          NOT NULL,
    Motivo               NVARCHAR(500) NULL,
    Estado               VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    CONSTRAINT PK_PermisoLaboral PRIMARY KEY (PermisoLaboralID),
    CONSTRAINT FK_PermisoLaboral_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_PermisoLaboral_Tipo     FOREIGN KEY (TipoPermisoLaboralID) REFERENCES rrhh.TipoPermisoLaboral (TipoPermisoLaboralID),
    CONSTRAINT CK_PermisoLaboral_Fechas CHECK (FechaFin >= FechaInicio)
);

CREATE TABLE rrhh.Asistencia (
    AsistenciaID INT IDENTITY(1,1) NOT NULL,
    PersonalID   INT           NOT NULL,
    Fecha        DATE          NOT NULL,
    HoraEntrada  TIME(0)       NULL,
    HoraSalida   TIME(0)       NULL,
    Estado       VARCHAR(30)   NOT NULL,
    Observacion  NVARCHAR(300) NULL,
    CONSTRAINT PK_Asistencia PRIMARY KEY (AsistenciaID),
    CONSTRAINT UQ_Asistencia_PersonalFecha UNIQUE (PersonalID, Fecha),
    CONSTRAINT FK_Asistencia_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID)
);

-- Cabecera de la programación mensual. (Anio, Mes) se valida contra el
-- periodo único del sistema.
CREATE TABLE rrhh.ProgramacionMensual (
    Anio          SMALLINT     NOT NULL,
    Mes           TINYINT      NOT NULL,
    Version       BIGINT       NOT NULL DEFAULT (1),
    ActualizadoEn DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    UsuarioID     INT          NULL,
    CONSTRAINT PK_ProgramacionMensual PRIMARY KEY (Anio, Mes),
    CONSTRAINT FK_ProgramacionMensual_Periodo FOREIGN KEY (Anio, Mes) REFERENCES catalogo.Periodo (Anio, Mes),
    CONSTRAINT FK_ProgramacionMensual_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID)
);

CREATE TABLE rrhh.ProgramacionMensualDetalle (
    Anio        SMALLINT    NOT NULL,
    Mes         TINYINT     NOT NULL,
    PersonalID  INT         NOT NULL,
    Dia         TINYINT     NOT NULL,
    CodigoTurno VARCHAR(16) NOT NULL,
    CONSTRAINT PK_ProgramacionMensualDetalle PRIMARY KEY (Anio, Mes, PersonalID, Dia),
    CONSTRAINT FK_ProgramacionDetalle_Cabecera FOREIGN KEY (Anio, Mes)   REFERENCES rrhh.ProgramacionMensual (Anio, Mes),
    CONSTRAINT FK_ProgramacionDetalle_Personal FOREIGN KEY (PersonalID)  REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_ProgramacionDetalle_Turno    FOREIGN KEY (CodigoTurno) REFERENCES rrhh.Turno (Codigo),
    CONSTRAINT CK_ProgramacionDetalle_Dia CHECK (Dia >= 1 AND Dia <= DAY(EOMONTH(DATEFROMPARTS(Anio, Mes, 1))))
);

CREATE TABLE rrhh.ImportacionBiometrica (
    ImportacionBiometricaID INT IDENTITY(1,1) NOT NULL,
    NombreArchivo           NVARCHAR(255)  NOT NULL,
    HashSHA256              CHAR(64)       NOT NULL,
    FechaHoraCarga          DATETIME2(0)   NOT NULL DEFAULT (SYSDATETIME()),
    UsuarioID               INT            NULL,
    TotalRegistros          INT            NOT NULL DEFAULT (0),
    MarcacionesValidas      INT            NOT NULL DEFAULT (0),
    MarcacionesOmitidas     INT            NOT NULL DEFAULT (0),
    MarcacionesDuplicadas   INT            NOT NULL DEFAULT (0),
    MarcacionesConsecutivas INT            NOT NULL DEFAULT (0),
    VinculadosDirectos      INT            NOT NULL DEFAULT (0),
    PendientesVinculacion   INT            NOT NULL DEFAULT (0),
    Estado                  VARCHAR(30)    NOT NULL DEFAULT (''COMPLETADO''),
    Observaciones           NVARCHAR(500)  NULL,
    MensajeError            NVARCHAR(1000) NULL,
    CONSTRAINT PK_ImportacionBiometrica PRIMARY KEY (ImportacionBiometricaID),
    CONSTRAINT FK_ImportacionBiometrica_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID)
);
CREATE INDEX IX_ImportacionBiometrica_Hash ON rrhh.ImportacionBiometrica (HashSHA256);

-- Vínculo ID del biométrico (BioStar) -> Personal
CREATE TABLE rrhh.VinculacionBiometrica (
    VinculacionBiometricaID INT IDENTITY(1,1) NOT NULL,
    BiostarUserID   VARCHAR(30)   NOT NULL,
    PersonalID      INT           NOT NULL,
    TipoVinculacion VARCHAR(30)   NOT NULL DEFAULT (''DIRECTA''),
    Observacion     NVARCHAR(200) NULL,
    FechaCreacion   DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    UsuarioID       INT           NULL,
    CONSTRAINT PK_VinculacionBiometrica PRIMARY KEY (VinculacionBiometricaID),
    CONSTRAINT UQ_VinculacionBiometrica_Biostar UNIQUE (BiostarUserID),
    CONSTRAINT FK_VinculacionBiometrica_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_VinculacionBiometrica_Usuario  FOREIGN KEY (UsuarioID)  REFERENCES seguridad.Usuario (UsuarioID)
);

CREATE TABLE rrhh.MarcacionBiometrica (
    MarcacionBiometricaID   BIGINT IDENTITY(1,1) NOT NULL,
    ImportacionBiometricaID INT           NOT NULL,
    BiostarUserID           VARCHAR(30)   NOT NULL,
    BiostarUserName         NVARCHAR(150) NULL,
    FechaHora               DATETIME2(0)  NOT NULL,
    DispositivoID           VARCHAR(50)   NOT NULL,
    DispositivoNombre       NVARCHAR(150) NULL,
    Evento                  NVARCHAR(100) NOT NULL,
    PersonalID              INT           NULL,
    EstadoProcesamiento     VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    EstadoVinculacion       VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    EsConsecutiva           BIT           NOT NULL DEFAULT (0),
    SegundosDiferencia      INT           NULL,
    Advertencia             NVARCHAR(200) NULL,
    CONSTRAINT PK_MarcacionBiometrica PRIMARY KEY (MarcacionBiometricaID),
    CONSTRAINT UQ_MarcacionBiometrica_Evento UNIQUE (BiostarUserID, FechaHora, DispositivoID, Evento),
    CONSTRAINT FK_MarcacionBiometrica_Importacion FOREIGN KEY (ImportacionBiometricaID) REFERENCES rrhh.ImportacionBiometrica (ImportacionBiometricaID),
    CONSTRAINT FK_MarcacionBiometrica_Personal    FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID)
);
CREATE INDEX IX_MarcacionBiometrica_PersonalFecha ON rrhh.MarcacionBiometrica (PersonalID, FechaHora);

CREATE TABLE rrhh.IncidenciaBiometrica (
    IncidenciaBiometricaID  INT IDENTITY(1,1) NOT NULL,
    ImportacionBiometricaID INT           NOT NULL,
    MarcacionBiometricaID   BIGINT        NULL,
    BiostarUserID           VARCHAR(30)   NULL,
    TipoIncidencia          NVARCHAR(50)  NOT NULL,
    Nivel                   VARCHAR(20)   NOT NULL DEFAULT (''ADVERTENCIA''),
    Detalle                 NVARCHAR(500) NOT NULL,
    FechaHoraEvento         DATETIME2(0)  NULL,
    FechaHoraRegistro       DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_IncidenciaBiometrica PRIMARY KEY (IncidenciaBiometricaID),
    CONSTRAINT FK_IncidenciaBiometrica_Importacion FOREIGN KEY (ImportacionBiometricaID) REFERENCES rrhh.ImportacionBiometrica (ImportacionBiometricaID),
    CONSTRAINT FK_IncidenciaBiometrica_Marcacion   FOREIGN KEY (MarcacionBiometricaID)   REFERENCES rrhh.MarcacionBiometrica (MarcacionBiometricaID)
);
CREATE INDEX IX_IncidenciaBiometrica_Importacion ON rrhh.IncidenciaBiometrica (ImportacionBiometricaID);
/* ============================ CLINICO ============================ */
/* ============================ PACIENTES INDEPENDIENTES ============================ */
-- NO existe PersonaID: los datos de identidad se guardan aquí y NO en rrhh.Personal.
CREATE TABLE clinico.Paciente (
    PacienteID                INT IDENTITY(1,1) NOT NULL,
    HistoriaClinica           VARCHAR(30)     NOT NULL,
    TipoDocumentoID           INT            NOT NULL,
    NumeroDocumento           VARCHAR(20)    NOT NULL,
    Nombres                   NVARCHAR(100)  NOT NULL,
    ApellidoPaterno           NVARCHAR(100)  NOT NULL,
    ApellidoMaterno           NVARCHAR(100)  NULL,
    FechaNacimiento           DATE           NULL,
    SexoID                    INT            NULL,
    EstadoCivilID             INT            NULL,
    Telefono                  VARCHAR(30)    NULL,
    Celular                   VARCHAR(30)    NULL,
    Correo                    VARCHAR(150)   NULL,
    FechaRegistro             DATETIME2(0)   NOT NULL DEFAULT (SYSDATETIME()),
    FechaActualizacion        DATETIME2(0)   NULL,
    Activo                    BIT            NOT NULL DEFAULT (1),
    CONSTRAINT PK_Paciente PRIMARY KEY (PacienteID),
    CONSTRAINT UQ_Paciente_Historia UNIQUE (HistoriaClinica),
    CONSTRAINT UQ_Paciente_Documento UNIQUE (TipoDocumentoID, NumeroDocumento),
    CONSTRAINT FK_Paciente_TipoDocumento FOREIGN KEY (TipoDocumentoID) REFERENCES catalogo.TipoDocumento (TipoDocumentoID),
    CONSTRAINT FK_Paciente_Sexo FOREIGN KEY (SexoID) REFERENCES catalogo.Sexo (SexoID),
    CONSTRAINT FK_Paciente_EstadoCivil FOREIGN KEY (EstadoCivilID) REFERENCES catalogo.EstadoCivil (EstadoCivilID),
    CONSTRAINT CK_Paciente_Nacimiento CHECK (FechaNacimiento IS NULL OR FechaNacimiento <= CAST(SYSDATETIME() AS DATE))
);
CREATE INDEX IX_Paciente_Apellidos ON clinico.Paciente (ApellidoPaterno, ApellidoMaterno);
CREATE INDEX IX_Paciente_Nombres ON clinico.Paciente (Nombres);

CREATE TABLE clinico.TipoAntecedente (
    TipoAntecedenteID INT IDENTITY(1,1) NOT NULL,
    Nombre            NVARCHAR(100) NOT NULL,
    Descripcion       NVARCHAR(250) NULL,
    Activo            BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoAntecedente PRIMARY KEY (TipoAntecedenteID),
    CONSTRAINT UQ_TipoAntecedente_Nombre UNIQUE (Nombre)
);

CREATE TABLE clinico.Antecedente (
    AntecedenteID     INT IDENTITY(1,1) NOT NULL,
    PacienteID        INT           NOT NULL,
    TipoAntecedenteID INT           NOT NULL,
    Descripcion       NVARCHAR(500) NOT NULL,
    FechaRegistro     DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Activo            BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Antecedente PRIMARY KEY (AntecedenteID),
    CONSTRAINT FK_Antecedente_Paciente FOREIGN KEY (PacienteID) REFERENCES clinico.Paciente (PacienteID),
    CONSTRAINT FK_Antecedente_Tipo     FOREIGN KEY (TipoAntecedenteID) REFERENCES clinico.TipoAntecedente (TipoAntecedenteID)
);

CREATE TABLE clinico.Alergia (
    AlergiaID     INT IDENTITY(1,1) NOT NULL,
    PacienteID    INT           NOT NULL,
    Alergeno      NVARCHAR(200) NOT NULL,
    Reaccion      NVARCHAR(300) NULL,
    Severidad     VARCHAR(20)   NULL,
    Observacion   NVARCHAR(500) NULL,
    FechaRegistro DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Activo        BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Alergia PRIMARY KEY (AlergiaID),
    CONSTRAINT FK_Alergia_Paciente FOREIGN KEY (PacienteID) REFERENCES clinico.Paciente (PacienteID),
    CONSTRAINT CK_Alergia_Severidad CHECK (Severidad IS NULL OR Severidad IN (''LEVE'', ''MODERADA'', ''SEVERA''))
);

CREATE TABLE clinico.Diagnostico (
    DiagnosticoID    INT IDENTITY(1,1) NOT NULL,
    PacienteID       INT           NOT NULL,
    CodigoCIE        VARCHAR(10)   NULL,
    Descripcion      NVARCHAR(500) NOT NULL,
    FechaDiagnostico DATE          NOT NULL,
    PersonalID       INT           NULL,      -- médico que diagnostica
    Activo           BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Diagnostico PRIMARY KEY (DiagnosticoID),
    CONSTRAINT FK_Diagnostico_Paciente FOREIGN KEY (PacienteID) REFERENCES clinico.Paciente (PacienteID),
    CONSTRAINT FK_Diagnostico_CIE10    FOREIGN KEY (CodigoCIE)  REFERENCES catalogo.CIE10 (CodigoCIE),
    CONSTRAINT FK_Diagnostico_Personal FOREIGN KEY (PersonalID) REFERENCES rrhh.Personal (PersonalID)
);
CREATE INDEX IX_Diagnostico_Paciente ON clinico.Diagnostico (PacienteID);
/* ========================== LABORATORIO ========================== */
CREATE TABLE laboratorio.TipoExamen (
    TipoExamenID INT IDENTITY(1,1) NOT NULL,
    Nombre       NVARCHAR(100) NOT NULL,
    Descripcion  NVARCHAR(250) NULL,
    Activo       BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoExamen PRIMARY KEY (TipoExamenID),
    CONSTRAINT UQ_TipoExamen_Nombre UNIQUE (Nombre)
);

CREATE TABLE laboratorio.Examen (
    ExamenID     INT IDENTITY(1,1) NOT NULL,
    TipoExamenID INT           NOT NULL,
    Codigo       VARCHAR(30)   NOT NULL,
    Nombre       NVARCHAR(200) NOT NULL,
    Descripcion  NVARCHAR(500) NULL,
    Activo       BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Examen PRIMARY KEY (ExamenID),
    CONSTRAINT UQ_Examen_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_Examen_TipoExamen FOREIGN KEY (TipoExamenID) REFERENCES laboratorio.TipoExamen (TipoExamenID)
);

CREATE TABLE laboratorio.UnidadMedida (
    UnidadMedidaID INT IDENTITY(1,1) NOT NULL,
    Codigo         VARCHAR(30)   NOT NULL,
    Nombre         NVARCHAR(100) NOT NULL,
    Simbolo        VARCHAR(30)   NULL,
    Activo         BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_UnidadMedida PRIMARY KEY (UnidadMedidaID),
    CONSTRAINT UQ_UnidadMedida_Codigo UNIQUE (Codigo)
);

CREATE TABLE laboratorio.MetodoAnalisis (
    MetodoAnalisisID INT IDENTITY(1,1) NOT NULL,
    Codigo           VARCHAR(30)   NOT NULL,
    Nombre           NVARCHAR(150) NOT NULL,
    Descripcion      NVARCHAR(300) NULL,
    Activo           BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_MetodoAnalisis PRIMARY KEY (MetodoAnalisisID),
    CONSTRAINT UQ_MetodoAnalisis_Codigo UNIQUE (Codigo)
);

CREATE TABLE laboratorio.Parametro (
    ParametroID      INT IDENTITY(1,1) NOT NULL,
    ExamenID         INT           NOT NULL,
    UnidadMedidaID   INT           NULL,
    MetodoAnalisisID INT           NULL,
    Nombre           NVARCHAR(150) NOT NULL,
    TipoDato         VARCHAR(30)   NOT NULL DEFAULT (''TEXTO''),
    Orden            INT           NOT NULL DEFAULT (1),
    Activo           BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Parametro PRIMARY KEY (ParametroID),
    CONSTRAINT UQ_Parametro_ExamenNombre UNIQUE (ExamenID, Nombre),
    CONSTRAINT FK_Parametro_Examen         FOREIGN KEY (ExamenID)         REFERENCES laboratorio.Examen (ExamenID),
    CONSTRAINT FK_Parametro_UnidadMedida   FOREIGN KEY (UnidadMedidaID)   REFERENCES laboratorio.UnidadMedida (UnidadMedidaID),
    CONSTRAINT FK_Parametro_MetodoAnalisis FOREIGN KEY (MetodoAnalisisID) REFERENCES laboratorio.MetodoAnalisis (MetodoAnalisisID)
);

CREATE TABLE laboratorio.ValorReferencia (
    ValorReferenciaID INT IDENTITY(1,1) NOT NULL,
    ParametroID       INT            NOT NULL,
    SexoID            INT            NULL,
    EdadMinima        DECIMAL(6,2)   NULL,
    EdadMaxima        DECIMAL(6,2)   NULL,
    ValorMinimo       DECIMAL(18,6)  NULL,
    ValorMaximo       DECIMAL(18,6)  NULL,
    TextoReferencia   NVARCHAR(300)  NULL,
    Activo            BIT            NOT NULL DEFAULT (1),
    CONSTRAINT PK_ValorReferencia PRIMARY KEY (ValorReferenciaID),
    CONSTRAINT FK_ValorReferencia_Parametro FOREIGN KEY (ParametroID) REFERENCES laboratorio.Parametro (ParametroID),
    CONSTRAINT FK_ValorReferencia_Sexo      FOREIGN KEY (SexoID)      REFERENCES catalogo.Sexo (SexoID),
    CONSTRAINT CK_ValorReferencia_Edad    CHECK (EdadMaxima IS NULL OR EdadMinima IS NULL OR EdadMaxima >= EdadMinima),
    CONSTRAINT CK_ValorReferencia_Valores CHECK (ValorMaximo IS NULL OR ValorMinimo IS NULL OR ValorMaximo >= ValorMinimo)
);

CREATE TABLE laboratorio.TipoMuestra (
    TipoMuestraID INT IDENTITY(1,1) NOT NULL,
    Nombre        NVARCHAR(100) NOT NULL,
    Descripcion   NVARCHAR(250) NULL,
    Activo        BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoMuestra PRIMARY KEY (TipoMuestraID),
    CONSTRAINT UQ_TipoMuestra_Nombre UNIQUE (Nombre)
);

-- Solicitud: el PACIENTE recibe, el PERSONAL solicita, desde un SERVICIO.
CREATE TABLE laboratorio.Solicitud (
    SolicitudID           INT IDENTITY(1,1) NOT NULL,
    CodigoSolicitud       VARCHAR(30)   NULL,
    PacienteID            INT           NOT NULL,
    SolicitantePersonalID INT           NOT NULL,
    ServicioID            INT           NOT NULL,
    FechaSolicitud        DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Prioridad             VARCHAR(30)   NOT NULL DEFAULT (''NORMAL''),
    Estado                VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    CodigoCIE             VARCHAR(10)   NULL,  -- diagnóstico presuntivo codificado
    DiagnosticoPresuntivo NVARCHAR(300) NULL,  -- texto libre complementario
    Observacion           NVARCHAR(500) NULL,
    CONSTRAINT PK_Solicitud PRIMARY KEY (SolicitudID),
    CONSTRAINT FK_Solicitud_Paciente    FOREIGN KEY (PacienteID)            REFERENCES clinico.Paciente (PacienteID),
    CONSTRAINT FK_Solicitud_Solicitante FOREIGN KEY (SolicitantePersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_Solicitud_Servicio    FOREIGN KEY (ServicioID)            REFERENCES organizacion.Servicio (ServicioID),
    CONSTRAINT FK_Solicitud_CIE10       FOREIGN KEY (CodigoCIE)             REFERENCES catalogo.CIE10 (CodigoCIE)
);
CREATE UNIQUE INDEX UX_Solicitud_Codigo ON laboratorio.Solicitud (CodigoSolicitud) WHERE CodigoSolicitud IS NOT NULL;
CREATE INDEX IX_Solicitud_Fecha    ON laboratorio.Solicitud (FechaSolicitud);
CREATE INDEX IX_Solicitud_Paciente ON laboratorio.Solicitud (PacienteID);

CREATE TABLE laboratorio.DetalleSolicitud (
    DetalleSolicitudID INT IDENTITY(1,1) NOT NULL,
    SolicitudID        INT         NOT NULL,
    ExamenID           INT         NOT NULL,
    Estado             VARCHAR(30) NOT NULL DEFAULT (''PENDIENTE''),
    CONSTRAINT PK_DetalleSolicitud PRIMARY KEY (DetalleSolicitudID),
    CONSTRAINT UQ_DetalleSolicitud UNIQUE (SolicitudID, ExamenID),
    CONSTRAINT FK_DetalleSolicitud_Solicitud FOREIGN KEY (SolicitudID) REFERENCES laboratorio.Solicitud (SolicitudID),
    CONSTRAINT FK_DetalleSolicitud_Examen    FOREIGN KEY (ExamenID)    REFERENCES laboratorio.Examen (ExamenID)
);

-- Responsables ahora son PERSONAL (antes eran nombres en texto libre).
CREATE TABLE laboratorio.Muestra (
    MuestraID                      INT IDENTITY(1,1) NOT NULL,
    SolicitudID                    INT           NOT NULL,
    TipoMuestraID                  INT           NOT NULL,
    CodigoMuestra                  VARCHAR(50)   NOT NULL,
    CodigoBarras                   VARCHAR(50)   NULL,
    FechaToma                      DATETIME2(0)  NULL,
    FechaRecepcion                 DATETIME2(0)  NULL,
    FechaProcesamiento             DATETIME2(0)  NULL,
    ResponsableTomaPersonalID      INT           NULL,
    ResponsableRecepcionPersonalID INT           NULL,
    Estado                         VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    CondicionMuestra               VARCHAR(50)   NULL DEFAULT (''ADECUADA''),
    ColorTubo                      VARCHAR(30)   NULL,
    VolumenMl                      DECIMAL(5,2)  NULL,
    Temperatura                    VARCHAR(30)   NULL,
    MotivoRechazo                  NVARCHAR(500) NULL,
    Observacion                    NVARCHAR(500) NULL,
    CONSTRAINT PK_Muestra PRIMARY KEY (MuestraID),
    CONSTRAINT UQ_Muestra_Codigo UNIQUE (CodigoMuestra),
    CONSTRAINT FK_Muestra_Solicitud   FOREIGN KEY (SolicitudID)   REFERENCES laboratorio.Solicitud (SolicitudID),
    CONSTRAINT FK_Muestra_TipoMuestra FOREIGN KEY (TipoMuestraID) REFERENCES laboratorio.TipoMuestra (TipoMuestraID),
    CONSTRAINT FK_Muestra_ResponsableToma      FOREIGN KEY (ResponsableTomaPersonalID)      REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_Muestra_ResponsableRecepcion FOREIGN KEY (ResponsableRecepcionPersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT CK_Muestra_Fechas CHECK (FechaRecepcion IS NULL OR FechaToma IS NULL OR FechaRecepcion >= FechaToma)
);

-- Un resultado por examen solicitado.
CREATE TABLE laboratorio.Resultado (
    ResultadoID             INT IDENTITY(1,1) NOT NULL,
    DetalleSolicitudID      INT           NOT NULL,
    ResponsablePersonalID   INT           NOT NULL,
    ValidadorPersonalID     INT           NULL,
    FechaResultado          DATETIME2(0)  NULL,
    FechaValidacion         DATETIME2(0)  NULL,
    FechaEmision            DATETIME2(0)  NULL,
    Estado                  VARCHAR(30)   NOT NULL DEFAULT (''PENDIENTE''),
    Observacion             NVARCHAR(500) NULL,
    CONSTRAINT PK_Resultado PRIMARY KEY (ResultadoID),
    CONSTRAINT UQ_Resultado_Detalle UNIQUE (DetalleSolicitudID),
    CONSTRAINT FK_Resultado_Detalle     FOREIGN KEY (DetalleSolicitudID)    REFERENCES laboratorio.DetalleSolicitud (DetalleSolicitudID),
    CONSTRAINT FK_Resultado_Responsable FOREIGN KEY (ResponsablePersonalID) REFERENCES rrhh.Personal (PersonalID),
    CONSTRAINT FK_Resultado_Validador   FOREIGN KEY (ValidadorPersonalID)   REFERENCES rrhh.Personal (PersonalID)
);

CREATE TABLE laboratorio.ResultadoParametro (
    ResultadoParametroID INT IDENTITY(1,1) NOT NULL,
    ResultadoID          INT           NOT NULL,
    ParametroID          INT           NOT NULL,
    Valor                NVARCHAR(500) NULL,
    Observacion          NVARCHAR(500) NULL,
    CONSTRAINT PK_ResultadoParametro PRIMARY KEY (ResultadoParametroID),
    CONSTRAINT UQ_ResultadoParametro UNIQUE (ResultadoID, ParametroID),
    CONSTRAINT FK_ResultadoParametro_Resultado FOREIGN KEY (ResultadoID) REFERENCES laboratorio.Resultado (ResultadoID),
    CONSTRAINT FK_ResultadoParametro_Parametro FOREIGN KEY (ParametroID) REFERENCES laboratorio.Parametro (ParametroID)
);

-- Reactivo ahora apunta a Examen por ID (antes: código y nombre en texto,
-- duplicando datos de laboratorio.Examen).
CREATE TABLE laboratorio.Reactivo (
    ReactivoID         INT IDENTITY(1,1) NOT NULL,
    ExamenID           INT           NOT NULL,
    Nombre             NVARCHAR(200) NOT NULL,
    SeccionLaboratorio NVARCHAR(100) NOT NULL,   -- antes "Area" (chocaba con organizacion.Area)
    Tubo               NVARCHAR(100) NOT NULL,
    TuboColor          VARCHAR(20)   NULL,
    StockActual        INT           NOT NULL DEFAULT (0),
    StockMinimo        INT           NOT NULL DEFAULT (10),
    StockAlerta        INT           NOT NULL DEFAULT (25),
    UnidadStock        NVARCHAR(50)  NULL DEFAULT (N''determinaciones''),
    Presentacion       NVARCHAR(150) NULL,
    FechaRegistro      DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Activo             BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_Reactivo PRIMARY KEY (ReactivoID),
    CONSTRAINT UQ_Reactivo_Examen UNIQUE (ExamenID),
    CONSTRAINT FK_Reactivo_Examen FOREIGN KEY (ExamenID) REFERENCES laboratorio.Examen (ExamenID),
    CONSTRAINT CK_Reactivo_Stock CHECK (StockActual >= 0 AND StockMinimo >= 0 AND StockAlerta >= StockMinimo)
);

-- Sin ON DELETE CASCADE: antes borrar un reactivo intentaba borrar sus
-- lotes en cascada pero el Kardex los referenciaba sin cascada -> error.
CREATE TABLE laboratorio.ReactivoLote (
    ReactivoLoteID     INT IDENTITY(1,1) NOT NULL,
    ReactivoID         INT          NOT NULL,
    NumeroLote         VARCHAR(50)  NOT NULL,
    FechaVencimiento   DATE         NOT NULL,
    CantidadInicial    INT          NOT NULL,
    CantidadDisponible INT          NOT NULL,
    Estado             VARCHAR(30)  NOT NULL DEFAULT (''ACTIVO''),
    FechaIngreso       DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_ReactivoLote PRIMARY KEY (ReactivoLoteID),
    CONSTRAINT UQ_ReactivoLote_Numero UNIQUE (ReactivoID, NumeroLote),
    CONSTRAINT FK_ReactivoLote_Reactivo FOREIGN KEY (ReactivoID) REFERENCES laboratorio.Reactivo (ReactivoID),
    CONSTRAINT CK_ReactivoLote_Cantidades CHECK (CantidadDisponible >= 0 AND CantidadDisponible <= CantidadInicial)
);

-- Usuario ahora es FK (antes texto con default fijo ''jose_dev'').
CREATE TABLE laboratorio.ReactivoKardex (
    ReactivoKardexID BIGINT IDENTITY(1,1) NOT NULL,
    ReactivoID       INT           NOT NULL,
    ReactivoLoteID   INT           NULL,
    TipoMovimiento   VARCHAR(30)   NOT NULL,
    Cantidad         INT           NOT NULL,
    StockAnterior    INT           NOT NULL,
    StockNuevo       INT           NOT NULL,
    SolicitudID      INT           NULL,
    Motivo           NVARCHAR(255) NULL,
    UsuarioID        INT           NULL,
    FechaMovimiento  DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_ReactivoKardex PRIMARY KEY (ReactivoKardexID),
    CONSTRAINT FK_ReactivoKardex_Reactivo  FOREIGN KEY (ReactivoID)     REFERENCES laboratorio.Reactivo (ReactivoID),
    CONSTRAINT FK_ReactivoKardex_Lote      FOREIGN KEY (ReactivoLoteID) REFERENCES laboratorio.ReactivoLote (ReactivoLoteID),
    CONSTRAINT FK_ReactivoKardex_Solicitud FOREIGN KEY (SolicitudID)    REFERENCES laboratorio.Solicitud (SolicitudID),
    CONSTRAINT FK_ReactivoKardex_Usuario   FOREIGN KEY (UsuarioID)      REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT CK_ReactivoKardex_Stock CHECK (StockAnterior >= 0 AND StockNuevo >= 0)
);
CREATE INDEX IX_ReactivoKardex_Reactivo ON laboratorio.ReactivoKardex (ReactivoID, FechaMovimiento);
/* ================================ ETL ================================ */
-- Registro de cada carga de Excel F239. Sede y periodo por ID
-- (antes hospital_codigo / anio / mes sueltos sin FK).
CREATE TABLE etl.Carga (
    CargaID       INT IDENTITY(1,1) NOT NULL,
    ArchivoOrigen NVARCHAR(255) NOT NULL,
    SedeID        INT           NOT NULL,
    PeriodoID     INT           NOT NULL,
    FechaCarga    DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    UsuarioID     INT           NULL,
    FilasLeidas   INT           NULL,
    FilasValidas  INT           NULL,
    FilasConError INT           NULL,
    Estado        VARCHAR(20)   NOT NULL DEFAULT (''PENDIENTE''),
    Observaciones NVARCHAR(MAX) NULL,
    CONSTRAINT PK_Carga PRIMARY KEY (CargaID),
    CONSTRAINT FK_Carga_Sede    FOREIGN KEY (SedeID)    REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_Carga_Periodo FOREIGN KEY (PeriodoID) REFERENCES catalogo.Periodo (PeriodoID),
    CONSTRAINT FK_Carga_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID)
);

CREATE TABLE etl.ErrorCarga (
    ErrorCargaID  INT IDENTITY(1,1) NOT NULL,
    CargaID       INT           NOT NULL,
    Hoja          NVARCHAR(50)  NULL,
    Celda         NVARCHAR(20)  NULL,
    Campo         NVARCHAR(100) NULL,
    ValorOriginal NVARCHAR(255) NULL,
    TipoError     NVARCHAR(100) NOT NULL,
    Descripcion   NVARCHAR(500) NULL,
    FechaRegistro DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_ErrorCarga PRIMARY KEY (ErrorCargaID),
    CONSTRAINT FK_ErrorCarga_Carga FOREIGN KEY (CargaID) REFERENCES etl.Carga (CargaID)
);

CREATE TABLE etl.StgF239Raw (
    StgF239RawID BIGINT IDENTITY(1,1) NOT NULL,
    CargaID      INT           NOT NULL,
    Hoja         NVARCHAR(50)  NOT NULL,
    FilaExcel    INT           NOT NULL,
    Seccion      NVARCHAR(50)  NOT NULL,
    Codigo       NVARCHAR(20)  NULL,
    Descripcion  NVARCHAR(200) NULL,
    Col1 NVARCHAR(50) NULL, Col2 NVARCHAR(50) NULL, Col3 NVARCHAR(50) NULL, Col4 NVARCHAR(50) NULL,
    Col5 NVARCHAR(50) NULL, Col6 NVARCHAR(50) NULL, Col7 NVARCHAR(50) NULL, Col8 NVARCHAR(50) NULL,
    FechaCarga   DATETIME2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    CONSTRAINT PK_StgF239Raw PRIMARY KEY (StgF239RawID),
    CONSTRAINT FK_StgF239Raw_Carga FOREIGN KEY (CargaID) REFERENCES etl.Carga (CargaID)
);
/* ========================== HOSPITALIZACION ========================== */
-- Clasificación estadística de servicios del F239 (jerárquica).
-- ServicioID enlaza (opcional) con el servicio real de organizacion.Servicio.
CREATE TABLE hospitalizacion.ServicioHospitalario (
    ServicioHospitalarioID      INT IDENTITY(1,1) NOT NULL,
    Codigo                      VARCHAR(10)   NOT NULL,
    Nombre                      NVARCHAR(150) NOT NULL,
    PadreServicioHospitalarioID INT           NULL,
    Nivel                       TINYINT       NOT NULL DEFAULT (1),
    ServicioID                  INT           NULL,
    CONSTRAINT PK_ServicioHospitalario PRIMARY KEY (ServicioHospitalarioID),
    CONSTRAINT UQ_ServicioHospitalario_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_ServicioHospitalario_Padre    FOREIGN KEY (PadreServicioHospitalarioID) REFERENCES hospitalizacion.ServicioHospitalario (ServicioHospitalarioID),
    CONSTRAINT FK_ServicioHospitalario_Servicio FOREIGN KEY (ServicioID) REFERENCES organizacion.Servicio (ServicioID)
);

CREATE TABLE hospitalizacion.ServicioApoyo (
    ServicioApoyoID      INT IDENTITY(1,1) NOT NULL,
    Codigo               VARCHAR(10)   NOT NULL,
    Nombre               NVARCHAR(200) NOT NULL,
    PadreServicioApoyoID INT           NULL,
    CONSTRAINT PK_ServicioApoyo PRIMARY KEY (ServicioApoyoID),
    CONSTRAINT UQ_ServicioApoyo_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_ServicioApoyo_Padre FOREIGN KEY (PadreServicioApoyoID) REFERENCES hospitalizacion.ServicioApoyo (ServicioApoyoID)
);

-- Único catálogo de procedimientos quirúrgicos (reemplaza dbo.TipoIntervencion
-- y dim_procedimiento_qx). Cesáreas, legrados, AQV, etc. van aquí.
CREATE TABLE hospitalizacion.ProcedimientoQuirurgico (
    ProcedimientoQuirurgicoID INT IDENTITY(1,1) NOT NULL,
    Codigo                    VARCHAR(10)   NOT NULL,
    CodigoOriginalExcel       VARCHAR(10)   NULL,
    Nombre                    NVARCHAR(200) NOT NULL,
    CONSTRAINT PK_ProcedimientoQuirurgico PRIMARY KEY (ProcedimientoQuirurgicoID),
    CONSTRAINT UQ_ProcedimientoQuirurgico_Codigo UNIQUE (Codigo)
);

-- Movimiento mensual por sede + periodo + servicio.
-- Une fact_movimiento_hosp + fact_camas_servicio + dbo.Hospitalizacion
-- (los tres tenían el mismo nivel de detalle).
CREATE TABLE hospitalizacion.MovimientoHospitalario (
    MovimientoHospitalarioID INT IDENTITY(1,1) NOT NULL,
    SedeID                   INT NOT NULL,
    PeriodoID                INT NOT NULL,
    ServicioHospitalarioID   INT NOT NULL,
    SaldoMesAnterior         INT NOT NULL DEFAULT (0),
    IngresosMes              INT NOT NULL DEFAULT (0),
    TransfDeOtrosServicios   INT NOT NULL DEFAULT (0),
    TransfAOtrosServicios    INT NOT NULL DEFAULT (0),
    EgresosMes               INT NOT NULL DEFAULT (0),
    SaldoMesSiguiente        INT NOT NULL DEFAULT (0),
    DiasPaciente             INT NOT NULL DEFAULT (0),
    DiasCama                 INT NOT NULL DEFAULT (0),
    DiasEstancia             INT NULL,          -- días de estancia de los egresados
    NumeroCamas              INT NOT NULL DEFAULT (0),
    CargaID                  INT NOT NULL,
    CONSTRAINT PK_MovimientoHospitalario PRIMARY KEY (MovimientoHospitalarioID),
    CONSTRAINT UQ_MovimientoHospitalario UNIQUE (SedeID, PeriodoID, ServicioHospitalarioID),
    CONSTRAINT FK_MovimientoHospitalario_Sede     FOREIGN KEY (SedeID)                 REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_MovimientoHospitalario_Periodo  FOREIGN KEY (PeriodoID)              REFERENCES catalogo.Periodo (PeriodoID),
    CONSTRAINT FK_MovimientoHospitalario_Servicio FOREIGN KEY (ServicioHospitalarioID) REFERENCES hospitalizacion.ServicioHospitalario (ServicioHospitalarioID),
    CONSTRAINT FK_MovimientoHospitalario_Carga    FOREIGN KEY (CargaID)                REFERENCES etl.Carga (CargaID),
    CONSTRAINT CK_MovimientoHospitalario_NoNegativos CHECK (
        SaldoMesAnterior >= 0 AND IngresosMes >= 0 AND TransfDeOtrosServicios >= 0 AND
        TransfAOtrosServicios >= 0 AND EgresosMes >= 0 AND SaldoMesSiguiente >= 0 AND
        DiasPaciente >= 0 AND DiasCama >= 0 AND NumeroCamas >= 0 AND (DiasEstancia IS NULL OR DiasEstancia >= 0)),
    -- ecuación del censo: lo que queda = lo que había + entra - sale
    CONSTRAINT CK_MovimientoHospitalario_Balance CHECK (
        SaldoMesSiguiente = SaldoMesAnterior + IngresosMes + TransfDeOtrosServicios - TransfAOtrosServicios - EgresosMes)
);
CREATE INDEX IX_MovimientoHospitalario_Periodo ON hospitalizacion.MovimientoHospitalario (PeriodoID, SedeID);

-- Resumen mensual por sede: natalidad, egresos, mortalidad y emergencia.
-- Une fact_natalidad_egresos + fact_consulta_emergencia (mismo detalle).
-- Se quitaron: numero_camas_total (es la suma de NumeroCamas) y las
-- columnas quirúrgicas (cesáreas, legrados, AQV...) que van en Cirugia.
CREATE TABLE hospitalizacion.ResumenMensual (
    ResumenMensualID     INT IDENTITY(1,1) NOT NULL,
    SedeID               INT NOT NULL,
    PeriodoID            INT NOT NULL,
    NacidosVivos         INT NULL,
    DefuncionesFetales   INT NULL,
    Abortos              INT NULL,
    Partos               INT NULL,
    PacientesEgresos     INT NULL,
    AltasVivas           INT NULL,
    Fallecidos           INT NULL,
    FallecidosAntes24h   INT NULL,
    Fallecidos24a48h     INT NULL,
    FallecidosDespues48h INT NULL,
    ConsultasEmergencia  INT NULL,
    CargaID              INT NOT NULL,
    CONSTRAINT PK_ResumenMensual PRIMARY KEY (ResumenMensualID),
    CONSTRAINT UQ_ResumenMensual UNIQUE (SedeID, PeriodoID),
    CONSTRAINT FK_ResumenMensual_Sede    FOREIGN KEY (SedeID)    REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_ResumenMensual_Periodo FOREIGN KEY (PeriodoID) REFERENCES catalogo.Periodo (PeriodoID),
    CONSTRAINT FK_ResumenMensual_Carga   FOREIGN KEY (CargaID)   REFERENCES etl.Carga (CargaID),
    CONSTRAINT CK_ResumenMensual_Fallecidos CHECK (Fallecidos = FallecidosAntes24h + Fallecidos24a48h + FallecidosDespues48h),
    CONSTRAINT CK_ResumenMensual_Egresos    CHECK (PacientesEgresos = AltasVivas + Fallecidos)
);

CREATE TABLE hospitalizacion.Cirugia (
    CirugiaID                 INT IDENTITY(1,1) NOT NULL,
    SedeID                    INT NOT NULL,
    PeriodoID                 INT NOT NULL,
    ProcedimientoQuirurgicoID INT NOT NULL,
    Cantidad                  INT NOT NULL DEFAULT (0),
    CargaID                   INT NOT NULL,
    CONSTRAINT PK_Cirugia PRIMARY KEY (CirugiaID),
    CONSTRAINT UQ_Cirugia UNIQUE (SedeID, PeriodoID, ProcedimientoQuirurgicoID),
    CONSTRAINT FK_Cirugia_Sede          FOREIGN KEY (SedeID)                    REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_Cirugia_Periodo       FOREIGN KEY (PeriodoID)                 REFERENCES catalogo.Periodo (PeriodoID),
    CONSTRAINT FK_Cirugia_Procedimiento FOREIGN KEY (ProcedimientoQuirurgicoID) REFERENCES hospitalizacion.ProcedimientoQuirurgico (ProcedimientoQuirurgicoID),
    CONSTRAINT FK_Cirugia_Carga         FOREIGN KEY (CargaID)                   REFERENCES etl.Carga (CargaID),
    CONSTRAINT CK_Cirugia_Cantidad CHECK (Cantidad >= 0)
);
CREATE INDEX IX_Cirugia_Periodo ON hospitalizacion.Cirugia (PeriodoID, SedeID);

CREATE TABLE hospitalizacion.ApoyoDiagnostico (
    ApoyoDiagnosticoID  INT IDENTITY(1,1) NOT NULL,
    SedeID              INT NOT NULL,
    PeriodoID           INT NOT NULL,
    ServicioApoyoID     INT NOT NULL,
    Hospitalizacion     INT NULL,
    ConsultaExterna     INT NULL,
    ConsultaEmergencia  INT NULL,
    CargaID             INT NOT NULL,
    CONSTRAINT PK_ApoyoDiagnostico PRIMARY KEY (ApoyoDiagnosticoID),
    CONSTRAINT UQ_ApoyoDiagnostico UNIQUE (SedeID, PeriodoID, ServicioApoyoID),
    CONSTRAINT FK_ApoyoDiagnostico_Sede          FOREIGN KEY (SedeID)          REFERENCES organizacion.Sede (SedeID),
    CONSTRAINT FK_ApoyoDiagnostico_Periodo       FOREIGN KEY (PeriodoID)       REFERENCES catalogo.Periodo (PeriodoID),
    CONSTRAINT FK_ApoyoDiagnostico_ServicioApoyo FOREIGN KEY (ServicioApoyoID) REFERENCES hospitalizacion.ServicioApoyo (ServicioApoyoID),
    CONSTRAINT FK_ApoyoDiagnostico_Carga         FOREIGN KEY (CargaID)         REFERENCES etl.Carga (CargaID)
);
CREATE INDEX IX_ApoyoDiagnostico_Periodo ON hospitalizacion.ApoyoDiagnostico (PeriodoID, SedeID);
-- Indicadores hospitalarios (fórmulas corregidas respecto a la vista original):
--   % ocupación          = días paciente / días cama x 100   (antes salía como fracción 0-1)
--   promedio permanencia = días estancia / egresos            (antes usaba días paciente)
--   intervalo sustitución= (días cama - días paciente) / egresos   (nuevo)
--   rendimiento cama     = egresos / número de camas          (antes aproximaba camas con días cama / 30)
EXEC(N''CREATE VIEW hospitalizacion.vw_IndicadoresHospitalarios
AS
SELECT
    m.SedeID,
    s.Nombre                AS Sede,
    p.PeriodoID,
    p.Anio,
    p.Mes,
    p.NombreMes,
    sh.ServicioHospitalarioID,
    sh.Codigo               AS ServicioCodigo,
    sh.Nombre               AS ServicioNombre,
    sh.Nivel                AS ServicioNivel,
    m.NumeroCamas,
    m.DiasCama,
    m.DiasPaciente,
    m.DiasEstancia,
    m.IngresosMes,
    m.EgresosMes,
    CAST(100.0 * m.DiasPaciente / NULLIF(m.DiasCama, 0)                AS DECIMAL(7,2)) AS PorcentajeOcupacion,
    CAST(1.0 * m.DiasEstancia / NULLIF(m.EgresosMes, 0)                AS DECIMAL(9,2)) AS PromedioPermanencia,
    CAST(1.0 * (m.DiasCama - m.DiasPaciente) / NULLIF(m.EgresosMes, 0) AS DECIMAL(9,2)) AS IntervaloSustitucion,
    CAST(1.0 * m.EgresosMes / NULLIF(m.NumeroCamas, 0)                 AS DECIMAL(9,2)) AS RendimientoCama
FROM hospitalizacion.MovimientoHospitalario m
JOIN organizacion.Sede                    s  ON s.SedeID  = m.SedeID
JOIN catalogo.Periodo                     p  ON p.PeriodoID = m.PeriodoID
JOIN hospitalizacion.ServicioHospitalario sh ON sh.ServicioHospitalarioID = m.ServicioHospitalarioID;'');

/* ============================== REDES ============================== */
CREATE TABLE redes.VLAN (
    VLANID      INT IDENTITY(1,1) NOT NULL,
    NumeroVLAN  INT           NOT NULL,
    Nombre      NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,
    Activo      BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_VLAN PRIMARY KEY (VLANID),
    CONSTRAINT UQ_VLAN_Numero UNIQUE (NumeroVLAN),
    CONSTRAINT CK_VLAN_Numero CHECK (NumeroVLAN BETWEEN 1 AND 4094)
);

CREATE TABLE redes.TipoEquipo (
    TipoEquipoID INT IDENTITY(1,1) NOT NULL,
    Nombre       NVARCHAR(100) NOT NULL,
    Descripcion  NVARCHAR(250) NULL,
    Activo       BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_TipoEquipo PRIMARY KEY (TipoEquipoID),
    CONSTRAINT UQ_TipoEquipo_Nombre UNIQUE (Nombre)
);

CREATE TABLE redes.Equipo (
    EquipoID          INT IDENTITY(1,1) NOT NULL,
    TipoEquipoID      INT           NOT NULL,
    CodigoPatrimonial VARCHAR(50)   NULL,
    Marca             NVARCHAR(100) NULL,
    Modelo            NVARCHAR(100) NULL,
    NumeroSerie       VARCHAR(100)  NULL,
    SistemaOperativo  NVARCHAR(100) NULL,
    FechaAdquisicion  DATE          NULL,
    Estado            VARCHAR(30)   NOT NULL DEFAULT (''ACTIVO''),
    CONSTRAINT PK_Equipo PRIMARY KEY (EquipoID),
    CONSTRAINT FK_Equipo_TipoEquipo FOREIGN KEY (TipoEquipoID) REFERENCES redes.TipoEquipo (TipoEquipoID)
);
CREATE UNIQUE INDEX UX_Equipo_CodigoPatrimonial ON redes.Equipo (CodigoPatrimonial) WHERE CodigoPatrimonial IS NOT NULL;
CREATE UNIQUE INDEX UX_Equipo_NumeroSerie       ON redes.Equipo (NumeroSerie)       WHERE NumeroSerie IS NOT NULL;

CREATE TABLE redes.DireccionIP (
    DireccionIPID INT IDENTITY(1,1) NOT NULL,
    IP            VARCHAR(45) NOT NULL,
    Mascara       VARCHAR(45) NULL,
    Gateway       VARCHAR(45) NULL,
    VLANID        INT         NULL,
    Estado        VARCHAR(30) NOT NULL DEFAULT (''DISPONIBLE''),
    CONSTRAINT PK_DireccionIP PRIMARY KEY (DireccionIPID),
    CONSTRAINT UQ_DireccionIP UNIQUE (IP),
    CONSTRAINT FK_DireccionIP_VLAN FOREIGN KEY (VLANID) REFERENCES redes.VLAN (VLANID)
);

CREATE TABLE redes.AsignacionIP (
    AsignacionIPID INT IDENTITY(1,1) NOT NULL,
    DireccionIPID  INT          NOT NULL,
    EquipoID       INT          NOT NULL,
    FechaInicio    DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    FechaFin       DATETIME2(0) NULL,
    Activo         BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_AsignacionIP PRIMARY KEY (AsignacionIPID),
    CONSTRAINT FK_AsignacionIP_DireccionIP FOREIGN KEY (DireccionIPID) REFERENCES redes.DireccionIP (DireccionIPID),
    CONSTRAINT FK_AsignacionIP_Equipo      FOREIGN KEY (EquipoID)      REFERENCES redes.Equipo (EquipoID),
    CONSTRAINT CK_AsignacionIP_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);
CREATE UNIQUE INDEX UX_AsignacionIP_Activa ON redes.AsignacionIP (DireccionIPID) WHERE Activo = 1;

CREATE TABLE redes.PuntoRed (
    PuntoRedID   INT IDENTITY(1,1) NOT NULL,
    AreaID       INT           NOT NULL,
    Codigo       VARCHAR(50)   NOT NULL,
    Ubicacion    NVARCHAR(200) NOT NULL,
    TipoConexion VARCHAR(30)   NOT NULL,
    Activo       BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_PuntoRed PRIMARY KEY (PuntoRedID),
    CONSTRAINT UQ_PuntoRed_Codigo UNIQUE (Codigo),
    CONSTRAINT FK_PuntoRed_Area FOREIGN KEY (AreaID) REFERENCES organizacion.Area (AreaID)
);

CREATE TABLE redes.AsignacionPuntoRed (
    AsignacionPuntoRedID INT IDENTITY(1,1) NOT NULL,
    PuntoRedID           INT          NOT NULL,
    EquipoID             INT          NOT NULL,
    FechaInicio          DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
    FechaFin             DATETIME2(0) NULL,
    Activo               BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_AsignacionPuntoRed PRIMARY KEY (AsignacionPuntoRedID),
    CONSTRAINT FK_AsignacionPuntoRed_PuntoRed FOREIGN KEY (PuntoRedID) REFERENCES redes.PuntoRed (PuntoRedID),
    CONSTRAINT FK_AsignacionPuntoRed_Equipo   FOREIGN KEY (EquipoID)   REFERENCES redes.Equipo (EquipoID),
    CONSTRAINT CK_AsignacionPuntoRed_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);
CREATE UNIQUE INDEX UX_AsignacionPuntoRed_Activa ON redes.AsignacionPuntoRed (PuntoRedID) WHERE Activo = 1;

CREATE TABLE redes.RedWifi (
    RedWifiID  INT IDENTITY(1,1) NOT NULL,
    NombreSSID NVARCHAR(100) NOT NULL,
    VLANID     INT           NULL,
    TipoRed    VARCHAR(30)   NOT NULL,
    Activo     BIT           NOT NULL DEFAULT (1),
    CONSTRAINT PK_RedWifi PRIMARY KEY (RedWifiID),
    CONSTRAINT UQ_RedWifi_SSID UNIQUE (NombreSSID),
    CONSTRAINT FK_RedWifi_VLAN FOREIGN KEY (VLANID) REFERENCES redes.VLAN (VLANID)
);

CREATE TABLE redes.AccesoRed (
    AccesoRedID INT IDENTITY(1,1) NOT NULL,
    UsuarioID   INT          NOT NULL,
    RedWifiID   INT          NOT NULL,
    FechaInicio DATETIME2(0) NOT NULL,
    FechaFin    DATETIME2(0) NULL,
    Activo      BIT          NOT NULL DEFAULT (1),
    CONSTRAINT PK_AccesoRed PRIMARY KEY (AccesoRedID),
    CONSTRAINT FK_AccesoRed_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT FK_AccesoRed_RedWifi FOREIGN KEY (RedWifiID) REFERENCES redes.RedWifi (RedWifiID),
    CONSTRAINT CK_AccesoRed_Fechas CHECK (FechaFin IS NULL OR FechaFin >= FechaInicio)
);

CREATE TABLE redes.Incidencia (
    IncidenciaID          INT IDENTITY(1,1) NOT NULL,
    EquipoID              INT            NULL,
    ReportanteUsuarioID   INT            NOT NULL,
    Descripcion           NVARCHAR(1000) NOT NULL,
    Prioridad             VARCHAR(30)    NOT NULL DEFAULT (''MEDIA''),
    FechaReporte          DATETIME2(0)   NOT NULL DEFAULT (SYSDATETIME()),
    FechaCierre           DATETIME2(0)   NULL,
    Estado                VARCHAR(30)    NOT NULL DEFAULT (''ABIERTA''),
    CONSTRAINT PK_Incidencia PRIMARY KEY (IncidenciaID),
    CONSTRAINT FK_Incidencia_Equipo     FOREIGN KEY (EquipoID)            REFERENCES redes.Equipo (EquipoID),
    CONSTRAINT FK_Incidencia_Reportante FOREIGN KEY (ReportanteUsuarioID) REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT CK_Incidencia_Fechas CHECK (FechaCierre IS NULL OR FechaCierre >= FechaReporte)
);
/* ============================ AUDITORIA ============================ */
CREATE TABLE auditoria.EventoAuditoria (
    EventoAuditoriaID BIGINT IDENTITY(1,1) NOT NULL,
    UsuarioID         INT            NULL,
    SistemaID         INT            NULL,
    ModuloID          INT            NULL,      -- antes texto libre
    FechaHora         DATETIME2(0)   NOT NULL DEFAULT (SYSDATETIME()),
    Accion            VARCHAR(50)    NOT NULL,
    TablaAfectada     NVARCHAR(150)  NULL,
    RegistroID        VARCHAR(100)   NULL,
    DireccionIP       VARCHAR(45)    NULL,
    Descripcion       NVARCHAR(1000) NULL,
    CONSTRAINT PK_EventoAuditoria PRIMARY KEY (EventoAuditoriaID),
    CONSTRAINT FK_EventoAuditoria_Usuario FOREIGN KEY (UsuarioID) REFERENCES seguridad.Usuario (UsuarioID),
    CONSTRAINT FK_EventoAuditoria_Sistema FOREIGN KEY (SistemaID) REFERENCES seguridad.Sistema (SistemaID),
    CONSTRAINT FK_EventoAuditoria_Modulo  FOREIGN KEY (ModuloID)  REFERENCES seguridad.Modulo (ModuloID)
);
CREATE INDEX IX_EventoAuditoria_Fecha   ON auditoria.EventoAuditoria (FechaHora);
CREATE INDEX IX_EventoAuditoria_Usuario ON auditoria.EventoAuditoria (UsuarioID);
/* ======================= DATOS SEMILLA: TURNOS =======================
   Códigos tomados del CHECK original. Completar horas reales.
   OJO, hay códigos que parecen duplicados (definir uno solo):
     V / VAC,  V1..V3 / VAC-1..VAC-3,  P / PER,  1-2-3 / M-T-N
   ===================================================================== */
INSERT INTO rrhh.Turno (Codigo, Nombre, Tipo) VALUES
 (''M'',     N''Mañana'',                         ''TURNO''),
 (''T'',     N''Tarde'',                          ''TURNO''),
 (''N'',     N''Noche'',                          ''TURNO''),
 (''1'',     N''Turno 1'',                        ''TURNO''),
 (''2'',     N''Turno 2'',                        ''TURNO''),
 (''3'',     N''Turno 3'',                        ''TURNO''),
 (''1/2'',   N''Turnos 1 y 2'',                   ''TURNO''),
 (''2/3'',   N''Turnos 2 y 3'',                   ''TURNO''),
 (''1/2/3'', N''Turnos 1, 2 y 3'',                ''TURNO''),
 (''GD'',    N''Guardia diurna'',                 ''TURNO''),
 (''GN'',    N''Guardia nocturna'',               ''TURNO''),
 (''G24'',   N''Guardia 24 horas'',               ''TURNO''),
 (''ADM'',   N''Horario administrativo'',         ''TURNO''),
 (''VAC'',   N''Vacaciones'',                     ''AUSENCIA''),
 (''V'',     N''Vacaciones (código corto)'',      ''AUSENCIA''),
 (''VAC-1'', N''Vacaciones tipo 1'',              ''AUSENCIA''),
 (''VAC-2'', N''Vacaciones tipo 2'',              ''AUSENCIA''),
 (''VAC-3'', N''Vacaciones tipo 3'',              ''AUSENCIA''),
 (''V1'',    N''Vacaciones tipo 1 (corto)'',      ''AUSENCIA''),
 (''V2'',    N''Vacaciones tipo 2 (corto)'',      ''AUSENCIA''),
 (''V3'',    N''Vacaciones tipo 3 (corto)'',      ''AUSENCIA''),
 (''DESC'',  N''Descanso'',                       ''AUSENCIA''),
 (''C'',     N''C (definir significado)'',        ''AUSENCIA''),
 (''CITT'',  N''Incapacidad temporal (CITT)'',    ''AUSENCIA''),
 (''F'',     N''F (definir: falta / feriado)'',   ''AUSENCIA''),
 (''P'',     N''Permiso (código corto)'',         ''AUSENCIA''),
 (''PER'',   N''Permiso'',                        ''AUSENCIA''),
 (''LIB'',   N''Libre'',                          ''AUSENCIA'');

INSERT INTO seguridad.VersionDatos (VersionDatosID, Revision) VALUES (1, 1);
/* ===================== CATALOGOS MINIMOS =====================
   Datos de referencia, NO informacion real de pacientes ni personal.
   Si ya tienes catálogos institucionales propios, ajustarlos antes de
   integrar datos reales. Se crean solo en base de datos VACIA.
   ================================================================= */
INSERT INTO catalogo.TipoDocumento (Codigo, Nombre) VALUES
    (''DNI'', N''Documento Nacional de Identidad''),
    (''CE'',  N''Carné de Extranjería''),
    (''PAS'', N''Pasaporte'');

INSERT INTO catalogo.Sexo (Codigo, Nombre) VALUES
    (''M'', N''Masculino''),
    (''F'', N''Femenino''),
    (''ND'', N''No declarado'');

INSERT INTO catalogo.EstadoCivil (Nombre) VALUES
    (N''Soltero(a)''),
    (N''Casado(a)''),
    (N''Conviviente''),
    (N''Divorciado(a)''),
    (N''Viudo(a)'');

INSERT INTO rrhh.Profesion (Codigo, Nombre) VALUES
    (''MED'',  N''Medicina humana''),
    (''ENF'',  N''Enfermería''),
    (''OBS'',  N''Obstetricia''),
    (''ODO'',  N''Odontología''),
    (''TM'',   N''Tecnología médica''),
    (''FAR'',  N''Farmacia y bioquímica''),
    (''NUT'',  N''Nutrición''),
    (''PSI'',  N''Psicología''),
    (''ADM'',  N''Administración''),
    (''INF'',  N''Informática''),
    (''TENF'', N''Técnico en enfermería'');

INSERT INTO rrhh.Cargo (Codigo, Nombre) VALUES
    (''MED'',  N''Médico(a)''),
    (''ENF'',  N''Enfermero(a)''),
    (''OBS'',  N''Obstetra''),
    (''TEC'',  N''Técnico(a)''),
    (''AUX'',  N''Auxiliar''),
    (''ADM'',  N''Administrativo(a)''),
    (''JEF'',  N''Jefe(a) de área'');

INSERT INTO rrhh.TipoContrato (Nombre) VALUES
    (N''DL 276''),
    (N''DL 728''),
    (N''CAS (DL 1057)''),
    (N''Locación de servicios''),
    (N''Otro'');

/* ============================ PERMISOS ============================
   En el original los 6 usuarios *_dev eran db_owner (pueden borrar la
   BD entera y ver todos los datos clínicos). Se propone un rol por uso.
   Crear los USER para los logins existentes y agregarlos al rol.
   ================================================================== */
CREATE ROLE rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::catalogo        TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::organizacion    TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::rrhh            TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::seguridad       TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::clinico         TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::laboratorio     TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::etl             TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::hospitalizacion TO rol_desarrollo;
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::redes           TO rol_desarrollo;
GRANT SELECT, INSERT                          ON SCHEMA::auditoria       TO rol_desarrollo; -- la auditoría no se edita
-- Ejemplo (repetir por cada login):
-- CREATE USER kevin_dev FOR LOGIN kevin_dev WITH DEFAULT_SCHEMA = dbo;
-- ALTER ROLE rol_desarrollo ADD MEMBER kevin_dev;
-- Revisar si eduar_dev y eduardo_dev son la misma persona.
/* ================= EQUIVALENCIA TABLA ANTIGUA -> NUEVA =================
   core.Persona                          -> eliminada; datos copiados a rrhh.Personal / clinico.Paciente
   clinico.Paciente                      -> clinico.Paciente   (identidad propia, sin PersonaID)
   rrhh.Personal                         -> rrhh.Personal      (identidad propia + campos laborales completos)
   seguridad.Usuario.PersonaID           -> seguridad.Usuario.PersonalID
   hospitalizacion.dim_hospital          -> organizacion.Sede  (hospital_id -> SedeID)
   dbo.Periodo, hospitalizacion.dim_periodo -> catalogo.Periodo
   dbo.Servicio                          -> organizacion.Servicio
   hospitalizacion.dim_servicio_hosp     -> hospitalizacion.ServicioHospitalario
   hospitalizacion.dim_servicio_apoyo    -> hospitalizacion.ServicioApoyo
   dbo.TipoIntervencion, dim_procedimiento_qx -> hospitalizacion.ProcedimientoQuirurgico
   dbo.IntervencionQuirurgica, fact_cirugia   -> hospitalizacion.Cirugia
   dbo.Hospitalizacion (movimiento + camas),
     fact_movimiento_hosp, fact_camas_servicio -> hospitalizacion.MovimientoHospitalario
   dbo.Hospitalizacion (natalidad/mortalidad),
     fact_natalidad_egresos, fact_consulta_emergencia -> hospitalizacion.ResumenMensual
     (cesareas, legrados, aqv_*, otras_ginecologicas -> filas en Cirugia)
   fact_apoyo_diagnostico                -> hospitalizacion.ApoyoDiagnostico
   etl_carga_log / etl_error_log / stg_f239_raw -> etl.Carga / etl.ErrorCarga / etl.StgF239Raw
   rrhh.Horario                          -> eliminado (lo reemplaza ProgramacionMensual + Turno)
   laboratorio.Reactivo.CodigoExamen/ExamenNombre -> Reactivo.ExamenID
   ReactivoKardex.Usuario (texto)        -> ReactivoKardex.UsuarioID
   Muestra.ResponsableToma/Recepcion (texto) -> ...PersonalID
   Columnas Estado BIT                   -> Activo BIT
   ====================================================================== */

/* =================== VERIFICACION DE INSTALACION =================== */
IF OBJECT_ID(N''rrhh.Personal'', N''U'') IS NULL OR OBJECT_ID(N''clinico.Paciente'', N''U'') IS NULL
BEGIN
    ;THROW 51012, N''La instalacion quedo incompleta: faltan Personal o Paciente.'', 1;
END;
IF OBJECT_ID(N''persona.Persona'', N''U'') IS NOT NULL
BEGIN
    ;THROW 51013, N''No debe existir la tabla compartida persona.Persona.'', 1;
END;
SELECT DB_NAME() AS BaseDatos,
       (SELECT COUNT_BIG(*) FROM sys.tables WHERE is_ms_shipped = 0) AS TotalTablas,
       (SELECT COUNT_BIG(*) FROM rrhh.Personal) AS Trabajadores,
       (SELECT COUNT_BIG(*) FROM clinico.Paciente) AS Pacientes,
       N''Instalacion del esquema finalizada'' AS Estado;

');
