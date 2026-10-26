# 10. Infraestructura y despliegue local

## Servidor

Se está utilizando una PC del hospital como servidor de SQL Server Express.

Los integrantes del equipo pueden conectarse desde sus propias laptops cuando se encuentran en la red del hospital.

## Conectividad validada

Desde Fedora se comprobó conectividad TCP al servidor utilizando el puerto:

```text
1433
```

Ejemplo de comprobación:

```bash
nc -vz IP_DEL_SERVIDOR 1433
```

## Clientes de base de datos

Herramientas utilizadas o probadas:

- DBeaver.
- DataGrip.

DataGrip ha resultado cómodo para trabajar con SQL Server desde Linux.

## Configuración necesaria de SQL Server Express

Aspectos que deben permanecer correctamente configurados:

- TCP/IP habilitado.
- Puerto TCP conocido, preferiblemente 1433 si así se ha definido.
- Firewall de Windows permitiendo el acceso.
- Autenticación configurada según la política del equipo.
- Usuario de conexión para los desarrolladores.
- IP del servidor estable o reserva DHCP.

## Regla importante: suspensión

Si la PC servidor entra en suspensión:

```text
SQL Server deja de estar accesible para los clientes.
```

Por ello, durante el trabajo o producción el equipo servidor debe mantenerse encendido y sin suspensión automática.

## Arquitectura de red

```text
                RED LOCAL DEL HOSPITAL
                        │
        ┌───────────────┼────────────────┐
        │               │                │
        ▼               ▼                ▼
 Laptop Fedora     Laptop Windows   PC de RR.HH.
 DBeaver/DataGrip   SSMS/cliente     Navegador
        │               │                │
        └───────────────┼────────────────┘
                        ▼
              PC SERVIDOR LOCAL
          ┌────────────────────────┐
          │ Web Server + PHP       │
          │ SQL Server Express     │
          └────────────────────────┘
```

## Base compartida por el equipo

Cada practicante puede desarrollar un sistema distinto utilizando una misma base central.

Para evitar conflictos:

- separar dominios mediante esquemas;
- reutilizar maestros compartidos;
- no duplicar Personal, Cargo, Profesión o Área sin necesidad;
- acordar nombres de tablas;
- revisar scripts antes de ejecutarlos;
- utilizar respaldos.

## Respaldos

Se recomienda:

- backup periódico `.bak`;
- copia antes de cambios estructurales;
- scripts versionados;
- registro de migraciones;
- no modificar directamente producción sin respaldo.

## Versionado

Repositorio recomendado:

```text
project/
├── frontend/
├── backend/
├── database/
│   ├── migrations/
│   ├── seeds/
│   └── views/
└── docs/
```

Cada cambio de estructura de base de datos debería quedar en un script reproducible.
