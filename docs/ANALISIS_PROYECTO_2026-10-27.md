# Análisis del proyecto — 27 de octubre de 2026

## Alcance y resultado

Revisión del código PHP y JavaScript, configuración, permisos, persistencia, respaldos, importación biométrica, reportes y pruebas disponibles. Se probaron 24 rutas de lectura contra BD_HOSPITAL_V3. Las consultas se ejecutaron dentro de transacciones revertidas y con snapshots temporales. No se ejecutaron migraciones, importaciones reales ni cambios funcionales durante esta auditoría.

La aplicación conecta a la base nueva y los listados principales responden. Esto no equivale a una validación completa del sistema: la base consultada tiene un solo trabajador activo y un solo rol asignado; faltan escenarios reales de concurrencia, múltiples contratos, guardias e importaciones completas. No se verificó la interfaz en un navegador ni la configuración del servidor que utiliza el usuario.

## Hallazgos de prioridad alta

### A1. La sincronización general de respaldos sigue usando columnas del esquema anterior

- Evidencia: `includes/SnapshotStore.php:161` y `:256` consultan `p.CorreoElectronico`, inexistente en V3; la columna real es `Correo`.
- Reproducción real de `syncAllFromDatabase()` con directorio temporal: `personal_error = Invalid column name 'CorreoElectronico'`. La rama de asistencia no alcanzó esa consulta porque no había fechas de asistencia en esta base; el mismo defecto permanece en su SQL.
- Impacto: las operaciones pueden funcionar en línea, pero el respaldo de personal no se refresca; ante una caída puede quedar información anterior. La sincronización captura el error y continúa.
- Acción: corregir ambas consultas y comprobar el resultado de cada respaldo. Añadir una prueba del sincronizador con el esquema V3 y datos de asistencia.

### A2. Asistencia y Administración insertan nombres dentro de JavaScript sin escape adecuado

- Evidencia: `assets/js/pages/asistencia.js:399` y `:585`, y `assets/js/pages/administracion.js:229`, construyen atributos `onclick` con nombres/usuarios entre comillas simples. Sus funciones `escapeHtml` no escapan apóstrofes.
- Impacto: un apóstrofe puede romper el botón; un valor especialmente construido puede ejecutar JavaScript al pulsarlo. Es un riesgo de XSS almacenado si ese dato llega desde un registro editable o una carga.
- Acción: eliminar handlers inline; crear botones y asociar listeners con `addEventListener`, pasando datos como valores. Escapar HTML por sí solo no resuelve el contexto JavaScript.
- Validación pendiente: recorrido completo de entrada del dato hasta el clic en navegador; no se inyectaron valores en la base.

### A3. La revalidación SQL de la sesión no es un control obligatorio de cada API

- Evidencia: `includes/api_bootstrap.php:14` autoriza con el usuario y rol guardados en sesión. La comprobación de cuenta activa, bloqueo y rol SQL reside en `api/data_revision.php`.
- Impacto: un cliente que omita esa consulta puede seguir haciendo solicitudes directas con una sesión anterior después de bloquear la cuenta o retirar su rol. El navegador habitual consulta la revisión, pero eso no constituye un control del servidor para todas las rutas.
- Acción: centralizar la revalidación de cuenta, roles y servicio en el guard de API, con una política explícita para lecturas de contingencia. Las escrituras deben validar permisos vigentes en el servidor.

### A4. La bitácora puede mostrar eventos inventados como institucionales

- Evidencia: `api/administracion/auditoria.php:98` genera eventos de ejemplo si la consulta no devuelve eventos, incluso con filtros sin coincidencias. `includes/auditoria.php:70` usa un buffer de sesión con nombre fijo de usuario, limitado a 100 eventos, y devuelve éxito.
- Impacto: un resultado vacío puede convertirse en una bitácora ficticia. Los fallos de persistencia no se distinguen de una auditoría durable; al perder la sesión se pierde el buffer.
- Acción: devolver una lista vacía para consultas sin resultados; eliminar ejemplos. Registrar errores de auditoría y persistir eventos con identidad real, además de definir la relación transaccional con las operaciones que deben auditarse.

### A5. Los parámetros dicen guardarse, pero no cambian las reglas del sistema

- Evidencia: `api/administracion/parametros.php:22` y `:59` guardan únicamente en `$_SESSION['sistema_parametros']`. `includes/AttendanceRules.php` mantiene los umbrales 5/30 y `config/workload_rules.php` mantiene la meta de 150 horas por separado.
- Impacto: dos sesiones pueden ver configuraciones diferentes. Cambiar la tolerancia o la meta en Administración no modifica los cálculos, aunque se confirme el guardado.
- Acción: persistir parámetros versionados en SQL y hacer que los evaluadores consuman esa fuente. Definir vigencia histórica para no recalcular meses cerrados con reglas nuevas.

### A6. El aislamiento de respaldos por base de datos es incompleto

- Evidencia: `includes/SnapshotStore.php:35` y `:99` validan `database_source` únicamente para `asistencia_diaria_*` y `ausentes_*`. Personal, turnos, permisos, horarios y programación comparten directorio y claves sin esa validación.
- Impacto: después de cambiar DB_HOST o DB_NAME, una caída puede mostrar respaldos de la base anterior en otros módulos. La separación de caché del navegador ya implementada no aísla todos los archivos del servidor.
- Acción: separar todos los snapshots por identidad de base, o exigir procedencia en todos los tipos. Definir cómo retirar o regenerar respaldos antiguos sin borrar evidencia necesaria.

## Hallazgos de prioridad media

### M1. Gestión de cuentas y catálogo de roles incompletos

- `api/administracion/usuarios.php:217` responde 501 a todos los POST: crear, bloquear, cambiar rol y resetear contraseña no están implementados en SQL. Hay código simulado inalcanzable después de ese rechazo y fallback de usuarios ficticios en GET.
- `api/administracion/roles.php` devuelve cuatro roles y cantidades fijas; la base inspeccionada contiene el rol `RRHH_ADMIN`.
- Acción: usar catálogo real y operaciones transaccionales. Mientras tanto, deshabilitar acciones pendientes en la interfaz e identificar su disponibilidad.

### M2. Exportación del rol tiene nombres de servicios y periodos fijos

- `api/reportes/exportar_excel.php:104` traduce IDs a nombres mediante un arreglo fijo. La categoría también se deduce de IDs concretos.
- El mes predeterminado sigue siendo septiembre y el año 2026; el año se recorta a 2035 antes de la validación posterior, que admite hasta 2100.
- Impacto: con IDs diferentes en la base nueva, el archivo puede identificar mal el servicio o su categoría; un parámetro inválido se convierte silenciosamente en otro periodo.
- Acción: obtener nombres y categoría desde SQL; usar fecha actual como valor predeterminado y rechazar periodos fuera de rango de forma consistente.

### M3. Reconciliación automática parcial y con escrituras desde GET

- `api/asistencia/diaria.php:47` y `api/dashboard/resumen.php:46` solo concilian automáticamente si el total de asistencias de la fecha es cero.
- Impacto: si ya existe una asistencia pero faltan otras, esa condición evita completar la fecha por esta vía. Además, consultar una vista puede ejecutar escrituras de conciliación bajo permisos de lectura.
- Acción: separar consulta de conciliación y establecer un flujo de actualización explícito, idempotente y con autorización propia; probar fechas parcialmente procesadas.

### M4. Estado Offline reúne causas distintas

- `assets/js/app.js` convierte cualquier excepción de `API.checkRevision()` en Offline. `_emergency` en `assets/js/api.js` cambia el indicador global al servir un respaldo de un endpoint.
- Impacto: una sesión vencida, un permiso denegado o una consulta defectuosa pueden parecer una caída de SQL. Un módulo secundario puede cambiar el indicador de toda la aplicación.
- Acción: distinguir estado SQL, sesión, permisos y disponibilidad de cada consulta. Mostrar el mensaje del error y la antigüedad/procedencia del respaldo.

### M5. El resumen antiguo conserva métricas biométricas de ejemplo y clasificación incorrecta de pendientes

- `api/dashboard/resumen.php` inicia biométrico como ONLINE, cuatro dispositivos y hora actual, aunque no existan marcaciones. Además, su clasificación termina contando como presente cualquier estado no contemplado, incluidos estados pendientes o descanso.
- La pantalla actual de Inicio consume `asistencia/diaria.php`; este hallazgo afecta al endpoint de resumen y a cualquier consumidor que lo use.
- Acción: retirar valores de ejemplo y compartir el cálculo de estadísticas con el endpoint diario; representar estados desconocidos o sin medición explícitamente.

### M6. Configuración y despliegue necesitan separación de desarrollo y operación

- `config/database.php` conserva credenciales de prueba dentro del código y desactiva cifrado. `.env.example` no se carga automáticamente, como el propio archivo advierte. Las claves se mantuvieron previamente por una preferencia de desarrollo, por lo que su presencia no es un cambio accidental de esta auditoría.
- Las protecciones de `storage`, `database`, `config`, `includes` y `tests` dependen de `.htaccess`. Los scripts `database/seeder_minsa.php` y `database/sincronizar_personal_csv.php` no tienen la restricción CLI explícita que sí tiene `aplicar_sql_prioridad.php`.
- Impacto condicionado al despliegue: el servidor embebido de PHP o Nginx no aplica `.htaccess`; si se publica toda la raíz, pueden exponerse snapshots o ejecutarse scripts internos. No se probó exposición HTTP en el servidor del usuario.
- Acción: documentar y validar el servidor concreto; ubicar respaldos fuera de la raíz web, restringir scripts a CLI y usar configuración de credenciales propia del entorno operativo.

### M7. Endurecimiento de autenticación pendiente

- `Auth::login` no implementa limitación de intentos y el campo `login_time` no se usa para imponer una duración máxima o inactividad.
- La consulta une todos los roles activos, pero consume una sola fila sin orden. Si se asignan varios roles, el rol efectivo no tiene una selección determinista ni una combinación explícita de permisos.
- Acción: definir política de sesión, intentos y múltiples roles, manteniendo los permisos del lado del servidor. La base actual de un solo rol no ejercita el último caso.

## Pruebas y deuda de verificación

- Sintaxis: 77 archivos PHP y 11 JavaScript revisados, sin errores de sintaxis.
- Pasan: `tests/auth_roles.php`; reglas y persistencia (13 + 3 comprobaciones); prioridad SQL simulada (6); regresiones de jornadas y conciliación (12 + 4); cliente de caché (19).
- `python3 tests/integration.py` falla en la prueba de snapshot por servicio: su fixture no incluye `database_source`, obligatorio desde la protección reciente contra respaldos de otra base. Este fallo fue introducido por el cambio de contrato anterior y debe corregirse en la prueba conservando la protección.
- Se ejecutó una copia temporal de esa prueba adaptando únicamente la procedencia de los fixtures: pasan sus 90 comprobaciones. El archivo original no se modificó; sigue fallando hasta actualizarlo.
- `tests/test_biometrico.php` previsualiza 50.000 filas, pero la fase de importación no terminó en el entorno aislado porque requiere SQL real. `tests/test_conciliacion.php` tampoco pudo ejecutarse allí por falta de conexión. No se repitieron sobre la base del hospital, porque esos scripts escriben datos.
- Auditoría SQL: 24 rutas probadas. Las rutas de detalle con parámetro incorrecto, incidencias sin ID y validación con GET devolvieron 400/405 según correspondía; esos resultados no se clasificaron como defectos. Los endpoints principales de lectura respondieron con JSON válido.
- Sin pruebas completas de navegador, importación persistente, concurrencia real, exportaciones abiertas en Excel ni cuentas operativas de varios servicios. Los resultados actuales no certifican esos flujos.

## Orden recomendado de trabajo

1. Corregir XSS y revalidación de permisos de servidor.
2. Corregir sincronizador e identidad de todos los respaldos; actualizar los fixtures y dejar la suite original en verde.
3. Eliminar datos ficticios de auditoría y administración; hacer durable la bitácora.
4. Persistir y aplicar parámetros; implementar gestión real de cuentas y roles.
5. Corregir metadatos de exportación, conciliación parcial e indicadores de estado.
6. Validar despliegue y ejecutar pruebas con una base de ensayo representativa y navegador.

Este documento registra hallazgos y recomendaciones. No aplica cambios funcionales ni interpreta o certifica normativa laboral.

## Correcciones aplicadas posteriormente

Con autorización del usuario se corrigieron los hallazgos operativos de esta revisión:

- Consultas V3 del sincronizador; uso del catálogo común de turnos y respeto de horas explícitas iguales a cero; errores de escritura de respaldo informados.
- Procedencia obligatoria en todos los snapshots y nueva versión de la caché del navegador.
- Revalidación de cuenta, contraseña, vigencia del rol y servicio en todas las API; límite de intentos y duración de sesión. Las sesiones antiguas requieren iniciar sesión nuevamente.
- Eventos de botones con datos separados de JavaScript en Asistencia, Administración y Ausencias; escape adicional de documentos y resultados de validación.
- Auditoría sin ejemplos ni usuarios inventados; fallo SQL registrado y respaldo durable en archivo identificado como pendiente, sin declarar éxito SQL. La consolidación automática de esos archivos en SQL queda fuera de esta corrección.
- Creación de cuentas, bloqueo, rol y restablecimiento de contraseña transaccionales; protección contra autobloqueo y eliminación del último administrador. Las claves generadas se muestran únicamente al administrador que ejecuta la operación; no se ha implementado un flujo obligatorio de cambio de clave en primer acceso.
- Catálogo real de roles y cantidades, y selector de trabajadores paginado sin cuentas ya registradas.
- Historial SQL de parámetros instalado mediante migración aditiva. Tolerancia y tardanza se aplican por fecha; plazo de regularización y obligación de CITT se aplican en sus operaciones. Los cambios rigen desde el día siguiente. Meta de 150 horas y modo de importación quedan fijos y no se presentan como editables.
- Lecturas de asistencia sin conciliaciones con escrituras implícitas. La conciliación tiene POST propio y transacción; puede repetirse para completar fechas parciales. Se retiraron métricas biométricas ficticias del resumen.
- Reportes con servicio tomado de SQL, periodo validado y fecha actual predeterminada. El consolidado sin programación no inventa 150/176 horas. CSV protege celdas de fórmulas.
- Indicadores distinguen sesión vencida, acceso denegado, error de consulta y SQL offline.
- Scripts de mantenimiento restringidos a CLI. Router de desarrollo probado por HTTP para bloquear archivos internos. Opciones TLS configurables; credenciales de desarrollo conservadas, sin cambiarlas en el servidor.
- Migración previa de control de revisión compatible con `ID` y `VersionDatosID`.

Las pruebas originales de integración vuelven a pasar sin copias temporales. Se añadieron regresiones de seguridad y una prueba de administración contra SQL con rollback completo. Consulte `docs/CORRECCIONES_2026-10-27.md` para ejecución y límites pendientes.

La prueba de programación SQL detectó además la ausencia de triggers de revisión en la base nueva. Se instaló la migración compatible con V3 y se verificó el incremento de revisión, el guardado físico y los conflictos, con rollback completo. Al cierre, faltan cargar servicios y asignaciones institucionales reales (ambos catálogos operativos están vacíos).
