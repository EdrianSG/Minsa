<?php
/** Términos de búsqueda compartidos entre la lectura SQL y los respaldos. */
final class PersonalSearch {
    public static function terms(string $query): array {
        return array_values(array_unique(preg_split('/[\s,;]+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    public static function matches(array $person, string $query): bool {
        $values=[];
        foreach (['nombres','apellido_paterno','apellido_materno','nombre_completo','nombre_completo_original',
            'dni','codigo_empleado','cargo_nombre','profesion_nombre','biostar_user_id'] as $field) {
            $values[]=(string)($person[$field] ?? '');
        }
        $text=self::fold(implode(' ',$values));
        foreach (self::terms($query) as $term) {
            if (!str_contains($text,self::fold($term))) return false;
        }
        return true;
    }

    private static function fold(string $value): string {
        return strtr(mb_strtoupper($value,'UTF-8'),[
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
            'À'=>'A','È'=>'E','Ì'=>'I','Ò'=>'O','Ù'=>'U','Â'=>'A','Ê'=>'E','Î'=>'I','Ô'=>'O','Û'=>'U'
        ]);
    }
}
