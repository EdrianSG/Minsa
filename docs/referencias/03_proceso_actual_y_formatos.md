# 3. Proceso actual y formatos del hospital

## Programación mensual

Los formatos revisados muestran que el hospital trabaja el rol mediante una matriz mensual:

- Una fila por trabajador.
- Columnas para los días del mes.
- Códigos de turno o condición dentro de cada celda.
- Organización por servicio, área o grupo.
- Separación según condición laboral cuando es necesario.

Este modelo debe conservarse visualmente en el sistema porque es familiar para RR. HH. y para los responsables de los servicios.

## Códigos observados y discutidos

En los formatos y conversaciones aparecen códigos como:

- `1`: turno de mañana.
- `2`: turno de tarde.
- `3`: turno de noche, dentro del modelo planteado actualmente.
- `V`: vacaciones.
- `F`: feriado.
- Celda vacía o código equivalente: sin programación / descanso, según la regla definida.

Los códigos definitivos y las horas exactas asociadas deben parametrizarse en un catálogo de turnos para evitar dejar valores rígidos en el código fuente.

## Grupos y condiciones observadas

Los formatos revisados han mostrado agrupaciones como:

- Administrativos.
- Asistencial CAS.
- Nombrados.
- Terceros.

También aparecen áreas o servicios como:

- Informática.
- Estadística.
- Admisión / Archivos.
- Telesalud.
- Call Center.

La base de datos debe permitir incorporar nuevas áreas sin modificar la estructura del sistema.

## Proceso de RR. HH. que debe digitalizarse

1. Se prepara el rol mensual.
2. Se asigna un turno o situación a cada trabajador por día.
3. Se verifica que la programación cumpla las reglas correspondientes.
4. RR. HH. revisa la programación.
5. Durante el mes se obtienen las marcaciones reales.
6. Se comparan las marcaciones con el turno programado.
7. Se identifican puntualidad, tardanza, ausencia o incidencia.
8. Antes de declarar una falta se revisan vacaciones, permisos, descansos médicos u otras justificaciones.
9. Se consolidan resultados y reportes.
10. Cuando corresponde, se prepara información para SETI y para la Red de Salud.

## Aplicación biométrica existente

El hospital ya dispone de una aplicación separada usada para revisar la hora de entrada del personal.

Situación actual:

- No forma parte del nuevo sistema.
- No existe todavía una integración confirmada.
- La marcación puede ser consultada desde su aplicación actual.
- El sistema nuevo debe poder recibir esos datos en el futuro mediante API, importación o algún mecanismo acordado con Informática.

Por esta razón, el diseño debe separar claramente:

- **programación laboral**, y
- **fuente de marcaciones**.

Así se evita amarrar todo el sistema a un solo equipo biométrico.
