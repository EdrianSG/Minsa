#!/usr/bin/env python3
"""Pruebas locales del análisis, sin conectarse ni modificar la base hospitalaria."""
import csv
import hashlib
import importlib.util
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('preflight',ROOT/'database/comparar_padron_v3.py')
mod=importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)
checks=0
def check(value,description):
    global checks
    if not value: raise AssertionError(description)
    checks+=1

check(not mod.bit('0') and not mod.bit(False) and mod.bit('1') and mod.bit(True),'Flags de SQL interpretados correctamente.')
columns=[{'column_name':'dato','is_nullable':'0','is_identity':'0','is_computed':'0','default_definition':None}, {'column_name':'id','is_nullable':'0','is_identity':'1','is_computed':'0','default_definition':None}, {'column_name':'opcional','is_nullable':'1','is_identity':'0','is_computed':'0','default_definition':None}]
check(mod.required_columns(columns)==['dato'],'No confundir columnas obligatorias, opcionales e IDENTITY.')
match=mod.catalog_match('ENFERMERÍA',[{'AreaID':'12','Nombre':'ENFERMERIA','Activo':'1'}],'AreaID')
check(match['exact_ids']==[] and match['normalized_candidate_ids']==['12'] and not match['approved'],'Normalización propone candidato, no autoriza.')
match=mod.catalog_match('ENFERMERIA',[{'AreaID':'12','Nombre':'ENFERMERIA','Activo':'1'},{'AreaID':'13','Nombre':'ENFERMERÍA','Activo':'1'}],'AreaID')
check(len(match['normalized_candidate_ids'])==2 and not match['approved'],'Ambigüedad no resuelta automáticamente.')
check(mod.catalog_match('X',[{'AreaID':'1','Nombre':'X','Activo':'0'}],'AreaID')['exact_ids']==[],'Excluir catálogos inactivos.')
folder=ROOT/'private-audits/padron-v3'
r=json.loads((folder/'esquema_y_datos_v3.json').read_text())
s=json.loads((folder/'comparacion_resumen.json').read_text())
check(r['base']==s['base']=='BD_HOSPITAL_V3','Base real esperada.')
check(r['archivo_sha256']==s['sha256']==hashlib.sha256((ROOT/'padron_personal_con_turnos.txt').read_bytes()).hexdigest(),'El archivo original conserva su hash.')
check(not s['apto_para_cargar'] and s['filas_con_conflicto']==s['registros'],'No aprobar filas con campos obligatorios pendientes.')
check(s['duplicados_archivo']=={'DNI':0,'APELLIDOS_Y_NOMBRES':0},'Conteo de duplicados del archivo.')
check(s['conflictos_por_tipo']['DNI_FORMATO']==1,'Detectar documento de longitud incompatible.')
check(s['conflictos_por_tipo']['HORARIO_8H_NO_COINCIDE']==14,'Detectar duración administrativa contradictoria.')
check(s['conflictos_por_tipo']['ROTACION_NO_ES_TURNO']==167,'No equiparar alternativas rotativas a turnos continuos.')
check(s['conflictos_por_tipo']['SIN_HORARIO']==269,'Mantener sin horario las filas S/T.')
check(s['staging']['coinciden_id']==444 and len(s['staging']['lineas_con_otro_id_y_mismo_nombre'])==6,'Identificar cambios potenciales de identificador.')
check(s['staging']['nombres_normalizados_ausentes_en_archivo']==3,'No borrar las tres ausencias del staging.')
required=set(s['obligatorios_sin_default']['rrhh.Personal'])
check(required=={'CodigoEmpleado','TipoDocumentoID','NumeroDocumento','Nombres','ApellidoPaterno','FechaIngreso'},'Campos obligatorios calculados desde metadatos reales.')
check('FechaInicio' in s['obligatorios_sin_default']['rrhh.Horario'],'No inventar vigencia de horario.')
check(all(not f['is_disabled'] and not f['is_not_trusted'] for f in r['foreign_keys']),'Relaciones habilitadas y verificadas.')
refs={x['table']+'.'+x['column']:x for x in r['personal_inbound_references']}
check(refs['laboratorio.Resultado.ResponsablePersonalID']['non_null']==140 and refs['laboratorio.Solicitud.SolicitantePersonalID']['non_null']==85,'Advertir dependencias del trabajador existente.')
check(all(x['orphans']==0 for x in refs.values()),'Sin huérfanos en referencias a Personal.')
check(not s['coincidencias_personal_existente'],'Ningún documento DNI del archivo en Personal operativo.')
with (folder/'conflictos_por_fila.csv').open() as handle:
    issues=list(csv.DictReader(handle))
check(any(i['linea']=='159' and i['conflicto']=='DNI_FORMATO' for i in issues),'Detalle por línea reproducible.')
print(f'OK: {checks} comprobaciones locales; no se ejecutaron escrituras SQL.')
