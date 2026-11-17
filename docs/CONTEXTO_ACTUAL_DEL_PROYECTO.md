# Contexto actual del proyecto SIGH-MINSA

Actualizado el 16 de noviembre de 2026. Hospital Distrital de Pacasmayo, PMYO.

Este documento reúne el objetivo, las decisiones del usuario, los cambios implementados y los pendientes para continuar el trabajo. El estado se sustenta en el código local, las pruebas registradas y las confirmaciones del usuario. Las cifras de cargas y respaldos conservan la fecha de su verificación.

## 1. Objetivo y alcance

El objetivo general es adaptar los sistemas hospitalarios a sus tablas reales, conservar información y funcionalidades, evitar identidades duplicadas y permitir trabajar con registros incompletos sin inventar datos.

El pedido inicial contempló Recursos Humanos, Clínica y Laboratorio sobre `BD_HOSPITAL_V4`. Posteriormente el usuario indicó trabajar sobre `BD_HOSPITAL_V3`, validarla en el servidor de pruebas y trasladarla al equipo del ingeniero. La configuración predeterminada actual apunta a **V3**.

Este repositorio contiene el proyecto de **Recursos Humanos**. Sus módulos son Dashboard, Personal, Horarios y Turnos, Programación mensual, Asistencia, Ausencias, Reportes y Administración. La adaptación completa de los proyectos independientes de Clínica y Laboratorio queda fuera de lo verificado en esta etapa.

La prioridad actual es consolidar rendimiento, integridad y experiencia de uso en la instalación del ingeniero, manteniendo las reglas institucionales confirmadas.

## 2. Reglas del usuario que deben conservarse

- Mantener independientes los proyectos de RRHH, Clínica y Laboratorio.
- Utilizar tablas y columnas reales. Justificar cualquier cambio de estructura antes de introducirlo.
- Identificar trabajadores mediante `rrhh.Personal` y pacientes mediante `clinico.Paciente`. Eliminar dependencias de `core.Persona` y `PersonaID` en los flujos adaptados.
- Diferenciar profesión, especialidad, cargo, departamento, área y servicio. La existencia de un área no acredita una asignación a un servicio.
- Conservar `BiostarUserID` como identificador biométrico textual. El vínculo con el trabajador se resuelve mediante `PersonalID` y `rrhh.VinculacionBiometrica`.
- Mantener los ceros iniciales de los documentos y los identificadores originales del dispositivo.
- Tratar `ESTADO` del archivo depurado como calidad técnica del registro. Nunca usarlo para bajas, ceses, inactivaciones o revocaciones automáticas.
- La ausencia de marcaciones tampoco acredita una baja laboral.
- Mantener ausentes los datos desconocidos. No deducir contratación a partir de la primera huella, el alta biométrica o la fecha de carga.
- Preservar horarios, asistencia, CSV, incidencias, vacaciones, contratos, reportes y auditoría, con las validaciones propias de cada procedimiento.
- Evaluar acceso por sistema, usuario, rol y alcance de servicio.
- Preservar registros previos y referencias de otras áreas hospitalarias.
- El usuario canceló la publicación en Git. La instalación en el ingeniero se ha realizado mediante copia de archivos por el usuario. Cualquier publicación posterior requiere una nueva instrucción.

## 3. Entornos y configuración

| Entorno | Lo conocido |
|---|---|
| Repositorio de trabajo | PHP, JavaScript sin framework de SPA y CSS modular |
| Base de pruebas consultada | `BD_HOSPITAL_V3`, SQL Server 2022 Express, servidor de pruebas `192.168.11.38:1433` |
| Instalación del ingeniero | El usuario accede por `http://192.168.5.200/recursos_humanos/`, con IIS y PHP 8.2.33 observados en una respuesta HTTP |
| Base del ingeniero | El usuario confirmó conexión, restauración/copia y pruebas de funcionamiento. La dirección del servidor web no demuestra cuál es la instancia SQL configurada |

El acceso SQL se configura en `config/database.local.php`. Las variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` tienen prioridad, incluso si están definidas con un valor vacío. Después se usan los valores locales y, por último, los predeterminados de `config/database.php`.

Conservar la configuración local propia del ingeniero al copiar actualizaciones. El código actual requiere **`pdo_sqlsrv`** para abrir la conexión. La extensión procedimental `sqlsrv` por sí sola no satisface esa comprobación.

`APP_SYSTEM_CODE` identifica al sistema, normalmente `RRHH`, y debe corresponder a `seguridad.Sistema` y a las habilitaciones del usuario. La conexión a SQL y la cuenta para entrar a la página se configuran por separado.

Esta documentación omite contraseñas, hashes de acceso y registros identificables de trabajadores. Las evidencias privadas están excluidas mediante `.gitignore`.

## 4. Arquitectura y fuentes de verdad

- `index.php` enruta los módulos y exige autenticación. Cambiar de módulo carga un documento PHP nuevo.
- `pages/` contiene las vistas. Las pestañas internas se controlan desde `assets/js/pages/`.
- `api/` contiene consultas y operaciones PHP con controles compartidos en `includes/api_bootstrap.php`.
- `config/database.php` centraliza PDO, configuración, tiempo de conexión y circuit breaker.
- `includes/auth.php` y `includes/SystemContext.php` controlan sesión, sistema, roles y alcance.
- `includes/PersonalRepository.php` gestiona las escrituras de personal y relaciones.
- `includes/ProgramacionStore.php` persiste la programación mensual con versión y control de conflictos.
- `includes/AttendanceSchedule.php`, `AttendanceRules.php`, `attendance_evaluator.php` y `AttendanceSummary.php` resuelven programación, evaluación y resúmenes.
- SQL Server conserva los datos institucionales. `SnapshotStore.php` guarda copias de consulta por origen de base. Las escrituras institucionales requieren conexión SQL.
- `assets/js/api.js` gestiona solicitudes, caché y errores. La caché del navegador y los snapshots del servidor tienen ciclos distintos.

La estructura real y sus relaciones prevalecen sobre las descripciones antiguas. El pedido original nombró `01_BD_HOSPITAL_V4_ESTRUCTURA.sql`, `07_PLAN_MIGRACION_Y_MAPEO.md` y `DICCIONARIO_DE_CAMPOS_V4.csv`. Esos archivos no se encontraron en la raíz en esta revisión. Si vuelven a suministrarse, deben contrastarse con la base objetivo.

Los documentos V4 describen una etapa anterior. Sus scripts no son instrucciones automáticas de instalación para la V3 actual.

## 5. Personal y datos cargados

### Carga verificada del 9 de noviembre de 2026

La fuente aprobada fue `personal_limpio_asistencia.txt`, con 450 filas y 13 columnas. Su SHA-256 registrado es `55cf61db5f314a8acb83ee77b44f488d67849301bff2b12ee792b5904da8d655`.

| Resultado de la carga | Cantidad |
|---|---:|
| Trabajadores insertados | 450 |
| Trabajadores totales después de la carga | 451, incluyendo el registro previo |
| Asignaciones nuevas de área/cargo verificadas | 192 |
| Vínculos biométricos nuevos | 269 |
| Documentos pendientes de regularización | 1 |
| Casos de apellidos que requieren revisión | 10 |
| Bajas realizadas por la carga | 0 |
| Marcaciones importadas por esa carga | 0 |
| Horarios creados por esa carga | 0 |

Se conservaron los registros originales y sus referencias. La validación posterior registró **8.548 comprobaciones sin fallos**, sin documentos o BioStar duplicados, vínculos huérfanos, asignaciones múltiples actuales ni FK deshabilitadas. La edición de fichas completas e incompletas se probó en 15 comprobaciones con rollback.

La carga dejó sin informar fechas de ingreso y códigos de empleado que el archivo no acreditaba. El identificador de nueve dígitos pendiente no se convirtió en un DNI válido. Se conservó información de origen para revisión.

La compatibilidad de V3 incluyó permitir nulos en campos de identidad/ingreso que bloqueaban la carga de datos incompletos, en `AsignacionPersonal.FechaInicio` y usar índices únicos filtrados para documentos y códigos informados. Estas decisiones constan en los manifiestos privados del lote.

Las herramientas relevantes son `database/cargar_personal_limpio_v3.php`, `validar_carga_personal_v3.php`, `plan_carga_personal_v3.py`, `preparar_personal_limpio_v3.py` y `auditar_padron_v3.php`. La carga está registrada como `IMPORT_PERSONAL_TXT`; revisar su manifiesto antes de plantear otra ejecución.

Las evidencias se encuentran en `private-audits/carga-personal-v3/`, especialmente `resultado_20261109T173722Z_2e6038.json` y `validacion_posterior.json`.

### Biométrico

El archivo analizado fue `Report_20261015T070613_1.csv`, con 50.000 eventos y 41.382 marcas de huella admitidas en la revisión. Los 269 vínculos preparados cubrían 41.375 marcas. Quedó un identificador sin resolver, con siete marcas.

La relación explícita es `BiostarUserID → PersonalID`. Un documento coincidente por sí solo no sustituye el vínculo que consulta el importador. Tres equivalencias verificadas conservaron un DNI de ocho dígitos y un identificador biométrico original de siete, sin rellenar ni recortar valores.

Un vínculo correcto permite atribuir eventos. La asistencia requiere además programación vigente, fecha y conciliación. Las marcaciones consecutivas se conservan como eventos y se distinguen de duplicados exactos.

Los dos archivos de entrada anteriores no se encontraron actualmente en la raíz. Los análisis históricos no autorizan a reconstruir sus datos ni a repetir la carga sin verificar el estado presente. La carga de personal citada no importó asistencia. El inventario de importaciones actual del ingeniero necesita comprobarse en su propia instalación.

## 6. Cambios funcionales implementados

| Módulo | Comportamiento implementado y archivos principales |
|---|---|
| Personal | Editor de relaciones y catálogos PMYO. Filtros separados por área y servicio. Listado paginado en SQL. `pages/personal.php`, `assets/js/pages/personal.js`, `api/personal/listar.php`, `includes/PersonalDirectory.php`, `OrganizationCatalog.php`, `PersonalRepository.php` |
| Selectores de trabajadores | Consulta ligera compartida con ID, nombre, documento y relaciones necesarias. Contexto de permisos del módulo y respaldo por alcance. `api/personal/opciones.php`; consumidores en Personal, Reportes, Horarios, Ausencias y Administración |
| Horarios y Programación | Catálogo SQL de turnos, pauta semanal y matriz mensual. Filtro de área en Programación y exportación asociada. Se retiró la etiqueta BORRADOR del título visible |
| Reportes | Récord individual, Consolidado, SETI y Rol Mensual Oficial. Filtro de área en las cuatro pestañas. Selector de trabajador del rol dependiente de área y servicio. Selecciones aplicadas al Excel. El CSV disponible sigue los tipos admitidos por su endpoint |
| Dashboard | Turnos confirmados, puntualidad, faltas evaluadas, pendientes de verificación, calidad del padrón, carga biométrica y contratos/solicitudes. Filtros de área/servicio/trabajador, cobertura y listado de 10 trabajadores por página |
| Interfaz | Skeletons en el dashboard y los cuatro reportes. Transición de 180 ms en módulos y pestañas, con preferencia de movimiento reducido. `assets/css/components.css` |
| Auditoría | Compatibilidad V3 con la consulta existente. Se preservó la clave original y se documentaron la proyección `AuditoriaID` y el campo `Modulo`. Herramienta `database/compatibilidad_auditoria_v3.php` |

### Corrección de asistencia en el dashboard

El panel tenía una etiqueta predeterminada Asistió para estados que no acreditaban asistencia. Ahora solo muestra esa etiqueta para `ASISTIO`. También usa `hora_entrada` del endpoint y muestra la ausencia de programación confirmada.

La fecha de registro de un trabajador no acredita asistencia. La lectura cruza `rrhh.Asistencia` por `PersonalID` y fecha, y evalúa el rol mensual o la pauta semanal vigente.

`SIN_DATOS`, `PROGRAMADO`, `EN_CURSO`, descansos y permisos se presentan por separado. El conteo de programados exige turno y horas confirmadas. La puntualidad utiliza su propio numerador. Los horarios semanales se leen agrupados por fecha.

El estado de conexión/origen aparece al pie del listado. La cobertura muestra cantidades programadas; para evaluar déficit falta definir dotaciones mínimas institucionales.

## 7. Optimización realizada

El selector anterior reunía el personal mediante varias llamadas al listado completo. La nueva consulta ligera entrega todos los trabajadores autorizados en una petición. En la base de pruebas se verificaron **451 opciones**, pasando de 10 peticiones a 1 y de aproximadamente 383 KB a 94 KB de contenido JSON comparable.

`PersonalDirectory.php` aplica filtros y paginación SQL, conserva los criterios históricos y calcula estadísticas. Materializa claves y contadores en una variable de tabla que vive solo durante la consulta, y recupera las fichas de la página pedida. Conserva respaldos de páginas y la lectura de respaldos completos anteriores.

Se compararon resultados y estadísticas de 16 casos contra una copia del endpoint anterior. Todos coincidieron. La mayor mejora medida está en los selectores. Algunos filtros individuales conservaron tiempos similares o superiores; falta revisar sus planes de ejecución y medir en la VM del ingeniero. No se añadieron índices permanentes en esta optimización.

El usuario confirmó que copió esta entrega al servidor y percibió mayor fluidez. Cambiar de módulo todavía genera HTML PHP nuevo. Los tiempos de la base de pruebas no equivalen a la carga visual completa en el ingeniero.

## 8. Caché verificable y skeletons de Reportes

### Implementación más reciente

`assets/js/api.js` utiliza memoria y `sessionStorage`. La clave separa sesión, sistema, origen de base, rol y servicio. Antes de reutilizar datos se consulta `api/data_revision.php`, que revalida la sesión y sus permisos mediante los controles existentes.

`includes/DataRevision.php` comprueba la cobertura de triggers y lee `seguridad.VersionDatos`. Una revisión fiable habilita la reutilización. Ante cobertura incompleta se mantiene la lectura de datos. La revisión incorpora el día y las consultas temporales de asistencia, dashboard y reportes caducan por minuto cuando se vuelven a consultar.

La revisión es **global**. Cualquier cambio supervisado invalida las vistas guardadas. Los guardados locales invalidan la caché incluso si se pierde su respuesta. Las solicitudes simultáneas idénticas comparten petición y reciben copias independientes. Así una edición local no modifica la copia guardada.

Las comprobaciones periódicas y al recuperar el foco avisan sobre cambios externos. El botón Actualizar fuerza una lectura. Los errores de autorización eliminan la caché. La contingencia se identifica como respaldo de consulta.

En los cuatro reportes se muestran skeletons de cabecera, indicadores y tabla. Una vista vigente se mantiene mientras se comprueba su revisión, con un skeleton diferido si la espera se prolonga. Las respuestas antiguas se descartan cuando existe una selección más reciente.

### Activación pendiente de confirmar en el ingeniero

Ejecutar una vez `database/habilitar_cache_rrhh_v3.sql` en su `BD_HOSPITAL_V3`. El script requiere la tabla `seguridad.VersionDatos` existente con una fila y completa los triggers de seguimiento sobre tablas reales. Debe acompañarse de estos archivos de aplicación de la misma entrega.

| Archivo | Función |
|---|---|
| `includes/DataRevision.php` | Verificación de cobertura y revisión |
| `api/data_revision.php` | Revalidación para el navegador |
| `includes/footer.php` | Configuración del cliente para usar revisión |
| `assets/js/api.js` | Persistencia, invalidación, vencimiento y solicitudes compartidas |
| `assets/js/app.js` | Avisos y actualización forzada |
| `assets/js/pages/dashboard.js` | Reutilización y botón Actualizar |
| `assets/js/pages/reportes.js` | Skeletons y conservación de vistas |
| `assets/css/pages/reportes.css` | Estilos de los skeletons |
| `pages/reportes.php` | Skeletons iniciales de los cuatro paneles |

El script se probó en V3 con rollback y seis comprobaciones. Al terminar se restauró el estado original. **No consta una confirmación posterior del usuario de que esta última entrega y el script ya estén instalados en el ingeniero.**

## 9. Seguridad, acceso y diagnósticos registrados

- La autenticación verifica bcrypt, usuario habilitado, rol del sistema y `UsuarioSistema`, con sus vigencias.
- La autorización de módulos continúa usando la matriz PHP de `Auth::roleAllows`. Poblar `Permiso` y `RolPermiso` no sustituye automáticamente esa matriz.
- `database/crear_admin_v3.sql` prepara la cuenta administrativa y sus relaciones RRHH, con ocho módulos y doce permisos derivados de funciones reales. Su ejecución restablece la clave solicitada. Revisarlo como una operación administrativa explícita.
- La V3 inspeccionada exige `Usuario.PersonalID`. El script preserva el vínculo existente y exige un trabajador real si debe crear la cuenta en ese esquema. La creación de una cuenta técnica sin vínculo requiere resolver esa compatibilidad.
- `LoginThrottle.php` limita a 10 intentos por usuario/IP y 100 por IP en una ventana de 15 minutos. Cuenta las solicitudes antes de comprobar credenciales o SQL. El bloqueo HTTP 429 se guarda en temporales del servidor.
- El mensaje genérico de JavaScript sobre servidor de autenticación puede aparecer cuando la respuesta no es JSON válido o falla la petición. Revisar estado HTTP, Response y el registro PHP para distinguirlo de un error SQL.
- En el código local el botón `btnAuxiliaryLogin` está oculto. `config/auxiliary_account.php` mantiene habilitada la cuenta de contingencia. Ocultar el botón no cambia esa configuración.

## 10. Respaldos, exportación y reversión

Se prepararon herramientas de respaldo nativo, exportación de esquema e instalador para una base nueva. Entre ellas están `respaldar_v3.php`, `exportar_esquema_v3.php`, `generar_instalador_v3.php` y `validar_exportacion_v3.py` en `database/`.

El respaldo posterior a la carga registrado en pruebas es `BD_HOSPITAL_V3_CON_PERSONAL_20261109T175159Z_943feb5d.bak`. Su manifiesto está en `private-audits/entrega-v3/backup_20261109T175159Z_943feb5d.json` y señala una ruta en el servidor Windows, sin archivo descargado local. `RESTORE VERIFYONLY` fue rechazado por permisos de la cuenta. Ese manifiesto no acredita una restauración probada.

Un backup completo conserva las tablas de usuarios, hashes, roles y relaciones de la aplicación presentes al crearlo. La instancia SQL destino necesita sus propios logins y mapeos de acceso. Los archivos locales del proyecto se entregan aparte.

El SQL/DACPAC de estructura y los paquetes de preparación anteriores tienen su propia fecha. Un instalador de esquema vacío no incorpora por sí solo los datos del respaldo. Los paquetes SIN_CARGAR_PADRON y la documentación previa al 9 de noviembre no representan el lote posteriormente cargado.

`docs/PLAN_REVERSION_PADRON_V3.md` conserva el plan anterior a la carga. La identificación efectiva de lo insertado corresponde al manifiesto del lote aplicado. Cualquier recuperación debe preservar referencias previas y comprobar primero el estado del destino.

## 11. Pruebas y evidencia

| Comprobación | Evidencia o prueba |
|---|---|
| Carga de 450 trabajadores | Resultado y validación privados del lote del 9 de noviembre |
| Lecturas reales V3 | `tests/v3_live_read.php` |
| Equivalencia de 16 consultas y benchmark del selector | `tests/personal_directory_live.php`, requiere una copia del endpoint anterior para comparar |
| Selectores completos y registros incompletos | `tests/personal_selectors.cjs` |
| Permisos y respaldos por alcance | `tests/personal_options_security.php` |
| Selección de área/servicio/trabajador en reportes | `tests/reportes_area.cjs` |
| Estados del dashboard y paginación/skeletons | `tests/dashboard_attendance_states.js`, `dashboard_loading.cjs`, `dashboard_metrics.php` |
| Caché, invalidación y errores | `tests/api-client.cjs`, 33 comprobaciones en la última ejecución |
| Skeletons de los cuatro reportes y respuestas antiguas | `tests/reportes_loading.cjs` |
| Triggers de revisión con restauración del estado original | `tests/cache_revision_live.php`, seis comprobaciones con rollback |
| Creación de usuario y selector | `tests/admin-new-user.cjs` |
| Jornadas y conciliación | `tests/attendance_regressions.php`, `tests/rules.php` |

Las pruebas con PDO simulado, las consultas reales y los ensayos con rollback acreditan alcances distintos. Los ensayos CLI sobre SQL necesitan conexión y permisos, y algunas pruebas históricas necesitan archivos de entrada que actualmente faltan en la raíz.

## 12. Pendientes y límites conocidos

1. Confirmar la instalación de la última entrega de caché y ejecutar su script en la V3 del ingeniero. Verificar reutilización, guardados, cambios desde otra sesión y caducidad temporal.
2. Medir navegación y consultas en su VM. Revisar planes de ejecución antes de proponer índices permanentes.
3. Revisar el alta ordinaria de `PersonalRepository.php`. Todavía contiene valores automáticos para fecha de ingreso, código EMP, tipo de documento y copia de código a DNI cuando faltan datos. También existen normalizaciones de régimen y una fecha de asignación por defecto. La carga validada de noviembre usó su importador específico. Este flujo ordinario requiere corregirse para cumplir plenamente la regla de datos desconocidos.
4. Revisar las clasificaciones históricas por coincidencias de texto que el listado conserva para sus filtros y estadísticas. No acreditan profesión, especialidad o régimen confirmados.
5. Resolver compatibilidad de cuentas técnicas sin `PersonalID` en el esquema V3 real.
6. Regularizar el documento y los apellidos pendientes, además de las áreas, cargos y datos laborales ausentes, con evidencia institucional.
7. Verificar horarios históricos, vigencias y conciliación antes de cargar asistencia de los CSV. Una nueva pauta no acredita programación de meses anteriores.
8. Confirmar restauración de respaldo en un destino de prueba y comprobar permisos/login SQL del destino.
9. Investigar el retroceso de archivos y datos que reportó el usuario en la VM. Se plantearon restauración de snapshot, sincronización o acceso a otra copia/instancia como hipótesis. No se confirmó la causa. Revisar historial del hipervisor, respaldos, tareas, ruta física de IIS y configuración SQL efectiva.
10. Completar por separado la adaptación y validación de los proyectos de Clínica y Laboratorio cuando estén disponibles.

## 13. Cómo continuar en una nueva sesión

Leer este documento y comprobar el código presente antes de proponer cambios. Confirmar la base objetivo y distinguir servidor web, instancia SQL y carpeta física de IIS. Los recuentos históricos deben contrastarse si se va a modificar o migrar datos.

El usuario suele copiar manualmente los archivos al ingeniero. Cada entrega debe indicar todos los archivos modificados o nuevos, cuáles vuelven a cambiar respecto de la entrega anterior y cualquier script SQL necesario. Copiar solo la vista PHP puede mostrar un filtro cuyo JavaScript o endpoint aún sea antiguo.

La recarga con Ctrl+F5 ayuda a recibir los assets nuevos. Si un filtro no produce una petición, comprobar el JavaScript servido y la consola. Si la petición existe, revisar URL, estado y respuesta. No atribuir el fallo a SQL únicamente por un mensaje genérico del navegador.

Los documentos de inventario, preflight, preparación de carga y V4 siguen disponibles para rastrear decisiones anteriores. El README histórico queda identificado como tal. Este documento es la referencia actual de continuidad y debe actualizarse con las siguientes entregas y sus validaciones.


## 14. Consolidación de denominaciones solicitada el 16 de noviembre

El usuario confirmó haber ejecutado `database/consolidar_area_cirugia_v3.sql` en el entorno del ingeniero. El script consolida CIRUGIA en CIRUGIA GENERAL, conserva las referencias de personal, servicios y puntos de red existentes, audita la operación y elimina el área de origen. La confirmación procede del usuario; el asistente no ejecutó ese script en el destino.

Posteriormente se preparó `database/normalizar_enfermeria_v3.sql` para consolidar el área ENFERMERA en ENFERMERIA y el cargo ENFERMERIA en ENFERMERA, conforme a la denominación indicada por el usuario. Conserva los IDs de los destinos existentes y traslada sus referencias, incluyendo asignaciones históricas del mismo concepto. No altera profesión, especialidad, técnicos, internos, licenciados ni el cargo Enfermero(a).

Esta segunda consolidación se entrega sin ejecución SQL y está pendiente de confirmación del usuario. Usa una transacción única, auditoría y comprobación de referencias adicionales. Los nombres se buscan completos; los IDs del inventario histórico no se codifican como IDs del ingeniero.


### Aclaración posterior de la limpieza de Enfermería

El usuario indicó que ya trasladó manualmente el personal al área ENFERMERIA y cambió sus cargos a ENFERMERA. La solicitud vigente queda limitada a eliminar el área ENFERMERA vacía.

Se preparó `database/eliminar_area_enfermera_vacia_v3.sql`. Comprueba todas las FK reales, incluidas relaciones históricas y acciones en cascada, y elimina únicamente el área si no encuentra referencias. Audita el registro eliminado. No reasigna personal ni modifica cargos. Este script queda pendiente de ejecución por el usuario. El script anterior de consolidación completa de Enfermería queda identificado como histórico y sustituido para esta solicitud.


## 15. Nueva normalización de cargos de Enfermería

El usuario solicitó eliminar el área vacía de LIC ENFERMERIA y corregir las denominaciones del catálogo de cargos. Se preparó `database/normalizar_roles_enfermeria_v3.sql`, pendiente de ejecución en el ingeniero.

El cargo general queda como ENFERMERO. El script reconoce ENFERMERA, ENFERMERIA y Enfermero(a) como variantes del mismo grupo. Prioriza un ENFERMERO existente; de faltar, renombra una variante existente siguiendo el orden explícito del script. Conserva su ID y código técnico y traslada las asignaciones de los cargos redundantes antes de eliminarlos. Rechaza duplicados ambiguos y diferencias de nivel que requieran revisión.

El cargo de licenciado queda separado como LIC ENFERMERIA. La variante ortográfica LIC.ENFEMERIA o LIC ENFEMERIA se corrige o consolida en ese grupo. Técnicos, internos, profesiones y especialidades quedan fuera de la normalización.

La eliminación del área solo procede si no tiene referencias, incluidas las históricas. Todo se realiza en una transacción con auditoría. El script se contrastó estáticamente con los metadatos disponibles; no se ejecutó SQL.

También se actualizó `includes/PmyoCatalog.php` para reconocer el nombre ENFERMERO en el selector de cargos del área y en sus propuestas explícitas de profesión. Este archivo debe copiarse al ingeniero junto con la ejecución del script. El mapa de origen conserva sus denominaciones históricas. `tests/pmyo_cargos_normalizados.php` verificó seis casos de compatibilidad.


## 16. Búsqueda rápida por varios nombres y apellidos

Se corrigió la búsqueda de Personal que descartaba una frase cuando no estaba completa en un único campo de identidad. Ahora cada término debe aparecer en los datos del trabajador, con independencia del orden, permitiendo combinar apellido paterno, materno y nombres. Se admiten espacios repetidos y comas. La comparación omite diferencias de mayúsculas y acentos.

Se añadió `includes/PersonalSearch.php`, compartido por la construcción SQL y la búsqueda en respaldos. Se modificaron `includes/PersonalDirectory.php`, `api/personal/listar.php` y `assets/js/pages/personal.js`. La versión de búsqueda cambió para descartar resultados de caché anteriores. También se consulta el nombre completo original informado cuando está disponible, sin inferir apellidos ausentes.

Las pruebas locales `tests/personal_search.php` cubrieron 18 combinaciones y la construcción parametrizada de SQL con varios términos. `tests/personal_search_live.php` quedó preparado para verificar registros existentes mediante solo lectura, pero la conexión a la base de pruebas no estuvo disponible. La copia de los cuatro archivos de aplicación y la validación en el ingeniero quedan pendientes de confirmación.


## 17. Área Asistente Social y diagnóstico de edición

Se preparó `database/renombrar_area_asistente_social_v3.sql` para corregir ASISTENTA SOCIAL a ASISTENTE SOCIAL conservando el mismo ID y todas sus relaciones. Está pendiente de ejecución. `includes/PmyoCatalog.php` reconoce el nombre corregido mediante una equivalencia explícita con el mapa de origen, conservando la oferta del cargo LIC TRABAJO SOCIAL.

El usuario reportó HTTP 500 en la instalación IIS del ingeniero al guardar esa relación. Indicó que editar género funciona y que había modificado otras áreas anteriormente. La captura del POST a `api/personal/editar.php` mostraba estado 500 con contenido HTML. La respuesta posterior «Método no permitido» corresponde al control del método HTTP y todavía no identifica la causa del 500.

El mapa contempla esa relación de área/cargo. La causa concreta del fallo sigue pendiente de la respuesta del POST fallido o del registro PHP a la misma hora. Un fallo al registrar auditoría es una posibilidad contemplada por el código, no un diagnóstico confirmado. No se modificó el repositorio de guardado para ocultar o saltar validaciones.


## 18. Clasificación asistencial y vigencia del contrato (16/11/2026)

Se encontró una clasificación errónea en Personal: buscar SIS como subcadena del área también coincidía con ASISTENTA SOCIAL / ASISTENTE SOCIAL. Además, el editor recibía códigos legales (DL 1153, etc.) en un selector que solamente contenía ADMINISTRATIVO/ASISTENCIAL y convertía esas categorías en regímenes legales al guardar.

Se separaron régimen laboral legal y clasificación de personal en el formulario. La clasificación confirmada se guarda en la auditoría existente de rrhh.Personal, dentro de la misma transacción, y WorkloadPolicy la consulta por sistema junto con las confirmaciones de Horarios. Una clasificación vacía confirmada permite retirar una clasificación anterior sin inventar otra. No se crean tablas ni columnas. La fecha técnica de confirmación no representa contratación. Las etiquetas y filtros del directorio ahora utilizan confirmaciones explícitas; sin confirmación pueden aparecer como Sin clasificar. No se deduce profesión, clasificación o régimen a partir del área. Los datos de auditoría antiguos con prefijo [HORARIOS] o [PERSONAL] se reconocen.

Vigencia corresponde a rrhh.Contrato: sin contrato muestra Sin contrato registrado; si existe sin fechas, Fechas del contrato sin registrar. No se crean contratos ni fechas al cambiar área o cargo. Deben revisarse documentalmente los regímenes legales que pudieran haber sido inferidos por versiones anteriores; no se modificaron masivamente.

Archivos de esta corrección: includes/WorkloadPolicy.php, includes/PersonalRepository.php, includes/PersonalDirectory.php, api/personal/listar.php, api/personal/detalle.php, pages/personal.php, assets/js/pages/personal.js. Prueba añadida: tests/personal_classification.php. Validaciones locales de clasificación, búsqueda y selectores aprobadas, así como sintaxis PHP/JS. La suite histórica tests/v4_regressions.php no terminó: su fixture SQLite de TipoDocumento carece de Codigo, columna que ya utiliza validateRelations. No se ejecutaron cambios en SQL Server; pendiente prueba de guardado y reapertura en el servidor del ingeniero.


## 19. Error IM001 al crear personal o asignaciones

El servidor del ingeniero devolvió: SQLSTATE[IM001], driver does not support lastInsertId(). PersonalRepository usaba esa función después de insertar rrhh.Personal y rrhh.AsignacionPersonal. Se reemplazó por INSERT seguido de SELECT CAST(SCOPE_IDENTITY() AS int) en el mismo lote y conexión; se recorren resultados intermedios, se valida identidad positiva y se cierra el cursor. Se conserva la transacción y la reversión ante fallo. SCOPE_IDENTITY evita tomar la identidad generada dentro de un trigger. No se altera el esquema ni se insertan datos durante las pruebas locales.

Archivo para desplegar: includes/PersonalRepository.php. Prueba local: php tests/personal_insert_identity.php (5 casos con PDO simulado), clasificación y búsqueda también aprobadas. Pendiente comprobación con el controlador real del ingeniero. Este mensaje confirma la causa de ese intento; solo explicaría el fallo anterior de Asistente Social si ese guardado también insertó una asignación y falló en lastInsertId.


## 20. Habilitar cuentas compartidas para RRHH (17/11/2026)

Diagnóstico remitido por el usuario desde SVR-WEB / BD_HOSPITAL_V3: UsuarioID 7, PersonalID 2302 existe; no tiene filas en UsuarioSistema ni UsuarioRol. RolID 3 es CONSULTA del sistema RRHH. Compartir rrhh.Personal y seguridad.Usuario con Laboratorio no concede permisos de RRHH. El script puntual anterior no fue ejecutado por el usuario y deja de ser necesario para este caso: se habilita desde Administración.

En Nuevo Usuario se agregó la operación Habilitar una cuenta existente para RRHH: consulta cuentas activas y no bloqueadas sin vínculo previo con RRHH, permite elegir una y asignar un rol real de RRHH. La acción habilitar reutiliza UsuarioID, PersonalID y contraseña; agrega UsuarioSistema y asigna el rol exclusivamente del sistema actual en una transacción auditada. No crea personal ni cuentas duplicadas, no toca roles de Laboratorio. No reactiva automáticamente vínculos previos vencidos o inactivos. Las cuentas técnicas requieren rol administrador; los roles con alcance por servicio requieren un servicio real asignado. Repetir la habilitación se rechaza sin duplicar registros.

La edición de una cuenta compartida permite cambiar el rol de RRHH si los datos globales no cambian. Cambiar identidad, contraseña, bloqueo o estado global desde RRHH se rechaza en cuentas compartidas. El listado ahora incluye cuentas vinculadas a RRHH aunque carezcan de rol, para regularizarlas, y selecciona únicamente un rol vigente del sistema. El catálogo de roles deja de ofrecer roles de otros sistemas, sin SistemaID, respaldos históricos o identificadores predeterminados como si fueran asignables. Sin catálogo SQL vigente devuelve error.

Se corrigió además el alta nueva: antes creaba Usuario y UsuarioSistema pero omitía UsuarioRol. Ahora persiste los tres registros y auditoría en la misma transacción. Auditoria::registrar acepta la conexión PDO de la operación para que sus errores reviertan exactamente esa transacción. Auth no requirió cambios: login y validateSession ya exigen rol activo/vigente de RRHH y UsuarioSistema activo/vigente, además del estado de cuenta y trabajador.

Archivos para despliegue: includes/UserAdmin.php, includes/auditoria.php, api/administracion/usuarios.php, api/administracion/roles.php, pages/administracion.php, assets/js/pages/administracion.js.

Pruebas: php tests/user_admin_shared.php (incluye tests/test_user_admin_edition.php), php tests/v4_auth.php, node tests/admin-new-user.cjs, node tests/api-client.cjs; sintaxis PHP/JS de los archivos modificados. La prueba compartida utiliza SQLite en memoria y verifica acceso antes/después de habilitar, integridad de cuenta y Laboratorio, rol ajeno rechazado, doble habilitación, edición del rol RRHH, bloqueo de cambios globales, cuentas inactivas/bloqueadas, alta nueva con rol y reversión de vínculo/rol si falla auditoría. La prueba JavaScript verifica selección de cuenta y envío exclusivo de acción, UsuarioID y RolID sin contraseña. No se insertaron datos reales ni se alteró el esquema. Pendiente validación en IIS/SQL Server del ingeniero: copiar archivos, recargar, Nuevo Usuario > Habilitar cuenta existente, elegir cuenta 40955497 y rol apropiado. El rol CONSULTA exige un servicio asignado según la regla vigente de alcance.


## 21. Corrección TEC FARCACIA a TEC FARMACIA (17/11/2026)

El usuario confirmó el error ortográfico heredado del Excel. Se preparó database/corregir_cargo_tec_farmacia_v3.sql: renombra exactamente el cargo en rrhh.Cargo conservando ID y todos los demás datos; por tanto las asignaciones actuales e históricas siguen apuntando al mismo registro. Incluye transacción, auditoría y guardas por duplicidad. Si ya hay otro TEC FARMACIA, aborta sin consolidar ni eliminar cargos: requiere revisar ambos IDs. Si ya está corregido, no modifica nada. No cambia profesiones ni estados laborales. No se ejecutó en SQL Server.

includes/PmyoCatalog.php reconoce TEC FARMACIA como equivalencia explícita del perfil histórico TEC FARCACIA, manteniendo su filtro en FARMACIA y la propuesta profesional TFAR para revisión; no asigna profesiones automáticamente. config/pmyo_personal_map.php conserva el texto histórico de origen. tests/pmyo_cargos_normalizados.php pasó 10 comprobaciones y la sintaxis PHP fue válida. Copiar includes/PmyoCatalog.php al servidor además de ejecutar el script en la base real; recargar catálogo después de la corrección.


## 22. Áreas confirmadas: Rehabilitación y SIS; Medicina separada (17/11/2026)

El usuario confirmó: MEDICINA y MEDICINA GENERAL son áreas distintas, no consolidar; REHABILITACIÓN FÍSICA se consolida en REHABILITACIÓN; SIS se consolida en SIS (SEGURO INTEGRAL DE SALUD). No se autoriza con esto reclasificar otras áreas con nombres de cargo.

Se preparó database/unificar_areas_rehabilitacion_sis_v3.sql, sin ejecutarlo: una transacción para ambos pares, identificación por nombre completo ignorando acentos y mayúsculas, destino único y activo ya existente, control de departamento, revisión de FK reales y de relaciones área/servicio y de códigos de servicio coincidentes. Conserva ServicioID, CargoID y PersonalID; traslada servicios y todas las asignaciones de cada origen, incluidas históricas, respetando la FK compuesta AreaID/ServicioID mediante una separación temporal dentro de la transacción. Traslada referencias conocidas de redes.PuntoRed, se detiene ante otras referencias con datos y conserva cada área de origen con Activo=0; no elimina filas. Audita las consolidaciones y el nombre final acentuado de REHABILITACIÓN. Un fallo en cualquier par revierte ambos. Repetir no crea áreas, servicios ni asignaciones adicionales.

includes/OrganizationCatalog.php ahora filtra áreas activas y respeta el servicio activo permitido. El editor ya filtraba catálogos inactivos preservando las relaciones existentes al abrir una ficha, por lo que no requirió cambios. includes/PmyoCatalog.php reconoce el nombre final REHABILITACIÓN para mantener los perfiles observados del padrón sin atribuir profesiones. La copia histórica del mapa sigue sin modificarse.

Se añadió docs/GUIA_AREAS_PERSONAL.md para capacitación: explica la separación confirmada de Medicina y Medicina General sin inventar una diferencia funcional no documentada, y distingue área, servicio, cargo, profesión y rol de acceso. Pruebas locales: 12 comprobaciones PMYO y 28 del modelo/editor; sintaxis PHP correcta. No se probó la ejecución T-SQL contra el servidor del ingeniero. Desplegar includes/OrganizationCatalog.php e includes/PmyoCatalog.php, ejecutar el script completo en BD_HOSPITAL_V3 y recargar conectado a SQL Server para actualizar catálogos. Si falta un destino, hay duplicados, códigos de servicio coincidentes o referencias adicionales, revisar el error; no deshabilitar restricciones para forzarlo.


## 23. Condición laboral Tercero (17/11/2026)

Se agregó Tercero (valor TERCERO) al editor de Personal y al filtro de Condición Laboral en pages/personal.php. Es un valor existente y permitido por CK_Personal_Condicion en el esquema V3 revisado; no requiere cambios SQL. El repositorio conserva TERCERO y los filtros de listado ya consultan CondicionLaboral por igualdad. El usuario debe confirmar y asignar esta condición en cada ficha; no se modificaron trabajadores en masa.

La condición TERCERO no se deduce de la ausencia de marcaciones ni de la vinculación biométrica; no cambia actividad, clasificación asistencial, régimen legal, área ni servicio. No se generaron BiostarUserID ni se cambiaron reglas de asistencia. Verificación local: sintaxis PHP del formulario y normalización TERCERO sin campos laborales o biométricos inferidos. Pendiente guardar/reabrir una ficha y filtrar Tercero en el servidor del ingeniero. Archivo de despliegue único de esta entrega: pages/personal.php.


## 24. Inventario de áreas, servicios, cargos y roles

Se preparó database/consultar_areas_servicios_cargos_roles_v3.sql, exclusivamente de lectura. Presenta entorno, departamentos, áreas y servicios activos/inactivos, cargos y conteos de asignaciones, distribución actual por área/servicio/cargo, profesiones/especialidades, denominaciones repetidas, áreas con nombres de puestos para revisión, relaciones inconsistentes, roles separados por sistema y cuentas sin registros de rol/vínculo RRHH. Las cuentas exclusivas de Laboratorio no deben habilitarse automáticamente por aparecer en ese resultado. Incluye conteos de fichas sin asignación, sin interpretarlos como bajas.

El detalle nominal se activa expresamente con @IncluirDetalleNominal=1 y puede limitarse con @AreaID; no consulta contraseñas ni documentos. Asignación actual sigue el criterio EsActual=1 AND Activo=1 del proyecto. Personas con asignación y personal activo son conteos diferentes; no se multiplican por el número de servicios del área. Se verificaron las tablas referenciadas contra el inventario V3 y la ausencia de instrucciones de escritura; no se ejecutó T-SQL en el servidor del ingeniero. No se cambiaron archivos de la aplicación ni datos.
