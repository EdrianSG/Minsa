# 12. Decisiones tomadas y pendientes

## Decisiones vigentes

### 1. El foco no es crear un biométrico nuevo

El hospital ya tiene una aplicación biométrica.

La primera versión se centra en:

```text
programación + control + consulta + reportes
```

### 2. La programación mensual es el núcleo

La matriz por trabajador y día es la representación principal del rol.

### 3. La asistencia depende del turno programado

No basta con tener una hora de entrada. La marcación debe compararse con el turno correspondiente.

### 4. Tolerancia confirmada: 5 minutos

La referencia anterior de 10 minutos queda descartada.

Clasificación actual:

```text
0 a 5 min      → tolerancia
>5 a 30 min    → tardanza
>30 min        → tratamiento/descuento pendiente de fórmula exacta
```

### 5. Una ausencia debe cruzarse con justificaciones

No se declara falta antes de revisar incidencias.

### 6. Tecnología

```text
Frontend: HTML + CSS + JavaScript
Backend:  PHP
BD:       SQL Server Express
Red:      local del hospital
```

### 7. El trabajador no necesita login para marcar

La autenticación corresponde principalmente a los módulos administrativos.

### 8. Base de datos compartida

Los proyectos de los practicantes utilizan una base común y deben reutilizar los maestros existentes.

## Pendientes por confirmar con RR. HH. / Informática

### A. Fórmula después de 30 minutos

Definir exactamente:

- cuándo se considera falta;
- cuándo corresponde descuento;
- cómo se calcula el descuento;
- si hay diferencias por régimen laboral.

### B. Horas exactas de cada código de turno

Confirmar oficialmente:

```text
1 = ?
2 = ?
3 = ?
```

y cualquier otro código utilizado por servicio.

### C. Regla mensual del personal administrativo

Confirmar si además del calendario de días hábiles existe una meta mensual rígida de horas o si basta con cumplir el horario establecido por calendario.

### D. Fuente de datos biométricos

Determinar si la aplicación existente permite:

- API;
- exportación Excel/CSV;
- acceso a BD;
- consulta por archivo;
- integración directa.

### E. Identificador común

Confirmar cuál será la clave para relacionar biometría y RR. HH.:

- DNI;
- código de trabajador;
- código del biométrico;
- otro identificador.

### F. Catálogo oficial de incidencias

Obtener de RR. HH. la lista completa y su efecto:

```text
vacaciones
permiso
descanso médico
licencia
comisión
feriado
descanso
otro
```

### G. Formato final de SETI

Conseguir un ejemplo oficial actual para reproducir exactamente:

- columnas;
- orden;
- códigos;
- agrupaciones;
- encabezados;
- firma o pie;
- formato Excel/PDF.

### H. Responsables de programación

Definir si la grilla será editada por:

- RR. HH.;
- jefe de cada servicio;
- ambos con flujo de aprobación.

### I. Política de correcciones

Definir:

- quién puede corregir;
- si requiere aprobación;
- cuánto tiempo existe para corregir;
- qué cambios deben quedar bloqueados después del cierre mensual.

## Riesgo principal del proyecto

El mayor riesgo técnico no es PHP ni SQL Server. Es implementar reglas que todavía no hayan sido confirmadas administrativamente.

Por ello, cualquier valor dudoso debe quedar parametrizado y documentado, no enterrado como constante en el código.
