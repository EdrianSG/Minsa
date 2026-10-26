# 2. Problema, objetivos y alcance

## Problema principal

Recursos Humanos necesita conocer con rapidez:

- Qué horario o turno tenía asignado cada trabajador.
- Si el trabajador cumplió ese horario.
- Quién faltó en una fecha determinada.
- Qué ausencia está justificada.
- Quién llegó tarde.
- Qué personal se encuentra de vacaciones, permiso u otra incidencia.
- Cuántas horas o turnos acumula el personal durante el mes.
- Qué información debe enviarse en los reportes institucionales, incluido SETI cuando corresponda.

Actualmente existe una aplicación biométrica independiente que permite consultar marcaciones, pero no se encuentra integrada con la programación de horarios ni con el resto de la gestión de RR. HH.

## Objetivo general de trabajo

Desarrollar un módulo web para gestionar horarios y turnos del personal del Hospital Distrital de Pacasmayo, controlar su cumplimiento mediante el cruce con información de asistencia y facilitar a Recursos Humanos la consulta, consolidación y reporte de incidencias laborales.

## Objetivos operativos

- Centralizar la programación mensual de turnos.
- Representar el rol mensual mediante una matriz por trabajador y día.
- Diferenciar personal asistencial y administrativo.
- Calcular horas programadas.
- Detectar tardanzas y ausencias.
- Considerar justificaciones antes de clasificar una ausencia como falta.
- Facilitar la búsqueda de faltas por fecha o rango de fechas.
- Generar indicadores y reportes.
- Mantener trazabilidad de las modificaciones.
- Preparar la solución para una futura integración con el sistema biométrico existente.

## Alcance actual

Incluye:

- Personal de RR. HH.
- Personal del hospital.
- Áreas y servicios.
- Horarios y turnos.
- Programación mensual.
- Vacaciones, permisos y otras justificaciones.
- Cruce de programación con asistencia real.
- Tardanzas y faltas.
- Dashboard.
- Reportes.
- Exportaciones.
- Auditoría.
- Infraestructura local con SQL Server Express.

## Fuera del alcance inicial

No es prioridad de la primera versión:

- Reemplazar la aplicación biométrica que ya posee el hospital.
- Desarrollar desde cero el firmware de un lector de huellas.
- Obligar al trabajador a iniciar sesión para marcar asistencia.
- Migrar el sistema a infraestructura cloud pública.

La captura directa mediante QR o huella puede añadirse posteriormente si el hospital decide integrar o reemplazar el mecanismo existente.
