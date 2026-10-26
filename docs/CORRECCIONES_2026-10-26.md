# Correcciones verificadas — 26 de octubre de 2026

> Actualización posterior: consulte [SQL Server y caché de vistas](SQL_Y_CACHE.md). La programación ya dispone de modelo SQL y la contingencia pasa a ser de consulta.

Esta revisión sustituye las descripciones históricas sobre cuentas de contingencia y reportes de ejemplo. No certifica el sistema para planillas.

## Comportamiento actual

- La autenticación exige hash SQL; las pruebas ya no esperan cuentas de contingencia.
- Un usuario con alcance limitado sin servicio asignado recibe 403. El reporte individual autoriza el trabajador antes de consultar datos y no sustituye un ID inexistente por otro trabajador.
- La asistencia filtra tanto filas como KPIs por servicio. Sus snapshots parciales usan una clave por servicio para no sobrescribir el snapshot global.
- Las marcaciones biométricas se limitan al servicio asignado. El historial técnico y las incidencias de importaciones globales requieren alcance institucional.
- La importación identifica al usuario de sesión. Al recibir una salida nocturna también concilia la jornada del día anterior.
- El rol mensual local prevalece sobre la pauta semanal en la conciliación, vista diaria, reporte individual y regularización. El catálogo de turnos aporta los horarios; un código sin horario produce un error explícito. La regularización no acepta el horario programado enviado por el cliente.
- La conciliación conserva fechas completas al emparejar marcas. La ventana técnica va desde dos horas antes del inicio hasta dos horas después del fin. El ingreso debe estar en la primera mitad de la jornada y como máximo cuatro horas después del inicio. La salida se elige entre marcas posteriores al ingreso, separadas al menos treinta minutos, por proximidad al fin programado. Son criterios técnicos de emparejamiento, no tolerancias laborales; casos fuera de ventana requieren revisión.
- Sin asistencia conciliada se distinguen PROGRAMADO, EN_CURSO y SIN_DATOS. No se deduce una falta de la ausencia de datos. Las faltas ya registradas y las regularizaciones/permisos se conservan. No existe todavía un indicador que certifique que todos los relojes y todas las marcas de una jornada fueron importados; por ello no se crean faltas automáticamente por falta de marcas.
- El consolidado diferencia SIN_DATOS y PENDIENTE de CONFORME. La vista muestra estas etiquetas sin convertirlas en “Con Descuento”.
- Sin SQL ni snapshot de la fecha, asistencia y dashboard devuelven 503. Un conjunto vacío no implica 100 % de asistencia. Los reportes requieren SQL y los errores de consulta no se convierten en informes exitosos de ejemplo.
- CITT en consolidado y SETI cuenta días calendario dentro del mes, incluye permisos que cubren el mes completo y evita duplicados por solapamientos. El individual usa también la intersección de fechas.

## Validación reproducible

```bash
python3 tests/integration.py
php tests/rules.php
php tests/attendance_regressions.php
node tests/api-client.cjs
find . -name '*.php' -exec php -l {} \;
node --check assets/js/pages/asistencia.js
node --check assets/js/pages/dashboard.js
node --check assets/js/pages/reportes.js
```

Resultado: 86 comprobaciones de API, 16 de reglas/persistencia, 16 regresiones de jornadas/CITT/conciliación y 4 del cliente JavaScript. Los fixtures usan directorios temporales y PDO simulado; no modifican datos del hospital. Las advertencias por JSON inválido, HTTP 403 y HTTP 503 son fallos provocados deliberadamente por los tests.

Pendiente de validar en infraestructura: consultas y escrituras contra SQL Server real, carga HTTP multipart y navegación/impresión. No se ejecutaron los scripts de integración que importan o concilian registros reales. La programación sigue siendo local (`sincronizado_sql: false`); no se añadió una cola ni sincronización SQL del rol. La administración y auditoría durable mantienen los pendientes descritos en la revisión histórica.
