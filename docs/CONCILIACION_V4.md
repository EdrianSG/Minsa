# Alineación del sistema y los datos con BD_HOSPITAL_V4

Intervención: 2026-11-03. Alcance: Recursos Humanos, el repositorio disponible y las bases existentes en SQL Server. No se publicó código ni información en GitHub. No se modificó la estructura SQL V4 ni se crearon tablas o columnas.

Este informe actualiza el estado de `INFORME_ADAPTACION_V4.md`: ya se realizó una carga conservadora y se ejecutaron pruebas reales en SQL Server. Las limitaciones antiguas sobre ausencia total de integración SQL y parámetros de solo lectura quedan sustituidas por los resultados siguientes.

## Qué se comprobó antes de cargar

El esquema instalado tiene las mismas tablas y columnas que el diccionario suministrado. V4 contenía los padrones de origen (453 registros biométricos y 181 de horarios) y la seguridad recuperada de admin; las tablas operativas y catálogos estaban vacíos.

En V3, 443 registros con código BIO correspondían al padrón, pero también tenían documento igual al ID biométrico y fecha de ingreso igual al alta del dispositivo. En la base original, los 283 vínculos biométricos tenían tipo de vinculación por DNI. Por ello, esos documentos, fechas laborales y asociaciones anteriores no se aceptaron automáticamente como información confirmada.

## Datos incorporados a V4

| Tabla | Filas posteriores a la carga | Procedencia y tratamiento |
|---|---:|---|
| rrhh.Personal | 443 | Correspondencia exacta entre código BIO de V3 y BiostarUserID del padrón. Se preservaron PersonalID y código existentes. NombreCompletoOriginal, DepartamentoOrigen, CargoOrigen, contactos y fechas biométricas proceden del padrón. |
| rrhh.VinculacionBiometrica | 443 | ID BioStar exacto del padrón; no se usa como DNI. Tipo PADRON_ORIGEN. |
| rrhh.ImportacionBiometrica | 7 | Base original, conservando identificadores y metadatos compatibles. Usuario histórico sin equivalencia se deja NULL, no se atribuye a admin. |
| rrhh.MarcacionBiometrica | 41 587 | MarcacionID → MarcacionBiometricaID e ImportacionID → ImportacionBiometricaID. Datos crudos conservados. PersonalID se resuelve mediante los vínculos V4, nunca copiando un ID laboral de otra base. Procesamiento queda pendiente de conciliación. |
| rrhh.IncidenciaBiometrica | 404 | Base original; PK y referencias biométricas renombradas según V4. |
| catalogo.TipoDocumento / Sexo / EstadoCivil | 4 / 3 / 5 | Catálogos existentes de V3; IDs preservados. |
| organizacion.Sede / Departamento / Area / Servicio | 1 / 3 / 3 / 8 | Catálogo organizacional existente de V3; relaciones y PK preservadas. No se asignaron trabajadores automáticamente. |
| rrhh.Profesion / Cargo / TipoContrato / Turno | 11 / 7 / 5 / 6 | Catálogos existentes de V3; sin inferencias desde textos del padrón. |
| rrhh.TipoPermisoLaboral | 7 | Catálogo de la base original; Estado binario convertido a Activo. |

Los padrones originales de 453 y 181 filas permanecen íntegros. Se dejaron 10 IDs especiales sin crear fichas laborales automáticas. Hay 566 marcas sin vínculo; permanecen disponibles para revisión. La pantalla de pendientes incluye tanto IDs del padrón como identificadores presentes únicamente en las marcas.

**Los 443 documentos, fechas de ingreso, profesiones y situaciones laborales no confirmados se mantienen NULL.** La interfaz muestra «Por confirmar», sin confundirlo con cesado ni con activo. Las fichas se pueden completar con información verificada. Los nombres no se separan en apellidos por inferencia.

No se trasladaron las asignaciones, contratos ni asistencias evaluadas de las bases anteriores, porque sus relaciones y procedencia aún no están conciliadas. Esas tablas continúan conservadas en origen, incluyendo 5 029 asistencias, 1 712 horarios y 283 contratos de la base original. No se eliminaron ni sobrescribieron registros de esas bases.

## Cambios del sistema

| Archivos principales | Resultado |
|---|---|
| database/auditar_compatibilidad_v4.php | Auditoría de solo lectura de esquema, conteos, relaciones, duplicados, procedencia y conservación de marcas. Guarda informe local en storage/compatibilidad_v4.json, excluido de Git. |
| database/cargar_origen_verificado_v4.php | Vista previa por defecto; carga con --aplicar, transacción, bloqueo, destinos vacíos y auditoría. Detecta su ejecución previa y evita volver a insertar. No escribe en origen. |
| includes/PersonalRepository.php, api/personal/listar.php, detalle.php, pages/personal.php, assets/js/pages/personal.js | Estado laboral nullable, nombre de origen, ID biométrico separado del documento, datos originales visibles sin convertirlos en asignaciones. Alta y edición de fichas parciales. |
| api/personal/pendientes.php, vincular_biometrico.php | Revisión y vínculo manual por HR autorizado con sustento y confirmación explícita. Rechaza conflictos, no sobrescribe vínculos de otro trabajador y audita dentro de transacción. No genera documentos ni datos laborales. |
| includes/OrganizationCatalog.php; filtros de Personal, Asistencia, Ausencias, Programación y Reportes | Servicios e IDs desde organizacion.Servicio, limitados al servicio autorizado. Se retiraron opciones fijas que no correspondían a V4. |
| includes/TurnoCatalog.php, api/programacion/mensual.php, guardar.php | Horas de turnos laborales y códigos disponibles desde el catálogo real. Se mantienen equivalencias institucionales explícitas, sin inventar un turno SQL ausente. Se rechaza guardar un código sin duración definida. |
| includes/WorkloadPolicy.php, config/workload_rules.php, api/horarios/asignar.php, assets/js/pages/horarios.js | Las reglas asistenciales/administrativas se eligen para la pauta, con vigencia y auditoría. No se deducen del texto de cargo, profesión ni GrupoOcupacional. La lectura usa la regla confirmada aplicable a la fecha. |
| includes/ProgramacionStore.php, includes/attendance_evaluator.php, api/reportes/individual.php | Un día sin programación conserva el estado SIN_DATOS; no se convierte automáticamente en descanso ni en falta. El reporte individual puede consultar una ficha existente aunque su estado laboral esté pendiente. |
| includes/SystemSettings.php, includes/auditoria.php, api/administracion/parametros.php | Parámetros guardados en V4 con validación, usuario, sistema e historial; vigencia desde el día siguiente. La auditoría guarda el contenido estructurado sin prefijos que invaliden el JSON. |
| tests/integration.py, tests/ReportFixturePDO.php | Pruebas ajustadas al origen/sistema de sesión V4, catálogos reales simulados y HTTP 503 cuando SQL no está disponible. |

### Uso de auditoría para reglas y parámetros

V4 no contiene una tabla específica de parámetros. Se usa `auditoria.EventoAuditoria`, que ya existe, como registro de eventos versionados de configuración: Modulo=ADMINISTRACION, Accion=CONFIGURAR_RRHH, RegistroID=PARAMETROS_RRHH y Descripcion con valores y vigencia en JSON. La aplicación solo agrega versiones; no reescribe eventos anteriores. La lectura filtra por SistemaID y fecha de vigencia.

La selección de reglas al asignar una pauta también queda en su evento de auditoría, con PersonalID en RegistroID, fecha de inicio y tipo de reglas aplicadas. Esto describe una decisión del procedimiento y evita reutilizar GrupoOcupacional para un significado diferente. Régimen contractual, grupo ocupacional, profesión y ubicación laboral permanecen separados.

## Pruebas y resultados

- **23 endpoints de lectura contra SQL Server:** respuestas HTTP 200, incluida ficha parcial, listado de 443 fichas, catálogos, biometría, pendientes, programación, permisos, parámetros, reportes y administración. La consulta de pendientes ampliada se volvió a comprobar después de su última modificación.
- **7 comprobaciones transaccionales reales con rollback obligatorio:** edición parcial de una ficha existente, conservación tras rollback, rechazo de servicio de otra área, configuración con vigencia futura, usuario de auditoría real, ausencia de eventos de prueba persistidos y ausencia de clasificación horaria inventada. No se agregaron personas de prueba ni quedaron parámetros de prueba guardados.
- **94 comprobaciones de endpoints con PHP CLI y fixtures aislados:** autenticación, permisos, restricciones por servicio, errores, fechas y ausencia de guardados ficticios sin SQL.
- **28 comprobaciones V4:** datos incompletos, relaciones, documentos por catálogo, estados NULL frente a falso, IDs biométricos, aislamiento de sesión, reglas explícitas y horas de catálogo.
- Importador: 7 comprobaciones con PDO simulado de reimportación, marcas idénticas, ráfagas y vínculos explícitos.
- Seguridad SQL simulada: 6 comprobaciones. Configuración/login: 9 comprobaciones. Roles administrativos: correcto.
- Reglas/persistencia: 13 comprobaciones y 3 de guardias/licencias. Jornadas: 12 regresiones y 4 del conciliador. Persistencia SQL simulada: 6 comprobaciones. Cliente API: 19 comprobaciones.
- Contrato estático: tablas e INSERT de 70 archivos contrastados con el diccionario V4. Sintaxis PHP y JavaScript y revisión de espacios: correctas.
- Reejecución de la herramienta de carga: detecta el evento de carga previa y no inserta ni modifica registros.

La auditoría final real dio:

| Comprobación | Resultado |
|---|---:|
| Diferencias de tablas/columnas frente al diccionario | 0 |
| Especialidad/profesión incoherente | 0 |
| Servicio/área incoherente | 0 |
| Documentos duplicados | 0 |
| Marcas idénticas repetidas | 0 |
| Asignaciones actuales múltiples | 0 |
| FK deshabilitadas | 0 |
| FK no verificadas | 0 |
| Marcas crudas diferentes de las originales | 0 |

## Lo que falta confirmar

1. Estado laboral, documentos, datos profesionales, asignaciones y contratación reales de cada ficha. Ninguna correspondencia se completa por semejanza de nombres ni por el número del dispositivo.
2. Los 10 identificadores especiales y las 566 marcas pendientes; la interfaz permite documentar vínculos verificados y conserva los conflictos para revisión.
3. Migración histórica de contratos, asignaciones, permisos y asistencia evaluada, una vez aprobadas sus correspondencias; especialidades del modelo original requieren un mapeo explícito a las profesiones del catálogo V4. No se copiaron IDs de catálogos incompatibles.
4. Aceptación institucional de horarios, créditos vacacionales/CITT y cifras de planillas/exportaciones con datos completos. Las pruebas de lectura no certifican resultados de nómina ni sustituyen esa aceptación.
5. Pruebas integrales de navegador y de concurrencia simultánea real. Se comprobaron API, SQL y rollback; no se declara una certificación completa de interfaz o concurrencia.
6. Localizar los repositorios de Clínica y Laboratorio. Esta intervención no inserta pacientes ni módulos de esos sistemas en RR. HH.

El objetivo de esta fase es mantener datos y procedimientos coherentes con la información comprobada. Los campos y vínculos pendientes se muestran como pendientes, sin fabricar una apariencia de expediente laboral completo.
