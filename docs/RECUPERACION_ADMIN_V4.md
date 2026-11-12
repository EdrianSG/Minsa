# Recuperación del acceso administrativo a V4

Fecha: 2026-11-03. Esta intervención es posterior al informe inicial de adaptación.

Se confirmaron dos causas del bloqueo: no había credenciales DB_USER/DB_PASS en el proceso PHP tras retirar los valores predeterminados del código, y las cinco tablas de seguridad de V4 estaban vacías. En V3 existía una única cuenta `admin`, activa y sin bloqueo, con rol `RRHH_ADMIN` y habilitación del sistema `RRHH`.

## Correcciones realizadas

- `config/database.php`: admite configuración en `config/database.local.php`; variables de entorno explícitas prevalecen. Se recuperó la conexión que usaba el proyecto en el archivo local, excluido de Git. `config/database.local.example.php` sirve de plantilla sin credenciales.
- `includes/auth.php`: fallos de conexión y consultas devuelven HTTP 503, en lugar de afirmar que la contraseña es incorrecta.
- `database/recuperar_admin_v4.php`: recuperación transaccional limitada a seguridad V4 vacía. Lee la cuenta real y sus vínculos vigentes de V3; conserva exactamente el hash de contraseña. Copia sistema/rol, registra la cuenta administrativa sin PersonalID y crea sus habilitaciones. Registra el evento en auditoría. No escribe en V3 ni en las otras bases.
- `includes/PasswordHashFormat.php`: reconoce variantes bcrypt $2a$/$2b$ admitidas por password_verify aunque password_get_info informe `unknown`. No convierte ni recalcula hashes.

La primera recuperación se canceló antes de escribir porque la comprobación de formato era demasiado restrictiva. Tras comprobar localmente la compatibilidad bcrypt, se corrigió esa validación y se aplicó la transacción.

## Resultado comprobado en SQL Server

Se insertaron en V4 una fila de Sistema, Rol, Usuario, UsuarioRol y UsuarioSistema, más el evento de auditoría. No se crearon trabajadores ni pacientes. La cuenta de origen y su contraseña permanecieron intactas.

`php database/recuperar_admin_v4.php --verificar` confirmó:

- Hash de V4 idéntico al de V3.
- Cuenta habilitada y vigente para RRHH mediante Auth::validateSession.
- Permiso de administración reconocido por la aplicación.
- PersonalID nulo, sin asociación ficticia ni reutilización de un ID laboral de otra base.

No se solicitó ni utilizó la contraseña del usuario para las pruebas; el inicio interactivo se debe realizar con `admin` y su contraseña habitual. No se emitió una sesión de navegador durante la verificación CLI.

## Pruebas locales

- `php tests/login_config.php`: 9 comprobaciones de formatos bcrypt, precedencia de entorno y errores de conexión, sin SQL Server.
- `php tests/v4_auth.php`: 6 comprobaciones de cuenta técnica, permisos por sistema y revocación.
- `php tests/auth_roles.php`: restricciones de roles conservadas.
- Sintaxis PHP de archivos intervenidos y `git diff --check`: correctos.

Las comprobaciones contra SQL Server se ejecutaron con autorización fuera del aislamiento de red. El diagnóstico de solo lectura contó registros de seguridad y consultó cuentas administrativas; no mostró hashes ni contraseñas. No se deshabilitó la validación de contraseña ni el aislamiento por sistema.
