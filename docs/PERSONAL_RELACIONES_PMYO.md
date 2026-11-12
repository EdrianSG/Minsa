# Edición de Personal y catálogos PMYO

Fecha: 2026-11-03. Cambios aplicados a la base real `BD_HOSPITAL_V4` y al módulo de Recursos Humanos. No se crearon tablas ni columnas ni se publicaron archivos en GitHub.

## Catálogos contrastados y actualizados

El padrón operativo existente coincide con el mapa recibido: 181 registros, 29 áreas y 57 denominaciones distintas de perfil. El padrón general real contiene 453 registros, 63 áreas y 96 denominaciones; se conserva su contenido, sin forzar las estimaciones de 58/81 ni convertir las variantes en acreditaciones profesionales.

| Catálogo V4 | Total después de la actualización | Tratamiento |
|---|---:|---|
| organizacion.Area | 39 | 29 áreas operativas del mapa, 7 complementarias y 3 áreas institucionales preexistentes. |
| rrhh.Cargo | 74 | 68 perfiles del mapa, sus complementos y formación; se conservan los cargos preexistentes y se reutiliza la denominación coincidente. |
| rrhh.Profesion | 27 | 11 profesiones existentes y 16 adicionales diferenciadas según títulos explícitos. |
| rrhh.Especialidad | 10 | Cada especialidad tiene ProfesionID válido; no se repite la especialidad en AsignacionPersonal. |

Las áreas funcionales nuevas no reciben un departamento o servicio supuesto. DepartamentoID puede permanecer NULL. Los servicios disponibles se filtran por su AreaID real.

Se conservaron las denominaciones de origen, incluidas variantes como LIC ENFEMERIA, TEC ADMNISTRATIVO y TEC FARCACIA. Sus equivalencias se presentan como propuestas explícitas para revisar. No se equiparan técnico en farmacia y farmacia/bioquímica, ni se transforma un internado o una ubicación en título profesional.

Se corrigieron de forma reversible dos textos con problemas de codificación en los catálogos existentes, conservando sus IDs y referencias. Las bases anteriores no se modificaron.

**La actualización de catálogos no cambió las asignaciones ni las profesiones de los trabajadores.** El título y la relación individual se guardan únicamente al editar y confirmar la ficha. UAM, LABORATORIO, RAYOS X, CONSUTORIO EXTERNO, internados y otras denominaciones insuficientes no reciben una profesión por inferencia.

## Cómo editar una ficha

1. En Personal, busque al trabajador y seleccione **Editar ficha**. Se cargan la ubicación, el cargo, la profesión y la especialidad existentes, incluidos valores inactivos ya vinculados para evitar que desaparezcan sin advertencia.
2. Compare la tarjeta de datos de origen. **Usar área y perfil de origen** propone coincidencias exactas del catálogo; no crea relaciones por semejanza ni modifica por sí solo la profesión.
3. Seleccione departamento formal si corresponde, área y servicio opcional. Al cambiar de padre se retiran los hijos incompatibles. Las áreas funcionales PMYO se identifican en su propio grupo.
4. El selector de cargo muestra los perfiles observados para el área. **Mostrar también perfiles no observados** permite revisar una asignación diferente; no prohíbe, por ejemplo, técnico en enfermería en Farmacia, Rayos X o Salud Ambiental cuando el mapa lo registra.
5. Confirme la profesión real y, si corresponde, su especialidad. La propuesta profesional requiere una acción explícita; el cargo o el área no cambian automáticamente el título del trabajador.
6. Elija la operación de asignación y guarde. Los datos de contrato permanecen protegidos hasta habilitar expresamente su edición.

### Operaciones de asignación

- **Registrar o corregir la relación actual:** crea la primera relación con los datos conocidos, o corrige los IDs de la relación existente. Una corrección conserva AsignacionPersonalID; no exige inventar una fecha ni generar una vigencia nueva. Cambiar una relación existente requiere motivo y queda auditado con antes/después.
- **Registrar traslado con nueva vigencia:** exige fecha de inicio posterior a la vigencia anterior cuando se conoce. Cierra la relación previa y crea otra, conservando el histórico.
- **Finalizar la asignación:** exige fecha de fin válida; retira EsActual sin borrar las filas históricas.
- **Conservar sin cambios:** permite editar identidad o datos profesionales manteniendo la ubicación.

Guardar una relación sin modificaciones no crea otra fila. Se comprueba la versión de la ficha mediante su auditoría y el identificador de asignación abierto en el editor. Una edición obsoleta devuelve HTTP 409 y pide recargar, sin sobrescribir los cambios del otro operador.

Las validaciones del servidor comprueban existencia de catálogos, departamento/área, servicio/área, profesión/especialidad, fechas y versión. La situación laboral, el documento, los datos de contratación y las fechas desconocidas siguen admitiendo NULL.

## Archivos principales

- `config/pmyo_personal_map.php`: mapa explícito recibido, 29 áreas operativas, complementos, formación y propuestas de profesión/especialidad.
- `includes/PmyoCatalog.php`: relaciona el mapa con IDs reales de catálogo para los selectores; no escribe fichas.
- `database/revisar_catalogos_pmyo.php`: diagnóstico de catálogos y distribución de origen, solo lectura.
- `database/ajustar_catalogos_pmyo.php`: vista previa por defecto y aplicación transaccional con `--aplicar`; valida previamente las 181 filas y el mapa 29/57, conserva registros existentes y audita la actualización.
- `api/personal/catalogos.php`, `detalle.php`, `crear.php`, `editar.php`: catálogos completos, carga de relación y versiones y guardado con conflictos identificables.
- `includes/PersonalRepository.php`, `includes/auditoria.php`: validaciones, corrección/traslado/finalización y auditoría íntegra de las relaciones.
- `pages/personal.php`, `assets/css/pages/personal.css`, `assets/js/personal-relations.js`, `assets/js/pages/personal.js`, `includes/footer.php`: editor por secciones, selectores dependientes, origen y aplicación explícita de propuestas.

## Pruebas ejecutadas

- `node tests/personal_relations.cjs`: **28 comprobaciones** del modelo y el controlador real con DOM simulado: carga sin borrar selecciones, dependencias, perfiles por área, propuestas explícitas, valores inactivos existentes, NULL frente a inactivo y ausencia de valores automáticos.
- `php tests/pmyo_relations_live.php --rollback`: **17 comprobaciones reales en SQL Server** de registro, guardado sin duplicación, corrección manteniendo ID, profesión/especialidad, traslado y cierre de histórico, finalización, conflicto de versión y rechazo de especialidad ajena. Se verificó rollback completo; no quedaron personas, asignaciones ni cambios de ficha de prueba.
- `php tests/pmyo_catalog_live.php`: **139 comprobaciones de solo lectura** de áreas, perfiles, profesiones, especialidades, formación y relaciones del mapa en V4; confirma los totales de catálogo indicados arriba.
- APIs `personal/catalogos.php` y `personal/detalle.php` contra V4 real: HTTP 200, sin mostrar datos personales en los resultados de prueba.
- `python3 tests/integration.py`: **94 comprobaciones** de API con sesiones y fixtures aislados.
- Regresiones V4: **28 comprobaciones**, correctas. Contrato estático de tablas/columnas y sintaxis PHP/JavaScript: correctos.

No se declara una prueba integral de navegador: la interacción del editor se verificó con DOM simulado y su persistencia se ejercitó directamente contra SQL Server con rollback.
