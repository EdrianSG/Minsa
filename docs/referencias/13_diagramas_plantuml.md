# 13. Diagramas PlantUML

## 13.1 Arquitectura general

```plantuml
@startuml
skinparam componentStyle rectangle

actor "RR.HH." as RRHH
actor "Jefe de Servicio" as Jefe

component "Frontend Web
HTML/CSS/JS" as Front
component "Backend PHP" as Back
database "SQL Server Express" as DB
component "Aplicación biométrica
existente" as Bio

RRHH --> Front
Jefe --> Front
Front --> Back : HTTP / JSON
Back --> DB : SQL
Bio ..> Back : Integración futura
API / archivo / BD

@enduml
```

## 13.2 Flujo de rol mensual

```plantuml
@startuml

actor "Jefe de Servicio / RR.HH." as Usuario
actor "RR.HH." as RRHH

rectangle "Sistema Web" {
  usecase "Crear rol mensual" as UC1
  usecase "Asignar turnos" as UC2
  usecase "Calcular horas" as UC3
  usecase "Validar programación" as UC4
  usecase "Enviar rol" as UC5
  usecase "Aprobar / observar" as UC6
}

Usuario --> UC1
UC1 --> UC2
UC2 --> UC3
UC3 --> UC4
UC4 --> UC5
RRHH --> UC6
UC5 --> UC6

@enduml
```

## 13.3 Control de asistencia

```plantuml
@startuml

start

:Obtener turno programado;
:Obtener marcaciones del trabajador;
:Buscar incidencias / justificaciones;

if (¿Existe incidencia justificada?) then (Sí)
  :Clasificar según incidencia;
else (No)
  if (¿Existe marcación de ingreso?) then (Sí)
    :Calcular diferencia contra hora programada;

    if (Diferencia <= 5 min) then (Sí)
      :PRESENTE / dentro de tolerancia;
    else (No)
      if (Diferencia <= 30 min) then (Sí)
        :TARDANZA;
      else (No)
        :Aplicar regla administrativa
posterior a 30 min;
      endif
    endif

  else (No)
    :FALTA;
  endif
endif

stop

@enduml
```

## 13.4 Integración de marcaciones

```plantuml
@startuml

rectangle "Sistema biométrico existente" as Bio
rectangle "Adaptador de integración" as Adapter
database "Marcaciones" as Marks
rectangle "Motor de consolidación" as Engine
database "Programación mensual" as Program
database "Incidencias" as Inc
rectangle "Dashboard / Reportes" as Dash

Bio --> Adapter : API / CSV / BD
Adapter --> Marks
Marks --> Engine
Program --> Engine
Inc --> Engine
Engine --> Dash

@enduml
```

## 13.5 Esquema conceptual de datos

```plantuml
@startuml

entity Personal {
  * PersonalID
  --
  DNI
  CargoID
  ProfesionID
  ServicioID
  CondicionLaboral
  Estado
}

entity Cargo {
  * CargoID
  --
  Codigo
  Nombre
}

entity Profesion {
  * ProfesionID
  --
  Codigo
  Nombre
}

entity Servicio {
  * ServicioID
  --
  Nombre
  EsAsistencial
}

entity Turno {
  * TurnoID
  --
  Codigo
  HoraInicio
  HoraFin
  HorasComputables
  CruzaMedianoche
}

entity RolMensual {
  * RolID
  --
  ServicioID
  Anio
  Mes
  Estado
}

entity RolDetalle {
  * DetalleID
  --
  RolID
  PersonalID
  Fecha
  TurnoID
}

entity Marcacion {
  * MarcacionID
  --
  PersonalID
  FechaHora
  Origen
}

entity Incidencia {
  * IncidenciaID
  --
  PersonalID
  Tipo
  FechaInicio
  FechaFin
}

entity ConsolidadoDiario {
  * ConsolidadoID
  --
  PersonalID
  FechaLaboral
  Estado
  MinutosTardanza
}

Cargo ||--o{ Personal
Profesion ||--o{ Personal
Servicio ||--o{ Personal
Servicio ||--o{ RolMensual
RolMensual ||--o{ RolDetalle
Personal ||--o{ RolDetalle
Turno ||--o{ RolDetalle
Personal ||--o{ Marcacion
Personal ||--o{ Incidencia
Personal ||--o{ ConsolidadoDiario

@enduml
```
