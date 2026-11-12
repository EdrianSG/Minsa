<?php
declare(strict_types=1);
require_once __DIR__.'/../database/SchemaDelivery.php';
$raw=<<<'SQL'
:setvar DatabaseName "BD_HOSPITAL_V3_ENTREGA_NUEVA"
:setvar DefaultDataPath "C:\origen\"
:setvar DefaultLogPath "C:\origen\"
:setvar DefaultFilePrefix "BD_HOSPITAL_V3_ENTREGA_NUEVA"
IF (DB_ID(N'$(DatabaseName)') IS NOT NULL)
BEGIN
    ALTER DATABASE [$(DatabaseName)] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE [$(DatabaseName)];
END
GO
CREATE DATABASE [$(DatabaseName)]
    ON PRIMARY(NAME = [$(DatabaseName)], FILENAME = N'$(DefaultDataPath)$(DefaultFilePrefix)_Primary.mdf')
    LOG ON (NAME = [$(DatabaseName)_log], FILENAME = N'$(DefaultLogPath)$(DefaultFilePrefix)_Primary.ldf') COLLATE Modern_Spanish_CI_AS
GO
CREATE SCHEMA [rrhh];
GO
SQL;
$checks=0;
function scriptCheck(bool $ok,string $why):void{global $checks;++$checks;if(!$ok)throw new RuntimeException($why);}
function rejected(callable $action):void{try{$action();}catch(RuntimeException|InvalidArgumentException $e){scriptCheck(true,'Rechazo esperado.');return;}throw new RuntimeException('No se rechazó el formato inseguro.');}
$out=SchemaDelivery::newDatabaseOnly("\xEF\xBB\xBF".$raw,'BD_HOSPITAL_V3_ENTREGA_NUEVA');
scriptCheck(!preg_match('/DROP\s+DATABASE/i',$out),'No permitir borrados, incluso después del guard.');
scriptCheck(substr_count($out,':setvar DatabaseName')===1,'Un único destino para el guard y para la creación.');
scriptCheck(str_starts_with($out,':on error exit'),'Detener sqlcmd ante error.');
scriptCheck(strpos($out,'THROW 51080')<strpos($out,'CREATE DATABASE'),'Rechazar destino existente antes de crear.');
scriptCheck(!str_contains($out,'DefaultDataPath')&&!str_contains($out,'DefaultLogPath'),'Rutas de destino por defecto, sin rutas de origen.');
scriptCheck(str_contains($out,'COLLATE Modern_Spanish_CI_AS')&&str_contains($out,'CREATE SCHEMA [rrhh]'),'Conservar collation y objetos.');
scriptCheck(!str_contains($out,"\xEF\xBB\xBF"),'Sin BOM en mitad del script.');
rejected(static fn()=>SchemaDelivery::newDatabaseOnly($raw,'base;DROP'));
rejected(static fn()=>SchemaDelivery::newDatabaseOnly($raw,'OtraBase'));
rejected(static fn()=>SchemaDelivery::newDatabaseOnly($raw."\nDROP DATABASE [ajena];",'BD_HOSPITAL_V3_ENTREGA_NUEVA'));
rejected(static fn()=>SchemaDelivery::newDatabaseOnly(str_replace('DROP DATABASE [$(DatabaseName)];','',$raw),'BD_HOSPITAL_V3_ENTREGA_NUEVA'));
rejected(static fn()=>SchemaDelivery::newDatabaseOnly(str_replace('_Primary.mdf','_otro_archivo.mdf',$raw),'BD_HOSPITAL_V3_ENTREGA_NUEVA'));
echo "OK: $checks comprobaciones del instalador de base nueva; sin ejecutar SQL.\n";
