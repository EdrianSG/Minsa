# Contexto actual del proyecto SIGH-MINSA

Actualizado el 12 de noviembre de 2026. Hospital Distrital de Pacasmayo, PMYO.

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
