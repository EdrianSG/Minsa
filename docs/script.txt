/* =========================================================
   BD INSTITUCIONAL - HOSPITAL
   Compatible con SQL Server / SQL Server Express
   ========================================================= */

USE master;
GO

IF DB_ID('BD_HOSPITAL') IS NULL
BEGIN
    CREATE DATABASE BD_HOSPITAL;
END
GO

USE BD_HOSPITAL;
GO


/* =========================================================
   SCHEMAS
   ========================================================= */

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'catalogo')
    EXEC('CREATE SCHEMA catalogo');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'core')
    EXEC('CREATE SCHEMA core');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'organizacion')
    EXEC('CREATE SCHEMA organizacion');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'clinico')
    EXEC('CREATE SCHEMA clinico');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'seguridad')
    EXEC('CREATE SCHEMA seguridad');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'rrhh')
    EXEC('CREATE SCHEMA rrhh');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'laboratorio')
    EXEC('CREATE SCHEMA laboratorio');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'redes')
    EXEC('CREATE SCHEMA redes');
GO

IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'auditoria')
    EXEC('CREATE SCHEMA auditoria');
GO


/* =========================================================
   CATALOGOS
   ========================================================= */

CREATE TABLE catalogo.TipoDocumento
(
    TipoDocumentoID INT IDENTITY(1,1) PRIMARY KEY,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(100) NOT NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoDocumento_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE catalogo.Sexo
(
    SexoID INT IDENTITY(1,1) PRIMARY KEY,
    Codigo VARCHAR(10) NOT NULL,
    Nombre NVARCHAR(50) NOT NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Sexo_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE catalogo.EstadoCivil
(
    EstadoCivilID INT IDENTITY(1,1) PRIMARY KEY,
    Nombre NVARCHAR(50) NOT NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_EstadoCivil_Nombre
        UNIQUE (Nombre)
);
GO


/* =========================================================
   CORE - PERSONAS
   ========================================================= */

CREATE TABLE core.Persona
(
    PersonaID INT IDENTITY(1,1) PRIMARY KEY,

    TipoDocumentoID INT NOT NULL,
    NumeroDocumento VARCHAR(20) NOT NULL,

    Nombres NVARCHAR(100) NOT NULL,
    ApellidoPaterno NVARCHAR(100) NOT NULL,
    ApellidoMaterno NVARCHAR(100) NULL,

    FechaNacimiento DATE NULL,

    SexoID INT NULL,
    EstadoCivilID INT NULL,

    Telefono VARCHAR(30) NULL,
    Celular VARCHAR(30) NULL,
    Correo VARCHAR(150) NULL,

    FechaRegistro DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    FechaActualizacion DATETIME2(0) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Persona_Documento
        UNIQUE (TipoDocumentoID, NumeroDocumento),

    CONSTRAINT FK_Persona_TipoDocumento
        FOREIGN KEY (TipoDocumentoID)
        REFERENCES catalogo.TipoDocumento(TipoDocumentoID),

    CONSTRAINT FK_Persona_Sexo
        FOREIGN KEY (SexoID)
        REFERENCES catalogo.Sexo(SexoID),

    CONSTRAINT FK_Persona_EstadoCivil
        FOREIGN KEY (EstadoCivilID)
        REFERENCES catalogo.EstadoCivil(EstadoCivilID),

    CONSTRAINT CK_Persona_FechaNacimiento
        CHECK (
            FechaNacimiento IS NULL
            OR FechaNacimiento <= CAST(GETDATE() AS DATE)
        )
);
GO


CREATE INDEX IX_Persona_Apellidos
ON core.Persona(ApellidoPaterno, ApellidoMaterno);
GO


CREATE INDEX IX_Persona_Nombres
ON core.Persona(Nombres);
GO


/* =========================================================
   ORGANIZACION
   ========================================================= */

CREATE TABLE organizacion.Sede
(
    SedeID INT IDENTITY(1,1) PRIMARY KEY,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Direccion NVARCHAR(250) NULL,
    Telefono VARCHAR(30) NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Sede_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE organizacion.Departamento
(
    DepartamentoID INT IDENTITY(1,1) PRIMARY KEY,
    SedeID INT NOT NULL,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Departamento_Codigo
        UNIQUE (SedeID, Codigo),

    CONSTRAINT FK_Departamento_Sede
        FOREIGN KEY (SedeID)
        REFERENCES organizacion.Sede(SedeID)
);
GO


CREATE TABLE organizacion.Area
(
    AreaID INT IDENTITY(1,1) PRIMARY KEY,
    DepartamentoID INT NOT NULL,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Area_Codigo
        UNIQUE (DepartamentoID, Codigo),

    CONSTRAINT FK_Area_Departamento
        FOREIGN KEY (DepartamentoID)
        REFERENCES organizacion.Departamento(DepartamentoID)
);
GO


CREATE TABLE organizacion.Servicio
(
    ServicioID INT IDENTITY(1,1) PRIMARY KEY,
    AreaID INT NOT NULL,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Servicio_Codigo
        UNIQUE (AreaID, Codigo),

    CONSTRAINT FK_Servicio_Area
        FOREIGN KEY (AreaID)
        REFERENCES organizacion.Area(AreaID)
);
GO


/* =========================================================
   CLINICO - PACIENTES
   ========================================================= */

CREATE TABLE clinico.Paciente
(
    PacienteID INT IDENTITY(1,1) PRIMARY KEY,

    PersonaID INT NOT NULL,

    HistoriaClinica VARCHAR(30) NOT NULL,

    FechaRegistro DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    FechaAlta DATETIME2(0) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Paciente_Persona
        UNIQUE (PersonaID),

    CONSTRAINT UQ_Paciente_Historia
        UNIQUE (HistoriaClinica),

    CONSTRAINT FK_Paciente_Persona
        FOREIGN KEY (PersonaID)
        REFERENCES core.Persona(PersonaID)
);
GO


CREATE TABLE clinico.TipoAntecedente
(
    TipoAntecedenteID INT IDENTITY(1,1) PRIMARY KEY,
    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,
    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoAntecedente_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE clinico.Antecedente
(
    AntecedenteID INT IDENTITY(1,1) PRIMARY KEY,

    PacienteID INT NOT NULL,
    TipoAntecedenteID INT NOT NULL,

    Descripcion NVARCHAR(500) NOT NULL,

    FechaRegistro DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    Activo BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Antecedente_Paciente
        FOREIGN KEY (PacienteID)
        REFERENCES clinico.Paciente(PacienteID),

    CONSTRAINT FK_Antecedente_Tipo
        FOREIGN KEY (TipoAntecedenteID)
        REFERENCES clinico.TipoAntecedente(TipoAntecedenteID)
);
GO


CREATE TABLE clinico.Alergia
(
    AlergiaID INT IDENTITY(1,1) PRIMARY KEY,

    PacienteID INT NOT NULL,

    Alergeno NVARCHAR(200) NOT NULL,
    Reaccion NVARCHAR(300) NULL,
    Severidad NVARCHAR(50) NULL,
    Observacion NVARCHAR(500) NULL,

    FechaRegistro DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    Activo BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Alergia_Paciente
        FOREIGN KEY (PacienteID)
        REFERENCES clinico.Paciente(PacienteID)
);
GO


CREATE TABLE clinico.Diagnostico
(
    DiagnosticoID INT IDENTITY(1,1) PRIMARY KEY,

    PacienteID INT NOT NULL,

    CodigoCIE VARCHAR(20) NULL,
    Descripcion NVARCHAR(500) NOT NULL,

    FechaDiagnostico DATE NOT NULL,

    Activo BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Diagnostico_Paciente
        FOREIGN KEY (PacienteID)
        REFERENCES clinico.Paciente(PacienteID)
);
GO


/* =========================================================
   RRHH
   ========================================================= */

CREATE TABLE rrhh.Cargo
(
    CargoID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,

    Nivel INT NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Cargo_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE rrhh.Profesion
(
    ProfesionID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Profesion_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE rrhh.Especialidad
(
    EspecialidadID INT IDENTITY(1,1) PRIMARY KEY,

    ProfesionID INT NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Especialidad
        UNIQUE (ProfesionID, Nombre),

    CONSTRAINT FK_Especialidad_Profesion
        FOREIGN KEY (ProfesionID)
        REFERENCES rrhh.Profesion(ProfesionID)
);
GO


CREATE TABLE rrhh.Personal
(
    PersonalID INT IDENTITY(1,1) PRIMARY KEY,

    PersonaID INT NOT NULL,

    CodigoEmpleado VARCHAR(30) NOT NULL,

    FechaIngreso DATE NOT NULL,
    FechaCese DATE NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Personal_Persona
        UNIQUE (PersonaID),

    CONSTRAINT UQ_Personal_Codigo
        UNIQUE (CodigoEmpleado),

    CONSTRAINT FK_Personal_Persona
        FOREIGN KEY (PersonaID)
        REFERENCES core.Persona(PersonaID),

    CONSTRAINT CK_Personal_Fechas
        CHECK (
            FechaCese IS NULL
            OR FechaCese >= FechaIngreso
        )
);
GO


CREATE TABLE rrhh.AsignacionPersonal
(
    AsignacionID INT IDENTITY(1,1) PRIMARY KEY,

    PersonalID INT NOT NULL,
    AreaID INT NOT NULL,
    ServicioID INT NULL,
    CargoID INT NOT NULL,
    ProfesionID INT NULL,
    EspecialidadID INT NULL,

    FechaInicio DATE NOT NULL,
    FechaFin DATE NULL,

    EsActual BIT NOT NULL DEFAULT 0,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Asignacion_Personal
        FOREIGN KEY (PersonalID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT FK_Asignacion_Area
        FOREIGN KEY (AreaID)
        REFERENCES organizacion.Area(AreaID),

    CONSTRAINT FK_Asignacion_Servicio
        FOREIGN KEY (ServicioID)
        REFERENCES organizacion.Servicio(ServicioID),

    CONSTRAINT FK_Asignacion_Cargo
        FOREIGN KEY (CargoID)
        REFERENCES rrhh.Cargo(CargoID),

    CONSTRAINT FK_Asignacion_Profesion
        FOREIGN KEY (ProfesionID)
        REFERENCES rrhh.Profesion(ProfesionID),

    CONSTRAINT FK_Asignacion_Especialidad
        FOREIGN KEY (EspecialidadID)
        REFERENCES rrhh.Especialidad(EspecialidadID),

    CONSTRAINT CK_Asignacion_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


CREATE UNIQUE INDEX UX_Asignacion_Actual
ON rrhh.AsignacionPersonal(PersonalID)
WHERE EsActual = 1;
GO


CREATE TABLE rrhh.TipoContrato
(
    TipoContratoID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoContrato_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE rrhh.Contrato
(
    ContratoID INT IDENTITY(1,1) PRIMARY KEY,

    PersonalID INT NOT NULL,
    TipoContratoID INT NOT NULL,

    NumeroContrato VARCHAR(50) NULL,

    FechaInicio DATE NOT NULL,
    FechaFin DATE NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Contrato_Personal
        FOREIGN KEY (PersonalID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT FK_Contrato_Tipo
        FOREIGN KEY (TipoContratoID)
        REFERENCES rrhh.TipoContrato(TipoContratoID),

    CONSTRAINT CK_Contrato_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


CREATE TABLE rrhh.Turno
(
    TurnoID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,

    HoraInicio TIME NOT NULL,
    HoraFin TIME NOT NULL,

    Estado BIT NOT NULL DEFAULT 1
);
GO


CREATE TABLE rrhh.Horario
(
    HorarioID INT IDENTITY(1,1) PRIMARY KEY,

    PersonalID INT NOT NULL,
    TurnoID INT NOT NULL,

    DiaSemana TINYINT NOT NULL,

    FechaInicio DATE NOT NULL,
    FechaFin DATE NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Horario_Personal
        FOREIGN KEY (PersonalID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT FK_Horario_Turno
        FOREIGN KEY (TurnoID)
        REFERENCES rrhh.Turno(TurnoID),

    CONSTRAINT CK_Horario_Dia
        CHECK (DiaSemana BETWEEN 1 AND 7)
);
GO


CREATE TABLE rrhh.Asistencia
(
    AsistenciaID INT IDENTITY(1,1) PRIMARY KEY,

    PersonalID INT NOT NULL,
    Fecha DATE NOT NULL,

    HoraEntrada TIME NULL,
    HoraSalida TIME NULL,

    Estado NVARCHAR(50) NOT NULL,

    Observacion NVARCHAR(300) NULL,

    CONSTRAINT FK_Asistencia_Personal
        FOREIGN KEY (PersonalID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT UQ_Asistencia_Personal_Fecha
        UNIQUE (PersonalID, Fecha)
);
GO


CREATE TABLE rrhh.TipoPermisoLaboral
(
    TipoPermisoLaboralID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoPermisoLaboral_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE rrhh.PermisoLaboral
(
    PermisoLaboralID INT IDENTITY(1,1) PRIMARY KEY,

    PersonalID INT NOT NULL,
    TipoPermisoLaboralID INT NOT NULL,

    FechaInicio DATE NOT NULL,
    FechaFin DATE NOT NULL,

    Motivo NVARCHAR(500) NULL,

    Estado NVARCHAR(50) NOT NULL,

    CONSTRAINT FK_Permiso_Personal
        FOREIGN KEY (PersonalID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT FK_Permiso_Tipo
        FOREIGN KEY (TipoPermisoLaboralID)
        REFERENCES rrhh.TipoPermisoLaboral(TipoPermisoLaboralID),

    CONSTRAINT CK_Permiso_Fechas
        CHECK (FechaFin >= FechaInicio)
);
GO


/* =========================================================
   SEGURIDAD
   ========================================================= */

CREATE TABLE seguridad.Sistema
(
    SistemaID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(30) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,

    Version VARCHAR(30) NULL,
    URL VARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Sistema_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE seguridad.Modulo
(
    ModuloID INT IDENTITY(1,1) PRIMARY KEY,

    SistemaID INT NOT NULL,

    Codigo VARCHAR(30) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,

    Descripcion NVARCHAR(300) NULL,
    Ruta VARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Modulo_Sistema_Codigo
        UNIQUE (SistemaID, Codigo),

    CONSTRAINT FK_Modulo_Sistema
        FOREIGN KEY (SistemaID)
        REFERENCES seguridad.Sistema(SistemaID)
);
GO


CREATE TABLE seguridad.Rol
(
    RolID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(50) NOT NULL,
    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Rol_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE seguridad.Permiso
(
    PermisoID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(100) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Permiso_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE seguridad.Usuario
(
    UsuarioID INT IDENTITY(1,1) PRIMARY KEY,

    PersonaID INT NOT NULL,

    NombreUsuario VARCHAR(100) NOT NULL,
    PasswordHash VARCHAR(500) NOT NULL,

    FechaCreacion DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    UltimoAcceso DATETIME2(0) NULL,

    Bloqueado BIT NOT NULL DEFAULT 0,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Usuario_Persona
        UNIQUE (PersonaID),

    CONSTRAINT UQ_Usuario_Nombre
        UNIQUE (NombreUsuario),

    CONSTRAINT FK_Usuario_Persona
        FOREIGN KEY (PersonaID)
        REFERENCES core.Persona(PersonaID)
);
GO


CREATE TABLE seguridad.UsuarioRol
(
    UsuarioID INT NOT NULL,
    RolID INT NOT NULL,

    FechaAsignacion DATETIME2(0)
        NOT NULL DEFAULT SYSDATETIME(),

    FechaInicio DATE NOT NULL,
    FechaFin DATE NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT PK_UsuarioRol
        PRIMARY KEY (UsuarioID, RolID),

    CONSTRAINT FK_UsuarioRol_Usuario
        FOREIGN KEY (UsuarioID)
        REFERENCES seguridad.Usuario(UsuarioID),

    CONSTRAINT FK_UsuarioRol_Rol
        FOREIGN KEY (RolID)
        REFERENCES seguridad.Rol(RolID),

    CONSTRAINT CK_UsuarioRol_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


CREATE TABLE seguridad.RolPermiso
(
    RolID INT NOT NULL,
    PermisoID INT NOT NULL,

    FechaAsignacion DATETIME2(0)
        NOT NULL DEFAULT SYSDATETIME(),

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT PK_RolPermiso
        PRIMARY KEY (RolID, PermisoID),

    CONSTRAINT FK_RolPermiso_Rol
        FOREIGN KEY (RolID)
        REFERENCES seguridad.Rol(RolID),

    CONSTRAINT FK_RolPermiso_Permiso
        FOREIGN KEY (PermisoID)
        REFERENCES seguridad.Permiso(PermisoID)
);
GO


CREATE TABLE seguridad.UsuarioSistema
(
    UsuarioID INT NOT NULL,
    SistemaID INT NOT NULL,

    FechaInicio DATE NOT NULL,
    FechaFin DATE NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT PK_UsuarioSistema
        PRIMARY KEY (UsuarioID, SistemaID),

    CONSTRAINT FK_UsuarioSistema_Usuario
        FOREIGN KEY (UsuarioID)
        REFERENCES seguridad.Usuario(UsuarioID),

    CONSTRAINT FK_UsuarioSistema_Sistema
        FOREIGN KEY (SistemaID)
        REFERENCES seguridad.Sistema(SistemaID),

    CONSTRAINT CK_UsuarioSistema_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


/* =========================================================
   LABORATORIO
   ========================================================= */

CREATE TABLE laboratorio.TipoExamen
(
    TipoExamenID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoExamen_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE laboratorio.Examen
(
    ExamenID INT IDENTITY(1,1) PRIMARY KEY,

    TipoExamenID INT NOT NULL,

    Codigo VARCHAR(30) NOT NULL,
    Nombre NVARCHAR(200) NOT NULL,

    Descripcion NVARCHAR(500) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Examen_Codigo
        UNIQUE (Codigo),

    CONSTRAINT FK_Examen_Tipo
        FOREIGN KEY (TipoExamenID)
        REFERENCES laboratorio.TipoExamen(TipoExamenID)
);
GO


CREATE TABLE laboratorio.TipoMuestra
(
    TipoMuestraID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoMuestra_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE laboratorio.Solicitud
(
    SolicitudID INT IDENTITY(1,1) PRIMARY KEY,

    PacienteID INT NOT NULL,

    PersonalSolicitanteID INT NOT NULL,

    ServicioSolicitanteID INT NOT NULL,

    FechaSolicitud DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    Prioridad VARCHAR(30) NOT NULL DEFAULT 'NORMAL',

    Estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',

    Observacion NVARCHAR(500) NULL,

    CONSTRAINT FK_Solicitud_Paciente
        FOREIGN KEY (PacienteID)
        REFERENCES clinico.Paciente(PacienteID),

    CONSTRAINT FK_Solicitud_Personal
        FOREIGN KEY (PersonalSolicitanteID)
        REFERENCES rrhh.Personal(PersonalID),

    CONSTRAINT FK_Solicitud_Servicio
        FOREIGN KEY (ServicioSolicitanteID)
        REFERENCES organizacion.Servicio(ServicioID)
);
GO


CREATE INDEX IX_Solicitud_Paciente
ON laboratorio.Solicitud(PacienteID);
GO


CREATE INDEX IX_Solicitud_Fecha
ON laboratorio.Solicitud(FechaSolicitud);
GO


CREATE TABLE laboratorio.DetalleSolicitud
(
    DetalleSolicitudID INT IDENTITY(1,1) PRIMARY KEY,

    SolicitudID INT NOT NULL,
    ExamenID INT NOT NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',

    CONSTRAINT UQ_DetalleSolicitud
        UNIQUE (SolicitudID, ExamenID),

    CONSTRAINT FK_Detalle_Solicitud
        FOREIGN KEY (SolicitudID)
        REFERENCES laboratorio.Solicitud(SolicitudID),

    CONSTRAINT FK_Detalle_Examen
        FOREIGN KEY (ExamenID)
        REFERENCES laboratorio.Examen(ExamenID)
);
GO


CREATE TABLE laboratorio.Muestra
(
    MuestraID INT IDENTITY(1,1) PRIMARY KEY,

    SolicitudID INT NOT NULL,

    TipoMuestraID INT NOT NULL,

    CodigoMuestra VARCHAR(50) NOT NULL,

    FechaToma DATETIME2(0) NULL,
    FechaRecepcion DATETIME2(0) NULL,
    FechaProcesamiento DATETIME2(0) NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',

    CONSTRAINT UQ_Muestra_Codigo
        UNIQUE (CodigoMuestra),

    CONSTRAINT FK_Muestra_Solicitud
        FOREIGN KEY (SolicitudID)
        REFERENCES laboratorio.Solicitud(SolicitudID),

    CONSTRAINT FK_Muestra_Tipo
        FOREIGN KEY (TipoMuestraID)
        REFERENCES laboratorio.TipoMuestra(TipoMuestraID)
);
GO


CREATE TABLE laboratorio.UnidadMedida
(
    UnidadMedidaID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(30) NOT NULL,
    Nombre NVARCHAR(100) NOT NULL,
    Simbolo VARCHAR(30) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Unidad_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE laboratorio.MetodoAnalisis
(
    MetodoAnalisisID INT IDENTITY(1,1) PRIMARY KEY,

    Codigo VARCHAR(30) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,

    Descripcion NVARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_Metodo_Codigo
        UNIQUE (Codigo)
);
GO


CREATE TABLE laboratorio.Parametro
(
    ParametroID INT IDENTITY(1,1) PRIMARY KEY,

    ExamenID INT NOT NULL,

    UnidadMedidaID INT NULL,
    MetodoAnalisisID INT NULL,

    Nombre NVARCHAR(150) NOT NULL,

    TipoDato VARCHAR(30) NOT NULL DEFAULT 'TEXTO',

    Orden INT NOT NULL DEFAULT 1,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Parametro_Examen
        FOREIGN KEY (ExamenID)
        REFERENCES laboratorio.Examen(ExamenID),

    CONSTRAINT FK_Parametro_Unidad
        FOREIGN KEY (UnidadMedidaID)
        REFERENCES laboratorio.UnidadMedida(UnidadMedidaID),

    CONSTRAINT FK_Parametro_Metodo
        FOREIGN KEY (MetodoAnalisisID)
        REFERENCES laboratorio.MetodoAnalisis(MetodoAnalisisID),

    CONSTRAINT UQ_Parametro_Examen_Nombre
        UNIQUE (ExamenID, Nombre)
);
GO


CREATE TABLE laboratorio.ValorReferencia
(
    ValorReferenciaID INT IDENTITY(1,1) PRIMARY KEY,

    ParametroID INT NOT NULL,

    SexoID INT NULL,

    EdadMinima DECIMAL(6,2) NULL,
    EdadMaxima DECIMAL(6,2) NULL,

    ValorMinimo DECIMAL(18,6) NULL,
    ValorMaximo DECIMAL(18,6) NULL,

    TextoReferencia NVARCHAR(300) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_Referencia_Parametro
        FOREIGN KEY (ParametroID)
        REFERENCES laboratorio.Parametro(ParametroID),

    CONSTRAINT FK_Referencia_Sexo
        FOREIGN KEY (SexoID)
        REFERENCES catalogo.Sexo(SexoID),

    CONSTRAINT CK_Referencia_Edad
        CHECK (
            EdadMaxima IS NULL
            OR EdadMinima IS NULL
            OR EdadMaxima >= EdadMinima
        ),

    CONSTRAINT CK_Referencia_Valores
        CHECK (
            ValorMaximo IS NULL
            OR ValorMinimo IS NULL
            OR ValorMaximo >= ValorMinimo
        )
);
GO


CREATE TABLE laboratorio.Resultado
(
    ResultadoID INT IDENTITY(1,1) PRIMARY KEY,

    DetalleSolicitudID INT NOT NULL,

    ProfesionalResponsableID INT NOT NULL,

    FechaResultado DATETIME2(0) NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',

    Observacion NVARCHAR(500) NULL,

    CONSTRAINT FK_Resultado_Detalle
        FOREIGN KEY (DetalleSolicitudID)
        REFERENCES laboratorio.DetalleSolicitud(DetalleSolicitudID),

    CONSTRAINT FK_Resultado_Profesional
        FOREIGN KEY (ProfesionalResponsableID)
        REFERENCES rrhh.Personal(PersonalID)
);
GO


CREATE TABLE laboratorio.ResultadoParametro
(
    ResultadoParametroID INT IDENTITY(1,1) PRIMARY KEY,

    ResultadoID INT NOT NULL,

    ParametroID INT NOT NULL,

    Valor NVARCHAR(500) NULL,

    Observacion NVARCHAR(500) NULL,

    CONSTRAINT FK_ResultadoParametro_Resultado
        FOREIGN KEY (ResultadoID)
        REFERENCES laboratorio.Resultado(ResultadoID),

    CONSTRAINT FK_ResultadoParametro_Parametro
        FOREIGN KEY (ParametroID)
        REFERENCES laboratorio.Parametro(ParametroID),

    CONSTRAINT UQ_Resultado_Parametro
        UNIQUE (ResultadoID, ParametroID)
);
GO


/* =========================================================
   REDES
   ========================================================= */

CREATE TABLE redes.TipoEquipo
(
    TipoEquipoID INT IDENTITY(1,1) PRIMARY KEY,

    Nombre NVARCHAR(100) NOT NULL,
    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_TipoEquipo_Nombre
        UNIQUE (Nombre)
);
GO


CREATE TABLE redes.Equipo
(
    EquipoID INT IDENTITY(1,1) PRIMARY KEY,

    TipoEquipoID INT NOT NULL,

    CodigoPatrimonial VARCHAR(50) NULL,
    Marca NVARCHAR(100) NULL,
    Modelo NVARCHAR(100) NULL,
    NumeroSerie VARCHAR(100) NULL,

    SistemaOperativo NVARCHAR(100) NULL,

    FechaAdquisicion DATE NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'ACTIVO',

    CONSTRAINT FK_Equipo_Tipo
        FOREIGN KEY (TipoEquipoID)
        REFERENCES redes.TipoEquipo(TipoEquipoID)
);
GO


CREATE TABLE redes.VLAN
(
    VLANID INT IDENTITY(1,1) PRIMARY KEY,

    NumeroVLAN INT NOT NULL,

    Nombre NVARCHAR(100) NOT NULL,

    Descripcion NVARCHAR(250) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_VLAN_Numero
        UNIQUE (NumeroVLAN)
);
GO


CREATE TABLE redes.DireccionIP
(
    DireccionIPID INT IDENTITY(1,1) PRIMARY KEY,

    IP VARCHAR(45) NOT NULL,

    Mascara VARCHAR(45) NULL,
    Gateway VARCHAR(45) NULL,

    VLANID INT NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'DISPONIBLE',

    CONSTRAINT UQ_DireccionIP
        UNIQUE (IP),

    CONSTRAINT FK_IP_VLAN
        FOREIGN KEY (VLANID)
        REFERENCES redes.VLAN(VLANID)
);
GO


CREATE TABLE redes.AsignacionIP
(
    AsignacionIPID INT IDENTITY(1,1) PRIMARY KEY,

    DireccionIPID INT NOT NULL,
    EquipoID INT NOT NULL,

    FechaInicio DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    FechaFin DATETIME2(0) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_AsignacionIP_IP
        FOREIGN KEY (DireccionIPID)
        REFERENCES redes.DireccionIP(DireccionIPID),

    CONSTRAINT FK_AsignacionIP_Equipo
        FOREIGN KEY (EquipoID)
        REFERENCES redes.Equipo(EquipoID),

    CONSTRAINT CK_AsignacionIP_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


CREATE UNIQUE INDEX UX_AsignacionIP_Activa
ON redes.AsignacionIP(DireccionIPID)
WHERE Estado = 1;
GO


CREATE TABLE redes.PuntoRed
(
    PuntoRedID INT IDENTITY(1,1) PRIMARY KEY,

    AreaID INT NOT NULL,

    Codigo VARCHAR(50) NOT NULL,

    Ubicacion NVARCHAR(200) NOT NULL,

    TipoConexion VARCHAR(30) NOT NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT UQ_PuntoRed_Codigo
        UNIQUE (Codigo),

    CONSTRAINT FK_PuntoRed_Area
        FOREIGN KEY (AreaID)
        REFERENCES organizacion.Area(AreaID)
);
GO


CREATE TABLE redes.AsignacionPuntoRed
(
    AsignacionPuntoRedID INT IDENTITY(1,1) PRIMARY KEY,

    PuntoRedID INT NOT NULL,
    EquipoID INT NOT NULL,

    FechaInicio DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    FechaFin DATETIME2(0) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_AsignacionPunto_Punto
        FOREIGN KEY (PuntoRedID)
        REFERENCES redes.PuntoRed(PuntoRedID),

    CONSTRAINT FK_AsignacionPunto_Equipo
        FOREIGN KEY (EquipoID)
        REFERENCES redes.Equipo(EquipoID)
);
GO


CREATE TABLE redes.RedWifi
(
    RedWifiID INT IDENTITY(1,1) PRIMARY KEY,

    NombreSSID NVARCHAR(100) NOT NULL,

    VLANID INT NULL,

    TipoRed VARCHAR(30) NOT NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_RedWifi_VLAN
        FOREIGN KEY (VLANID)
        REFERENCES redes.VLAN(VLANID)
);
GO


CREATE TABLE redes.AccesoRed
(
    AccesoRedID INT IDENTITY(1,1) PRIMARY KEY,

    UsuarioID INT NOT NULL,

    RedWifiID INT NOT NULL,

    FechaInicio DATETIME2(0) NOT NULL,

    FechaFin DATETIME2(0) NULL,

    Estado BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_AccesoRed_Usuario
        FOREIGN KEY (UsuarioID)
        REFERENCES seguridad.Usuario(UsuarioID),

    CONSTRAINT FK_AccesoRed_RedWifi
        FOREIGN KEY (RedWifiID)
        REFERENCES redes.RedWifi(RedWifiID),

    CONSTRAINT CK_AccesoRed_Fechas
        CHECK (
            FechaFin IS NULL
            OR FechaFin >= FechaInicio
        )
);
GO


CREATE TABLE redes.Incidencia
(
    IncidenciaID INT IDENTITY(1,1) PRIMARY KEY,

    EquipoID INT NULL,

    ReportadoPorUsuarioID INT NOT NULL,

    Descripcion NVARCHAR(1000) NOT NULL,

    Prioridad VARCHAR(30) NOT NULL DEFAULT 'MEDIA',

    FechaReporte DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    FechaCierre DATETIME2(0) NULL,

    Estado VARCHAR(30) NOT NULL DEFAULT 'ABIERTA',

    CONSTRAINT FK_Incidencia_Equipo
        FOREIGN KEY (EquipoID)
        REFERENCES redes.Equipo(EquipoID),

    CONSTRAINT FK_Incidencia_Usuario
        FOREIGN KEY (ReportadoPorUsuarioID)
        REFERENCES seguridad.Usuario(UsuarioID)
);
GO


/* =========================================================
   AUDITORIA
   ========================================================= */

CREATE TABLE auditoria.EventoAuditoria
(
    AuditoriaID BIGINT IDENTITY(1,1) PRIMARY KEY,

    UsuarioID INT NULL,

    SistemaID INT NULL,

    FechaHora DATETIME2(0) NOT NULL
        DEFAULT SYSDATETIME(),

    Modulo NVARCHAR(100) NULL,

    Accion VARCHAR(50) NOT NULL,

    TablaAfectada NVARCHAR(150) NULL,

    RegistroID VARCHAR(100) NULL,

    DireccionIP VARCHAR(45) NULL,

    Descripcion NVARCHAR(1000) NULL,

    CONSTRAINT FK_Auditoria_Usuario
        FOREIGN KEY (UsuarioID)
        REFERENCES seguridad.Usuario(UsuarioID),

    CONSTRAINT FK_Auditoria_Sistema
        FOREIGN KEY (SistemaID)
        REFERENCES seguridad.Sistema(SistemaID)
);
GO


CREATE INDEX IX_Auditoria_Fecha
ON auditoria.EventoAuditoria(FechaHora);
GO


CREATE INDEX IX_Auditoria_Usuario
ON auditoria.EventoAuditoria(UsuarioID);
GO


PRINT 'BD_HOSPITAL creada correctamente.';
GO
