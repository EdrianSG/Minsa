# Verificación funcional — 20 de octubre de 2026

> Documento histórico. El estado más reciente está en [SQL y caché](SQL_Y_CACHE.md). Consulte las [correcciones verificadas del 26 de octubre](CORRECCIONES_2026-10-26.md) para el estado actual de autenticación, autorización, asistencia, biométrico y reportes.

El README describe el alcance esperado. Sus afirmaciones sobre sincronización bidireccional, biométrico y reportes de planilla no son una certificación de la implementación actual.

## Pruebas reproducibles

Desde la raíz del proyecto:

```bash
python3 tests/integration.py
php tests/rules.php
node tests/api-client.cjs
find . -name '*.php' -exec php -l {} \;
```

La integración ejecuta los puntos de entrada PHP reales con contexto HTTP simulado, sesiones temporales y snapshots sintéticos. No utiliza el padrón ni los snapshots del hospital. Fuerza SQL no disponible. No sustituye las pruebas de servidor web, SQL Server ni navegador.

Cobertura automatizada:

- Todos los endpoints distintos del login rechazan solicitudes anónimas.
- Lectura y escritura respetan roles y métodos; se rechazan envíos desde otro origen.
- Las tres cuentas de desarrollo permiten iniciar sesión sin SQL Server; una contraseña incorrecta se rechaza.
- Regularización, permisos, dictamen, turnos y horarios no confirman operaciones sin conexión.
- Fechas imposibles, acciones desconocidas, códigos de turno inválidos, días fuera del mes y trabajadores ajenos al rol se rechazan.
- La programación se conserva al recargar y se replica al snapshot global existente.
- Versión obsoleta: 409; dos escritores de la misma versión: uno confirma y otro recibe conflicto.
- Error de serialización o disco: no se confirma el snapshot y se conserva el anterior.
- Límites de tolerancia: 5, 6, 30 y 31 minutos; ausencia de marcación.
- Salida de guardia nocturna antes y después de medianoche; prioridad de licencia aprobada.
- La caché del navegador no oculta denegaciones HTTP y se invalida después de guardar.

## Cambios realizados

- Controles comunes de API en `includes/api_bootstrap.php`, aplicados antes de leer datos o exportar.
- Roles del catálogo administrativo reconocidos por autenticación. Escrituras restringidas por operación.
- Por preferencia del usuario para desarrollo local: se restauraron las claves de prueba, las cuentas de contingencia y los accesos rápidos visibles en el login. También se admite la verificación de hash en SQL Server.
- Cookies de sesión HttpOnly y SameSite=Lax; redirección de login limitada a `index.php`.
- Credenciales predeterminadas de conexión visibles en `config/database.php`, restauradas para desarrollo local. Se pueden sobrescribir con variables de entorno; `.env.example` no se carga automáticamente.
- Escrituras que antes descartaban errores ahora responden con error. La falta de una cola offline se informa con 503, sin inventar identificadores.
- Dictamen de permiso y actualización de asistencia en una transacción. Acciones inválidas rechazadas. Se bloquea la modificación de solicitudes ya dictaminadas: aún no existe información suficiente para revertir aprobaciones de forma segura.
- Regularización parcial conserva las marcaciones no enviadas.
- Asistencia y dashboard no sustituyen una fecha sin datos por otro día. La programación sin datos no fabrica trabajadores.
- Snapshots escritos con bloqueo, archivo temporal y reemplazo atómico. El guardado mensual bloquea el periodo completo antes de verificar su versión y actualizar servicio/global.
- La programación declara `persistencia: LOCAL` y `sincronizado_sql: false`.
- Pautas nuevas cierran la vigencia anterior sin desactivarla para consultas históricas; se rechaza sobrescribir una pauta con vigencias posteriores. La consulta diaria utiliza lunes=1 y fecha de vigencia, independientemente de DATEFIRST de SQL Server.
- La modificación de cuentas responde 501: el código anterior solo cambiaba datos de sesión, sin crear cuentas utilizables.
- Directorios internos bloqueados para Apache mediante `.htaccess`.

## Pendientes que impiden considerar terminado el sistema

1. **Sincronización offline:** no existe una cola durable ni reconciliación contra SQL Server. La programación se guarda localmente; no hay tabla de rol mensual en el esquema proporcionado. Definir y migrar ese modelo antes de prometer sincronización automática. Los snapshots cruzados se escriben por archivo: no existe transacción de varios archivos ante caída del proceso.
2. **Reportes para planilla:** consolidado e individual contienen valores de ejemplo o inferencias (p. ej. todos los días programados como laborados y tardanza cero). Deben calcularse desde asistencia efectiva, programación y permisos. No están validados para sustentar pagos/descuentos.
3. **Biométrico:** falta confirmar o implementar importación real, duplicados, emparejamiento de ingreso/salida y asignación de fecha de jornada nocturna.
4. **Programación y asistencia:** la asistencia SQL aún se basa en pauta semanal; falta aplicar el rol mensual como primera fuente de horario. La regularización todavía acepta la hora programada del cliente y debe obtenerla de esa fuente común.
5. **Autorización por servicio:** los roles limitan operaciones por módulo; falta limitar los registros a los servicios asignados a cada jefatura. No basta con ocultar servicios en la interfaz.
6. **Administración:** implementar creación/bloqueo/roles/reset de contraseña transaccionales contra las tablas SQL; parámetros institucionales siguen en sesión y no son compartidos ni aplicados globalmente.
7. **Auditoría durable:** el fallback sigue en sesión y no equivale a una bitácora institucional inmutable. Se necesita un registro persistente fuera de la sesión y sin identidad de ejemplo.
8. **Datos ficticios restantes:** otros listados, ausentes, reportes y vistas conservan ejemplos/fallbacks. Eliminarlos o separarlos en un modo de demostración explícito. No confundirlos con registros del hospital.
9. **Reglas y nomenclatura:** unificar catálogo y cómputo entre editor, validación, asistencia y exportación. Hay diferencias para DESC, vacaciones, CITT, VAC-2/VAC-3 y códigos oficiales; validar contra criterios institucionales antes de cambiar sus horas.
10. **Configuración de servidor:** `.htaccess` solo protege Apache si permite estas directivas. En Nginx bloquear `/storage/`, `/config/`, `/includes/`, `/database/`, `/tests/` y archivos ocultos; configurar SNAPSHOT_PATH fuera de la raíz web. El servidor embebido de PHP no aplica `.htaccess`.

## Validación pendiente con infraestructura disponible

- Con una base de pruebas SQL: login por hash correcto/incorrecto, usuario bloqueado, permisos de cada rol, claves foráneas, inserciones, rollback del dictamen al fallar una fecha y solapamientos de permisos.
- Verificar que anular/rechazar una solicitud pendiente no altere asistencias y definir la reversión de una ya aprobada con historial previo.
- Pautas vigentes: cambios de mes, vigencia a mitad de mes y consulta de jornadas pasadas. Revisar filas históricas antiguas que ya tengan Estado=0.
- Navegador: altas, filtros, formularios, conservación de cambios ante error, impresión y descarga; permisos y denegaciones deben mostrarse sin presentar datos anteriores.
- Reiniciar el servidor tras guardar y simular caída de disco/red. No se ha ejecutado una prueba de SQL Server ni de servidor HTTP: el entorno de trabajo bloquea sockets locales.
