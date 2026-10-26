# 8. Dashboard y reportería

## Dashboard principal de RR. HH.

El tablero debe priorizar información útil para decisiones rápidas, no solamente gráficos decorativos.

Indicadores recomendados:

- Personal programado hoy.
- Presentes.
- Tardanzas.
- Faltas pendientes de justificar.
- Faltas confirmadas.
- Personal de vacaciones.
- Permisos / descansos médicos activos.
- Roles mensuales pendientes de revisión.
- Servicios con programación incompleta.
- Personal asistencial que aún no completa sus horas mensuales.

## Vista "¿Quién faltó?"

Debe ser una consulta destacada.

Filtros:

- Fecha.
- Rango de fechas.
- Servicio / área.
- Trabajador.
- Cargo.
- Condición laboral.
- Estado de justificación.

Columnas:

```text
DNI
Trabajador
Servicio
Turno programado
Hora esperada
Marcación
Estado
Justificación
Observación
```

## Reporte mensual individual

Debe permitir revisar por trabajador:

- Días programados.
- Turnos.
- Horas programadas.
- Horas efectivamente registradas.
- Tardanzas.
- Faltas.
- Incidencias.
- Vacaciones.
- Observaciones.

## Rol mensual

Formato visual tipo matriz:

| Trabajador | 1 | 2 | 3 | 4 | ... | 31 | Horas |
|---|---:|---:|---:|---:|---|---:|---:|
| Persona A | 1 | 1 | V | 2 | ... | 3 | 150 |
| Persona B | 2 | 2 | 2 | F | ... | 1 | 150 |

La interfaz web debe parecerse a la lógica de las hojas que actualmente maneja el hospital.

## Reporte SETI

Características:

- Solo personal al que aplique SETI.
- Periodo mensual.
- Filtro por servicio.
- Filtro por condición laboral.
- Separación rápida de Nombrado y CAS.
- Formato adecuado para impresión o exportación.

## Exportaciones

Formatos de trabajo previstos:

- PDF.
- Excel.

Para reportes tabulares extensos, Excel resulta especialmente útil para RR. HH.

## Gráficos útiles

Ejemplos:

- Tardanzas por servicio.
- Faltas por mes.
- Evolución semanal de puntualidad.
- Distribución de incidencias.
- Cumplimiento de horas por servicio.

Los gráficos deben acompañar a las tablas, no reemplazarlas.

## Consultas de backend recomendadas

En lugar de enviar todos los datos al navegador para calcularlos allí, el backend debería ofrecer respuestas ya agregadas:

```text
GET /api/dashboard/resumen
GET /api/asistencia/faltas
GET /api/asistencia/tardanzas
GET /api/reportes/mensual
GET /api/reportes/rol
GET /api/reportes/seti
```

Los nombres son referenciales y pueden adaptarse a la estructura final del backend.
