<?php
declare(strict_types=1);
/** Transformaciones de archivos DDL. No abre conexiones ni ejecuta SQL. */
final class SchemaDelivery {
    public static function newDatabaseOnly(string $sql,string $target): string {
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/D',$target))throw new InvalidArgumentException('Nombre de base destino inválido.');
        $sql=preg_replace('/^\xEF\xBB\xBF/','',$sql);
        $dropPattern=<<<'REGEX'
~IF\s*\(DB_ID\(N'\$\(DatabaseName\)'\)\s+IS\s+NOT\s+NULL\)\s*BEGIN\s*ALTER\s+DATABASE\s+\[\$\(DatabaseName\)\]\s+SET\s+SINGLE_USER\s+WITH\s+ROLLBACK\s+IMMEDIATE;\s*DROP\s+DATABASE\s+\[\$\(DatabaseName\)\];\s*END~i
REGEX;
        $exists="IF DB_ID(N'\$(DatabaseName)') IS NOT NULL\n    THROW 51080, N'La base destino ya existe. No se permite sobrescribir.', 1;";
        $sql=preg_replace($dropPattern,$exists,$sql,-1,$dropCount);
        if($dropCount!==1)throw new RuntimeException('Formato DacFx distinto del revisado: no se entrega el instalador.');
        if(preg_match('/\bDROP\s+DATABASE\b/i',$sql))throw new RuntimeException('Queda una instrucción de borrado de base.');
        $createPattern=<<<'REGEX'
~CREATE\s+DATABASE\s+\[\$\(DatabaseName\)\]\s+ON\s+PRIMARY\(NAME\s*=\s*\[\$\(DatabaseName\)\],\s*FILENAME\s*=\s*N'\$\(DefaultDataPath\)\$\(DefaultFilePrefix\)_Primary\.mdf'\)\s+LOG\s+ON\s*\(NAME\s*=\s*\[\$\(DatabaseName\)_log\],\s*FILENAME\s*=\s*N'\$\(DefaultLogPath\)\$\(DefaultFilePrefix\)_Primary\.ldf'\)\s+COLLATE\s+([A-Za-z0-9_]+)~i
REGEX;
        $sql=preg_replace_callback($createPattern,static fn($m)=>'CREATE DATABASE [$(DatabaseName)] COLLATE '.$m[1],$sql,-1,$createCount);
        if($createCount!==1)throw new RuntimeException('Distribución de archivos no reconocida; revisar antes de generar un instalador portable.');
        foreach(['DefaultDataPath','DefaultLogPath','DefaultFilePrefix'] as $variable){
            if(str_contains($sql,'$(' . $variable . ')'))throw new RuntimeException('Quedan referencias de rutas de origen.');
            $sql=preg_replace('/^:setvar '.preg_quote($variable,'/').'[^\r\n]*\r?\n/m','',$sql);
        }
        if(!preg_match('/^:setvar DatabaseName "'.preg_quote($target,'/').'"\r?$/m',$sql))throw new RuntimeException('El destino no coincide con el plan generado.');
        $guard=":on error exit\n:setvar DatabaseName \"$target\"\nUSE [master];\nGO\n$exists\nGO\n";
        $sql=preg_replace('/^:setvar DatabaseName[^\r\n]*\r?\n/m','',$sql);
        return $guard.$sql;
    }
}
