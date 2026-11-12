> Estado actualizado: consulte [CONCILIACION_V4.md](CONCILIACION_V4.md). Se cargaron fichas parciales y biometría verificable, se probaron endpoints y transacciones reales y se habilitó la configuración versionada usando la auditoría V4 existente. Los apartados anteriores conservan el contexto de su intervención original.

# Informe de adaptación a BD_HOSPITAL_V4

Actualización posterior: se recuperó el acceso de la cuenta administrativa existente en V4. Véase `docs/RECUPERACION_ADMIN_V4.md` para los registros insertados y las verificaciones reales; la ausencia de escrituras indicada abajo corresponde a la intervención inicial.

Fecha: 2026-11-03. Alcance real: un repositorio de Recursos Humanos. **No se ha completado la adaptación de los tres sistemas ni se certifica producción.** Clínica y Laboratorio no están disponibles en el árbol revisado; se solicitó su ubicación. El estado de Git contenía numerosos cambios anteriores; se conservaron. El inventario previo está en `docs/INVENTARIO_V4.md`.

## Recursos Humanos

Se preserva la arquitectura de PHP por endpoint, JavaScript por página y servicios PDO. Las fuentes de verdad SQL 01, plan 07 y diccionario CSV no fueron modificadas. No se instaló el esquema, no se migraron datos y no se modificaron registros de SQL Server.

### Archivos intervenidos en esta adaptación

| Archivos | Cambios |
|---|---|
| `config/database.php`, `.env.example`, `README.md` | Base predeterminada V4; credenciales únicamente por entorno; código de sistema configurable; documentación de alcance y pendientes. |
| `includes/SystemContext.php` (nuevo), `includes/auth.php` | Sistema por código real y único, habilitación UsuarioSistema y rol de ese sistema, vigencias nulas abiertas, sesiones ligadas a origen/sistema, revalidación, cuentas técnicas administrativas sin PersonalID. Se conserva la matriz de módulos/roles de la aplicación. |
| `includes/UserAdmin.php`, `api/administracion/usuarios.php`, `api/administracion/roles.php`, `pages/administracion.php`, `assets/js/pages/administracion.js` | Administración limitada al sistema. Cambio de rol preserva roles de otras aplicaciones. INSERT explícito de Activo y fechas técnicas porque V4 no tiene los defaults anteriores. Contraseña y bloqueo globales de cuentas compartidas se rechazan desde RR. HH. para evitar afectar otros sistemas. Selector permite cuenta técnica. |
| `includes/auditoria.php`, `api/administracion/auditoria.php` | Modulo textual y AuditoriaID reales; SistemaID resuelto desde catálogo, consulta limitada a ese sistema. Sin ID numérico supuesto. |
| `includes/PersonalRepository.php` (nuevo), `api/personal/crear.php`, `api/personal/editar.php` (nuevo) | Alta y edición transaccionales de Personal, con auditoría. Valores ausentes NULL; sin DNI, códigos, fechas laborales o contratos fabricados. Validaciones de catálogos, formato documental según catálogo, fechas, longitudes, especialidad/profesión y servicio/área. Contrato se registra con información proporcionada o se edita mediante ContratoID del trabajador. Asignaciones previas conservan historia y requieren nueva vigencia para reemplazarlas. |
| `api/personal/listar.php`, `api/personal/detalle.php`, `api/personal/catalogos.php`, `pages/personal.php`, `assets/js/pages/personal.js` | Sin persona_id ni fichas simuladas. Ficha desconocida: 404; fallo SQL: 503. Datos de sexo/profesión/contrato ausentes se muestran como no registrados. Catálogos incluyen departamentos y especialidades. Formulario admite fichas parciales; edición de contrato/asignación requiere selección explícita de su casilla. |
| `includes/BiostarCsvParser.php`, `database/sincronizar_personal_csv.php` | BiostarUserID conservado, incluidos ceros y caracteres especiales; ausencia de ID permanece NULL. Sin vínculos por DNI o interpretación de nombres. Solo VinculacionBiometrica aprobada. Carga transaccional serializada con sp_getapplock; deduplicación por hash completado y por evento, incluyendo archivos distintos. Conserva ráfagas válidas. Sin recuperación de ID por MAX. Sin truncar identificadores. Fechas CSV inválidas rechazan la importación. CLI solo aplica vínculos ya existentes, rechaza conflictos y no crea personal, contratos ni horarios. |
| `includes/ProgramacionStore.php`, `includes/AttendanceSchedule.php`, `api/horarios/asignar.php`, `api/horarios/listar.php`, `config/workload_rules.php` | Se elimina catalogo.Periodo, inexistente en V4. Pauta semanal real y rol mensual conservados; el mensual prevalece. Superposición de pautas y turno incompleto requieren conciliación. Se desactiva la pauta reemplazada de la misma fecha en vez de borrarla. Clasificación horaria explícita; no se deduce de cargo/área. Pautas sin clasificación confirmada no se guardan aplicando reglas supuestas. |
| `includes/SnapshotStore.php`, `includes/DataRevision.php`, `api/data_revision.php`, `includes/footer.php` | Snapshots nuevos separados por origen; archivos previos conservados. Caché de sesión también separada por sistema. V4 no contiene triggers de revisión: no se reutiliza una revisión estática para decidir que los datos no cambiaron. |
| `api/asistencia/diaria.php`, `api/asistencia/ausentes.php`, `api/dashboard/resumen.php`, `includes/attendance_evaluator.php` | Consultas sin multiplicación por contratos activos; retirada de generador de asistencia ficticia, horarios fijos supuestos y asignaciones de servicio por defecto. |
| `api/permisos/listar.php`, `api/permisos/guardar.php`, `api/permisos/dictaminar.php` | Sin permisos simulados, sustento generado ni goce supuesto. Se recupera sustento cuando realmente está contenido en Motivo. Alta y dictamen con auditoría dentro de transacción; no se presume aprobación si falta acción. |
| `api/reportes/consolidado.php`, `api/reportes/individual.php`, `api/reportes/seti.php`, `assets/js/pages/reportes.js` | No clasificar por texto de cargo ni convertir toda condición desconocida en CAS. Contratos representativos sin multiplicar trabajadores. Se retiran horas de sustitución de 6/8 horas para turnos desconocidos; SETI incompleto se marca pendiente. No se atribuye cumplimiento normativo sin clasificación. Condiciones sin confirmar tienen grupo separado en SETI. |
| `includes/SystemSettings.php`, `api/administracion/parametros.php` | V4 no tiene seguridad.ParametrosRRHH. Se conservan reglas de configuración y se deshabilita la edición persistente de parámetros; no se inventó una tabla. Limitación funcional pendiente de diseño. |
| `database/seeder_minsa.php`, `database/aplicar_sql_prioridad.php`, `database/aplicar_administracion.php` | Procesos históricos bloqueados antes de conectar cuando el destino configurado es V4; no ejecutar instaladores ni seeders V3 sobre V4. Los SQL históricos quedan preservados. |
| `tests/v4_regressions.php`, `tests/v4_auth.php`, `tests/v4_import.php`, `tests/V4ImportFixture.php`, `tests/v4_schema.py` (nuevos), `tests/auth_roles.php`, `tests/test_biometrico.php` | Nuevas pruebas aisladas; sesiones simuladas con origen y sistema. La prueba biométrica heredada exige habilitación explícita para escribir en V4. |

Las consultas con varios contratos activos seleccionan una fila representativa mediante fecha de inicio y ContratoID. No eliminan ni consolidan contratos históricos. La política institucional para contratos simultáneos sigue pendiente de validación.

La clasificación de carga horaria se obtiene de GrupoOcupacional con valores explícitos ASISTENCIAL/ADMINISTRATIVO; RegimenLaboral y CondicionLaboral se conservan separados. Es necesario confirmar institucionalmente esta convención antes de cargar clasificaciones. No se homologaron valores de origen ni se rellenaron fichas existentes. Los endpoints de reportes conservan el alias histórico `regimen_laboral` para la clasificación horaria; la ficha de Personal devuelve el régimen contractual real y `grupo_ocupacional` por separado.

### Pruebas ejecutadas

| Prueba | Resultado |
|---|---|
| `php tests/v4_regressions.php` | 22 comprobaciones: datos ausentes, fechas, referencias inexistentes, especialidad ajena, servicio ajeno, documentos por catálogo, IDs de origen, sesiones de otro sistema/base. SQLite solo en memoria para referencias. |
| `php tests/v4_auth.php` | 6 comprobaciones ejecutando los predicados de revalidación sobre SQLite en memoria: cuenta técnica, administrador de Clínica sin acceso RRHH, habilitación revocada/vencida, rol futuro y contraseña cambiada. |
| `php tests/v4_import.php` | 7 comprobaciones con PDO simulado: mismo archivo, distintos archivos con marcas iguales, ráfaga conservada, vínculos explícitos y origen sin alterar. |
| `python3 tests/v4_schema.py` | Tablas referenciadas e INSERT estáticos de 63 archivos contrastados con las 87 tablas del diccionario; cuatro FK de pacientes/profesionales de Laboratorio verificadas en el DDL. No certifica SQL dinámico ni ejecución SQL Server. |
| `php tests/auth_roles.php` | Roles de la aplicación y restricciones: correcto. |
| `php tests/rules.php` | 13 comprobaciones de reglas/persistencia y 3 de guardias/licencias: correcto. El mensaje de Inf/NaN es el rechazo esperado de un snapshot inválido. |
| `php tests/attendance_regressions.php` | 12 regresiones de jornadas/CITT/programación y 4 del conciliador con PDO simulado: correcto. |
| `php tests/security_regressions.php` | Origen de respaldos, aislamiento de bases, límite de acceso y auditoría durable: correcto. |
| `php tests/sql_priority.php` | 6 comprobaciones con PDO simulado: persistencia, rollback y conflictos. |
| `node tests/api-client.cjs` | 19 comprobaciones de caché, revisión y errores: correcto. |
| `php -l` | 89 archivos PHP de api/includes/config/pages/tests/database sin errores de sintaxis. |
| `node --check` | Todos los archivos JavaScript de assets/js sin errores de sintaxis. |
| `git diff --check` | Sin errores de formato. |
| Prueba biométrica heredada | Previsualización de 50 000 filas del CSV disponible completada. La importación SQL falló por conexión no disponible antes de guardar. Posteriormente se añadió habilitación explícita para escrituras; no se volvió a intentar importar contra el servidor. |

### Errores corregidos y pendientes

Corregidos: identidad ficticia al fallar una consulta, PersonaID como alias artificial, catálogos numéricos supuestos, vínculos biométricos por DNI, asignación de personal por inferencias, columnas de auditoría inexistentes, catalogo.Periodo inexistente, multiplicación de filas por contratos y uso de tablas de parámetros ausentes.

Pendientes de aceptación antes de producción:

- Instancia V4 de pruebas con credenciales configuradas (`DB_USER`, `DB_PASS`, `DB_NAME`) y `APP_SYSTEM_CODE` igual al código real de RR. HH.; usuarios/sistemas/roles reales con habilitaciones, sin sembrar cuentas supuestas.
- Ejecutar integración real de cada endpoint y SQL Server: tipos, bindings PDO_SQLSRV, OUTPUT/SCOPE_IDENTITY, rollback de auditoría, sp_getapplock y cargas simultáneas. Las pruebas locales no demuestran concurrencia real ni equivalencia de motores.
- Validar creación/edición de fichas, asignaciones y contratos desde navegador. Se comprobó sintaxis JavaScript, no navegación integral automatizada.
- Confirmar semántica de GrupoOcupacional y reglas institucionales, contratos simultáneos, vacaciones, créditos de CITT/permisos y reporte SETI/Excel/PDF con datos incompletos. Las exportaciones históricas necesitan aceptación y revisión adicional para las nuevas agrupaciones; no se certifican cifras de planilla.
- Diseñar almacenamiento versionado de parámetros con justificación previa. La edición persistente del panel no está habilitada con el esquema suministrado.
- Verificar catálogo real de Modulo/Permiso/RolPermiso: se conserva la matriz de autorización por roles de la arquitectura existente, ahora limitada al sistema. No se han supuesto códigos de permisos desconocidos ni se ha sustituido esa matriz por permisos granulares de catálogo.
- Conciliar identidades/documentos/biometría ya migradas, conteos, relaciones históricas y duplicados en una copia de prueba. Las fichas sin documento no se fusionan por nombre: requieren verificación humana cuando se sospeche repetición.
- Confirmar el tratamiento de fechas CSV con fracciones de segundo o formatos distintos de `YYYY-MM-DD HH:MM:SS`; se rechazan formatos no validados en vez de interpretarlos libremente.

## Clínica

No se modificaron archivos: repositorio no disponible. No se ejecutaron pruebas de sus modelos, controladores, formularios o autenticación. Contrato constatado en V4: clinico.Paciente contiene identidad propia, sin PersonaID; relaciones clínicas usan PacienteID. Pendientes: localizar proyecto, inventariar SQL existente, adaptar sus flujos y probarlos separadamente. No se añadieron módulos clínicos a RR. HH.

## Laboratorio

No se modificaron archivos: repositorio no disponible. No se ejecutaron pruebas de su aplicación. El DDL suministrado confirma Solicitud.PacienteID → clinico.Paciente y PersonalSolicitanteID, ProfesionalResponsableID, ProfesionalValidaID → rrhh.Personal. Las comprobaciones del DDL no prueban que una aplicación ausente respete esos vínculos. Pendientes: localizar proyecto, revisar solicitudes/muestras/resultados/reactivos/reportes/seguridad y ejecutar sus pruebas independientemente. No se añadieron módulos de laboratorio a RR. HH.
