# 5. Arquitectura técnica

## Stack definido

### Frontend

- HTML.
- CSS.
- JavaScript.
- Diseño alineado con la página web existente del hospital.

No es obligatorio migrar a un framework para la primera versión.

### Backend

- PHP.
- API o controladores HTTP para separar interfaz, lógica de negocio y acceso a datos.
- Validaciones realizadas en servidor.

### Base de datos

- Microsoft SQL Server Express.
- Servidor local dentro de la red del hospital.
- Esquemas compartidos para permitir que distintos sistemas del equipo utilicen la misma base central.

## Arquitectura lógica

```text
┌──────────────────────────┐
│ Navegador de RR.HH.      │
│ HTML + CSS + JavaScript  │
└────────────┬─────────────┘
             │ HTTP
             ▼
┌──────────────────────────┐
│ Backend PHP              │
│                          │
│ - Autenticación          │
│ - Turnos                 │
│ - Programación mensual   │
│ - Asistencia             │
│ - Incidencias            │
│ - Reportes               │
│ - Auditoría              │
└────────────┬─────────────┘
             │ SQL
             ▼
┌──────────────────────────┐
│ SQL Server Express       │
│ PC servidor del hospital │
└──────────────────────────┘

             ▲
             │ futura integración
┌────────────┴─────────────┐
│ Aplicación biométrica    │
│ existente                │
└──────────────────────────┘
```

## Separación por capas recomendada

```text
frontend/
├── css/
├── js/
└── views/

backend/
├── config/
├── controllers/
├── services/
├── repositories/
├── middleware/
└── routes/

database/
├── schema/
├── migrations/
├── views/
└── procedures/
```

### Responsabilidad de cada capa

**Frontend:** mostrar información y capturar acciones del usuario.

**Controllers:** recibir solicitudes HTTP.

**Services:** aplicar reglas del negocio.

**Repositories:** ejecutar consultas y procedimientos de base de datos.

**SQL Server:** mantener integridad, relaciones, índices y datos históricos.

## Integración biométrica futura

La arquitectura debe permitir que la fuente de marcaciones sea reemplazable.

Posibles estrategias futuras:

1. API del software biométrico.
2. Lectura de una tabla compartida.
3. Importación periódica de CSV/Excel.
4. Servicio intermedio que sincronice marcaciones.
5. Webhook desde un dispositivo propio.

La primera versión no debe depender de que esta integración esté lista.
