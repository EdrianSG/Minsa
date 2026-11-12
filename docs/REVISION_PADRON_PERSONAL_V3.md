# Revisión previa de padron_personal_con_turnos.txt contra V3

**Resultado: carga bloqueada. No existe coincidencia total. No se insertó, actualizó ni eliminó ningún registro SQL.**

Se consultó exclusivamente `BD_HOSPITAL_V3` en `192.168.11.38:1433`; el inspector comprueba `DB_NAME()` y rechaza otra base. La captura real, no un script antiguo de estructura, es la referencia de este informe. Captura: 9 de noviembre de 2026, 15:29 UTC (10:29 de Lima).

El archivo tiene 450 filas de datos, 11 columnas, codificación UTF-8 y tabuladores reales. Todas las filas tienen 11 campos. SHA-256: `121514ab0b0d365ff322f559a7f786062bf8a529b067f9408ee060a89af6caed`. Las líneas citadas incluyen el encabezado como línea 1. El original permanece intacto.

## Esquema y datos actuales

Se inspeccionaron **158 tablas, 1.386 columnas y 118 claves foráneas** (122 pares de columnas). Los campos, longitudes, NULL, DEFAULT, claves únicas, relaciones y CHECK relevantes están en [el inventario real](INVENTARIO_PERSONAL_V3.md). No hay FK deshabilitadas ni sin verificar.

| Elemento | Estado actual |
|---|---:|
| `rrhh.Personal` | 1 trabajador |
| `rrhh.AsignacionPersonal` | 1 asignación |
| `rrhh.Horario`, `rrhh.Contrato`, `rrhh.Asistencia` | 0 filas |
| `rrhh.ProgramacionMensual` | 1 período |
| `rrhh.ProgramacionMensualDetalle` | 25 filas |
| Marcaciones, importaciones, incidencias y vínculos biométricos | 0 filas |
| `dbo.StagingPersonalPacasmayo` | 453 filas |
| Áreas / departamentos / servicios | 40 / 7 / 8 |
| Cargos / profesiones / especialidades | 74 / 27 / 10 |
| Turnos | 22: 7 operativos, 9 ausencias, 6 emergencias en vacaciones |

El único trabajador ya existente tiene referencias desde **140 resultados de Laboratorio, 85 solicitudes, 25 detalles de programación, una asignación y una cuenta**. No coincide por documento DNI con ninguna fila del archivo. Debe conservarse: no es admisible vaciar Personal o reemplazarlo por el padrón. Los 15 campos FK entrantes a Personal no tienen huérfanos. También existen datos clínicos, estadísticos y de otros módulos que no pertenecen a esta operación.

## Correspondencia de las once columnas

| Archivo | Campos reales de destino posibles | Evaluación |
|---|---|---|
| `DNI` | `rrhh.Personal.TipoDocumentoID`, `NumeroDocumento` | Existe tipo DNI activo con ID 1. Hay un valor de nueve dígitos en línea 159; no corregirlo por recorte o relleno. Ocho dígitos solo confirma formato, no acreditación de identidad. |
| `APELLIDOS_Y_NOMBRES` | `NombreCompletoOriginal`; por separado `Nombres`, `ApellidoPaterno`, `ApellidoMaterno` | El original cabe en `nvarchar(250)`. Falta separación confirmada; Nombres y ApellidoPaterno son obligatorios. No dividir automáticamente por cantidad de palabras. |
| `AREA` | `rrhh.AsignacionPersonal.AreaID` → `organizacion.Area` | Requiere mapa explícito de catálogos. No equivale a DepartamentoID ni a ServicioID. No colocar el área limpia en DepartamentoOrigen como si fuera un departamento. |
| `CARGO` | `rrhh.AsignacionPersonal.CargoID` → `rrhh.Cargo`; `Personal.CargoOrigen` conserva etiqueta si se aprueba | Mezcla cargos, profesiones, especialidades y formación. No acreditar ProfesionID o EspecialidadID a partir de esa etiqueta. |
| `REGIMEN` | No hay equivalencia directa con `Personal.RegimenLaboral` | Son clasificaciones asistenciales/administrativas/formativas, no el régimen legal. No copiarlas a CondicionLaboral o GrupoOcupacional por aproximación. Requiere decisión institucional; se puede conservar el archivo como fuente sin convertir sus etiquetas. |
| `COD_TURNO` | `rrhh.Turno.Codigo`, `Horario.TurnoID`, `ProgramacionMensualDetalle.CodigoTurno` | Los conjuntos rotativos no son un único turno SQL. El ID del turno tampoco es su código. |
| `DESC_TURNO` | Descripción de la pauta de origen | No existe un campo equivalente en Horario para almacenar una pauta rotativa. No crear turnos por estas descripciones sin confirmar su significado. |
| `HORARIO` | `rrhh.Turno.HoraInicio`, `HoraFin`, `Horas` | El texto contiene alternativas y hay contradicciones de duración/código en 14 filas. No alterar el catálogo actual automáticamente. |
| `DIAS` | `rrhh.Horario.DiaSemana` (1–7), fechas de vigencia; o programación por fecha | No es fecha de inicio, mes ni calendario diario. Rotar exige decidir el turno efectivo por día. |
| `TELEFONO` | `rrhh.Personal.Telefono` (`varchar(30)`) | Longitud máxima 22: cabe. Hay 42 valores `SIN REGISTRO` y otro valor que requiere revisión; no cargarlos como números ni inventar contactos. No copiar a Celular sin criterio confirmado. |
| `EMAIL` | `rrhh.Personal.Correo` (`varchar(150)`) | Máximo 40 caracteres: cabe. 21 valores son `sin registro`; proponer NULL porque es opcional, pendiente de aprobar esa normalización. No inventar correos. |

Los campos de negocio opcionales que no están en la fuente —nacimiento, colegiatura, especialidad, contratación, etc.— pueden permanecer NULL cuando el esquema lo permite. Su ausencia no justifica rellenarlos por suposición. La falta de un horario tampoco obliga a inventar uno.

## Bloqueos concretos

1. **Personal exige datos que el archivo no aporta.** En V3 son NOT NULL y no tienen DEFAULT: CodigoEmpleado, TipoDocumentoID, NumeroDocumento, Nombres, ApellidoPaterno y FechaIngreso. El archivo solo aporta documento y nombre agregado. No contiene fecha de ingreso ni código de empleado y no desglosa nombres. `FechaCarga` del staging y `FechaRegistro` no son fechas de contratación.
2. **Todos los 450 valores REGIMEN son incompatibles con el CHECK legal de RegimenLaboral.** Admite `DL 276`, `DL 728`, `DL 1057 CAS`, `DL 1153`, `LEY 30057`, `LOCACION`, `OTRO` o NULL. Las categorías del archivo son otras. En 16 filas, `ASISTENCIAL ADMINISTRATIVO` además excede `varchar(20)`. No sustituir por OTRO, por DL 1153 o por CAS para hacer pasar la carga.
3. **Un identificador no tiene formato DNI.** Línea 159: nueve dígitos. Preservar el valor original hasta verificarlo; no asumir que Identificador del staging es un DNI o un BiostarUserID.
4. **No hay mapa total de áreas y cargos.** Hay 42 etiquetas de área, de las que solo 6 coinciden textualmente con un área activa (46 filas). Otras coincidencias por mayúsculas/acentos elevan las candidatas únicas a 16 etiquetas, todavía pendientes de revisión. Hay 63 etiquetas de cargo: solo 6 exactas (12 filas) y 13 con candidato único normalizado. La normalización no autoriza asignaciones. `POR ASIGNAR` aparece en 128 áreas y 100 cargos; no crear un área o profesión con ese marcador.
5. **La fuente no aporta organización completa.** Sin departamento, servicio ni sede verificados no se pueden deducir por texto. ServicioID es opcional en la asignación, pero si se indica debe pertenecer al AreaID: hay una FK compuesta que lo exige. Una asignación exige además AreaID, CargoID y FechaInicio reales.
6. **Los horarios no son una programación diaria.** 31 filas dicen `1-2` y 136 dicen `1-2-3`: son alternativas rotativas, no `1/2` de 12 horas ni `1/2/3` de 24 horas. Las 181 filas con alguna pauta no tienen vigencia ni calendario detallado. Horario exige FechaInicio; ProgramacionMensualDetalle exige año/mes/día/código y tiene FK a Turno.Codigo. Las otras 269 dicen S/T: dejarlas sin horario, sin asignar un turno por defecto.
7. **14 jornadas administrativas discrepan del catálogo actual.** El archivo indica código `1`, 07:00–15:00 y 8 horas. En SQL el código `1` es 07:00–13:00 y 6 horas. `AD` es 08:00–16:00 y 8 horas. Ni reutilizar `1` ni sustituir por `AD` conserva el horario de origen. Debe confirmarse la jornada correcta y después decidir si corresponde corregir la fuente o autorizar un registro de catálogo; no requiere inventar una tabla nueva.
8. **No hay evidencia para vincular biométrico.** El archivo no contiene BiostarUserID, dispositivo, evento, fecha/hora de marcación ni procedencia de vinculación. No se pueden poblar VinculacionBiometrica o MarcacionBiometrica a partir de DNI. La similitud del identificador del staging no constituye esa evidencia.

Para las obligaciones de V3 hay dos caminos que requieren revisión: aportar los datos reales faltantes, o justificar y aprobar por separado una adaptación de nulabilidad compatible con las aplicaciones. Este análisis no modifica el esquema ni propone rellenar los campos con valores ficticios.

## Comparación con staging y protección de identidades

No hay DNI repetidos ni nombres exactamente repetidos dentro de las 450 filas. Tampoco hay nombres duplicados tras normalizar acentos y espacios. No se encontró documento DNI del archivo en el trabajador operativo existente; esto no prueba que todos los documentos sean válidos ni que deban insertarse automáticamente.

De los 453 registros de `dbo.StagingPersonalPacasmayo`:

- 444 identificadores coinciden exactamente con el archivo; 443 tienen nombre idéntico y los 444 coinciden después de normalizar acentos/espacios.
- Las seis filas con identificador no presente en staging son las líneas **38, 330, 331, 332, 333 y 334**. Sus nombres sí tienen candidato con otro identificador en staging: confirmar si son correcciones documentales. No tratarlas como seis altas independientes ni enlazarlas solo por nombre.
- Nueve identificadores del staging no aparecen en el archivo; seis corresponden a esos posibles cambios. Por nombre normalizado quedan tres registros del staging sin contraparte. Conservarlos; su ausencia no autoriza una baja ni un borrado.
- En las 444 coincidencias por identificador hay cambios en 408 áreas, 433 cargos, 266 clasificaciones, 266 códigos de pauta, 444 horarios, 444 textos de días, 43 teléfonos y 83 correos. La normalización del archivo no demuestra por sí sola la corrección institucional de esas modificaciones.

No se actualizó staging. Se generaron listados privados por línea para verificar todas estas diferencias.

## Evidencias y repetición

Herramientas, ambas sin modo de carga ni de borrado:

```bash
php database/auditar_padron_v3.php
python3 database/comparar_padron_v3.py
python3 tests/padron_preflight.py
```

El inspector SQL solo ejecuta SELECT; el comparador trabaja con archivos locales y rechaza comparar si cambia el hash del padrón respecto de la captura SQL. Repetir ambos antes de cualquier operación futura: la base puede cambiar mientras se usa el sistema. No se ha obtenido un backup restaurable ni se realizó una simulación con INSERT/ROLLBACK.

Evidencias privadas en `private-audits/padron-v3/`, ignoradas por Git y con archivos de datos a 0600:

- `esquema_y_datos_v3.json`: columnas, FK, índices, CHECK, triggers, dependencias, catálogos, filas de RR. HH./staging y referencias agregadas. No incluye contraseñas ni hashes de autenticación ni filas clínicas de pacientes.
- `diccionario_real_v3.csv`: las 1.386 columnas reales.
- `comparacion_resumen.json`: conteos y estado `apto_para_cargar=false`.
- `conflictos_por_fila.csv`: línea, campo, valor y motivo.
- `coincidencias_catalogos.json` y `mapeo_catalogos_por_confirmar.csv`: IDs exactos/candidatos; columnas de aprobación vacías.
- `posibles_cambios_identificador.csv`: las seis parejas de identificador candidatas, sin aprobar.
- `cambios_respecto_staging.csv`: diferencias entre las 444 filas cuyo identificador coincide.

Los archivos privados contienen información personal: no publicarlos. El archivo original se excluyó de Git sin alterar su contenido. El router local ya restringe los accesos HTTP a páginas, API y assets; no expone esta carpeta.

## Condiciones antes de autorizar una futura carga

- Resolver el DNI de línea 159 y los seis posibles cambios de identificador; confirmar las tres ausencias del staging.
- Aportar nombres separados, códigos de empleado y fechas reales; o aprobar expresamente una adaptación justificada de nulabilidad. No se ha hecho esa adaptación.
- Aprobar el mapa de áreas/cargos con IDs reales y separar profesión, especialidad, cargo, formación y tipo de jornada del régimen laboral legal.
- Definir vigencias y turnos por día para las 181 pautas, confirmar las 14 jornadas administrativas y conservar sin horario las 269 filas S/T.
- Confirmar marcadores de contacto como NULL y revisar el teléfono restante, sin crear datos.
- Establecer un manifiesto por lote, un respaldo restaurable y una prueba de carga/reversión en una copia aislada. Ver [el plan de emergencia](PLAN_REVERSION_PADRON_V3.md).
- Repetir la inspección, comprobar de nuevo restricciones y referencias, presentar la previsualización de altas/cambios/reutilizaciones y obtener autorización de carga. **Una confirmación no convierte en válidos los conflictos técnicos: deben estar resueltos o expresamente excluidos del alcance antes de insertar.**

## Validaciones ejecutadas

- Inspección SQL real: únicamente SELECT, con verificación de base V3.
- Comparación local completa de 450 filas y hash del archivo original.
- `python3 tests/padron_preflight.py`: 22 comprobaciones correctas de flags SQL, obligatoriedad, catálogos ambiguos/inactivos, conflictos de identidad, horarios, referencias y detalle por línea.
- Sintaxis PHP y compilación Python: correctas.
- Comprobación final por SELECT: los conteos de 13 tablas protegidas de personal, asignación, horarios, programación, biométrico, staging, Laboratorio y usuarios coinciden con la captura inicial. Los conteos por sí solos no son un respaldo ni prueban igualdad de cada campo; las herramientas de esta tarea no ejecutan DML.
- No se ejecutaron pruebas de inserción, actualización, eliminación, cambios de esquema ni carga con ROLLBACK. La autorización actual es solo para inspección y planificación.

## Actualización posterior: compatibilidad con la copia actual

Durante la preparación de entrega se detectó y corrigió de forma aditiva el esquema de auditoría, conservando sus campos originales y sin cargar el padrón. El inventario actual tiene 1.388 columnas por los dos campos añadidos; el análisis inicial de este informe inspeccionó 1.386. Se conservó la captura anterior y se actualizó el diccionario privado. Los bloqueos del padrón siguen vigentes. Ver [el informe de pruebas y entrega](ENTREGA_PRUEBAS_V3.md).

## Nueva fuente de revisión

El usuario proporcionó después `personal_limpio_asistencia.txt` y confirmó que ESTADO es solo calidad de datos. La preparación actual, la separación de indicadores y las tres coincidencias con ceros iniciales están documentadas en [FUENTE_PERSONAL_ACTUAL.md](FUENTE_PERSONAL_ACTUAL.md). Las capturas y paquetes previos se conservan como históricos; no sustituyen esta revisión. No se ha cargado la nueva fuente.
