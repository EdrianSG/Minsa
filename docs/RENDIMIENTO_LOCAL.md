# Rendimiento del servidor local

Iniciar desde la raíz con `scripts/serve-dev.sh` (puerto 8080 por defecto). Puede indicarse otra dirección como primer argumento. Requiere PHP, Python 3, unixODBC y el controlador Microsoft SQL Server instalado. Para entornos de producción, configurar la reutilización de conexiones en el servidor correspondiente.

El lanzador copia la configuración de controladores unixODBC a un directorio temporal privado, activa `Pooling=Yes` y `CPTimeout=120` en los controladores Microsoft SQL Server, y ejecuta PHP con ocho procesos por defecto. No modifica `/etc`, credenciales, tablas ni permisos. Respeta `PHP_CLI_SERVER_WORKERS`, `ODBCSYSINI` y `ODBCINSTINI` de entrada. El archivo temporal se elimina al salir del lanzador normalmente.

La reutilización es por proceso: la primera conexión de cada proceso sigue pagando el costo de apertura, y tras 120 segundos sin uso puede requerir una nueva conexión. No es una caché de resultados: cada solicitud revalida la sesión y consulta los datos actuales.

El 9 de noviembre de 2026 se comparó el endpoint real `api/personal/listar.php` por HTTP en localhost contra BD_HOSPITAL_V3 del servidor .38. Se utilizó una sesión temporal eliminada al finalizar y no se imprimieron datos personales. Seis lecturas del servidor anterior demoraron 1167–1576 ms. En el servidor de prueba, las lecturas que abrían conexión demoraron 1510–1526 ms y las restantes 111–241 ms. Estas medidas corresponden al endpoint, no a la carga visual completa del navegador.

Referencia: https://learn.microsoft.com/en-us/sql/connect/php/connection-pooling-microsoft-drivers-for-php-for-sql-server

Después de reiniciar el servidor habitual 8080 con el lanzador, 16 solicitudes HTTP de Personal respondieron correctamente: las conexiones nuevas tardaron 1120–1146 ms y las solicitudes con conexión reutilizada, 33–52 ms. Login devolvió HTTP 200 en menos de 1 ms. Se detuvo el servidor temporal 8081 y se eliminó la sesión temporal.
