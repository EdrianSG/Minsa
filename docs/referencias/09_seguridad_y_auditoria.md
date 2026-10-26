# 9. Seguridad, auditoría e integridad

## Autenticación

Los módulos administrativos deben ser utilizados únicamente por usuarios autorizados.

Como frontend y backend funcionarán inicialmente dentro de la red local y previsiblemente bajo el mismo servidor web, una opción simple y apropiada es:

```text
PHP Session
```

Si en el futuro existe un frontend separado o integraciones externas, se puede evaluar JWT u otro mecanismo.

## Autorización

Se recomienda RBAC: control de acceso basado en roles.

Roles conceptuales:

- Administrador del sistema.
- Recursos Humanos.
- Jefe de servicio / responsable de programación.
- Consulta / auditoría.

El trabajador común no necesita una cuenta para marcar asistencia si la marcación procede del biométrico.

## Contraseñas

En PHP:

```php
password_hash()
password_verify()
```

Nunca almacenar contraseñas en texto plano.

## Acceso a SQL Server

Usar:

- consultas preparadas;
- parámetros;
- usuario de base de datos con permisos mínimos necesarios;
- credenciales fuera del repositorio;
- configuración separada por entorno.

## Auditoría

Operaciones que deberían registrarse:

- Inicio de sesión.
- Cambio de contraseña.
- Creación o modificación de turnos.
- Modificación de un rol.
- Aprobación u observación del rol.
- Registro o modificación de una incidencia.
- Corrección manual de asistencia.
- Exportación sensible.
- Cambio de permisos o roles.

## Historial de correcciones

Nunca sobrescribir una asistencia sin dejar rastro.

Registrar:

```text
quién
qué cambió
cuándo
por qué
valor anterior
valor nuevo
```

## Integridad

La base debe impedir inconsistencias mediante:

- claves primarias;
- claves foráneas;
- restricciones UNIQUE;
- restricciones CHECK;
- transacciones;
- validaciones de fechas;
- estados controlados.

## Datos sensibles

Aunque el proyecto no gestiona historias clínicas, los datos laborales y biométricos siguen siendo información sensible.

Evitar:

- exponer templates de huella al frontend;
- guardar datos biométricos si no son necesarios;
- usar DNI como token secreto;
- imprimir información innecesaria en logs;
- mostrar errores SQL crudos al usuario.

## Principio recomendado para biometría

Si la aplicación biométrica existente ya resuelve la identificación, el nuevo sistema debería recibir únicamente lo necesario para el control de asistencia:

```text
identificador del trabajador
fecha/hora
dispositivo/origen
identificador de evento
```

No es necesario copiar huellas dactilares al nuevo sistema salvo que exista una razón técnica y autorización institucional específica.
