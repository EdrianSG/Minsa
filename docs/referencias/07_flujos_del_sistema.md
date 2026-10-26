# 7. Flujos del sistema

## 7.1 Programación mensual

```text
Jefe de servicio / RR.HH.
        │
        ▼
Selecciona mes y servicio
        │
        ▼
Sistema carga trabajadores
        │
        ▼
Se completa grilla día 1..31
        │
        ▼
Sistema calcula horas
        │
        ▼
Valida calendario y reglas
        │
        ├── Error → corregir programación
        │
        ▼
Guardar / enviar rol
        │
        ▼
RR.HH. revisa
        │
        ├── Observado → regresa a edición
        │
        ▼
Aprobado
```

## 7.2 Cruce de asistencia

```text
Turno programado
      +
Marcaciones reales
      +
Incidencias justificadas
      │
      ▼
Motor de consolidación
      │
      ├── Dentro de tolerancia → PRESENTE
      ├── >5 y <=30 min       → TARDANZA
      ├── >30 min             → incidencia/descuento según regla
      ├── sin marca + permiso → JUSTIFICADO
      └── sin marca y sin justificación → FALTA
```

## 7.3 Consulta de faltas

Este es uno de los casos de uso operativos más importantes para RR. HH.

```text
RR.HH.
  │
  ▼
Selecciona fecha o rango
  │
  ▼
Sistema obtiene personal programado
  │
  ▼
Cruza marcaciones
  │
  ▼
Cruza incidencias
  │
  ▼
Lista faltas no justificadas
```

La consulta debería responder rápidamente preguntas como:

```text
¿Quién faltó hoy?
¿Quién faltó entre el 1 y el 15?
¿Cuántas faltas tiene este trabajador?
¿Qué faltas siguen sin justificar?
```

## 7.4 Corrección de una asistencia

```text
RR.HH. abre registro
        │
        ▼
Selecciona "corregir"
        │
        ▼
Ingresa motivo obligatorio
        │
        ▼
Sistema guarda valor anterior
        │
        ▼
Aplica corrección
        │
        ▼
Registra auditoría
```

## 7.5 Vacaciones o permiso

```text
Registrar incidencia
      │
      ▼
Indicar trabajador y periodo
      │
      ▼
Aprobar / validar
      │
      ▼
Cruce automático con programación
      │
      ▼
La ausencia deja de aparecer como falta
```

## 7.6 Integración biométrica futura

```text
Aplicación biométrica
        │
        ▼
API / archivo / tabla de intercambio
        │
        ▼
Proceso de importación
        │
        ▼
Tabla de marcaciones
        │
        ▼
Consolidación diaria
```

El proceso de importación debe ser idempotente, es decir, volver a importar el mismo lote no debe duplicar las marcaciones.
