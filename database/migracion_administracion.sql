SET XACT_ABORT ON;
IF OBJECT_ID(N'seguridad.ParametrosRRHH', N'U') IS NULL
BEGIN
    CREATE TABLE seguridad.ParametrosRRHH (
        ParametrosID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        VigenteDesde DATE NOT NULL,
        Datos NVARCHAR(MAX) NOT NULL CHECK (ISJSON(Datos) = 1),
        UsuarioID INT NOT NULL REFERENCES seguridad.Usuario(UsuarioID),
        CreadoEn DATETIME2 NOT NULL DEFAULT SYSDATETIME()
    );
    CREATE INDEX IX_ParametrosRRHH_Vigencia ON seguridad.ParametrosRRHH (VigenteDesde DESC, ParametrosID DESC);
END;
GO
CREATE OR ALTER TRIGGER seguridad.TR_SIGH_Revision_ParametrosRRHH
ON seguridad.ParametrosRRHH AFTER INSERT, UPDATE, DELETE AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM inserted) OR EXISTS (SELECT 1 FROM deleted)
        UPDATE seguridad.VersionDatos SET Revision = Revision + 1;
END;
