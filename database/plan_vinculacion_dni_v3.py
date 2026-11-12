#!/usr/bin/env python3
"""Prepara candidatos de identidad. No conecta a SQL ni crea vínculos."""
import csv
import hashlib
import importlib.util
import json
import re
from collections import defaultdict
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('comparison',ROOT/'database/comparar_padron_v3.py')
comparison=importlib.util.module_from_spec(spec)
spec.loader.exec_module(comparison)

def build():
    out=ROOT/'private-audits/entrega-v3'
    out.mkdir(exist_ok=True,mode=0o700)
    snapshot=json.loads((ROOT/'private-audits/padron-v3/esquema_y_datos_v3.json').read_text())
    if snapshot['base']!='BD_HOSPITAL_V3': raise ValueError('Solo V3.')
    source=ROOT/'padron_personal_con_turnos.txt'
    csvfile=ROOT/'Report_20261015T070613_1.csv'
    if hashlib.sha256(source.read_bytes()).hexdigest()!=snapshot['archivo_sha256']: raise ValueError('El padrón cambió: repetir inspección.')
    with source.open(encoding='utf-8-sig',newline='') as handle:
        reader=csv.DictReader(handle,delimiter='\t')
        roster={}
        for row in reader:
            if row['DNI'] in roster: raise ValueError('DNI repetido: bloquear plan.')
            row['linea']=reader.line_num
            roster[row['DNI']]=row
    users=defaultdict(lambda:{'nombres':set(),'marcas':0})
    with csvfile.open(encoding='utf-8-sig',newline='') as handle:
        for row in csv.DictReader(handle):
            if row['Evento'].strip()!='Autenticación 1:N correcta (Huella)':continue
            raw=row['Usuario'].strip();match=re.fullmatch(r'([^()]+)\s*\((.*)\)',raw)
            uid=match[1].strip() if match else raw
            name=match[2].strip() if match else ''
            users[uid]['nombres'].add(name)
            users[uid]['marcas']+=1
    dni_types={str(r['TipoDocumentoID']) for r in snapshot['data']['catalogo.TipoDocumento'] if r['Codigo']=='DNI' and comparison.bit(r['Activo'])}
    existing=defaultdict(list)
    for p in snapshot['data']['rrhh.Personal']:
        if str(p['TipoDocumentoID']) in dni_types:existing[p['NumeroDocumento']].append(p['PersonalID'])
    plans=[]
    for bio,info in sorted(users.items()):
        row=roster.get(bio)
        names=info['nombres']
        candidate=bool(row and re.fullmatch(r'[0-9]{8}',bio) and len(names)==1 and comparison.normalize(next(iter(names)))==comparison.normalize(row['APELLIDOS_Y_NOMBRES']))
        plans.append({'BiostarUserID':bio,'NombreBioStar':' | '.join(sorted(names)),'marcaciones':info['marcas'],'DNI_candidato':bio if candidate else '', 'linea_padron':row['linea'] if row else '', 'NombrePadron':row['APELLIDOS_Y_NOMBRES'] if row else '', 'PersonalID_actual':existing[bio][0] if len(existing[bio])==1 else '', 'estado':'CANDIDATO_NO_APROBADO' if candidate else 'REQUIERE_IDENTIFICACION_MANUAL','verificado_por':'','sustento_verificacion':'','aprobado':False})
    path=out/'candidatos_dni_biostar.csv'
    with path.open('w',newline='') as handle:
        writer=csv.DictWriter(handle,fieldnames=list(plans[0]));writer.writeheader();writer.writerows(plans)
    path.chmod(0o600)
    fields=['DNI','APELLIDOS_Y_NOMBRES','AREA','CARGO','REGIMEN','COD_TURNO','DESC_TURNO','HORARIO','DIAS','TELEFONO','EMAIL']
    pending=out/'padron_campos_por_confirmar.csv'
    extra=['linea_origen','Nombres_confirmados','ApellidoPaterno_confirmado','ApellidoMaterno_confirmado','CodigoEmpleado_confirmado','FechaIngreso_confirmada','AreaID_aprobado','CargoID_aprobado','ServicioID_aprobado','FechaInicioAsignacion_confirmada','RegimenLaboral_legal_confirmado','EstadoActivo_confirmado','observaciones_revision']
    with pending.open('w',newline='') as handle:
        writer=csv.DictWriter(handle,fieldnames=fields+extra);writer.writeheader()
        for row in roster.values():writer.writerow({**{field:row[field] for field in fields},'linea_origen':row['linea']})
    pending.chmod(0o600)
    result={'modo':'ARCHIVOS_LOCALES_SIN_SQL','sha256_csv':hashlib.sha256(csvfile.read_bytes()).hexdigest(),'sha256_padron':snapshot['archivo_sha256'],'usuarios_biostar':len(plans),'candidatos_no_aprobados':sum(p['estado']=='CANDIDATO_NO_APROBADO' for p in plans),'marcas_de_candidatos':sum(p['marcaciones'] for p in plans if p['estado']=='CANDIDATO_NO_APROBADO'),'identificacion_manual':sum(p['estado']=='REQUIERE_IDENTIFICACION_MANUAL' for p in plans),'personal_id_existente_en_candidatos':sum(p['PersonalID_actual']!='' for p in plans),'vinculos_aprobados':0,'vinculos_creados':0,'padron_filas_por_confirmar':len(roster),'apto_para_cargar':False}
    path=out/'plan_dni_biostar_resumen.json';path.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');path.chmod(0o600)
    print(json.dumps(result,ensure_ascii=False,indent=2))
    return result

if __name__=='__main__':build()
