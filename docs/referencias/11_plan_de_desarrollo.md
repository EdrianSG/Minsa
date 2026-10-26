# 11. Plan de desarrollo

## Etapa 1. Levantamiento del proceso

Estado: avanzado.

Actividades:

- Conversación con Informática.
- Conversación con Recursos Humanos.
- Revisión de formatos reales.
- Identificación de turnos.
- Identificación de reglas de tardanza.
- Identificación del uso actual del biométrico.
- Identificación de necesidades de reportes.

## Etapa 2. Revisión de base de datos existente

Objetivo:

No diseñar tablas al tanteo.

Pasos:

1. Extraer el script de las tablas existentes del esquema `rrhh`.
2. Crear un diagrama de relaciones.
3. Identificar tablas maestras compartidas.
4. Marcar qué datos faltan para asistencia y turnos.
5. Diseñar únicamente las nuevas tablas necesarias.
6. Revisar claves foráneas con los demás integrantes.

## Etapa 3. Modelo de turnos y rol mensual

Implementar primero:

- catálogo de turnos;
- áreas/servicios;
- programación mensual;
- detalle por día;
- cálculo de horas;
- tratamiento de turnos que cruzan medianoche.

Esta es la base de todo el módulo.

## Etapa 4. Incidencias

Agregar:

- vacaciones;
- permisos;
- descansos médicos;
- licencias;
- justificaciones;
- observaciones.

Sin esta etapa, la detección de faltas produciría falsos positivos.

## Etapa 5. Marcaciones y consolidación

Crear un mecanismo interno para cargar marcaciones aunque todavía no exista API biométrica.

Durante desarrollo se pueden utilizar:

- datos de prueba;
- carga controlada;
- importación temporal.

Después se implementa el adaptador hacia la fuente biométrica real.

## Etapa 6. Dashboard y consultas

Prioridad:

1. Quién faltó.
2. Quién llegó tarde.
3. Quién está de vacaciones o permiso.
4. Estado del rol mensual.
5. Cumplimiento de horas.
6. Indicadores generales.

## Etapa 7. Reportes

Desarrollar:

- rol mensual;
- asistencia diaria;
- asistencia por rango;
- faltas;
- tardanzas;
- consolidado individual;
- SETI cuando corresponda;
- exportación a Excel/PDF.

## Etapa 8. Seguridad y auditoría

- Login administrativo.
- Roles.
- Permisos.
- Hash de contraseñas.
- Auditoría.
- Validaciones del backend.
- Protección de rutas.

## Etapa 9. Integración con biometría

Solo después de confirmar cómo se puede acceder al sistema existente:

- API;
- base de datos;
- archivo;
- exportación;
- otro método.

Crear un adaptador para evitar acoplar la lógica del sistema a la marca del biométrico.

## Etapa 10. Pruebas

Casos esenciales:

- entrada exacta;
- entrada dentro de 5 minutos;
- tardanza de 6 minutos;
- tardanza de 30 minutos;
- llegada después de 30 minutos;
- ausencia con vacaciones;
- ausencia con permiso;
- ausencia sin justificación;
- turno nocturno;
- marcación duplicada;
- corrección manual;
- cambio de rol;
- personal CAS;
- personal nombrado;
- reporte SETI;
- cierre de mes.

## Etapa 11. Documentación

Guardar:

- modelo de datos;
- diccionario de datos;
- diagramas;
- decisiones;
- manual de instalación;
- manual de usuario;
- evidencias;
- scripts;
- pruebas.
