/* INVENTARIO DE SOLO LECTURA — BD_HOSPITAL_V3
   Ejecutar completo en SQL Server Management Studio.
   No crea ni cambia tablas, catálogos, trabajadores, cuentas o permisos.
   No consulta contraseñas ni documentos de identidad.

   Devuelve resultados numerados:
   01 Entorno; 02 Departamentos; 03 Áreas; 04 Servicios; 05 Cargos;
   06 Cruce área/servicio/cargo; 07 Profesiones; 08 Especialidades;
   09 Nombres repetidos; 10 Áreas con denominación de puesto (solo revisión);
   11 Relaciones que requieren revisión; 12 Roles de acceso por sistema;
   13 Cuentas sin vínculo o sin rol RRHH; 14 Fichas sin asignación actual;
   15 Detalle nominal opcional de asignaciones actuales.

   Asignación actual = EsActual=1 y Activo=1, criterio del proyecto.
   Personal activo = rrhh.Personal.Activo=1; NULL no se interpreta como baja.
   Tener servicio vacío no es, por sí solo, un error; puede faltar confirmación.
   Coincidencias de nombres NO autorizan fusiones, traslados ni bajas.
   MEDICINA y MEDICINA GENERAL deben mantenerse separadas.
*/
USE [BD_HOSPITAL_V3];
SET NOCOUNT ON;
DECLARE @IncluirDetalleNominal bit=0; -- Cambiar a 1 si se necesita revisar nombres.
DECLARE @AreaID int=NULL; -- Opcional: limita únicamente el detalle nominal 15.
DECLARE @SistemaRRHH nvarchar(50)=N'RRHH';

PRINT '01 - Entorno consultado';
SELECT CAST(SERVERPROPERTY('ServerName') AS nvarchar(128)) AS Servidor,
       DB_NAME() AS BaseActual,SYSDATETIME() AS FechaConsulta;

PRINT '02 - Departamentos (incluye inactivos)';
SELECT d.DepartamentoID,d.SedeID,d.Codigo,d.Nombre,d.Activo,
    (SELECT COUNT(*) FROM organizacion.Area a WHERE a.DepartamentoID=d.DepartamentoID) AS AreasTotales,
    (SELECT COUNT(*) FROM organizacion.Area a WHERE a.DepartamentoID=d.DepartamentoID AND a.Activo=1) AS AreasActivas
FROM organizacion.Departamento d ORDER BY d.Nombre,d.DepartamentoID;

PRINT '03 - Areas y dependencia; conteos sin duplicar personas';
SELECT a.AreaID,a.Codigo,a.Nombre,a.Activo,a.DepartamentoID,d.Nombre AS Departamento,
    (SELECT COUNT(*) FROM organizacion.Servicio s WHERE s.AreaID=a.AreaID) AS ServiciosTotales,
    (SELECT COUNT(*) FROM organizacion.Servicio s WHERE s.AreaID=a.AreaID AND s.Activo=1) AS ServiciosActivos,
    (SELECT COUNT(*) FROM rrhh.AsignacionPersonal ap WHERE ap.AreaID=a.AreaID) AS AsignacionesTotales,
    (SELECT COUNT(DISTINCT ap.PersonalID) FROM rrhh.AsignacionPersonal ap
        WHERE ap.AreaID=a.AreaID AND ap.EsActual=1 AND ap.Activo=1) AS PersonasConAsignacionActual,
    (SELECT COUNT(DISTINCT ap.PersonalID) FROM rrhh.AsignacionPersonal ap
        JOIN rrhh.Personal p ON p.PersonalID=ap.PersonalID
        WHERE ap.AreaID=a.AreaID AND ap.EsActual=1 AND ap.Activo=1 AND p.Activo=1) AS PersonasActivasConAsignacionActual
FROM organizacion.Area a LEFT JOIN organizacion.Departamento d ON d.DepartamentoID=a.DepartamentoID
ORDER BY a.Nombre,a.AreaID;

PRINT '04 - Servicios y su area real (incluye inactivos)';
SELECT s.ServicioID,s.Codigo,s.Nombre,s.Activo,s.AreaID,a.Nombre AS Area,a.Activo AS AreaActiva,
    (SELECT COUNT(*) FROM rrhh.AsignacionPersonal ap WHERE ap.ServicioID=s.ServicioID) AS AsignacionesTotales,
    (SELECT COUNT(DISTINCT ap.PersonalID) FROM rrhh.AsignacionPersonal ap
        WHERE ap.ServicioID=s.ServicioID AND ap.EsActual=1 AND ap.Activo=1) AS PersonasConAsignacionActual
FROM organizacion.Servicio s LEFT JOIN organizacion.Area a ON a.AreaID=s.AreaID
ORDER BY a.Nombre,s.Nombre,s.ServicioID;

PRINT '05 - Cargos laborales; distintos de los roles de acceso';
SELECT c.CargoID,c.Codigo,c.Nombre,c.Nivel,c.Activo,
    (SELECT COUNT(*) FROM rrhh.AsignacionPersonal ap WHERE ap.CargoID=c.CargoID) AS AsignacionesTotales,
    (SELECT COUNT(DISTINCT ap.PersonalID) FROM rrhh.AsignacionPersonal ap
        WHERE ap.CargoID=c.CargoID AND ap.EsActual=1 AND ap.Activo=1) AS PersonasConAsignacionActual
FROM rrhh.Cargo c ORDER BY c.Nombre,c.CargoID;

PRINT '06 - Distribucion actual por area, servicio y cargo';
SELECT ap.AreaID,a.Nombre AS Area,ap.ServicioID,s.Nombre AS Servicio,ap.CargoID,c.Nombre AS Cargo,
    COUNT(*) AS AsignacionesActuales,COUNT(DISTINCT ap.PersonalID) AS PersonasDistintas,
    COUNT(DISTINCT CASE WHEN p.Activo=1 THEN p.PersonalID END) AS PersonasActivas,
    COUNT(DISTINCT CASE WHEN p.Activo=0 THEN p.PersonalID END) AS PersonasInactivas,
    COUNT(DISTINCT CASE WHEN p.Activo IS NULL THEN p.PersonalID END) AS PersonasEstadoSinConfirmar
FROM rrhh.AsignacionPersonal ap
LEFT JOIN rrhh.Personal p ON p.PersonalID=ap.PersonalID
LEFT JOIN organizacion.Area a ON a.AreaID=ap.AreaID
LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
LEFT JOIN rrhh.Cargo c ON c.CargoID=ap.CargoID
WHERE ap.EsActual=1 AND ap.Activo=1
GROUP BY ap.AreaID,a.Nombre,ap.ServicioID,s.Nombre,ap.CargoID,c.Nombre
ORDER BY a.Nombre,s.Nombre,c.Nombre;

PRINT '07 - Profesiones documentadas; no se deducen del cargo';
SELECT pr.ProfesionID,pr.Codigo,pr.Nombre,pr.Activo,
    (SELECT COUNT(*) FROM rrhh.Personal p WHERE p.ProfesionID=pr.ProfesionID) AS FichasVinculadas
FROM rrhh.Profesion pr ORDER BY pr.Nombre,pr.ProfesionID;

PRINT '08 - Especialidades y profesion correspondiente';
SELECT e.EspecialidadID,e.Nombre,e.Activo,e.ProfesionID,pr.Nombre AS Profesion,
    (SELECT COUNT(*) FROM rrhh.Personal p WHERE p.EspecialidadID=e.EspecialidadID
        AND p.ProfesionID=e.ProfesionID) AS FichasConRelacionCoincidente
FROM rrhh.Especialidad e LEFT JOIN rrhh.Profesion pr ON pr.ProfesionID=e.ProfesionID
ORDER BY pr.Nombre,e.Nombre,e.EspecialidadID;

PRINT '09 - Nombres repetidos (espacios exteriores, mayusculas y acentos); revisar IDs y dependencia';
WITH Nombres AS (
    SELECT N'AREA' AS Catalogo,AreaID AS ID,Nombre,DepartamentoID AS PadreID FROM organizacion.Area
    UNION ALL SELECT N'SERVICIO',ServicioID,Nombre,AreaID FROM organizacion.Servicio
    UNION ALL SELECT N'CARGO',CargoID,Nombre,CAST(NULL AS int) FROM rrhh.Cargo
), Claves AS (
    SELECT *,LTRIM(RTRIM(Nombre)) COLLATE Latin1_General_100_CI_AI AS NombreNormalizado FROM Nombres
), Duplicados AS (
    SELECT *,COUNT(*) OVER(PARTITION BY Catalogo,NombreNormalizado) AS Coincidencias FROM Claves
)
SELECT Catalogo,ID,Nombre,PadreID,Coincidencias FROM Duplicados WHERE Coincidencias>1
ORDER BY Catalogo,NombreNormalizado,PadreID,ID;

PRINT '10 - Areas candidatas a revision de denominacion; NO es una orden de traslado';
SELECT a.AreaID,a.Nombre,a.Activo,a.DepartamentoID,
    c.CargoID AS CargoConMismoNombre,c.Nombre AS Cargo,
    (SELECT COUNT(DISTINCT ap.PersonalID) FROM rrhh.AsignacionPersonal ap
        WHERE ap.AreaID=a.AreaID AND ap.EsActual=1 AND ap.Activo=1) AS PersonasConAsignacionActual
FROM organizacion.Area a LEFT JOIN rrhh.Cargo c
    ON LTRIM(RTRIM(c.Nombre)) COLLATE Latin1_General_100_CI_AI=LTRIM(RTRIM(a.Nombre)) COLLATE Latin1_General_100_CI_AI
WHERE c.CargoID IS NOT NULL OR LTRIM(RTRIM(a.Nombre)) COLLATE Latin1_General_100_CI_AI IN
    (N'TEC ENFERMERIA',N'DIGITADORA',N'NUTRICIONISTA',N'ODONTOLOGO',N'PILOTO AMBULANCIA',N'MEDICO AUDITO',N'ENFERMERA',N'LIC ENFERMERIA')
ORDER BY a.Nombre,a.AreaID,c.CargoID;

PRINT '11 - Relaciones para revisar (incluye historial); fechas vacias o servicio vacio no se inventan';
SELECT N'ASIGNACION_SIN_PERSONAL' AS Hallazgo,ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap LEFT JOIN rrhh.Personal p ON p.PersonalID=ap.PersonalID WHERE p.PersonalID IS NULL
UNION ALL
SELECT N'AREA_NO_REGISTRADA',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap LEFT JOIN organizacion.Area a ON a.AreaID=ap.AreaID WHERE a.AreaID IS NULL
UNION ALL
SELECT N'CARGO_NO_REGISTRADO',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap LEFT JOIN rrhh.Cargo c ON c.CargoID=ap.CargoID WHERE c.CargoID IS NULL
UNION ALL
SELECT N'SERVICIO_INCOMPATIBLE_CON_AREA',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
WHERE ap.ServicioID IS NOT NULL AND (s.ServicioID IS NULL OR s.AreaID IS NULL OR ap.AreaID IS NULL OR s.AreaID<>ap.AreaID)
UNION ALL
SELECT N'ACTUAL_EN_AREA_INACTIVA',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap JOIN organizacion.Area a ON a.AreaID=ap.AreaID
WHERE ap.EsActual=1 AND ap.Activo=1 AND a.Activo=0
UNION ALL
SELECT N'ACTUAL_CON_CARGO_INACTIVO',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap JOIN rrhh.Cargo c ON c.CargoID=ap.CargoID
WHERE ap.EsActual=1 AND ap.Activo=1 AND c.Activo=0
UNION ALL
SELECT N'VARIAS_ASIGNACIONES_ACTUALES',ap.AsignacionPersonalID,ap.PersonalID,ap.AreaID,ap.ServicioID,ap.CargoID
FROM rrhh.AsignacionPersonal ap
WHERE ap.EsActual=1 AND ap.Activo=1 AND EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal otra
    WHERE otra.PersonalID=ap.PersonalID AND otra.EsActual=1 AND otra.Activo=1 AND otra.AsignacionPersonalID<>ap.AsignacionPersonalID)
ORDER BY Hallazgo,PersonalID,AsignacionPersonalID;

PRINT '12 - Roles de acceso separados por sistema; no son cargos laborales';
SELECT r.RolID,r.Codigo,r.Nombre,r.Activo,r.SistemaID,s.Codigo AS Sistema,
    (SELECT COUNT(DISTINCT ur.UsuarioID) FROM seguridad.UsuarioRol ur
        WHERE ur.RolID=r.RolID AND ur.Activo=1
        AND (ur.FechaInicio IS NULL OR ur.FechaInicio<=CAST(GETDATE() AS date))
        AND (ur.FechaFin IS NULL OR ur.FechaFin>=CAST(GETDATE() AS date))) AS CuentasConRolVigente,
    (SELECT COUNT(DISTINCT ur.UsuarioID) FROM seguridad.UsuarioRol ur
        JOIN seguridad.Usuario u ON u.UsuarioID=ur.UsuarioID
        WHERE ur.RolID=r.RolID AND r.Activo=1 AND s.Activo=1 AND ur.Activo=1 AND u.Activo=1 AND u.Bloqueado=0
        AND (ur.FechaInicio IS NULL OR ur.FechaInicio<=CAST(GETDATE() AS date))
        AND (ur.FechaFin IS NULL OR ur.FechaFin>=CAST(GETDATE() AS date))
        AND EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us WHERE us.UsuarioID=u.UsuarioID AND us.SistemaID=r.SistemaID
            AND us.Activo=1 AND (us.FechaInicio IS NULL OR us.FechaInicio<=CAST(GETDATE() AS date))
            AND (us.FechaFin IS NULL OR us.FechaFin>=CAST(GETDATE() AS date)))) AS CuentasConRolYVinculoVigentes
FROM seguridad.Rol r LEFT JOIN seguridad.Sistema s ON s.SistemaID=r.SistemaID
ORDER BY s.Codigo,r.Nombre,r.RolID;

PRINT '13 - Cuentas sin vinculo o sin rol RRHH; pueden ser exclusivas de otro sistema, NO habilitar automaticamente';
SELECT u.UsuarioID,u.NombreUsuario,u.PersonalID,u.Activo,u.Bloqueado,
    CASE WHEN EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us JOIN seguridad.Sistema s ON s.SistemaID=us.SistemaID
        WHERE us.UsuarioID=u.UsuarioID AND s.Codigo=@SistemaRRHH) THEN 1 ELSE 0 END AS TieneVinculoRRHH,
    CASE WHEN EXISTS (SELECT 1 FROM seguridad.UsuarioRol ur JOIN seguridad.Rol r ON r.RolID=ur.RolID
        JOIN seguridad.Sistema s ON s.SistemaID=r.SistemaID
        WHERE ur.UsuarioID=u.UsuarioID AND s.Codigo=@SistemaRRHH) THEN 1 ELSE 0 END AS TieneRegistroRolRRHH
FROM seguridad.Usuario u
WHERE NOT EXISTS (SELECT 1 FROM seguridad.UsuarioSistema us JOIN seguridad.Sistema s ON s.SistemaID=us.SistemaID
        WHERE us.UsuarioID=u.UsuarioID AND s.Codigo=@SistemaRRHH)
   OR NOT EXISTS (SELECT 1 FROM seguridad.UsuarioRol ur JOIN seguridad.Rol r ON r.RolID=ur.RolID
        JOIN seguridad.Sistema s ON s.SistemaID=r.SistemaID
        WHERE ur.UsuarioID=u.UsuarioID AND s.Codigo=@SistemaRRHH)
ORDER BY u.UsuarioID;

PRINT '14 - Fichas sin asignacion actual; no equivale a baja laboral';
SELECT p.Activo AS EstadoPersonal,p.CondicionLaboral,COUNT(*) AS FichasSinAsignacionActual
FROM rrhh.Personal p
WHERE NOT EXISTS (SELECT 1 FROM rrhh.AsignacionPersonal ap WHERE ap.PersonalID=p.PersonalID AND ap.EsActual=1 AND ap.Activo=1)
GROUP BY p.Activo,p.CondicionLaboral ORDER BY p.Activo,p.CondicionLaboral;

IF @IncluirDetalleNominal=1
BEGIN
    PRINT '15 - Detalle nominal actual (contiene nombres; compartir solo lo necesario)';
    SELECT p.PersonalID,COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(p.ApellidoPaterno,' ',p.ApellidoMaterno,' ',p.Nombres))),''),p.NombreCompletoOriginal) AS NombreCompleto,
        p.Activo AS EstadoPersonal,p.CondicionLaboral,p.RegimenLaboral,
        ap.AsignacionPersonalID,ap.AreaID,a.Nombre AS Area,ap.ServicioID,s.Nombre AS Servicio,
        ap.CargoID,c.Nombre AS Cargo,p.ProfesionID,pr.Nombre AS Profesion,p.EspecialidadID,e.Nombre AS Especialidad,
        ap.FechaInicio,ap.FechaFin
    FROM rrhh.Personal p
    LEFT JOIN rrhh.AsignacionPersonal ap ON ap.PersonalID=p.PersonalID AND ap.EsActual=1 AND ap.Activo=1
    LEFT JOIN organizacion.Area a ON a.AreaID=ap.AreaID
    LEFT JOIN organizacion.Servicio s ON s.ServicioID=ap.ServicioID
    LEFT JOIN rrhh.Cargo c ON c.CargoID=ap.CargoID
    LEFT JOIN rrhh.Profesion pr ON pr.ProfesionID=p.ProfesionID
    LEFT JOIN rrhh.Especialidad e ON e.EspecialidadID=p.EspecialidadID AND e.ProfesionID=p.ProfesionID
    WHERE @AreaID IS NULL OR ap.AreaID=@AreaID
    ORDER BY a.Nombre,c.Nombre,NombreCompleto,p.PersonalID;
END;
