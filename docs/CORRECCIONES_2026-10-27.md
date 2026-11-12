# Correcciones verificadas — 27 de octubre de 2026

Se corrigieron permisos, respaldos, administración y consultas según el análisis del proyecto. No se cambiaron las contraseñas de cuentas operativas ni los registros del hospital.

## Cambios que requieren conocer quienes operan el sistema

1. Cerrar sesión y volver a entrar una vez: las sesiones anteriores no contienen la comprobación de contraseña requerida ahora por las API.
2. Los cambios de tolerancia, tardanza y plazo de regularización se guardan en SQL y rigen desde el día siguiente. El formulario informa su vigencia. La meta de 150 horas y el modo de importación son de solo lectura.
3. Consultar asistencia ya no escribe conciliaciones. Usar **Reconciliar** o importar el CSV para procesar marcaciones; la operación explícita completa también fechas parcialmente procesadas.
4. Si un respaldo pertenece a otra base o no tiene procedencia, se rechaza. El sincronizador regeneró desde la base nueva los respaldos de personal y turnos. Los demás módulos generan sus respaldos al consultarlos con conexión.
5. La bitácora muestra eventos SQL reales. Una consulta sin coincidencias devuelve vacío. Si falla la escritura de auditoría fuera de una transacción, queda un archivo durable en `AUDIT_PATH` o `storage/audit`, marcado como pendiente de consolidación SQL; el fallo también se registra en el log del servidor.
6. Crear o restablecer cuentas muestra una contraseña aleatoria una sola vez al administrador. Entregarla al titular por un canal privado. No existe cambio obligatorio de contraseña en primer acceso; la interfaz ya no promete ese comportamiento.

## Instalación en otros entornos

Las migraciones de parámetros y triggers de revisión ya fueron aplicadas en la base configurada durante esta corrección. La prueba real detectó que faltaban triggers de revisión; ahora se verifica que cada guardado incrementa la revisión y permite invalidar la caché. Para otras instalaciones:

```bash
php database/aplicar_sql_prioridad.php
php database/aplicar_administracion.php
```

Para desarrollo, iniciar con el router que impide servir los directorios internos:

```bash
php -S 0.0.0.0:8080 router.php
```

Apache debe permitir los `.htaccess` incluidos. En Nginx se deben configurar restricciones equivalentes a los directorios internos; el router es específico del servidor de desarrollo de PHP. No se cambió la configuración de servicios externos al repositorio.

Configurar en el entorno de PHP `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. `.env.example` sirve de referencia y no se carga automáticamente. Para SQL con certificado válido, usar `DB_ENCRYPT=true` y `DB_TRUST_SERVER_CERTIFICATE=false`. `SNAPSHOT_PATH`, `AUDIT_PATH` y `AUTH_RATE_PATH` permiten ubicar archivos fuera de la raíz pública. Las credenciales de desarrollo existentes se conservaron.

## Verificación

```bash
python3 tests/integration.py
php tests/auth_roles.php
php tests/security_regressions.php
php tests/rules.php
php tests/sql_priority.php
php tests/attendance_regressions.php
node tests/api-client.cjs
node tests/ui-security.cjs
```

Estas pruebas usan archivos temporales o PDO simulado; no necesitan modificar datos reales. La prueba de reglas genera intencionadamente un error de serialización para verificar que conserva el respaldo anterior.

Con SQL Server disponible:

```bash
php tests/admin_sql_smoke.php
php tests/sql_server_smoke.php
```

La prueba administrativa verifica login de una cuenta temporal, creación, reset, revocación de sesión, bloqueo/desbloqueo, rol y parámetros con vigencia futura. Todos sus registros SQL se revierten y se comprueba que los totales y la revisión vuelven a su estado inicial. Los contadores IDENTITY pueden avanzar aunque se revierta una inserción.

La prueba SQL de programación usa personal, asignación y catálogos temporales cuando faltan datos de ensayo, y revierte toda la transacción. Pasaron lectura, persistencia física, versión, conflicto y actualización de revisión.

También se verificaron rutas HTTP de login y recursos públicos (200), API sin sesión (401) y archivos internos (404) utilizando el router. La validación de nombres con comillas, apóstrofes y HTML pasó en los tres módulos corregidos mediante ejecución JavaScript aislada.

## Límites de lo verificado

No se hizo una certificación normativa, una prueba visual completa de navegador ni una apertura de todos los archivos en Excel. Los scripts antiguos `test_biometrico.php` y `test_conciliacion.php` dependen de un CSV y padrón históricos concretos; no se ejecutaron contra el hospital durante esta corrección porque escriben datos y sus cantidades esperadas no representan la base nueva. Deben sustituirse por escenarios de una base de ensayo antes de usarlos como suite general.

La base inspeccionada contiene un trabajador activo; las pruebas transaccionales y simuladas cubren escenarios adicionales, pero no sustituyen una prueba de carga, concurrencia y operación con un padrón representativo.

## Datos institucionales pendientes de carga

Al finalizar se confirmó: 1 trabajador, 7 cargos, 0 servicios y 0 asignaciones de personal. Programación requiere servicios y asignaciones reales para mostrar una nómina; no se trasladaron los datos de la base anterior ni se inventó una estructura hospitalaria. Los registros temporales utilizados por las pruebas fueron revertidos.

Verificación final de sintaxis: 85 archivos PHP y 11 JavaScript sin errores. Las 90 comprobaciones de integración, las suites de reglas, persistencia, seguridad, caché y ambas pruebas transaccionales contra SQL terminaron correctamente.
