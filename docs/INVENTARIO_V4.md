# Inventario previo de incompatibilidades V4

Fecha: 2026-11-03. Fuentes examinadas: estructura SQL 01, plan 07, diccionario CSV y código PHP/JavaScript/SQL del repositorio disponible. El árbol ya contiene cambios locales anteriores; no se revierten ni se atribuyen a esta adaptación.

| Sistema | Disponibilidad | Resultado de inspección |
|---|---|---|
| RR. HH. | Repositorio actual (`main`) | Aplicación PHP sin framework, API por archivos, JavaScript por página, PDO SQL Server, snapshots. |
| Clínica | No se encontró su repositorio | Solo se conoce el contrato V4: identidad en `clinico.Paciente`; no es posible inventariar consultas, controladores o formularios ausentes. |
| Laboratorio | No se encontró su repositorio | Solo se conoce el contrato V4: Solicitud.PacienteID → clinico.Paciente; PersonalSolicitanteID y profesionales de Resultado → rrhh.Personal. Pendiente código de la aplicación. |

## RR. HH.: hallazgos anteriores a los cambios

| Archivos / flujo | Incompatibilidad o riesgo |
|---|---|
| config/database.php, .env.example | Base predeterminada V3; requiere aislamiento de sesiones y snapshots al cambiar de origen. |
| includes/auth.php | JOIN obligatorio a Personal impide cuentas técnicas. UsuarioRol/Rol sin Sistema ni UsuarioSistema permite heredar permisos de otras aplicaciones. FechaInicio nullable se considera inválida. Sesiones sin origen. |
| includes/UserAdmin.php, api/administracion/usuarios.php, roles.php | Roles globales, modificación de roles de otros sistemas, cuenta obligatoriamente ligada a trabajador, INSERT depende de defaults que V4 no define. |
| includes/auditoria.php, api/administracion/auditoria.php | ModuloID y EventoAuditoriaID no existen en V4: son Modulo y AuditoriaID. SistemaID=1 no es un mapeo autorizado. |
| api/personal/crear.php | Fecha de ingreso actual, DNI tipo 1, contrato tipo 1, número de contrato fabricado, sede arbitraria y régimen por ID de catálogo. No valida especialidad/profesión ni jerarquía de servicio/área. |
| api/personal/detalle.php | Ficha inexistente o error SQL genera identidad, DNI, contactos, contratos y fechas de ejemplo. Reinterpreta régimen como administrativo. |
| api/personal/listar.php, includes/SnapshotStore.php | Alias persona_id innecesario; CAS/General como sustitutos de información faltante; JOIN a varios contratos multiplica trabajadores; clasifica por texto de profesión/cargo. |
| pages/personal.php, assets/js/pages/personal.js | Documento, cargo, área, contrato y fecha obligatorios; fecha de hoy automática; sexo, fechas de contrato y horas inventados al visualizar faltantes. No existe endpoint de edición de ficha. |
| includes/BiostarCsvParser.php | Vinculación automática por igualdad de DNI con BioStar. Normalización del identificador altera el valor de origen. Recuperación de ID por MAX insegura. V4 no impone unicidad de hash ni de marcación; la importación debe serializarse en la aplicación. |
| database/sincronizar_personal_csv.php | Crea trabajadores/asignaciones/contratos/horarios desde marcas e inferencias. Usa AsignacionID, ausente en V4. No debe ejecutarse ese proceso en V4. |
| includes/SystemSettings.php | seguridad.ParametrosRRHH no existe en V4. No se creará una tabla sin justificación previa; conservar reglas confirmadas en configuración y explicitar indisponibilidad de edición persistente. |
| includes/DataRevision.php | Fallback a columna ID ausente; V4 usa VersionDatosID y no instala triggers ni fila de revisión. La caché requiere tratar ausencia de revisión como sin caché. |
| Horarios, asistencia, permisos y reportes | Consultas mayormente adaptadas previamente a V3; contratos múltiples duplican filas, existen sustituciones CAS/ASISTENCIAL y horarios fijos que requieren retirada. Clasificación horaria por texto pendiente de confirmación institucional. |
| database/*.sql y scripts históricos | Migraciones/seeders V3 no son instaladores V4; deben quedar preservados como históricos, sin ejecución sobre V4. |

## Validación requerida

Pruebas locales sin acceso a datos reales: datos incompletos, validación de jerarquía y especialidad, aislamiento de sistema/origen, IDs biométricos conservados, repetición de importación y marcaciones. Pruebas de SQL Server, concurrencia real, conteos origen/destino y validación institucional de reglas requieren instancia V4 de pruebas. No se ejecutará migración de datos ni instalación del esquema como parte de los cambios de aplicación.
