# Nueva fuente candidata: personal_limpio_asistencia.txt

La fuente candidata actual para preparar la carga es `personal_limpio_asistencia.txt`, proporcionada por el usuario después de la primera revisión. Tiene 450 registros y 13 columnas tabuladas. SHA-256: `55cf61db5f314a8acb83ee77b44f488d67849301bff2b12ee792b5904da8d655`.

**No se cargó personal, asistencia ni vínculos. No se modificaron Personal.Activo o FechaCese. No se añadieron columnas de estados técnicos a la base.**

El archivo original se mantiene intacto. La revisión deriva archivos privados separados en `private-audits/personal-limpio-v3/`. El paquete ZIP de preparación anterior conserva su captura anterior; sus CSV no deben usarse como fuente actual sin contrastarlos con esta revisión.

## Regla institucional confirmada por el usuario

`ESTADO` es una etiqueta de calidad del registro obtenida de la presencia o ausencia de departamento y cargo. No es un estado laboral legal o administrativo. **Nunca debe provocar baja, cese, inactivación ni cambios de permisos.** La presencia o ausencia de huellas tampoco prueba por sí sola el estado laboral actual.

Se separan dos indicadores locales de preparación:

| Indicador | Valores | Alcance |
|---|---|---|
| `ESTADO_DATOS` | `COMPLETO`, `DATOS_INCOMPLETOS` | Solo presencia de área y cargo en esta fuente; no acredita homologación de catálogos ni un legajo completo en todos sus campos. |
| `ESTADO_BIOMETRICO` | `ACTIVO_CON_MARCACIONES`, `SIN_MARCACIONES` | Existencia de eventos de huella admitidos en el período del CSV; no equivale a asistencia conciliada, puntualidad ni situación laboral. |

`DATOS_INCOMPLETOS` corresponde a ausencia de área **o** cargo, no únicamente a ausencia de ambos. Marcadores como PENDIENTE se tratan como ausencia de información; no como áreas o cargos nuevos.

## Resultado de separar ambos indicadores

| Datos de área/cargo | Con marcaciones | Sin marcaciones | Total |
|---|---:|---:|---:|
| Ambos informados (`COMPLETO`) | 227 | 75 | 302 |
| Falta uno o ambos (`DATOS_INCOMPLETOS`) | 42 | 106 | 148 |
| Total | 269 | 181 | 450 |

De los 148 incompletos, 80 no tienen área ni cargo y 68 solo carecen de uno de los campos. Entre los 80 originales PENDIENTE se verifican los 22 con huellas y los 58 sin huellas que indicó el usuario. La clasificación técnica original `ACTIVO` agrupa 370 registros, pero 68 de ellos tampoco tienen ambos campos completos según la nueva definición.

Se comprobaron los cuatro ejemplos señalados por el usuario contra el archivo y el CSV: sus conteos de 389, 229, 194 y 192 corresponden a marcaciones válidas; los cuatro permanecen DATOS_INCOMPLETOS y ACTIVO_CON_MARCACIONES, sin propuesta de baja.

## Contraste con el CSV de BioStar

Fuente: `Report_20261015T070613_1.csv`, SHA-256 `07981d72e5dcb5d3eb57fc4833fa306ffc7b1538fe0b2043e8dbb09c69f75127`.

Se verificaron eventos `Autenticación 1:N correcta (Huella)` entre el 23 de mayo y el 15 de octubre de 2026. La última marca es una marca individual: no implica salida, jornada cumplida ni fecha de ingreso laboral.

- 266 candidatos tienen ID exacto y nombre coincidente.
- Otros tres candidatos, en líneas 38, 331 y 333, tienen DNI de ocho dígitos con cero inicial y un ID BioStar original de siete dígitos. Nombre, total y última marca coinciden. Aportan 122, 221 y 133 marcas: 476 en conjunto.
- El plan conserva ambos valores como cadenas separadas: **no recorta el DNI, no rellena ni cambia el ID del dispositivo**. La equivalencia numérica se usa solo para proponer revisión y exige nombre coincidente y candidato único. No crea vínculos por sí sola.
- Los 269 candidatos reúnen 41.375 de las 41.382 marcas válidas. Resta un usuario BioStar, con siete marcas, sin identificación en este padrón. No se crea una identidad para resolverlo.
- Con esas tres propuestas explícitas, los conteos y las últimas marcas declarados en las **450 filas** coinciden con el CSV. Todos los vínculos siguen sin aprobar y sin PersonalID nuevo asignado.

La fuente mejora la preparación del mapa `BiostarUserID → PersonalID`, pero no modifica el criterio del importador: la relación debe quedar explícita y verificada en `rrhh.VinculacionBiometrica` cuando se autorice la carga. Los ceros iniciales muestran por qué no debe suponerse que ambos identificadores son siempre idénticos.

## Qué mejora y qué falta para cargar

La nueva columna `NOMBRE_PILA` permite proponer el contenido de `Personal.Nombres` sin extraerlo del nombre completo por suposición. `APELLIDOS` sigue agrupado: se prepararon 439 divisiones simples **por confirmar**, diez casos con partículas/compuestos para revisar y un caso de apellido único por confirmar. Las propuestas no se cargan automáticamente. El nombre completo de la línea 118 está en orden nombres–apellidos y coincide con sus partes; no es por sí mismo una contradicción de identidad.

Los 450 DNI coinciden con la copia archivada del padrón anterior. La línea 159 conserva un identificador de nueve dígitos y requiere revisión. Persisten los seis posibles cambios de identificador respecto del staging original; el nuevo archivo no los resuelve por cambiar el formato de nombres.

Se consultó nuevamente la V3 real, con 158 tablas y 1.388 columnas. `CodigoEmpleado`, `FechaIngreso`, `Nombres` y `ApellidoPaterno` siguen siendo campos obligatorios sin DEFAULT, junto al tipo y número de documento. El nuevo archivo **todavía no aporta CodigoEmpleado ni FechaIngreso**. No se usará ULTIMA_ASISTENCIA, la primera huella ni la fecha de carga como fecha de contratación.

Las pautas no cambiaron: 167 rotativas, 269 S/T y 14 administrativas cuyo código/horas discrepan del catálogo SQL. Se necesitan vigencias y turnos diarios verificados, sin convertir ausencia de programación en falta ni alterar turnos para hacer encajar el archivo.

Áreas y cargos sí cambiaron respecto de la limpieza anterior: 409 y 438 filas respectivamente. La nueva fuente contiene 64 etiquetas de área y 98 de cargo; hay variantes tipográficas y denominaciones de formación/profesión que no deben homologarse por semejanza. Comparadas con los catálogos capturados, 204 filas coinciden exactamente con un nombre de área y 239 con un nombre de cargo. Los candidatos normalizados siguen siendo propuestas. El indicador COMPLETO no autoriza una asignación SQL sin IDs y vigencia aprobados.

`TOTAL_MARCACIONES` y `ULTIMA_ASISTENCIA` son verificaciones derivadas; no se insertan como filas de `rrhh.Asistencia`, ni como campos inventados en Personal. Se conservan en la revisión. El nuevo archivo omite teléfono, correo y régimen: esa omisión no autoriza a borrar información existente ni a inferir el régimen legal de la base. El archivo anterior ya no está en la raíz del proyecto; se compararon sus valores conservados en el CSV privado de revisión, sin recrear ni sobrescribir la fuente retirada.

## Archivos y comprobaciones

- `personal_preparado_revision.csv`: fuente original más ambos indicadores, ID BioStar candidato, conteo/fecha verificados y propuestas de apellidos. Todas las filas indican `PERSONAL_ACTIVO_A_MODIFICAR=NO`, `FECHA_CESE_A_MODIFICAR=NO` y `VINCULO_APROBADO=NO`.
- `conflictos_por_fila.csv`: bloqueos y pendientes por línea.
- `catalogos_por_revisar.json`: coincidencias exactas y candidatas de catálogos, ninguna aprobada automáticamente.
- `usuarios_biostar_sin_identificar.json`: el usuario restante y sus siete marcas.
- `cambios_vs_archivo_anterior.csv` y `resumen.json`: trazabilidad y resultados.
- `esquema_y_datos_v3.json`: captura actual de esquema y registros relacionados, obtenida solo con SELECT.

Repetición:

```bash
php database/auditar_padron_v3.php personal_limpio_asistencia.txt personal-limpio-v3
python3 database/preparar_personal_limpio_v3.py
python3 tests/personal_limpio_preflight.py
```

Pasaron 30 comprobaciones locales: regla área y/o cargo, independencia de estados, coincidencias ambiguas, apellidos compuestos, preservación de identificadores, los cuatro casos aportados por el usuario y las comprobaciones completas de conteos/fechas. Se verificó que el original conserva su hash. No se ejecutó DML de carga ni de baja. El estado del lote continúa `apto_para_cargar=false`.
