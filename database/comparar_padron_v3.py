#!/usr/bin/env python3
"""Compara archivos locales; no abre conexiones ni tiene operaciones de carga/borrado."""
import csv
import hashlib
import json
import re
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'private-audits/padron-v3'
FIELDS = ['DNI','APELLIDOS_Y_NOMBRES','AREA','CARGO','REGIMEN','COD_TURNO','DESC_TURNO','HORARIO','DIAS','TELEFONO','EMAIL']

def normalize(value):
    return ' '.join(''.join(c for c in unicodedata.normalize('NFKD', value or '') if not unicodedata.combining(c)).upper().split())

def bit(value):
    return str(value).lower() in ('1','true')

def required_columns(columns):
    return [c['column_name'] for c in columns if not bit(c['is_nullable']) and not bit(c['is_identity']) and not bit(c['is_computed']) and c['default_definition'] is None]

def catalog_match(value, items, id_field):
    exact = [x for x in items if value == x['Nombre'] and bit(x['Activo'])]
    normalized = [x for x in items if normalize(value) == normalize(x['Nombre']) and bit(x['Activo'])]
    return {'exact_ids':[x[id_field] for x in exact], 'normalized_candidate_ids':[x[id_field] for x in normalized], 'approved':False}

def save_json(path, value):
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2)+'\n')
    path.chmod(0o600)

def csv_write(path, fields, rows):
    with path.open('w',newline='') as handle:
        writer=csv.DictWriter(handle,fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)
    path.chmod(0o600)

def compare():
    schema=json.loads((OUT/'esquema_y_datos_v3.json').read_text())
    if schema['base']!='BD_HOSPITAL_V3':
        raise ValueError('Se requiere el esquema real de V3.')
    source=ROOT/'padron_personal_con_turnos.txt'
    digest=hashlib.sha256(source.read_bytes()).hexdigest()
    if digest!=schema['archivo_sha256']:
        raise ValueError('El padrón cambió desde la inspección SQL: repetir inspección.')
    rows=[]
    with source.open(encoding='utf-8-sig',newline='') as handle:
        reader=csv.DictReader(handle,delimiter='\t')
        if reader.fieldnames!=FIELDS: raise ValueError('Encabezados distintos de los revisados.')
        for row in reader:
            if None in row or any(v is None for v in row.values()): raise ValueError(f'Fila irregular en línea {reader.line_num}')
            row['_line']=reader.line_num
            rows.append(row)
    issues=[]
    def issue(row,field,code,detail):
        issues.append({'linea':row['_line'],'campo':field,'conflicto':code,'valor_origen':row[field] if field in row else '', 'detalle':detail})
    columns=defaultdict(list)
    for c in schema['columns']: columns[c['schema_name']+'.'+c['table_name']].append(c)
    for row in rows:
        if not re.fullmatch(r'[0-9]{8}',row['DNI']):issue(row,'DNI','DNI_FORMATO','No cumple ocho dígitos; no corregir, recortar ni completar automáticamente.')
        if not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+',row['EMAIL']):issue(row,'EMAIL','CORREO_FORMATO','Campo opcional: revisar o confirmar ausencia; no cargar texto inválido como correo.')
        if not re.fullmatch(r'\+?[0-9 ()/.,;-]+',row['TELEFONO']):issue(row,'TELEFONO','TELEFONO_REVISAR','Revisar valor no numérico; no generar un número de contacto.')
        for field in ['AREA','CARGO']:
            if normalize(row[field])=='POR ASIGNAR': issue(row,field,'SIN_ASIGNACION_CONFIRMADA','Es un marcador de ausencia, no un catálogo organizacional confirmado.')
        issue(row,'APELLIDOS_Y_NOMBRES','NOMBRE_NO_DESGLOSADO','NombreCompletoOriginal existe; Nombres y ApellidoPaterno son NOT NULL y el texto no aporta una separación confirmada.')
        issue(row,'REGIMEN','CLASIFICACION_NO_ES_REGIMEN_LEGAL','El CHECK de RegimenLaboral admite DL 276, DL 728, DL 1057 CAS, DL 1153, LEY 30057, LOCACION y OTRO. No convertir la clasificación del archivo en régimen legal.')
        if len(row['REGIMEN'])>20:issue(row,'REGIMEN','LONGITUD_REGIMEN','Supera varchar(20); tampoco es un valor admitido por el CHECK.')
        issue(row,'','FALTAN_CODIGO_Y_FECHA_INGRESO','CodigoEmpleado y FechaIngreso son NOT NULL, sin DEFAULT, y no están en el archivo. No usar fecha de carga ni alta biométrica como ingreso.')
        if row['COD_TURNO'] in ['1-2','1-2-3']:
            issue(row,'COD_TURNO','ROTACION_NO_ES_TURNO','Es un conjunto de alternativas; no equivale al turno continuo 1/2 o 1/2/3, ni indica el turno de cada día.')
        elif row['COD_TURNO']=='S/T':issue(row,'COD_TURNO','SIN_HORARIO','Sin turno/días confirmados. No generar horarios ni programación.')
        elif row['COD_TURNO']=='1' and '8h' in row['HORARIO']:
            issue(row,'HORARIO','HORARIO_8H_NO_COINCIDE','Archivo: 07:00–15:00/8h con código 1. SQL: código 1=07:00–13:00/6h y AD=08:00–16:00/8h. Requiere decisión explícita.')
        if row['COD_TURNO']!='S/T':issue(row,'DIAS','FALTA_VIGENCIA_Y_ASIGNACION_DIARIA','Horario exige FechaInicio y un TurnoID por fila; la programación mensual exige año, mes, día y código concreto. El archivo no aporta esa información completa.')
    duplicates={}
    for field in ['DNI','APELLIDOS_Y_NOMBRES']:
        groups=defaultdict(list)
        for row in rows:groups[row[field]].append(row)
        duplicates[field]=sum(len(group)>1 for group in groups.values())
        for group in groups.values():
            if len(group)>1:
                for row in group:issue(row,field,'DUPLICADO_ARCHIVO','Mismo valor en varias filas del archivo; bloquear hasta revisar.')
    matches=[]
    for field,table,id_field in [('AREA','organizacion.Area','AreaID'),('CARGO','rrhh.Cargo','CargoID')]:
        counts=Counter(r[field] for r in rows)
        for value,count in sorted(counts.items()):
            match=catalog_match(value,schema['data'].get(table,[]),id_field)
            matches.append({'campo':field,'valor_origen':value,'trabajadores':count,'tabla':table,**match})
            if len(match['exact_ids'])!=1:
                for row in rows:
                    if row[field]==value:issue(row,field,'CATALOGO_SIN_COINCIDENCIA_EXACTA','No hay un único nombre activo idéntico. Las coincidencias normalizadas son propuestas, no asignaciones aprobadas.')
    personal=schema['data'].get('rrhh.Personal',[])
    dni_type={x['TipoDocumentoID'] for x in schema['data'].get('catalogo.TipoDocumento',[]) if x['Codigo']=='DNI' and bit(x['Activo'])}
    existing_dni=defaultdict(list)
    for person in personal:
        if person['TipoDocumentoID'] in dni_type: existing_dni[person['NumeroDocumento']].append(person)
    source_existing=[]
    for row in rows:
        if row['DNI'] in existing_dni:
            candidates=existing_dni[row['DNI']]
            source_existing.append({'linea':row['_line'],'personal_ids':[p['PersonalID'] for p in candidates]})
            for p in candidates:
                name=p.get('NombreCompletoOriginal') or ' '.join(filter(None,[p['ApellidoPaterno'],p['ApellidoMaterno'],p['Nombres']]))
                if normalize(name)!=normalize(row['APELLIDOS_Y_NOMBRES']): issue(row,'APELLIDOS_Y_NOMBRES','DOCUMENTO_EXISTENTE_NOMBRE_DISTINTO','Mismo documento en V3 con nombre distinto: no sobrescribir ni crear duplicado.')
    staging=schema['data'].get('dbo.StagingPersonalPacasmayo',[])
    staging_by_id=defaultdict(list)
    for item in staging:staging_by_id[item['Identificador']].append(item)
    staging_by_name=defaultdict(list)
    for item in staging: staging_by_name[normalize(item['NombreCompleto'])].append(item)
    name_candidates=[]
    source_names={normalize(r['APELLIDOS_Y_NOMBRES']) for r in rows}
    file_ids={r['DNI'] for r in rows}
    staging_check={'total':len(staging),'identificadores_duplicados':sum(len(v)>1 for v in staging_by_id.values()),'coinciden_id':0,'coinciden_id_nombre_exacto':0,'coinciden_id_nombre_normalizado':0,'lineas_no_en_staging':[], 'lineas_nombre_distinto':[], 'identificadores_staging_ausentes_en_archivo':[x['Identificador'] for x in staging if x['Identificador'] not in file_ids]}
    staging_details=[]
    for row in rows:
        items=staging_by_id.get(row['DNI'],[])
        if not items:
            staging_check['lineas_no_en_staging'].append(row['_line'])
            issue(row,'DNI','IDENTIFICADOR_NO_EN_STAGING','El identificador del archivo no figura en el staging de 453 registros. Verificar procedencia.')
            candidates=staging_by_name.get(normalize(row['APELLIDOS_Y_NOMBRES']),[])
            if candidates:
                issue(row,'DNI','IDENTIFICADOR_CAMBIADO_MISMO_NOMBRE','El nombre coincide con staging bajo otro identificador; posible corrección o identidad distinta. No crear ni enlazar automáticamente.')
                for item in candidates:
                    name_candidates.append({'linea':row['_line'],'identificador_archivo':row['DNI'],'identificador_staging':item['Identificador'],'nombre_archivo':row['APELLIDOS_Y_NOMBRES'],'nombre_staging':item['NombreCompleto'],'aprobado':False})
        else:
            staging_check['coinciden_id']+=1
            staging_check['coinciden_id_nombre_exacto']+=int(any(row['APELLIDOS_Y_NOMBRES']==x['NombreCompleto'] for x in items))
            same=any(normalize(row['APELLIDOS_Y_NOMBRES'])==normalize(x['NombreCompleto']) for x in items)
            staging_check['coinciden_id_nombre_normalizado']+=int(same)
            if not same:
                staging_check['lineas_nombre_distinto'].append(row['_line'])
                issue(row,'DNI','STAGING_ID_CON_NOMBRE_DISTINTO','Identificador conocido, nombre distinto. Bloquear vínculo.')
            fields={'AREA':'Departamento','CARGO':'CargoExcel','REGIMEN':'TipoPersonal','COD_TURNO':'TurnosExcel','HORARIO':'HoraExcel','DIAS':'DiaExcel','TELEFONO':'Telefono','EMAIL':'Correo'}
            for src,dst in fields.items():
                original=items[0].get(dst)
                if row[src]!=(original or ''):
                    staging_details.append({'linea':row['_line'],'campo':src,'valor_staging':original,'valor_archivo':row[src]})
    staging_check['lineas_con_otro_id_y_mismo_nombre']=[c['linea'] for c in name_candidates]
    staging_check['nombres_normalizados_ausentes_en_archivo']=sum(normalize(x['NombreCompleto']) not in source_names for x in staging)
    staging_check['nombres_archivo_con_candidato_en_staging']=sum(normalize(row['APELLIDOS_Y_NOMBRES']) in staging_by_name for row in rows)
    required={t:required_columns(cols) for t,cols in columns.items() if t.startswith(('rrhh.','organizacion.','seguridad.'))}
    summary={'fecha_esquema':schema['fecha'],'base':schema['base'],'modo':'COMPARACION_LOCAL_SIN_ESCRITURAS_SQL','sha256':digest,'registros':len(rows),'campos':len(FIELDS),'tablas_inspeccionadas':len(columns),'columnas_inspeccionadas':len(schema['columns']),'claves_foraneas_inspeccionadas':len({(f['parent_schema'],f['parent_table'],f['name']) for f in schema['foreign_keys']}),'pares_columnas_fk':len(schema['foreign_keys']),'duplicados_archivo':duplicates,'categorias':{field:dict(Counter(r[field] for r in rows)) for field in ['REGIMEN','COD_TURNO','DIAS']},'maximos_caracteres':{f:max(len(r[f]) for r in rows) for f in FIELDS},'conflictos_por_tipo':dict(Counter(x['conflicto'] for x in issues)),'filas_con_conflicto':len({x['linea'] for x in issues}),'coincidencias_personal_existente':source_existing,'staging':staging_check,'cambios_respecto_staging_por_campo':dict(Counter(x['campo'] for x in staging_details)),'catalogos':{field:{'etiquetas':sum(m['campo']==field for m in matches),'etiquetas_exactas':sum(m['campo']==field and len(m['exact_ids'])==1 for m in matches),'filas_exactas':sum(m['trabajadores'] for m in matches if m['campo']==field and len(m['exact_ids'])==1),'etiquetas_con_candidato_normalizado':sum(m['campo']==field and len(m['normalized_candidate_ids'])==1 for m in matches)} for field in ['AREA','CARGO']},'obligatorios_sin_default':required,'estado':'BLOQUEADO_NO_HAY_COINCIDENCIA_TOTAL','apto_para_cargar':False}
    save_json(OUT/'comparacion_resumen.json',summary)
    save_json(OUT/'coincidencias_catalogos.json',matches)
    csv_write(OUT/'conflictos_por_fila.csv',['linea','campo','conflicto','valor_origen','detalle'],issues)
    csv_write(OUT/'posibles_cambios_identificador.csv',['linea','identificador_archivo','identificador_staging','nombre_archivo','nombre_staging','aprobado'],name_candidates)
    csv_write(OUT/'cambios_respecto_staging.csv',['linea','campo','valor_staging','valor_archivo'],staging_details)
    csv_write(OUT/'diccionario_real_v3.csv',list(schema['columns'][0]),schema['columns'])
    printable={k:v for k,v in summary.items() if k not in ['obligatorios_sin_default','staging']}
    printable['staging']={k:v for k,v in staging_check.items() if k!='identificadores_staging_ausentes_en_archivo'}
    printable['staging']['ausentes_en_archivo']=len(staging_check['identificadores_staging_ausentes_en_archivo'])
    print(json.dumps(printable,ensure_ascii=False,indent=2))
    return summary

if __name__=='__main__': compare()
