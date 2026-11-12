<?php
/** Relaciones explícitas del mapa PMYO; no asigna ni acredita profesiones de trabajadores. */
final class PmyoCatalog {
    public static function map(): array { return require __DIR__.'/../config/pmyo_personal_map.php'; }
    public static function key(?string $value): string { return mb_strtoupper(trim(preg_replace('/\s+/u',' ', $value??'')),'UTF-8'); }
    public static function areas(): array {
        $m=self::map(); $areas=[];
        foreach ($m['operativo'] as $name=>$item) $areas[$name]=['perfiles'=>$item['perfiles'],'total'=>$item['total'],'operativa'=>true];
        foreach ($m['complementario'] as $name=>$items) $areas[$name]=['perfiles'=>$items,'total'=>null,'operativa'=>false];
        return $areas;
    }
    public static function profiles(): array {
        $items=self::map()['formacion'];
        foreach (self::areas() as $area) $items=array_merge($items,$area['perfiles']);
        $items=array_values(array_unique($items)); sort($items,SORT_STRING); return $items;
    }
    public static function enrich(array $data): array {
        $m=self::map(); $areas=self::areas();
        $profession=[]; foreach ($data['profesiones'] as $p) $profession[$p['codigo']]=$p;
        foreach ($data['areas'] as &$a) {
            $entry=$areas[self::key($a['nombre'])]??null;
            $a['pmyo']=$entry!==null; $a['operativa_pmyo']=$entry['operativa']??false;
            $a['perfiles_origen']=$entry['perfiles']??[]; $a['dotacion_origen']=$entry['total']??null;
        } unset($a);
        foreach ($data['cargos'] as &$c) {
            $name=self::key($c['nombre']); $proposal=$m['propuestas'][$name]??null;
            $c['perfil_pmyo']=in_array($name,self::profiles(),true);
            $c['formacion']=in_array($name,$m['formacion'],true);
            $c['profesion_propuesta_id']=$proposal ? ($profession[$proposal[0]]['id']??null) : null;
            $c['especialidad_propuesta_id']=null;
            if ($proposal && $proposal[1]!==null) foreach ($data['especialidades'] as $e) {
                if ((int)$e['profesion_id']===(int)$c['profesion_propuesta_id'] && self::key($e['nombre'])===self::key($proposal[1])) $c['especialidad_propuesta_id']=$e['id'];
            }
            $c['areas_pmyo']=[];
            foreach ($data['areas'] as $a) if (in_array($name,$a['perfiles_origen'],true)) $c['areas_pmyo'][]=$a['id'];
        } unset($c);
        return $data;
    }
}
