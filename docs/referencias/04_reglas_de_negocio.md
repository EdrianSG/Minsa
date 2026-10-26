# 4. Reglas de negocio

Este documento contiene las reglas operativas conocidas hasta el momento. No es un listado de requisitos.

## 4.1 Evaluación de la asistencia

La asistencia siempre se evalúa contra el horario o turno programado del trabajador.

### Tolerancia

Regla confirmada más reciente:

- Hasta **5 minutos** después de la hora programada: dentro de tolerancia.
- Más de **5 minutos** y hasta **30 minutos**: tardanza.
- Después de **30 minutos**: corresponde aplicar el tratamiento administrativo o descuento definido por RR. HH.

La fórmula exacta del descuento posterior a los 30 minutos todavía debe documentarse con RR. HH.

### Ausencias

Una persona no debe clasificarse automáticamente como falta únicamente porque no exista una marcación.

Antes se debe revisar si existe:

- Vacación.
- Permiso.
- Descanso médico.
- Licencia.
- Comisión de servicio.
- Justificación.
- Corrección autorizada.
- Otra incidencia reconocida por RR. HH.

Solo después de ese cruce se determina la falta injustificada.

## 4.2 Programación mensual

La programación se organiza por mes mediante una matriz trabajador x día.

Cada trabajador debe tener como máximo una programación principal por día, salvo una excepción expresamente autorizada.

Los estados conceptuales del rol pueden ser:

- BORRADOR.
- ENVIADO.
- APROBADO.
- OBSERVADO.

## 4.3 Personal asistencial

Para el personal asistencial se ha trabajado con la meta de **150 horas mensuales**.

Equivalencia base:

```text
25 turnos de 6 horas = 150 horas
```

Un turno de 12 horas equivale a dos bloques base de 6 horas.

Por tanto, la validación debe realizarse por **horas computables**, no únicamente por cantidad de celdas.

Ejemplo:

```text
15 turnos de 6 h = 90 h
5 turnos de 12 h = 60 h
Total            = 150 h
```

Los turnos asistenciales pueden incluir mañanas, tardes, noches y guardias.

## 4.4 Personal administrativo

El personal administrativo sigue principalmente el calendario laboral.

La lógica debe considerar:

- Días laborables.
- Fines de semana.
- Feriados.
- Horario fijo o programación definida para el trabajador.

No debe aplicarse mecánicamente la validación de "25 turnos de 6 horas" usada como referencia para personal asistencial.

## 4.5 Turnos nocturnos

Un turno puede iniciar un día y terminar al siguiente.

Ejemplo conceptual:

```text
Inicio: 20:00 del 15/09
Fin:    08:00 del 16/09
```

La salida del día siguiente pertenece funcionalmente al turno iniciado el día anterior.

El catálogo de turnos debe incluir un indicador como:

```text
cruza_medianoche = true
```

## 4.6 SETI

El reporte SETI corresponde principalmente al personal asistencial al que aplique el envío.

El sistema debe manejar una bandera o condición equivalente a:

```text
aplica_seti = true/false
```

La exportación SETI debe excluir automáticamente al personal que no corresponda.

Debe ser posible filtrar por condición laboral, especialmente:

- NOMBRADO.
- CAS.

## 4.7 Conservación del historial

Los datos históricos de asistencia y programación no deben desaparecer al editar un registro.

Las correcciones deben conservar:

- Usuario que realizó el cambio.
- Fecha y hora.
- Valor anterior.
- Valor nuevo.
- Motivo.

## 4.8 Fuente de la hora

La hora real de una marcación debe proceder de una fuente confiable, idealmente:

- dispositivo biométrico, o
- servidor.

No debe depender del reloj modificable del navegador del trabajador.
