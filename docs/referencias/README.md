# Documentación del proyecto - Hospital Distrital de Pacasmayo

Esta carpeta reúne la información consolidada del proyecto de prácticas preprofesionales relacionado con la gestión de horarios, turnos y control de asistencia del personal del Hospital Distrital de Pacasmayo.

> **Importante:** se han excluido deliberadamente los requisitos funcionales y no funcionales.  
> Las reglas de negocio sí se conservan porque describen cómo funciona realmente el proceso de Recursos Humanos.

## Índice

1. [Ficha y contexto del proyecto](01_ficha_y_contexto.md)
2. [Problema, objetivos y alcance](02_problema_objetivos_alcance.md)
3. [Proceso actual y formatos del hospital](03_proceso_actual_y_formatos.md)
4. [Reglas de negocio](04_reglas_de_negocio.md)
5. [Arquitectura técnica](05_arquitectura_tecnica.md)
6. [Base de datos y modelo de información](06_base_de_datos.md)
7. [Flujos del sistema](07_flujos_del_sistema.md)
8. [Dashboard y reportería](08_dashboard_y_reporteria.md)
9. [Seguridad, auditoría e integridad](09_seguridad_y_auditoria.md)
10. [Infraestructura y despliegue local](10_infraestructura_y_despliegue.md)
11. [Plan de desarrollo](11_plan_de_desarrollo.md)
12. [Decisiones tomadas y pendientes](12_decisiones_y_pendientes.md)
13. [Diagramas PlantUML](13_diagramas_plantuml.md)

## Nombre técnico de trabajo

**Sistema Web de Gestión de Turnos y Control de Asistencia del Personal del Hospital Distrital de Pacasmayo**

Este nombre se usa como denominación técnica dentro de la documentación. En el formato de informe de prácticas aparece actualmente una denominación más general relacionada con la **gestión de la información del Hospital Distrital de Pacasmayo**.

## Enfoque vigente

El sistema no parte de cero en materia biométrica. El hospital ya dispone de una aplicación independiente que permite consultar marcaciones, principalmente la hora de ingreso. Por ello, la prioridad actual es digitalizar y organizar la programación de horarios y turnos, cruzarla con la asistencia real y facilitar el trabajo de Recursos Humanos.

La integración directa con la aplicación biométrica se considera una etapa posterior.
