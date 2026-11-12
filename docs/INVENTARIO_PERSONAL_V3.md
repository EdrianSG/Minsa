# Inventario real de personal y horarios de BD_HOSPITAL_V3

Inspección de lectura: 2026-11-09T16:34:30+00:00. Servidor configurado: 192.168.11.38:1433. Se consultó `DB_NAME()` y se exigió V3 antes de leer datos.

Se inventariaron 158 tablas, 1388 columnas y 118 claves foráneas (122 pares de columnas). Este documento detalla las 43 tablas de RR. HH., organización, seguridad, catálogos y referencias directas de personal. El diccionario privado CSV contiene todas las columnas de las 158 tablas.

Los tamaños de nvarchar/nchar se muestran en unidades UTF-16 (bytes/2); los de varchar/char, en bytes. Los flags SQL se convierten explícitamente a booleanos: el texto `0` no se interpreta como verdadero. No se presupone compatibilidad con V4.

## Estado que debe conservarse

| Tabla | Filas |
|---|---:|
| `rrhh.Personal` | 1 |
| `rrhh.AsignacionPersonal` | 1 |
| `rrhh.Horario` | 0 |
| `rrhh.ProgramacionMensual` | 1 |
| `rrhh.ProgramacionMensualDetalle` | 25 |
| `rrhh.MarcacionBiometrica` | 0 |
| `rrhh.VinculacionBiometrica` | 0 |
| `dbo.StagingPersonalPacasmayo` | 453 |
| `laboratorio.Solicitud` | 85 |
| `laboratorio.Resultado` | 140 |
| `seguridad.Usuario` | 1 |

Las referencias actuales a personal incluyen 140 responsables de Resultado, 85 solicitantes de Solicitud, 25 detalles mensuales, una asignación y una cuenta. No hay huérfanos en las 15 columnas FK que apuntan a Personal. No borrar el trabajador existente ni su cuenta. No se inspeccionaron filas de pacientes: solo metadatos, conteos y referencias agregadas de esos módulos.

## Campos reales por tabla

### `auditoria.EventoAuditoria` — 51 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `EventoAuditoriaID` | `bigint` | No | `IDENTITY` |
| `UsuarioID` | `int` | Sí | `—` |
| `SistemaID` | `int` | Sí | `—` |
| `ModuloID` | `int` | Sí | `—` |
| `FechaHora` | `datetime2` | No | `(sysdatetime())` |
| `Accion` | `varchar(50)` | No | `—` |
| `TablaAfectada` | `nvarchar(150)` | Sí | `—` |
| `RegistroID` | `varchar(100)` | Sí | `—` |
| `DireccionIP` | `varchar(45)` | Sí | `—` |
| `Descripcion` | `nvarchar(1000)` | Sí | `—` |
| `AuditoriaID` | `bigint` | No | `COMPUTED` |
| `Modulo` | `nvarchar(100)` | Sí | `—` |

Relaciones salientes:

- `ModuloID` → `seguridad.Modulo.ModuloID` (`FK_EventoAuditoria_Modulo`, borrado `NO_ACTION`).
- `SistemaID` → `seguridad.Sistema.SistemaID` (`FK_EventoAuditoria_Sistema`, borrado `NO_ACTION`).
- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_EventoAuditoria_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_EventoAuditoria`: `EventoAuditoriaID`.

### `catalogo.CIE10` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `CodigoCIE` | `varchar(10)` | No | `—` |
| `Descripcion` | `nvarchar(500)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_CIE10`: `CodigoCIE`.

### `catalogo.EstadoCivil` — 5 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `EstadoCivilID` | `int` | No | `IDENTITY` |
| `Nombre` | `nvarchar(50)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_EstadoCivil`: `EstadoCivilID`.
- `UQ_EstadoCivil_Nombre`: `Nombre`.

### `catalogo.Periodo` — 12 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `PeriodoID` | `int` | No | `IDENTITY` |
| `Anio` | `smallint` | No | `—` |
| `Mes` | `tinyint` | No | `—` |
| `NombreMes` | `nvarchar(20)` | No | `—` |

Claves únicas y primarias:

- `PK_Periodo`: `PeriodoID`.
- `UQ_Periodo_AnioMes`: `Anio, Mes`.

Restricciones CHECK:

- `CK_Periodo_Anio`: `([Anio]>=(2000) AND [Anio]<=(2100))`.
- `CK_Periodo_Mes`: `([Mes]>=(1) AND [Mes]<=(12))`.

### `catalogo.Sexo` — 3 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `SexoID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(10)` | No | `—` |
| `Nombre` | `nvarchar(50)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_Sexo`: `SexoID`.
- `UQ_Sexo_Codigo`: `Codigo`.

### `catalogo.TipoDocumento` — 4 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `TipoDocumentoID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(100)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_TipoDocumento`: `TipoDocumentoID`.
- `UQ_TipoDocumento_Codigo`: `Codigo`.

### `clinico.Diagnostico` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `DiagnosticoID` | `int` | No | `IDENTITY` |
| `PacienteID` | `int` | No | `—` |
| `CodigoCIE` | `varchar(10)` | Sí | `—` |
| `Descripcion` | `nvarchar(500)` | No | `—` |
| `FechaDiagnostico` | `date` | No | `—` |
| `PersonalID` | `int` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `CodigoCIE` → `catalogo.CIE10.CodigoCIE` (`FK_Diagnostico_CIE10`, borrado `NO_ACTION`).
- `PacienteID` → `clinico.Paciente.PacienteID` (`FK_Diagnostico_Paciente`, borrado `NO_ACTION`).
- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_Diagnostico_Personal`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Diagnostico`: `DiagnosticoID`.

### `dbo.StagingPersonalPacasmayo` — 453 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `Identificador` | `varchar(30)` | No | `—` |
| `NombreCompleto` | `nvarchar(250)` | No | `—` |
| `Departamento` | `nvarchar(180)` | Sí | `—` |
| `CargoExcel` | `nvarchar(180)` | Sí | `—` |
| `Telefono` | `varchar(50)` | Sí | `—` |
| `Correo` | `nvarchar(200)` | Sí | `—` |
| `TipoPersonal` | `nvarchar(120)` | Sí | `—` |
| `TurnosExcel` | `nvarchar(100)` | Sí | `—` |
| `HoraExcel` | `nvarchar(100)` | Sí | `—` |
| `DiaExcel` | `nvarchar(100)` | Sí | `—` |
| `FechaCarga` | `datetime2` | No | `(sysdatetime())` |

Claves únicas y primarias:

- `PK__StagingP__F2374EB1854BCAB3`: `Identificador`.

### `laboratorio.Muestra` — 85 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `MuestraID` | `int` | No | `IDENTITY` |
| `SolicitudID` | `int` | No | `—` |
| `TipoMuestraID` | `int` | No | `—` |
| `CodigoMuestra` | `varchar(50)` | No | `—` |
| `CodigoBarras` | `varchar(50)` | Sí | `—` |
| `FechaToma` | `datetime2` | Sí | `—` |
| `FechaRecepcion` | `datetime2` | Sí | `—` |
| `FechaProcesamiento` | `datetime2` | Sí | `—` |
| `ResponsableTomaPersonalID` | `int` | Sí | `—` |
| `ResponsableRecepcionPersonalID` | `int` | Sí | `—` |
| `Estado` | `varchar(30)` | No | `('PENDIENTE')` |
| `CondicionMuestra` | `varchar(50)` | Sí | `('ADECUADA')` |
| `ColorTubo` | `varchar(30)` | Sí | `—` |
| `VolumenMl` | `decimal(5,2)` | Sí | `—` |
| `Temperatura` | `varchar(30)` | Sí | `—` |
| `MotivoRechazo` | `nvarchar(500)` | Sí | `—` |
| `Observacion` | `nvarchar(500)` | Sí | `—` |

Relaciones salientes:

- `ResponsableRecepcionPersonalID` → `rrhh.Personal.PersonalID` (`FK_Muestra_ResponsableRecepcion`, borrado `NO_ACTION`).
- `ResponsableTomaPersonalID` → `rrhh.Personal.PersonalID` (`FK_Muestra_ResponsableToma`, borrado `NO_ACTION`).
- `SolicitudID` → `laboratorio.Solicitud.SolicitudID` (`FK_Muestra_Solicitud`, borrado `NO_ACTION`).
- `TipoMuestraID` → `laboratorio.TipoMuestra.TipoMuestraID` (`FK_Muestra_TipoMuestra`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Muestra`: `MuestraID`.
- `UQ_Muestra_Codigo`: `CodigoMuestra`.

Restricciones CHECK:

- `CK_Muestra_Fechas`: `([FechaRecepcion] IS NULL OR [FechaToma] IS NULL OR [FechaRecepcion]>=[FechaToma])`.

### `laboratorio.Resultado` — 140 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ResultadoID` | `int` | No | `IDENTITY` |
| `DetalleSolicitudID` | `int` | No | `—` |
| `ResponsablePersonalID` | `int` | No | `—` |
| `ValidadorPersonalID` | `int` | Sí | `—` |
| `FechaResultado` | `datetime2` | Sí | `—` |
| `FechaValidacion` | `datetime2` | Sí | `—` |
| `FechaEmision` | `datetime2` | Sí | `—` |
| `Estado` | `varchar(30)` | No | `('PENDIENTE')` |
| `Observacion` | `nvarchar(500)` | Sí | `—` |

Relaciones salientes:

- `DetalleSolicitudID` → `laboratorio.DetalleSolicitud.DetalleSolicitudID` (`FK_Resultado_Detalle`, borrado `NO_ACTION`).
- `ResponsablePersonalID` → `rrhh.Personal.PersonalID` (`FK_Resultado_Responsable`, borrado `NO_ACTION`).
- `ValidadorPersonalID` → `rrhh.Personal.PersonalID` (`FK_Resultado_Validador`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Resultado`: `ResultadoID`.
- `UQ_Resultado_Detalle`: `DetalleSolicitudID`.

### `laboratorio.Solicitud` — 85 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `SolicitudID` | `int` | No | `IDENTITY` |
| `CodigoSolicitud` | `varchar(30)` | Sí | `—` |
| `PacienteID` | `int` | No | `—` |
| `SolicitantePersonalID` | `int` | No | `—` |
| `ServicioID` | `int` | No | `—` |
| `FechaSolicitud` | `datetime2` | No | `(sysdatetime())` |
| `Prioridad` | `varchar(30)` | No | `('NORMAL')` |
| `Estado` | `varchar(30)` | No | `('PENDIENTE')` |
| `CodigoCIE` | `varchar(10)` | Sí | `—` |
| `DiagnosticoPresuntivo` | `nvarchar(300)` | Sí | `—` |
| `Observacion` | `nvarchar(500)` | Sí | `—` |

Relaciones salientes:

- `CodigoCIE` → `catalogo.CIE10.CodigoCIE` (`FK_Solicitud_CIE10`, borrado `NO_ACTION`).
- `PacienteID` → `clinico.Paciente.PacienteID` (`FK_Solicitud_Paciente`, borrado `NO_ACTION`).
- `ServicioID` → `organizacion.Servicio.ServicioID` (`FK_Solicitud_Servicio`, borrado `NO_ACTION`).
- `SolicitantePersonalID` → `rrhh.Personal.PersonalID` (`FK_Solicitud_Solicitante`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Solicitud`: `SolicitudID`.
- `UX_Solicitud_Codigo`: `CodigoSolicitud`; filtro `([CodigoSolicitud] IS NOT NULL)`.

### `organizacion.Area` — 40 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `AreaID` | `int` | No | `IDENTITY` |
| `DepartamentoID` | `int` | No | `—` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `DepartamentoID` → `organizacion.Departamento.DepartamentoID` (`FK_Area_Departamento`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Area`: `AreaID`.
- `UQ_Area_Codigo`: `DepartamentoID, Codigo`.

### `organizacion.Departamento` — 7 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `DepartamentoID` | `int` | No | `IDENTITY` |
| `SedeID` | `int` | No | `—` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `SedeID` → `organizacion.Sede.SedeID` (`FK_Departamento_Sede`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Departamento`: `DepartamentoID`.
- `UQ_Departamento_Codigo`: `SedeID, Codigo`.

### `organizacion.Sede` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `SedeID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(200)` | No | `—` |
| `RedSalud` | `nvarchar(200)` | Sí | `—` |
| `NivelAtencion` | `varchar(20)` | Sí | `—` |
| `Direccion` | `nvarchar(250)` | Sí | `—` |
| `Telefono` | `varchar(30)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_Sede`: `SedeID`.
- `UQ_Sede_Codigo`: `Codigo`.

### `organizacion.Servicio` — 8 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ServicioID` | `int` | No | `IDENTITY` |
| `AreaID` | `int` | No | `—` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `AreaID` → `organizacion.Area.AreaID` (`FK_Servicio_Area`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Servicio`: `ServicioID`.
- `UQ_Servicio_AreaServicio`: `AreaID, ServicioID`.
- `UQ_Servicio_Codigo`: `AreaID, Codigo`.

### `rrhh.AsignacionPersonal` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `AsignacionPersonalID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `AreaID` | `int` | No | `—` |
| `ServicioID` | `int` | Sí | `—` |
| `CargoID` | `int` | No | `—` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | Sí | `—` |
| `EsActual` | `bit` | No | `((0))` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `AreaID` → `organizacion.Area.AreaID` (`FK_AsignacionPersonal_Area`, borrado `NO_ACTION`).
- `CargoID` → `rrhh.Cargo.CargoID` (`FK_AsignacionPersonal_Cargo`, borrado `NO_ACTION`).
- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_AsignacionPersonal_Personal`, borrado `NO_ACTION`).
- `AreaID` → `organizacion.Servicio.AreaID` (`FK_AsignacionPersonal_Servicio`, borrado `NO_ACTION`).
- `ServicioID` → `organizacion.Servicio.ServicioID` (`FK_AsignacionPersonal_Servicio`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_AsignacionPersonal`: `AsignacionPersonalID`.
- `UX_AsignacionPersonal_Actual`: `PersonalID`; filtro `([EsActual]=(1))`.

Restricciones CHECK:

- `CK_AsignacionPersonal_Fechas`: `([FechaFin] IS NULL OR [FechaFin]>=[FechaInicio])`.

### `rrhh.Asistencia` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `AsistenciaID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `Fecha` | `date` | No | `—` |
| `HoraEntrada` | `time` | Sí | `—` |
| `HoraSalida` | `time` | Sí | `—` |
| `Estado` | `varchar(30)` | No | `—` |
| `Observacion` | `nvarchar(300)` | Sí | `—` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_Asistencia_Personal`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Asistencia`: `AsistenciaID`.
- `UQ_Asistencia_PersonalFecha`: `PersonalID, Fecha`.

### `rrhh.Cargo` — 74 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `CargoID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Nivel` | `int` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_Cargo`: `CargoID`.
- `UQ_Cargo_Codigo`: `Codigo`.

### `rrhh.Contrato` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ContratoID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `TipoContratoID` | `int` | No | `—` |
| `NumeroContrato` | `varchar(50)` | Sí | `—` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_Contrato_Personal`, borrado `NO_ACTION`).
- `TipoContratoID` → `rrhh.TipoContrato.TipoContratoID` (`FK_Contrato_TipoContrato`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Contrato`: `ContratoID`.
- `UX_Contrato_Numero`: `NumeroContrato`; filtro `([NumeroContrato] IS NOT NULL)`.

Restricciones CHECK:

- `CK_Contrato_Fechas`: `([FechaFin] IS NULL OR [FechaFin]>=[FechaInicio])`.

### `rrhh.Especialidad` — 10 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `EspecialidadID` | `int` | No | `IDENTITY` |
| `ProfesionID` | `int` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `ProfesionID` → `rrhh.Profesion.ProfesionID` (`FK_Especialidad_Profesion`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Especialidad`: `EspecialidadID`.
- `UQ_Especialidad_Nombre`: `ProfesionID, Nombre`.
- `UQ_Especialidad_ProfesionEspecialidad`: `ProfesionID, EspecialidadID`.

### `rrhh.Horario` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `HorarioID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `TurnoID` | `int` | No | `—` |
| `DiaSemana` | `tinyint` | No | `—` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_Horario_Personal`, borrado `NO_ACTION`).
- `TurnoID` → `rrhh.Turno.TurnoID` (`FK_Horario_Turno`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK__Horario__BB881A9E814458AC`: `HorarioID`.

Restricciones CHECK:

- `CK_Horario_Dia`: `([DiaSemana]>=(1) AND [DiaSemana]<=(7))`.

### `rrhh.ImportacionBiometrica` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ImportacionBiometricaID` | `int` | No | `IDENTITY` |
| `NombreArchivo` | `nvarchar(255)` | No | `—` |
| `HashSHA256` | `char(64)` | No | `—` |
| `FechaHoraCarga` | `datetime2` | No | `(sysdatetime())` |
| `UsuarioID` | `int` | Sí | `—` |
| `TotalRegistros` | `int` | No | `((0))` |
| `MarcacionesValidas` | `int` | No | `((0))` |
| `MarcacionesOmitidas` | `int` | No | `((0))` |
| `MarcacionesDuplicadas` | `int` | No | `((0))` |
| `MarcacionesConsecutivas` | `int` | No | `((0))` |
| `VinculadosDirectos` | `int` | No | `((0))` |
| `PendientesVinculacion` | `int` | No | `((0))` |
| `Estado` | `varchar(30)` | No | `('COMPLETADO')` |
| `Observaciones` | `nvarchar(500)` | Sí | `—` |
| `MensajeError` | `nvarchar(1000)` | Sí | `—` |

Relaciones salientes:

- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_ImportacionBiometrica_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_ImportacionBiometrica`: `ImportacionBiometricaID`.

### `rrhh.IncidenciaBiometrica` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `IncidenciaBiometricaID` | `int` | No | `IDENTITY` |
| `ImportacionBiometricaID` | `int` | No | `—` |
| `MarcacionBiometricaID` | `bigint` | Sí | `—` |
| `BiostarUserID` | `varchar(30)` | Sí | `—` |
| `TipoIncidencia` | `nvarchar(50)` | No | `—` |
| `Nivel` | `varchar(20)` | No | `('ADVERTENCIA')` |
| `Detalle` | `nvarchar(500)` | No | `—` |
| `FechaHoraEvento` | `datetime2` | Sí | `—` |
| `FechaHoraRegistro` | `datetime2` | No | `(sysdatetime())` |

Relaciones salientes:

- `ImportacionBiometricaID` → `rrhh.ImportacionBiometrica.ImportacionBiometricaID` (`FK_IncidenciaBiometrica_Importacion`, borrado `NO_ACTION`).
- `MarcacionBiometricaID` → `rrhh.MarcacionBiometrica.MarcacionBiometricaID` (`FK_IncidenciaBiometrica_Marcacion`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_IncidenciaBiometrica`: `IncidenciaBiometricaID`.

### `rrhh.MarcacionBiometrica` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `MarcacionBiometricaID` | `bigint` | No | `IDENTITY` |
| `ImportacionBiometricaID` | `int` | No | `—` |
| `BiostarUserID` | `varchar(30)` | No | `—` |
| `BiostarUserName` | `nvarchar(150)` | Sí | `—` |
| `FechaHora` | `datetime2` | No | `—` |
| `DispositivoID` | `varchar(50)` | No | `—` |
| `DispositivoNombre` | `nvarchar(150)` | Sí | `—` |
| `Evento` | `nvarchar(100)` | No | `—` |
| `PersonalID` | `int` | Sí | `—` |
| `EstadoProcesamiento` | `varchar(30)` | No | `('PENDIENTE')` |
| `EstadoVinculacion` | `varchar(30)` | No | `('PENDIENTE')` |
| `EsConsecutiva` | `bit` | No | `((0))` |
| `SegundosDiferencia` | `int` | Sí | `—` |
| `Advertencia` | `nvarchar(200)` | Sí | `—` |

Relaciones salientes:

- `ImportacionBiometricaID` → `rrhh.ImportacionBiometrica.ImportacionBiometricaID` (`FK_MarcacionBiometrica_Importacion`, borrado `NO_ACTION`).
- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_MarcacionBiometrica_Personal`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_MarcacionBiometrica`: `MarcacionBiometricaID`.
- `UQ_MarcacionBiometrica_Evento`: `BiostarUserID, FechaHora, DispositivoID, Evento`.

### `rrhh.PermisoLaboral` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `PermisoLaboralID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `TipoPermisoLaboralID` | `int` | No | `—` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | No | `—` |
| `Motivo` | `nvarchar(500)` | Sí | `—` |
| `Estado` | `varchar(30)` | No | `('PENDIENTE')` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_PermisoLaboral_Personal`, borrado `NO_ACTION`).
- `TipoPermisoLaboralID` → `rrhh.TipoPermisoLaboral.TipoPermisoLaboralID` (`FK_PermisoLaboral_Tipo`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_PermisoLaboral`: `PermisoLaboralID`.

Restricciones CHECK:

- `CK_PermisoLaboral_Fechas`: `([FechaFin]>=[FechaInicio])`.

### `rrhh.Personal` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `PersonalID` | `int` | No | `IDENTITY` |
| `CodigoEmpleado` | `varchar(30)` | No | `—` |
| `TipoDocumentoID` | `int` | No | `—` |
| `NumeroDocumento` | `varchar(20)` | No | `—` |
| `Nombres` | `nvarchar(100)` | No | `—` |
| `ApellidoPaterno` | `nvarchar(100)` | No | `—` |
| `ApellidoMaterno` | `nvarchar(100)` | Sí | `—` |
| `FechaNacimiento` | `date` | Sí | `—` |
| `SexoID` | `int` | Sí | `—` |
| `EstadoCivilID` | `int` | Sí | `—` |
| `Telefono` | `varchar(30)` | Sí | `—` |
| `Celular` | `varchar(30)` | Sí | `—` |
| `Correo` | `varchar(150)` | Sí | `—` |
| `SedeID` | `int` | Sí | `—` |
| `ProfesionID` | `int` | Sí | `—` |
| `EspecialidadID` | `int` | Sí | `—` |
| `GradoAcademico` | `varchar(30)` | Sí | `—` |
| `ColegioProfesional` | `varchar(20)` | Sí | `—` |
| `NumeroColegiatura` | `varchar(20)` | Sí | `—` |
| `FechaColegiatura` | `date` | Sí | `—` |
| `FechaVencimientoHabilidad` | `date` | Sí | `—` |
| `NumeroRNE` | `varchar(20)` | Sí | `—` |
| `RegimenLaboral` | `varchar(20)` | Sí | `—` |
| `CondicionLaboral` | `varchar(20)` | Sí | `—` |
| `GrupoOcupacional` | `varchar(20)` | Sí | `—` |
| `NivelRemunerativo` | `varchar(20)` | Sí | `—` |
| `FechaIngreso` | `date` | No | `—` |
| `FechaCese` | `date` | Sí | `—` |
| `MotivoCese` | `nvarchar(200)` | Sí | `—` |
| `CorreoInstitucional` | `varchar(150)` | Sí | `—` |
| `TelefonoLaboral` | `varchar(30)` | Sí | `—` |
| `Anexo` | `varchar(10)` | Sí | `—` |
| `RUC` | `char(11)` | Sí | `—` |
| `SistemaPensionario` | `varchar(10)` | Sí | `—` |
| `NombreAFP` | `nvarchar(50)` | Sí | `—` |
| `CUSPP` | `varchar(20)` | Sí | `—` |
| `Banco` | `nvarchar(100)` | Sí | `—` |
| `NumeroCuenta` | `varchar(30)` | Sí | `—` |
| `CCI` | `char(20)` | Sí | `—` |
| `ContactoEmergenciaNombre` | `nvarchar(150)` | Sí | `—` |
| `ContactoEmergenciaParentesco` | `nvarchar(50)` | Sí | `—` |
| `ContactoEmergenciaTelefono` | `varchar(30)` | Sí | `—` |
| `FotoUrl` | `varchar(300)` | Sí | `—` |
| `Observacion` | `nvarchar(500)` | Sí | `—` |
| `FechaRegistro` | `datetime2` | No | `(sysdatetime())` |
| `FechaActualizacion` | `datetime2` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |
| `NombreCompletoOriginal` | `nvarchar(250)` | Sí | `—` |
| `DepartamentoOrigen` | `nvarchar(150)` | Sí | `—` |
| `CargoOrigen` | `nvarchar(150)` | Sí | `—` |

Relaciones salientes:

- `ProfesionID` → `rrhh.Especialidad.ProfesionID` (`FK_Personal_Especialidad`, borrado `NO_ACTION`).
- `EspecialidadID` → `rrhh.Especialidad.EspecialidadID` (`FK_Personal_Especialidad`, borrado `NO_ACTION`).
- `EstadoCivilID` → `catalogo.EstadoCivil.EstadoCivilID` (`FK_Personal_EstadoCivil`, borrado `NO_ACTION`).
- `ProfesionID` → `rrhh.Profesion.ProfesionID` (`FK_Personal_Profesion`, borrado `NO_ACTION`).
- `SedeID` → `organizacion.Sede.SedeID` (`FK_Personal_Sede`, borrado `NO_ACTION`).
- `SexoID` → `catalogo.Sexo.SexoID` (`FK_Personal_Sexo`, borrado `NO_ACTION`).
- `TipoDocumentoID` → `catalogo.TipoDocumento.TipoDocumentoID` (`FK_Personal_TipoDocumento`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Personal`: `PersonalID`.
- `UQ_Personal_Codigo`: `CodigoEmpleado`.
- `UQ_Personal_Documento`: `TipoDocumentoID, NumeroDocumento`.
- `UX_Personal_Colegiatura`: `ColegioProfesional, NumeroColegiatura`; filtro `([NumeroColegiatura] IS NOT NULL)`.
- `UX_Personal_CorreoInstitucional`: `CorreoInstitucional`; filtro `([CorreoInstitucional] IS NOT NULL)`.
- `UX_Personal_RUC`: `RUC`; filtro `([RUC] IS NOT NULL)`.

Restricciones CHECK:

- `CK_Personal_Nacimiento`: `([FechaNacimiento] IS NULL OR [FechaNacimiento]<=CONVERT([date],sysdatetime()))`.
- `CK_Personal_Especialidad`: `([EspecialidadID] IS NULL OR [ProfesionID] IS NOT NULL)`.
- `CK_Personal_RNE`: `([NumeroRNE] IS NULL OR [EspecialidadID] IS NOT NULL)`.
- `CK_Personal_Colegiatura`: `([NumeroColegiatura] IS NULL OR [ColegioProfesional] IS NOT NULL)`.
- `CK_Personal_Fechas`: `([FechaCese] IS NULL OR [FechaCese]>=[FechaIngreso])`.
- `CK_Personal_MotivoCese`: `([FechaCese] IS NOT NULL OR [MotivoCese] IS NULL)`.
- `CK_Personal_GradoAcademico`: `([GradoAcademico] IS NULL OR ([GradoAcademico]='DOCTORADO' OR [GradoAcademico]='MAESTRIA' OR [GradoAcademico]='TITULADO' OR [GradoAcademico]='BACHILLER' OR [GradoAcademico]='TECNICO' OR [GradoAcademico]='SECUNDARIA'))`.
- `CK_Personal_Regimen`: `([RegimenLaboral] IS NULL OR ([RegimenLaboral]='OTRO' OR [RegimenLaboral]='LOCACION' OR [RegimenLaboral]='LEY 30057' OR [RegimenLaboral]='DL 1153' OR [RegimenLaboral]='DL 1057 CAS' OR [RegimenLaboral]='DL 728' OR [RegimenLaboral]='DL 276'))`.
- `CK_Personal_Condicion`: `([CondicionLaboral] IS NULL OR ([CondicionLaboral]='TERCERO' OR [CondicionLaboral]='PRACTICANTE' OR [CondicionLaboral]='INTERNO' OR [CondicionLaboral]='SERUMS' OR [CondicionLaboral]='RESIDENTE' OR [CondicionLaboral]='DESIGNADO' OR [CondicionLaboral]='DESTACADO' OR [CondicionLaboral]='CAS' OR [CondicionLaboral]='CONTRATADO' OR [CondicionLaboral]='NOMBRADO'))`.
- `CK_Personal_Grupo`: `([GrupoOcupacional] IS NULL OR ([GrupoOcupacional]='ADMINISTRATIVO' OR [GrupoOcupacional]='AUXILIAR' OR [GrupoOcupacional]='TECNICO' OR [GrupoOcupacional]='PROFESIONAL' OR [GrupoOcupacional]='FUNCIONARIO'))`.
- `CK_Personal_Pension`: `([SistemaPensionario] IS NULL OR ([SistemaPensionario]='NINGUNO' OR [SistemaPensionario]='AFP' OR [SistemaPensionario]='ONP'))`.
- `CK_Personal_AFP`: `([SistemaPensionario]<>'AFP' OR [NombreAFP] IS NOT NULL AND [CUSPP] IS NOT NULL)`.
- `CK_Personal_RUC`: `([RUC] IS NULL OR len([RUC])=(11) AND NOT [RUC] like '%[^0-9]%')`.
- `CK_Personal_CCI`: `([CCI] IS NULL OR len([CCI])=(20) AND NOT [CCI] like '%[^0-9]%')`.
- `CK_Personal_Habilidad`: `([FechaVencimientoHabilidad] IS NULL OR [FechaColegiatura] IS NULL OR [FechaVencimientoHabilidad]>=[FechaColegiatura])`.

### `rrhh.Profesion` — 27 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ProfesionID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(20)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_Profesion`: `ProfesionID`.
- `UQ_Profesion_Codigo`: `Codigo`.

### `rrhh.ProgramacionMensual` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `Anio` | `smallint` | No | `—` |
| `Mes` | `tinyint` | No | `—` |
| `Version` | `bigint` | No | `((1))` |
| `ActualizadoEn` | `datetime2` | No | `(sysdatetime())` |
| `UsuarioID` | `int` | Sí | `—` |

Relaciones salientes:

- `Anio` → `catalogo.Periodo.Anio` (`FK_ProgramacionMensual_Periodo`, borrado `NO_ACTION`).
- `Mes` → `catalogo.Periodo.Mes` (`FK_ProgramacionMensual_Periodo`, borrado `NO_ACTION`).
- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_ProgramacionMensual_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_ProgramacionMensual`: `Anio, Mes`.

### `rrhh.ProgramacionMensualDetalle` — 25 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `Anio` | `smallint` | No | `—` |
| `Mes` | `tinyint` | No | `—` |
| `PersonalID` | `int` | No | `—` |
| `Dia` | `tinyint` | No | `—` |
| `CodigoTurno` | `varchar(16)` | No | `—` |

Relaciones salientes:

- `Anio` → `rrhh.ProgramacionMensual.Anio` (`FK_ProgramacionDetalle_Cabecera`, borrado `NO_ACTION`).
- `Mes` → `rrhh.ProgramacionMensual.Mes` (`FK_ProgramacionDetalle_Cabecera`, borrado `NO_ACTION`).
- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_ProgramacionDetalle_Personal`, borrado `NO_ACTION`).
- `CodigoTurno` → `rrhh.Turno.Codigo` (`FK_ProgramacionDetalle_Turno`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_ProgramacionMensualDetalle`: `Anio, Mes, PersonalID, Dia`.

Restricciones CHECK:

- `CK_ProgramacionDetalle_Dia`: `([Dia]>=(1) AND [Dia]<=datepart(day,eomonth(datefromparts([Anio],[Mes],(1)))))`.

### `rrhh.TipoContrato` — 5 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `TipoContratoID` | `int` | No | `IDENTITY` |
| `Nombre` | `nvarchar(100)` | No | `—` |
| `Descripcion` | `nvarchar(250)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_TipoContrato`: `TipoContratoID`.
- `UQ_TipoContrato_Nombre`: `Nombre`.

### `rrhh.TipoPermisoLaboral` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `TipoPermisoLaboralID` | `int` | No | `IDENTITY` |
| `Nombre` | `nvarchar(100)` | No | `—` |
| `Descripcion` | `nvarchar(250)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_TipoPermisoLaboral`: `TipoPermisoLaboralID`.
- `UQ_TipoPermisoLaboral_Nombre`: `Nombre`.

### `rrhh.Turno` — 22 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `TurnoID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(16)` | No | `—` |
| `Nombre` | `nvarchar(100)` | No | `—` |
| `Tipo` | `varchar(20)` | No | `—` |
| `HoraInicio` | `time` | Sí | `—` |
| `HoraFin` | `time` | Sí | `—` |
| `Horas` | `decimal(4,1)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |
| `Color` | `varchar(20)` | Sí | `—` |

Claves únicas y primarias:

- `PK_Turno`: `TurnoID`.
- `UQ_Turno_Codigo`: `Codigo`.

Restricciones CHECK:

- `CK_Turno_Tipo`: `([Tipo]='AUSENCIA' OR [Tipo]='TURNO')`.

### `rrhh.VinculacionBiometrica` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `VinculacionBiometricaID` | `int` | No | `IDENTITY` |
| `BiostarUserID` | `varchar(30)` | No | `—` |
| `PersonalID` | `int` | No | `—` |
| `TipoVinculacion` | `varchar(30)` | No | `('DIRECTA')` |
| `Observacion` | `nvarchar(200)` | Sí | `—` |
| `FechaCreacion` | `datetime2` | No | `(sysdatetime())` |
| `UsuarioID` | `int` | Sí | `—` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_VinculacionBiometrica_Personal`, borrado `NO_ACTION`).
- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_VinculacionBiometrica_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_VinculacionBiometrica`: `VinculacionBiometricaID`.
- `UQ_VinculacionBiometrica_Biostar`: `BiostarUserID`.

### `seguridad.Modulo` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ModuloID` | `int` | No | `IDENTITY` |
| `SistemaID` | `int` | No | `—` |
| `Codigo` | `varchar(30)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Ruta` | `varchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `SistemaID` → `seguridad.Sistema.SistemaID` (`FK_Modulo_Sistema`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Modulo`: `ModuloID`.
- `UQ_Modulo_Codigo`: `SistemaID, Codigo`.

### `seguridad.ParametrosRRHH` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `ParametrosID` | `bigint` | No | `IDENTITY` |
| `VigenteDesde` | `date` | No | `—` |
| `Datos` | `nvarchar(max)` | No | `—` |
| `UsuarioID` | `int` | No | `—` |
| `CreadoEn` | `datetime2` | No | `(sysdatetime())` |

Relaciones salientes:

- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK__Parametro__Usuar__1387E197`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK__Parametr__4C388A310297CBE4`: `ParametrosID`.

Restricciones CHECK:

- `CK__Parametro__Datos__1293BD5E`: `(isjson([Datos])=(1))`.

### `seguridad.Permiso` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `PermisoID` | `int` | No | `IDENTITY` |
| `ModuloID` | `int` | Sí | `—` |
| `Codigo` | `varchar(100)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `ModuloID` → `seguridad.Modulo.ModuloID` (`FK_Permiso_Modulo`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Permiso`: `PermisoID`.
- `UQ_Permiso_Codigo`: `Codigo`.

### `seguridad.Rol` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `RolID` | `int` | No | `IDENTITY` |
| `SistemaID` | `int` | Sí | `—` |
| `Codigo` | `varchar(50)` | No | `—` |
| `Nombre` | `nvarchar(100)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `SistemaID` → `seguridad.Sistema.SistemaID` (`FK_Rol_Sistema`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Rol`: `RolID`.
- `UQ_Rol_Codigo`: `Codigo`.

### `seguridad.RolPermiso` — 0 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `RolID` | `int` | No | `—` |
| `PermisoID` | `int` | No | `—` |
| `FechaAsignacion` | `datetime2` | No | `(sysdatetime())` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `PermisoID` → `seguridad.Permiso.PermisoID` (`FK_RolPermiso_Permiso`, borrado `NO_ACTION`).
- `RolID` → `seguridad.Rol.RolID` (`FK_RolPermiso_Rol`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_RolPermiso`: `RolID, PermisoID`.

### `seguridad.Sistema` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `SistemaID` | `int` | No | `IDENTITY` |
| `Codigo` | `varchar(30)` | No | `—` |
| `Nombre` | `nvarchar(150)` | No | `—` |
| `Descripcion` | `nvarchar(300)` | Sí | `—` |
| `Version` | `varchar(30)` | Sí | `—` |
| `URL` | `varchar(300)` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Claves únicas y primarias:

- `PK_Sistema`: `SistemaID`.
- `UQ_Sistema_Codigo`: `Codigo`.

### `seguridad.Usuario` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `UsuarioID` | `int` | No | `IDENTITY` |
| `PersonalID` | `int` | No | `—` |
| `NombreUsuario` | `varchar(100)` | No | `—` |
| `PasswordHash` | `varchar(500)` | No | `—` |
| `FechaCreacion` | `datetime2` | No | `(sysdatetime())` |
| `UltimoAcceso` | `datetime2` | Sí | `—` |
| `Bloqueado` | `bit` | No | `((0))` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `PersonalID` → `rrhh.Personal.PersonalID` (`FK_Usuario_Personal`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_Usuario`: `UsuarioID`.
- `UQ_Usuario_Nombre`: `NombreUsuario`.
- `UQ_Usuario_Personal`: `PersonalID`.

### `seguridad.UsuarioRol` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `UsuarioID` | `int` | No | `—` |
| `RolID` | `int` | No | `—` |
| `FechaAsignacion` | `datetime2` | No | `(sysdatetime())` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `RolID` → `seguridad.Rol.RolID` (`FK_UsuarioRol_Rol`, borrado `NO_ACTION`).
- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_UsuarioRol_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_UsuarioRol`: `UsuarioID, RolID`.

Restricciones CHECK:

- `CK_UsuarioRol_Fechas`: `([FechaFin] IS NULL OR [FechaFin]>=[FechaInicio])`.

### `seguridad.UsuarioSistema` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `UsuarioID` | `int` | No | `—` |
| `SistemaID` | `int` | No | `—` |
| `FechaInicio` | `date` | No | `—` |
| `FechaFin` | `date` | Sí | `—` |
| `Activo` | `bit` | No | `((1))` |

Relaciones salientes:

- `SistemaID` → `seguridad.Sistema.SistemaID` (`FK_UsuarioSistema_Sistema`, borrado `NO_ACTION`).
- `UsuarioID` → `seguridad.Usuario.UsuarioID` (`FK_UsuarioSistema_Usuario`, borrado `NO_ACTION`).

Claves únicas y primarias:

- `PK_UsuarioSistema`: `UsuarioID, SistemaID`.

Restricciones CHECK:

- `CK_UsuarioSistema_Fechas`: `([FechaFin] IS NULL OR [FechaFin]>=[FechaInicio])`.

### `seguridad.VersionDatos` — 1 filas

| Campo | Tipo | NULL | Generación / valor predeterminado |
|---|---|---|---|
| `VersionDatosID` | `tinyint` | No | `—` |
| `Revision` | `bigint` | No | `((1))` |

Claves únicas y primarias:

- `PK_VersionDatos`: `VersionDatosID`.

Restricciones CHECK:

- `CK_VersionDatos_Unica`: `([VersionDatosID]=(1))`.

## Disparadores y dependencias

Hay 27 disparadores registrados; los nombres, definiciones y estados están en el JSON privado. Los disparadores de revisión incrementan `seguridad.VersionDatos` al escribir en RR. HH. y otras tablas; no deben deshabilitarse ni revertirse sus contadores durante una carga o reversión. No se ejecutó ninguno: el inspector solo emite SELECT.

Las 118 FK se encuentran habilitadas y verificadas (`is_disabled=0`, `is_not_trusted=0`). El inventario incluye dependencias de objetos SQL; antes de una futura carga debe volver a capturarse porque puede cambiar. La inspección no es un respaldo restaurable ni una instantánea transaccional del servidor.
