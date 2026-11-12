#!/usr/bin/env python3
"""Preparación local de la nueva fuente; sin conexión SQL ni modo de carga."""
import csv
import hashlib
import importlib.util
import json
import re
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'private-audits/personal-limpio-v3'
spec=importlib.util.spec_from_file_location('comparison',ROOT/'database/comparar_padron_v3.py')
comparison=importlib.util.module_from_spec(spec);spec.loader.exec_module(comparison)
FIELDS=['DNI','NOMBRE_PILA','APELLIDOS','NOMBRE_COMPLETO','ESTADO','AREA','CARGO','COD_TURNO','DESC_TURNO','HORARIO','DIAS','TOTAL_MARCACIONES','ULTIMA_ASISTENCIA']


def missing(value):
    return comparison.normalize(value) in ('','PENDIENTE','POR ASIGNAR','SIN REGISTRO','NO ESPECIFICADO')


def data_state(area,cargo):
    return 'DATOS_INCOMPLETOS' if missing(area) or missing(cargo) else 'COMPLETO'


def biometric_state(count):
    return 'ACTIVO_CON_MARCACIONES' if count>0 else 'SIN_MARCACIONES'


def numeric_candidate(dni,stats,names):
    """Solo propone coincidencias; nunca modifica identificadores ni aprueba vínculos."""
    if not re.fullmatch(r'[0-9]{8}',dni):return []
    return [uid for uid,info in stats.items() if uid!=dni and re.fullmatch(r'[0-9]{1,8}',uid) and int(uid)==int(dni) and info['names'] and len(info['names'])==1 and comparison.normalize(next(iter(info['names']))) in names]


def surname_proposal(value):
    words=value.split()
    if len(words)==2 and words[0] not in ('DE','DEL','LA','LAS','LOS'):
        return words[0],words[1],'PROPUESTA_POR_CONFIRMAR'
    if len(words)==1:return words[0],'','APELLIDO_UNICO_POR_CONFIRMAR'
    return '','','REVISAR_APELLIDOS_COMPUESTOS'


def analyze():
    source=ROOT/'personal_limpio_asistencia.txt';csvfile=ROOT/'Report_20261015T070613_1.csv'
    schema=json.loads((OUT/'esquema_y_datos_v3.json').read_text())
    source_hash=hashlib.sha256(source.read_bytes()).hexdigest()
    if schema['base']!='BD_HOSPITAL_V3' or schema.get('archivo_sha256')!=source_hash:raise ValueError('La captura SQL no corresponde a esta nueva fuente.')
    rows=[]
    with source.open(encoding='utf-8-sig',newline='') as handle:
        reader=csv.DictReader(handle,delimiter='\t')
        if reader.fieldnames!=FIELDS:raise ValueError('Encabezado no revisado.')
        for row in reader:
            if None in row or any(v is None for v in row.values()):raise ValueError('Fila irregular: '+str(reader.line_num))
            row['_line']=reader.line_num;rows.append(row)
    stats=defaultdict(lambda:{'total':0,'last':None,'names':set()});events=Counter();dates=[]
    with csvfile.open(encoding='utf-8-sig',newline='') as handle:
        for row in csv.DictReader(handle):
            event=row['Evento'].strip();events[event]+=1
            if event!='Autenticación 1:N correcta (Huella)':continue
            date=row['Fecha'].strip();datetime.strptime(date,'%Y-%m-%d %H:%M:%S');dates.append(date)
            raw=row['Usuario'].strip();match=re.fullmatch(r'([^()]+)\s*\((.*)\)',raw)
            uid=match[1].strip() if match else raw;name=match[2].strip() if match else ''
            if not uid or len(uid)>30:raise ValueError('ID BioStar inválido.')
            info=stats[uid];info['total']+=1;info['last']=max(info['last'] or '',date);info['names'].add(name)
    baseline=ROOT/'private-audits/entrega-v3/padron_campos_por_confirmar.csv'
    with baseline.open(encoding='utf-8-sig',newline='') as handle:old={r['DNI']:r for r in csv.DictReader(handle)}
    plans=[];issues=[];used=defaultdict(list);catalogs=[];changes=[]
    def issue(row,field,code,detail,level='PENDIENTE'):
        issues.append({'linea':row['_line'],'campo':field,'codigo':code,'nivel':level,'detalle':detail})
    for row in rows:
        dni=row['DNI'];line=row['_line'];names={comparison.normalize(row['NOMBRE_COMPLETO']),comparison.normalize(row['APELLIDOS']+' '+row['NOMBRE_PILA']),comparison.normalize(row['NOMBRE_PILA']+' '+row['APELLIDOS'])}
        declared=row['TOTAL_MARCACIONES']
        if not re.fullmatch(r'[0-9]+',declared):raise ValueError('Conteo no entero en línea '+str(line))
        bio='';kind='SIN_MARCACIONES_EN_CSV'
        if dni in stats:
            if len(stats[dni]['names'])==1 and comparison.normalize(next(iter(stats[dni]['names']))) in names:bio=dni;kind='ID_EXACTO_Y_NOMBRE'
            else:issue(row,'DNI','NOMBRE_BIOSTAR_DISTINTO','El ID exacto tiene otro nombre; bloquear vinculación.','BLOQUEO')
        if not bio:
            candidates=numeric_candidate(dni,stats,names)
            if len(candidates)==1:bio=candidates[0];kind='CEROS_INICIALES_CANDIDATO'
            elif len(candidates)>1:issue(row,'DNI','ID_BIOSTAR_AMBIGUO','Varios ID equivalentes por valor numérico; no decidir automáticamente.','BLOQUEO')
        count=stats[bio]['total'] if bio else 0;last=stats[bio]['last'] if bio else 'SIN MARCAS'
        if bio:used[bio].append(line)
        if count!=int(declared):issue(row,'TOTAL_MARCACIONES','CONTEO_NO_COINCIDE','El total declarado difiere del CSV de huellas válidas.','BLOQUEO')
        if row['ULTIMA_ASISTENCIA']!=last:issue(row,'ULTIMA_ASISTENCIA','ULTIMA_MARCA_NO_COINCIDE','La última fecha declarada difiere de la marca verificada.','BLOQUEO')
        if not re.fullmatch(r'[0-9]{8}',dni):issue(row,'DNI','DNI_FORMATO','No son ocho dígitos; preservar y revisar, sin recortar ni completar.','BLOQUEO')
        paternal,maternal,surname_status=surname_proposal(row['APELLIDOS'])
        issue(row,'APELLIDOS',surname_status,'Confirmar el reparto entre ApellidoPaterno y ApellidoMaterno antes de cargar.')
        if not row['NOMBRE_PILA'].strip():issue(row,'NOMBRE_PILA','NOMBRE_VACIO','Nombres es obligatorio.','BLOQUEO')
        if not row['NOMBRE_PILA'].strip() or not row['APELLIDOS'].strip():raise ValueError('Nombre incompleto.')
        natural_order=comparison.normalize(row['APELLIDOS']+' '+row['NOMBRE_PILA']);given_order=comparison.normalize(row['NOMBRE_PILA']+' '+row['APELLIDOS'])
        if comparison.normalize(row['NOMBRE_COMPLETO']) not in (natural_order,given_order):issue(row,'NOMBRE_COMPLETO','PARTES_NOMBRE_CONTRADICTORIAS','Las partes no reconstruyen el nombre completo en ningún orden.','BLOQUEO')
        issue(row,'','FALTAN_CODIGO_Y_FECHA_INGRESO','CodigoEmpleado y FechaIngreso siguen sin fuente; no usar fechas de marcación o carga.','BLOQUEO')
        if row['COD_TURNO'] in ('1-2','1-2-3'):issue(row,'COD_TURNO','ROTACION_NO_ES_TURNO_DIARIO','Conjunto rotativo; faltan vigencias y turno de cada fecha.')
        if row['COD_TURNO']=='1' and '8h' in row['HORARIO']:issue(row,'HORARIO','JORNADA_ADMINISTRATIVA_CONTRADICTORIA','Código 1 SQL son 6h; archivo indica 07:00–15:00/8h y AD SQL es 08:00–16:00/8h.','BLOQUEO')
        if row['DNI'] in old:
            for field in ('AREA','CARGO','COD_TURNO','DESC_TURNO','HORARIO','DIAS'):
                if row[field]!=old[dni][field]:changes.append({'linea':line,'campo':field,'anterior':old[dni][field],'nuevo':row[field]})
        plans.append({**{f:row[f] for f in FIELDS},'LINEA_ORIGEN':line,'ESTADO_DATOS':data_state(row['AREA'],row['CARGO']),'ESTADO_BIOMETRICO':biometric_state(count),'BIOSTAR_USER_ID_CANDIDATO':bio,'CRITERIO_COINCIDENCIA':kind,'TOTAL_MARCACIONES_VERIFICADAS':count,'ULTIMA_MARCACION_VERIFICADA':last,'APELLIDO_PATERNO_PROPUESTO':paternal,'APELLIDO_MATERNO_PROPUESTO':maternal,'REVISION_APELLIDOS':surname_status,'PERSONAL_ACTIVO_A_MODIFICAR':'NO','FECHA_CESE_A_MODIFICAR':'NO','VINCULO_APROBADO':'NO','FECHA_INGRESO_CONFIRMADA':'','CODIGO_EMPLEADO_CONFIRMADO':''})
    duplicate_docs={d:n for d,n in Counter(r['DNI'] for r in rows).items() if n>1}
    if duplicate_docs:raise ValueError('Documentos repetidos: bloquear preparación.')
    if any(len(lines)>1 for lines in used.values()):raise ValueError('Un ID BioStar tiene más de un candidato; bloquear preparación.')
    for field,table,key in [('AREA','organizacion.Area','AreaID'),('CARGO','rrhh.Cargo','CargoID')]:
        for value,count in sorted(Counter(r[field] for r in rows).items()):
            result=comparison.catalog_match(value,schema['data'][table],key)
            catalogs.append({'campo':field,'valor':value,'filas':count,**result})
    current_ids=set(used)
    unknown=[{'BiostarUserID':uid,'marcaciones':st['total'],'ultima_marcacion':st['last']} for uid,st in stats.items() if uid not in current_ids]
    totals={'base':'BD_HOSPITAL_V3','source':source.name,'sha256':source_hash,'sha256_csv':hashlib.sha256(csvfile.read_bytes()).hexdigest(),'snapshot_date':schema['fecha'],'registros':len(rows),'estado_origen':dict(Counter(r['ESTADO'] for r in rows)),'estado_datos':dict(Counter(r['ESTADO_DATOS'] for r in plans)),'estado_biometrico':dict(Counter(r['ESTADO_BIOMETRICO'] for r in plans)),'cruce_estados':[{'datos':a,'biometrico':b,'registros':n} for (a,b),n in Counter((r['ESTADO_DATOS'],r['ESTADO_BIOMETRICO']) for r in plans).items()],'candidatos_biometricos':len(used),'candidatos_id_exacto':sum(p['CRITERIO_COINCIDENCIA']=='ID_EXACTO_Y_NOMBRE' for p in plans),'candidatos_ceros_iniciales':sum(p['CRITERIO_COINCIDENCIA']=='CEROS_INICIALES_CANDIDATO' for p in plans),'marcas_candidatas':sum(p['TOTAL_MARCACIONES_VERIFICADAS'] for p in plans),'marcas_validas_csv':sum(st['total'] for st in stats.values()),'usuarios_csv_sin_identificar':len(unknown),'marcas_csv_sin_identificar':sum(s['marcaciones'] for s in unknown),'periodo_csv':[min(dates),max(dates)],'conteos_txt_coincidentes':sum(int(p['TOTAL_MARCACIONES'])==p['TOTAL_MARCACIONES_VERIFICADAS'] for p in plans),'ultimas_fechas_coincidentes':sum(p['ULTIMA_ASISTENCIA']==p['ULTIMA_MARCACION_VERIFICADA'] for p in plans),'sin_area_y_cargo':sum(missing(r['AREA']) and missing(r['CARGO']) for r in rows),'sin_area_y_cargo_con_marcas':sum(missing(p['AREA']) and missing(p['CARGO']) and p['TOTAL_MARCACIONES_VERIFICADAS']>0 for p in plans),'cambios_vs_archivo_anterior':dict(Counter(c['campo'] for c in changes)),'conflictos':dict(Counter(x['codigo'] for x in issues)),'columnas_obligatorias_personal':comparison.required_columns([c for c in schema['columns'] if c['schema_name']+'.'+c['table_name']=='rrhh.Personal']),'apto_para_cargar':False,'personal_cargado':0,'marcaciones_cargadas':0,'vinculos_creados':0,'bajas_laborales':0,'estado_origen_mapea_a_activo':False}
    # Las etiquetas técnicas no se convierten en valores de Personal.Activo o FechaCese.
    for name,value in [('resumen.json',totals),('catalogos_por_revisar.json',catalogs),('usuarios_biostar_sin_identificar.json',unknown)]:comparison.save_json(OUT/name,value)
    comparison.csv_write(OUT/'personal_preparado_revision.csv',list(plans[0]),plans)
    comparison.csv_write(OUT/'conflictos_por_fila.csv',['linea','campo','codigo','nivel','detalle'],issues)
    comparison.csv_write(OUT/'cambios_vs_archivo_anterior.csv',['linea','campo','anterior','nuevo'],changes)
    print(json.dumps(totals,ensure_ascii=False,indent=2));return totals

if __name__=='__main__':analyze()
