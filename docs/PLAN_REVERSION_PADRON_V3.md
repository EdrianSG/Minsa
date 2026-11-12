# Plan de reversión de una eventual carga de personal en V3

**Estado: plan preparado; no ejecutado. No hay un lote cargado, un manifiesto de altas ni un backup restaurable creado por esta tarea. No se autoriza ni se programa un borrado automático.**

El alcance es revertir exclusivamente un futuro lote aprobado del archivo `padron_personal_con_turnos.txt`. No significa vaciar RR. HH. ni devolver toda la base hospitalaria a un estado anterior. La base actual contiene datos clínicos y de otros sistemas; una restauración completa sobre producción podría destruir cambios ajenos.

## Protección del estado actual

El estado inspeccionado tiene un trabajador en Personal, una asignación, un período mensual, 25 detalles de programación, una cuenta y referencias al trabajador desde 140 resultados y 85 solicitudes de Laboratorio. Ese trabajador y sus relaciones están fuera de cualquier borrado del padrón. Staging tiene 453 registros y no se eliminará ni sobrescribirá. Los catálogos existentes tampoco son propiedad del futuro lote.

El plan no presupone que las tablas vacías seguirán vacías. Antes de ejecutar una carga o reversión se debe obtener otro inventario real de FK y referencias, incluidas Clínica, Laboratorio, seguridad y cualquier nueva tabla.

## Preparación obligatoria antes de cargar

1. Resolver los conflictos del [informe previo](REVISION_PADRON_PERSONAL_V3.md), aprobar el alcance y cerrar temporalmente las escrituras sobre los registros afectados para capturar una base consistente. No detener los otros sistemas sin coordinación.
2. Obtener con el administrador de SQL Server un backup restaurable, en un destino aprobado fuera de la raíz web, y comprobar una restauración en una instancia aislada. `RESTORE VERIFYONLY` por sí solo no sustituye esa prueba. El JSON de inspección no reemplaza este backup.
3. Identificar el lote mediante UUID, SHA-256 exacto del archivo, servidor/base, fecha, operador autorizado y hash del esquema y de los mapas aprobados. El hash del archivo por sí solo no permite distinguir dos intentos de carga.
4. Generar un **manifiesto privado externo** al esquema hospitalario; no crear tablas nuevas sin justificación. Conservar copia íntegra, cifrada y con acceso restringido antes y después de cada fila que se modificará, incluidos NULL y claves compuestas. No depender de buscar por nombre, por DNI, por fecha de carga, por rango de ID o por `PersonalID > último_id` para deshacer.
5. Clasificar cada operación como `REUTILIZADO_SIN_CAMBIOS`, `ACTUALIZADO` o `INSERTADO`. Para actualizaciones guardar imagen anterior y posterior; para inserciones guardar la PK exacta devuelta por SQL y la imagen posterior. Registrar también las filas históricas que se cierren y su estado anterior.
6. En una eventual implementación transaccional, guardar en el manifiesto cada operación antes de confirmar el COMMIT, conservar una marca de preparación y reconciliar el resultado si se pierde la respuesta. Nunca reejecutar un lote porque hubo timeout sin comprobar si se confirmó. Verificar unicidad bajo concurrencia y no alterar identidades existentes.
7. Probar en una copia aislada: carga, repetición idempotente, fallo a mitad de carga, ROLLBACK, reversión posterior al COMMIT y conflicto por modificación de otro usuario. Comprobar las referencias cruzadas y que los registros anteriores permanecen idénticos.

Claves que el manifiesto debe conservar según el esquema inspeccionado:

| Tabla | Clave de identificación |
|---|---|
| `rrhh.Personal` | `PersonalID` |
| `rrhh.AsignacionPersonal` | `AsignacionPersonalID` |
| `rrhh.Horario` | `HorarioID` |
| `rrhh.ProgramacionMensualDetalle` | `Anio, Mes, PersonalID, Dia` |
| `rrhh.ProgramacionMensual` | `Anio, Mes`, más imagen/Version anterior y posterior |
| `rrhh.Turno` (solo si se autorizan altas de catálogo) | `TurnoID` y código único |
| `rrhh.Cargo`, `rrhh.Profesion`, `rrhh.Especialidad` | PK respectiva, solo si realmente se autorizan altas |
| `organizacion.Area`, `organizacion.Departamento`, `organizacion.Servicio` | PK respectiva, solo altas expresamente autorizadas |

Los registros reutilizados sin cambio no se borran ni restauran. Los contadores IDENTITY no se reinician; los huecos en IDs son aceptables. Las imágenes anteriores deben permitir restituir códigos y datos reales, no regenerarlos con reglas aproximadas.

## Detección de emergencia

Antes del COMMIT, si cualquier validación falla, la implementación futura debe ejecutar ROLLBACK de toda la transacción del lote. Eso no equivale a borrar datos históricos. Esta tarea no ha iniciado esa transacción.

Después del COMMIT, pausar nuevas operaciones sobre el lote, conservar evidencia y elaborar una **previsualización de reversión**. Debe listar cada PK, acción prevista, imagen esperada actual, referencias y conteos por tabla. La eliminación requiere autorización posterior y específica sobre esa previsualización; la solicitud actual solo prepara el plan.

Bloquear la reversión automática si:

- Falta el manifiesto, el backup verificado o la coincidencia de servidor/base/esquema.
- Una fila cambió desde la carga: comparar contra su imagen posterior, con tratamiento exacto de NULL y todas las columnas relevantes. V3 no aporta una columna rowversion de Personal que permita asumir ausencia de cambios.
- Hay nuevas referencias fuera del lote: diagnósticos, solicitantes, responsables/validadores, usuarios, marcaciones, contratos, permisos, asistencia u otras tablas descubiertas por FK.
- Un período mensual compartido tiene cambios ajenos o una versión distinta. No restaurar una cabecera que pertenece a toda la institución para deshacer unas filas de un trabajador.

Un trabajador que ya fue usado por Clínica/Laboratorio o seguridad se conserva. No borrar sus filas dependientes clínicas para desbloquear una FK. Evaluar una corrección o desactivación explícita con revisión humana, conservando evidencia y relaciones.

## Orden de una reversión posterior expresamente aprobada

Siempre dentro de una transacción acotada, con validación de imágenes y bloqueos adecuados para que no se creen referencias entre la comprobación y la operación:

1. Revertir únicamente cambios de programación y horarios **del manifiesto**. Restaurar las filas preexistentes actualizadas; retirar solamente las nuevas, por sus claves exactas. Preservar los 25 detalles existentes y cualquier cambio ajeno.
2. Revertir asignaciones creadas por el lote y restaurar los estados/fechas anteriores de las que se hubieran modificado. No borrar la asignación anterior existente.
3. Restituir los datos de Personal que el lote haya actualizado, solo si la fila todavía coincide con su imagen posterior. No sobrescribir una edición posterior de otro usuario.
4. Retirar únicamente las altas de Personal propiedad del lote que no tengan ninguna referencia externa y coincidan con su imagen de carga. El trabajador previo y la cuenta administrativa quedan excluidos de la selección por diseño.
5. Cabeceras mensuales: una anterior al lote no se elimina. Una creada por el lote solo se podría retirar si no quedan detalles de ningún trabajador y la versión/imagen permiten hacerlo sin descartar cambios ajenos. Mantener contadores de versión monotónicos; no reducir `seguridad.VersionDatos` ni una versión de programación para aparentar un estado anterior. Una restauración funcional de contenido debe emitir una versión nueva.
6. Catálogos: no tocar registros preexistentes. Una alta expresamente incluida en el lote solo se podría retirar tras comprobar **todas** sus referencias actuales y después de sus dependientes del lote, en orden topológico de las FK reales. Conservarla si fue utilizada fuera del lote; no crear cascadas manuales para forzar su borrado.
7. Revalidar integridad, claves únicas, asignaciones actuales, programación y referencias de Clínica/Laboratorio/seguridad. Comparar el estado previo esperado de los registros protegidos. Si algo no coincide, ROLLBACK y revisión; no continuar parcialmente.
8. Confirmar la transacción solo si todas las comprobaciones pasan; registrar la reversión y sus conteos en la auditoría existente. Conservar los eventos originales y el manifiesto. Invalidar solo los respaldos/cachés de los datos afectados de V3, sin borrar evidencia ni fuentes.

No se generó SQL destructivo listo para ejecutar porque aún no existen las PK de un lote aprobado. Un DELETE genérico basado en el archivo sería inseguro y no constituye una reversión.

## Biométrico y otras fuentes

Esta fuente no aporta BiostarUserID ni eventos biométricos: **la futura carga de este padrón no debe insertar ni borrar marcaciones, importaciones, incidencias o vínculos**. Debe preservar esas tablas aunque actualmente estén vacías. Cualquier importación biométrica posterior requiere un lote y plan separado, con identificadores explícitos; nunca reconstruir vínculos suponiendo DNI = BiostarUserID.

No se permite TRUNCATE, borrar todos los trabajadores, reseed de IDENTITY, deshabilitar FK/CHECK/triggers, eliminar el staging, eliminar auditoría, vaciar pacientes, ni restaurar toda la base sobre producción para revertir solo este padrón. Si se necesita recuperación completa del servidor, se trata como incidente independiente coordinado con todos los sistemas.
