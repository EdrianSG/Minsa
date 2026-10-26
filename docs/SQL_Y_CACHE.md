# SQL Server como fuente principal y caché de vistas

## Flujo operativo

1. El usuario inicia sesión contra SQL Server.
2. Antes de reutilizar una vista, el navegador consulta la sesión, la conexión y la revisión de datos en SQL.
3. Si la revisión sigue vigente, reutiliza la respuesta guardada en memoria o en `sessionStorage`, incluso después de navegar entre módulos. Si cambió, consulta de nuevo la API; la API lee SQL.
4. Las escrituras se confirman después del commit SQL. El rol mensual utiliza `rrhh.ProgramacionMensual` y `rrhh.ProgramacionMensualDetalle`, con versión y bloqueo por periodo. Servicio y vista global comparten esa versión.
5. Los triggers actualizan `seguridad.VersionDatos` dentro de la misma transacción que los datos. Detectan cambios de esta aplicación, otros equipos y SQL directo. No hay invalidación confirmada por transacciones revertidas.
6. Un guardado confirmado invalida las vistas locales. Cada 20 segundos y al recuperar el foco se comprueba si otro usuario actualizó datos; se avisa para actualizar sin descartar formularios ni ediciones pendientes.
7. Las jornadas y el reporte individual también se revalidan cada minuto porque el reloj puede cambiar su estado sin una escritura.

La caché está separada por sesión, rol y servicio. Antes de reutilizarla se comprueba que la cuenta, el rol y el servicio sigan autorizados en SQL. Los errores 401/403 eliminan la caché; no se ocultan con datos anteriores.

## Emergencia

Si SQL o la red dejan de responder se muestra la última vista disponible, identificada como respaldo de emergencia. Los endpoints con snapshots usan exclusivamente información anteriormente consultada, sin generar personal, horarios ni marcaciones de ejemplo. Un resultado SQL vacío es válido y no activa el respaldo.

La contingencia es de consulta. Las escrituras institucionales requieren SQL; no existe una cola de escrituras desconectadas. Si no hay respaldo de la fecha, servicio o consulta solicitados, se informa que los datos no están disponibles.

Los reportes oficiales requieren SQL. El backend no exporta un rol oficial desde un archivo antiguo. Los respaldos de programación guardan la versión SQL, pero no se utilizan para autorizar un commit en la base de datos. Fallar al escribir el respaldo local después de un commit SQL no oculta ni revierte ese commit: la respuesta indica `respaldo_local: false`.

## Instalación de las tablas y revisión

Desde la raíz del proyecto, con la conexión SQL configurada y una cuenta que pueda crear las tablas y triggers:

```bash
php database/aplicar_sql_prioridad.php --verificar
```

La migración es aditiva e idempotente. Crea las dos tablas de programación, el registro de revisión y los triggers; no elimina registros existentes. La verificación comprueba lectura, escritura, conflicto de versiones y cambio de revisión con datos de prueba que se revierten al finalizar. No cambia credenciales ni permisos de cuentas existentes.

Los roles antiguos que solo existen en JSON no se importan automáticamente: pueden contener bases generadas o ediciones que nunca fueron oficiales. Los archivos se conservan y deben revisarse antes de promoverlos a SQL. Sin celdas mensuales almacenadas, la vista SQL se calcula desde las pautas semanales vigentes; no inventa turnos alternados.

Si todavía no se instala el control de revisión, la aplicación evita reutilizar caché como vigente y realiza consultas completas. La programación mensual requiere su migración y responde con error explícito si falta.

## Pruebas locales

```bash
python3 tests/integration.py
php tests/rules.php
php tests/attendance_regressions.php
php tests/sql_priority.php
node tests/api-client.cjs
```

Los fixtures locales no modifican la base del hospital. La prueba SQL real es independiente:

```bash
php tests/sql_server_smoke.php
```

La administración de cuentas y parámetros institucionales mantiene los pendientes históricos: esta modificación incorpora programación SQL y consistencia de consultas/caché, no sustituye las funciones administrativas aún no implementadas.
