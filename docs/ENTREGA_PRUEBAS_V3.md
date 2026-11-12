# Preparación de V3 en el servidor de pruebas

## Alcance y estado

Servidor de pruebas: `192.168.11.38:1433`, base `BD_HOSPITAL_V3`, SQL Server 2022 Express, compatibilidad 160, collation `Modern_Spanish_CI_AS`.

El ingeniero tiene la versión actual del proyecto. Primero se valida y corrige la base de pruebas; la transferencia al servidor del ingeniero se hará después. Este trabajo no modifica el código de ejecución de la aplicación instalada, no carga el padrón ni importa marcaciones y no elimina datos hospitalarios. Los cambios de archivos de esta etapa son utilidades CLI, pruebas y documentación.

**La carga sigue bloqueada por datos de identidad/laborales faltantes y mapas pendientes. El respaldo no tiene aún una restauración de prueba. El paquete es de preparación, no una entrega final aprobada.**

## Respaldo del estado anterior a las correcciones

Se ejecutaron respaldos nativos `BACKUP DATABASE ... WITH COPY_ONLY, CHECKSUM`, con nombres nuevos en el servidor Windows:

- Antes de adaptar auditoría: `BD_HOSPITAL_V3_SIN_CARGAR_PADRON_20261109T155850Z_b5a0c48a.bak`.
- Después de la adaptación: `BD_HOSPITAL_V3_SIN_CARGAR_PADRON_20261109T163517Z_85689f5a.bak`.

Directorio en el servidor: `C:\Program Files\Microsoft SQL Server\MSSQL16.SQLEXPRESS\MSSQL\Backup`.

Incluyen la estructura y los datos de todos los módulos de la base en cada momento, no solo RR. HH. No incluyen los nuevos 450 trabajadores ni las 41.382 marcaciones del CSV porque no se cargaron. Ninguno de los .bak se ha copiado a este equipo ni al servidor del ingeniero. Los manifiestos privados indican `archivo_local=null` y `restauracion_probada=false`.

`RESTORE VERIFYONLY ... WITH CHECKSUM` fue rechazado por falta de permiso `CREATE DATABASE` en master. La cuenta actual tiene db_owner y BACKUP DATABASE en V3, pero no CREATE ANY DATABASE. No se elevaron permisos ni se habilitaron mecanismos del sistema operativo para eludir esta restricción. Un administrador debe verificar el archivo y restaurarlo en una base aislada, con otro nombre y sin REPLACE. VERIFYONLY tampoco sustituye una restauración de prueba.

Cuando termine una futura carga aprobada, deberá generarse otro backup del estado final y volver a verificarse. Los anteriores se conservan como puntos de recuperación.

## Corrección de auditoría aplicada solo en la base de pruebas

La primera comprobación obtuvo 22 lecturas correctas y un fallo 503 en `api/administracion/auditoria.php`: el proyecto actual espera `AuditoriaID` y `Modulo`, mientras V3 conservaba `EventoAuditoriaID` y `ModuloID`.

Sin cambiar el endpoint ni el proyecto instalado, se añadieron dos columnas:

- `AuditoriaID AS (EventoAuditoriaID)`: alias calculado, no nueva clave, PK ni identificador independiente.
- `Modulo nvarchar(100) NULL`: campo compatible con el escritor actual. Se derivó para los 51 eventos existentes a partir de su catálogo o prefijo histórico; no se inventaron módulos.

Primero se probó la transacción con ROLLBACK y se comprobó que las columnas volvían al estado inicial. Después se aplicó con el respaldo previo disponible. Los 51 eventos mantuvieron exactamente iguales sus campos originales, incluido ID, ModuloID, descripción, fecha y referencias. Se verificó mediante SHA-256 de todas las columnas anteriores: `e162d4c605a2cb7f46f1e7e3c49d8779b5149bc094aee624bf4cf76da919345f`. No se añadieron ni eliminaron eventos, personal o marcaciones. La captura previa de esquema se conserva por separado.

Tras la corrección, los **23 endpoints de lectura respondieron correctamente** sobre V3, con sesiones y respaldos temporales aislados. El esquema pasó de 1.386 a 1.388 columnas; tablas y FK siguen siendo 158 y 118. Los artefactos y el segundo backup incluyen la corrección.

## Esquema completo extraído

Se utilizó SqlPackage oficial 170.4.83.3 y verificación de extracción. El esquema real tiene:

| Objeto | Cantidad |
|---|---:|
| Tablas | 158 |
| Columnas | 1.388 |
| Claves foráneas | 118 |
| Vistas | 50 |
| Procedimientos almacenados | 9 |
| Disparadores | 27 |
| Funciones | 1 |

Se contrastaron todas las tablas y columnas con la captura real, el conteo de FK y la integridad ZIP del DACPAC. DacFx validó referencias del modelo. No se encontraron dependencias estáticas hacia otras bases o servidores ni módulos cuya definición fuera inaccesible en la consulta realizada. Esto no prueba ausencia de dependencias construidas con SQL dinámico.

La extracción inicial falló por seis usuarios de base que referencian logins del servidor. Se repitió correctamente excluyendo el mapeo a logins, sin cambiar usuarios ni contraseñas del servidor. El esquema contiene usuarios WITHOUT LOGIN y sus permisos; los logins de SQL Server y sus mapeos deben configurarse por separado durante la restauración o instalación. Las cuentas de la aplicación guardadas en `seguridad.Usuario` son datos del backup, no filas del script de esquema.

Artefactos privados:

- `BD_HOSPITAL_V3_REAL.dacpac`: modelo validado, sin datos de tablas.
- `BD_HOSPITAL_V3_ESQUEMA_REAL.sql`: definiciones completas de referencia, sin orden de instalación garantizado; no ejecutar como instalador.
- `BD_HOSPITAL_V3_INSTALADOR_BASE_NUEVA.sql`: plan ordenado para una base nueva, generado sin crearla. El destino por defecto es `BD_HOSPITAL_V3_ENTREGA_NUEVA`, no la V3 que se está usando. Rechaza una base ya existente y no contiene DROP DATABASE. Usa las rutas de archivos por defecto de la instancia destino.

El plan inicial generado por DacFx contenía un bloque condicional de eliminación/recreación; se retiró del archivo antes de preparar el paquete y se añadieron pruebas de rechazo. No se ejecutó ese plan inicial ni el revisado. Debe usarse el script completo en modo SQLCMD; no ejecutar trozos seleccionados. No aplicar el instalador sobre la base de pruebas ni sobre una base del ingeniero que ya tenga datos.

Restaurar un .bak e instalar un esquema vacío son caminos distintos. Para conservar estructura **y datos** se necesita el backup; el SQL/DACPAC de esquema por sí solo no carga datos. No ejecutar ambos sobre el mismo nombre de base.

Referencias de las herramientas: [SqlPackage Extract](https://learn.microsoft.com/en-us/sql/tools/sqlpackage/sqlpackage-extract), [SqlPackage Script](https://learn.microsoft.com/en-us/sql/tools/sqlpackage/sqlpackage-script).

## Pruebas realizadas sin cargar datos

| Prueba | Entorno y resultado |
|---|---|
| CSV completo con el importador real | 15 verificaciones por modo de auditoría (actual y legado), persistencia PDO simulada en memoria; ninguna conexión SQL en esa prueba |
| Importación, hash y duplicados | 7 verificaciones, PDO simulado |
| Jornadas y conciliación | 12 + 4 regresiones, reglas y PDO simulado |
| Acceso por sistema y revocación | 6 verificaciones en SQLite en memoria |
| Preflight del padrón | 22 verificaciones locales contra la captura de esquema |
| Seguridad del instalador | 12 verificaciones locales, sin ejecutar DDL |
| Lecturas reales V3 | 23 endpoints con HTTP 200 y respuesta correcta después de adaptar auditoría |
| SQL real del importador | 9 sentencias descritas por `sys.sp_describe_undeclared_parameters`: tablas, columnas y tipos válidos; no se ejecutaron sus INSERT/UPDATE |
| Estructura exportada | 158 tablas y 1.388 columnas iguales a la captura; 118 FK, objetos y DACPAC comprobados |

El fallo de auditoría que aparece en la prueba del importador es intencional: se provoca para comprobar que revierte el lote simulado. También se probó la reversión de una fecha inválida después de una marca válida. Se corrigió el doble de pruebas para contemplar las consultas de metadatos de auditoría y la inserción de incidencias que ya usa la aplicación; no se cambió el importador de producción.

No se ha realizado una carga ni una conciliación real en SQL Server, ni una restauración real. La descripción de metadatos comprueba compilación y parámetros, no sustituye pruebas de integridad, concurrencia y recuperación con datos en una base aislada.

## Qué demuestra el archivo BioStar

El CSV tiene 50.000 eventos: 41.382 marcaciones de huella válidas y 8.618 eventos no computables como asistencia. Hay 270 usuarios y 6.461 marcaciones consecutivas que se conservan como eventos, no se eliminan como duplicados exactos. El importador limita a 200 las incidencias de sistema por archivo; no preserva todos los eventos técnicos como incidencias individuales.

- Sin vínculos: las 41.382 marcas quedan sin PersonalID, aunque haya números parecidos a DNI.
- Con el mapa explícito simulado de 266 coincidencias: 40.899 marcas vinculadas y 483 pendientes de cuatro usuarios.
- Los 266 identificadores candidatos tienen ocho dígitos y nombres coincidentes con el padrón tras normalización. Eso no verifica por sí solo que sean DNI reales ni autoriza su carga.
- Los otros cuatro identificadores BioStar no tienen formato de DNI de ocho dígitos. Siguen siendo identificadores biométricos válidos como texto; no se descartan sus marcas. Requieren identificación manual.
- Repetir el archivo no duplica registros. Otro hash que contiene una marca idéntica tampoco la duplica en la simulación.

El CSV abarca mayo–octubre de 2026. Para generar asistencia histórica se necesitan horarios vigentes en esas fechas, no un horario creado hoy ni fechas de contratación deducidas de las marcas.

## Preparación de la carga por DNI

No se requiere crear una nueva tabla ni modificar el proyecto para la relación biométrica: ya existe `rrhh.VinculacionBiometrica`. Un lote aprobado debe resolver **DNI real → PersonalID real**, verificar el usuario BioStar y registrar después la relación **BiostarUserID → PersonalID**. No actualizar documentos o PK para hacerlos coincidir.

Se generaron dos documentos privados de trabajo:

1. `candidatos_dni_biostar.csv`: 270 usuarios, 266 candidatos y cuatro identificaciones manuales. La aprobación, el verificador y el sustento están vacíos; no tiene vínculos aprobados. Ninguno de estos candidatos tiene todavía un PersonalID en la base inspeccionada.
2. `padron_campos_por_confirmar.csv`: las 450 filas originales y columnas vacías para nombres separados, código de empleado, ingreso real, IDs organizacionales, vigencia de asignación y revisión. No se rellenó información empresarial por deducción. Los campos opcionales pueden seguir sin dato: una columna de revisión no significa que deba inventarse o exigirse ese valor.

Antes de aplicar un futuro lote deben volver a consultarse los datos actuales, obtener el mapa aprobado, respetar los vínculos existentes y rechazar cualquier conflicto. Guardar un manifiesto de PK e imágenes anteriores/posteriores y auditar la operación. Las marcas ya importadas requerirían asociar solo las pendientes y ejecutar la conciliación; repetir un archivo ya importado no equivale a volver a conciliar automáticamente.

## Lo que impide la carga real por ahora

Con la estructura actual, Personal exige CodigoEmpleado, FechaIngreso, Nombres y ApellidoPaterno, que el TXT no aporta por separado. No es correcto completar esas columnas con fecha de carga, fecha de la primera huella, nombres divididos por suposición o códigos biométricos usados como documento.

Hacerlas NULL en SQL sin revisar la interfaz tampoco resuelve toda la compatibilidad: la consulta actual de listado de Personal no devuelve NombreCompletoOriginal como respaldo del nombre, por lo que podría mostrar fichas sin nombre si solo se carga ese campo. No se aplicó una relajación de columnas que dejara la copia instalada funcionando incorrectamente. La vía compatible sin cambiar el proyecto es aportar los campos reales faltantes; cualquier alternativa necesita una propuesta específica y pruebas de lectura/edición antes de tocar el esquema.

Se mantienen los otros conflictos del [informe del padrón](REVISION_PADRON_PERSONAL_V3.md): documento de línea 159, seis posibles cambios de identificador, mapas de áreas y cargos, 14 jornadas administrativas contradictorias y pautas sin calendario confirmado. Las clasificaciones asistenciales no se copiarán al régimen legal. La ausencia de un dato opcional se trata como ausencia, no como una licencia para inventarlo.

El trabajador ya existente, su cuenta, los 140 resultados y las 85 solicitudes de Laboratorio siguen protegidos por el [plan de reversión](PLAN_REVERSION_PADRON_V3.md).

## Repetición y próximos pasos

```bash
php tests/schema_delivery.php
php -d memory_limit=768M tests/biostar_csv_real_sin_carga.php
php tests/import_sql_v3_sin_escrituras.php
python3 database/validar_exportacion_v3.py
python3 database/plan_vinculacion_dni_v3.py
```

Los scripts locales que usan la captura rechazan un padrón cuyo hash cambió. Antes de cargar debe repetirse la inspección de V3; las cifras de una captura no reemplazan esa comprobación.

Orden pendiente: resolver/aprobar los datos y mapas; disponer de una base aislada y permiso para restaurarla; verificar y restaurar el backup allí; probar un lote controlado y su reversión; autorizar y aplicar al servidor de pruebas; validar lectura, edición, importación y reportes en la versión instalada; obtener un nuevo backup final; verificarlo y solo después transferir al ingeniero por un canal acordado. No se ha subido nada a Git ni al destino.

## Nueva fuente de revisión

El usuario proporcionó después `personal_limpio_asistencia.txt` y confirmó que ESTADO es solo calidad de datos. La preparación actual, la separación de indicadores y las tres coincidencias con ceros iniciales están documentadas en [FUENTE_PERSONAL_ACTUAL.md](FUENTE_PERSONAL_ACTUAL.md). Las capturas y paquetes previos se conservan como históricos; no sustituyen esta revisión. No se ha cargado la nueva fuente.
