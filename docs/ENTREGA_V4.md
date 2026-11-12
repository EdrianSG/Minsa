> Estado actualizado: consulte [CONCILIACION_V4.md](CONCILIACION_V4.md). Se cargaron fichas parciales y biometría verificable, se probaron endpoints y transacciones reales y se habilitó la configuración versionada usando la auditoría V4 existente. Los apartados anteriores conservan el contexto de su intervención original.

# Entrega de RR. HH. y estado de BD_HOSPITAL_V4

## Alcance establecido

Este repositorio contiene Recursos Humanos. Clínica y Laboratorio mantienen proyectos independientes y todavía deben localizarse para completar su adaptación. Los informes de detalle son `INVENTARIO_V4.md`, `INFORME_ADAPTACION_V4.md` y `RECUPERACION_ADMIN_V4.md`.

## Acceso a la aplicación

- Entrada: `login.php`; usuario administrativo existente: `admin`.
- Se conserva la contraseña de la cuenta administrativa de V3; no hay una contraseña nueva establecida en esta entrega.
- Sistema: `RRHH`; rol: `RRHH_ADMIN`; acceso explícito mediante UsuarioSistema y UsuarioRol vigentes.
- Cuenta técnica sin PersonalID; no necesita un trabajador ficticio.
- SQL Server: base `BD_HOSPITAL_V4`. Usuario y contraseña SQL se configuran por entorno o en `config/database.local.php`, siguiendo `config/database.local.example.php`. El archivo local no se publica.
- `.env.example` documenta variables; no se carga automáticamente. Las variables del proceso tienen prioridad sobre el archivo local.
- Desarrollo: `scripts/serve-dev.sh 127.0.0.1:8080`. La URL concreta de un servidor institucional depende de su despliegue.

## Código, esquema y documentación

Contenido apto para una entrega de código tras revisión: API, servicios PHP, páginas, JavaScript/CSS, plantillas de configuración sin claves, pruebas aisladas, esquema SQL V4, diccionario y plan de migración. Los scripts históricos se conservan con sus restricciones de ejecución; no son instaladores V4.

El esquema no contiene datos operativos. Los instaladores no deben ejecutarse sobre una V4 poblada. `database/recuperar_admin_v4.php` es una herramienta de recuperación para seguridad vacía, no un seeder ni una vía de acceso sin contraseña. Su modo `--verificar` comprueba la cuenta recuperada sin modificar datos.

## Datos que requieren una entrega privada

| Contenido local | Motivo |
|---|---|
| `config/database.local.php`, `.env` | Credenciales de conexión. |
| `BD_HOSPITAL_V4/02_BD_HOSPITAL_V4_CARGAR_DATOS_REALES_EXCEL.sql` | Datos personales reales de los padrones. |
| `storage/snapshots/` | Copias de identidad, asistencia, horarios y programación. Algunos archivos ya pertenecen al historial Git; ignorarlos no elimina su historial. |
| `storage/audit/` y respaldos SQL Server | Registros operativos y de seguridad; un respaldo puede contener hashes de contraseñas y datos sensibles. |

GitHub `EdrianSG/Minsa` es público. Estas fuentes no deben agregarse a una entrega pública. Si ya existen en el historial remoto, retirar archivos de una revisión nueva no elimina las revisiones anteriores; la limpieza del historial exige una intervención separada, coordinada con el propietario.

## Datos cargados y pendientes

Se comprobó y recuperó en V4 la cuenta administrativa real: sistema, rol, usuario, vínculos y auditoría. Ese procedimiento no migró las tablas operativas de personal, biometría, contratos, pacientes o laboratorio. La presencia de snapshots y SQL de carga en el disco no prueba que todos esos datos estén cargados o conciliados en V4.

Una carga de datos reales debe distinguir:

1. Padrones de origen del SQL 02: preservan los valores del Excel, sin crear automáticamente fichas laborales válidas.
2. Importación opcional de personal del SQL 03: primero simulación y revisión de colisiones; no asumir DNI a partir de BioStar.
3. Datos operativos de V3 y otras bases: inventario, mapeo por PK y validación de conteos/relaciones antes de insertar en V4. No sobrescribir las bases originales.
4. Copia de seguridad: preservar origen y destino y documentar resultados; no sustituye la conciliación.

## Verificación disponible

Pruebas V4: `tests/v4_regressions.php`, `tests/v4_auth.php`, `tests/v4_import.php`, `tests/v4_schema.py`, `tests/login_config.php`. Las pruebas usan SQLite en memoria o PDO simulado cuando corresponde; no equivalen a validación SQL Server de todos los módulos.

Se verificó en SQL Server la recuperación de `admin`, igualdad del hash con V3 y permiso de administración. Siguen pendientes la aceptación de reportes/exportaciones, la clasificación horaria institucional y la edición persistente de parámetros, cuya tabla no existe en V4.

## Estado de publicación

La revisión del destino identificó un repositorio público y permisos de lectura para la conexión GitHub disponible. Esta guía y los archivos preparados localmente no implican que se haya realizado un commit, un push, una entrega privada o una carga de datos adicional en SQL Server. El destino de la entrega debe quedar definido antes de transferir información sensible.
