# 6. Base de datos y modelo de información

## Situación actual

El equipo trabaja con una base central alojada en una PC del hospital usando SQL Server Express.

Dentro de la base ya existe un esquema relacionado con Recursos Humanos:

```sql
rrhh
```

Entre las tablas conocidas del esquema existente se encuentran:

- `rrhh.Cargo`
- `rrhh.Profesion`

Ejemplo de la estructura ya utilizada:

```sql
CREATE TABLE rrhh.Cargo
(
    CargoID INT IDENTITY(1,1) PRIMARY KEY,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Descripcion NVARCHAR(300) NULL,
    Nivel INT NULL,
    Estado BIT NOT NULL DEFAULT 1,
    CONSTRAINT UQ_Cargo_Codigo UNIQUE (Codigo)
);
```

```sql
CREATE TABLE rrhh.Profesion
(
    ProfesionID INT IDENTITY(1,1) PRIMARY KEY,
    Codigo VARCHAR(20) NOT NULL,
    Nombre NVARCHAR(150) NOT NULL,
    Estado BIT NOT NULL DEFAULT 1
);
```

Estas estructuras deben reutilizarse en lugar de crear catálogos duplicados.

## Principio de integración

Antes de crear tablas nuevas se debe revisar:

1. Qué información ya existe.
2. Qué tablas utiliza otro sistema.
3. Qué identificador representa al trabajador.
4. Qué claves foráneas pueden reutilizarse.
5. Qué información corresponde realmente al esquema `rrhh`.

La idea es ampliar el modelo existente, no construir una segunda base de RR. HH. paralela.

## Entidades necesarias para el dominio

El modelo final debería cubrir conceptualmente:

### Personal

Datos maestros del trabajador:

- Identificador.
- DNI.
- Nombres y apellidos.
- Cargo.
- Profesión.
- Área o servicio.
- Condición laboral.
- Estado.
- Indicador de si aplica SETI.

### Área / Servicio / UPSS

Permite agrupar al personal y sus roles.

Campos conceptuales:

```text
ServicioID
Nombre
EsAsistencial
Estado
```

### Catálogo de turnos

Debe ser parametrizable.

```text
TurnoID
Codigo
Descripcion
HoraInicio
HoraFin
HorasComputables
CruzaMedianoche
EsGuardia
Estado
```

Ejemplos de códigos:

```text
1  Mañana
2  Tarde
3  Noche
V  Vacaciones
F  Feriado
```

Los códigos finales deben ser definidos por RR. HH. y no dejarse rígidos en JavaScript.

### Rol mensual

Cabecera:

```text
RolID
ServicioID
Anio
Mes
Estado
Observaciones
FechaEnvio
FechaAprobacion
UsuarioResponsable
```

Detalle:

```text
DetalleID
RolID
PersonalID
Fecha
TurnoID
Observacion
```

Restricción importante:

```text
UNIQUE (PersonalID, Fecha)
```

salvo que el modelo final contemple explícitamente múltiples bloques autorizados por día.

### Marcación

Representa un evento real obtenido del biométrico u otra fuente.

```text
MarcacionID
PersonalID
FechaHora
Origen
Metodo
IdentificadorExterno
FechaImportacion
```

No se recomienda guardar solamente "entrada" y "salida" en la misma fila desde el origen. Es más flexible conservar eventos y consolidarlos posteriormente.

### Consolidado diario

Resultado del cruce programación vs. marcaciones.

```text
ConsolidadoID
PersonalID
FechaLaboral
TurnoProgramadoID
PrimerIngreso
UltimoEgreso
MinutosTardanza
EstadoAsistencia
IncidenciaID
```

### Incidencias / justificaciones

Ejemplos:

- Vacaciones.
- Permiso.
- Descanso médico.
- Licencia.
- Comisión.
- Justificación.
- Corrección.

Campos conceptuales:

```text
IncidenciaID
PersonalID
Tipo
FechaInicio
FechaFin
Motivo
Estado
RegistradoPor
FechaRegistro
```

### Auditoría

```text
AuditoriaID
UsuarioID
Modulo
Accion
Tabla
RegistroID
ValorAnterior
ValorNuevo
Motivo
FechaHora
IP
```

## Índices recomendados

Especialmente importantes:

```sql
-- Búsqueda de marcaciones por trabajador y fecha
(PersonalID, FechaHora)

-- Búsqueda de programación
(PersonalID, Fecha)

-- Búsqueda por servicio y periodo
(ServicioID, Anio, Mes)

-- Reportes diarios
(FechaLaboral, EstadoAsistencia)
```

## Vistas útiles

Se pueden crear vistas para simplificar dashboard y reportes:

```text
vw_RolMensual
vw_AsistenciaDiaria
vw_FaltasPorFecha
vw_Tardanzas
vw_HorasMensuales
vw_PersonalSETI
vw_IncidenciasActivas
```

## Consideración importante

El SQL mostrado en documentos anteriores para `CatalogoServicios`, `PersonalHospital`, `ProgramacionRolCabecera`, etc. debe entenderse como **modelo propuesto**, no como prueba de que esas tablas ya existan en el servidor.

Antes de ejecutar cualquier script se debe contrastar con la base real.
