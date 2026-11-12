#!/usr/bin/env python3
"""Pruebas de la nueva fuente y estados técnicos. No conecta ni escribe en SQL."""
import csv
import hashlib
import importlib.util
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('clean',ROOT/'database/preparar_personal_limpio_v3.py')
mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
count=0
def check(condition,label):
    global count
    if not condition:raise AssertionError(label)
    count+=1

check(mod.data_state('AREA','CARGO')=='COMPLETO','Dos campos informados.')
check(mod.data_state('PENDIENTE','CARGO')=='DATOS_INCOMPLETOS','Falta solo área.')
check(mod.data_state('AREA','PENDIENTE')=='DATOS_INCOMPLETOS','Falta solo cargo.')
check(mod.data_state('','')=='DATOS_INCOMPLETOS','Faltan ambos.')
check(mod.biometric_state(1)=='ACTIVO_CON_MARCACIONES','Marca válida indica actividad solo en el período.')
check(mod.biometric_state(0)=='SIN_MARCACIONES','Sin marcas no equivale a baja.')
stats={'1234567':{'total':3,'names':{'APELLIDOS NOMBRES'}}}
check(mod.numeric_candidate('01234567',stats,{'APELLIDOS NOMBRES'})==['1234567'],'Proponer BioStar sin cero inicial conservando el DNI de ocho dígitos.')
check(list(stats)==['1234567'],'No modificar el ID original del dispositivo.')
check(mod.numeric_candidate('01234567',stats,{'OTRA PERSONA'})==[],'Igual número con nombre diferente no se propone.')
check(mod.numeric_candidate('001234567',stats,{'APELLIDOS NOMBRES'})==[],'No tratar un documento de nueve dígitos como DNI válido.')
ambiguous={'123456':{'total':2,'names':{'NOMBRE'}},'0123456':{'total':3,'names':{'NOMBRE'}}}
check(len(mod.numeric_candidate('00123456',ambiguous,{'NOMBRE'}))==2,'Las coincidencias ambiguas se devuelven para bloqueo, no se escoge una.')
check(mod.surname_proposal('DE GARCIA')[2]=='REVISAR_APELLIDOS_COMPUESTOS','No dividir automáticamente un apellido compuesto.')
check(mod.surname_proposal('GARCIA LOPEZ')[2]=='PROPUESTA_POR_CONFIRMAR','La división simple es propuesta, no aprobación.')
out=ROOT/'private-audits/personal-limpio-v3'
summary=json.loads((out/'resumen.json').read_text())
with (out/'personal_preparado_revision.csv').open() as f:rows=list(csv.DictReader(f))
check(summary['registros']==450 and len(rows)==450,'450 registros conservados.')
check(summary['estado_datos']=={'COMPLETO':302,'DATOS_INCOMPLETOS':148},'Incompletos con regla área y/o cargo, no solo ambos ausentes.')
check(summary['estado_biometrico']=={'ACTIVO_CON_MARCACIONES':269,'SIN_MARCACIONES':181},'Indicadores biométricos independientes.')
check(summary['sin_area_y_cargo']==80 and summary['sin_area_y_cargo_con_marcas']==22,'Proteger los 22 casos con marcas y sin asignación.')
check(summary['candidatos_id_exacto']==266 and summary['candidatos_ceros_iniciales']==3,'Identificar los tres ceros iniciales sin alterar documentos.')
check(summary['marcas_candidatas']==41375 and summary['marcas_csv_sin_identificar']==7,'Conservar el total de marcas y pendientes.')
check(summary['conteos_txt_coincidentes']==450 and summary['ultimas_fechas_coincidentes']==450,'Todos los conteos y últimas marcas se contrastaron con CSV.')
check(not summary['estado_origen_mapea_a_activo'] and summary['bajas_laborales']==0,'Prohibida la baja por estado técnico.')
check(all(r['PERSONAL_ACTIVO_A_MODIFICAR']=='NO' and r['FECHA_CESE_A_MODIFICAR']=='NO' for r in rows),'Sin propuestas de cambio de actividad laboral o cese.')
check(all(r['VINCULO_APROBADO']=='NO' for r in rows),'Ningún candidato se aprueba por similitud.')
check(not summary['apto_para_cargar'] and summary['personal_cargado']==summary['marcaciones_cargadas']==summary['vinculos_creados']==0,'No adelantar la carga.')
check(summary['sha256']==hashlib.sha256((ROOT/'personal_limpio_asistencia.txt').read_bytes()).hexdigest(),'Fuente original intacta.')
bydni={r['DNI']:r for r in rows}
for dni,expected in [('75479848',389),('19238314',229),('47260044',194),('19250574',192)]:
    r=bydni[dni]
    check(r['ESTADO_DATOS']=='DATOS_INCOMPLETOS' and r['ESTADO_BIOMETRICO']=='ACTIVO_CON_MARCACIONES' and int(r['TOTAL_MARCACIONES_VERIFICADAS'])==expected,'Caso verificado por RR. HH. conservado: '+dni)
check(summary['conflictos']['DNI_FORMATO']==1 and summary['conflictos']['JORNADA_ADMINISTRATIVA_CONTRADICTORIA']==14,'Los conflictos anteriores no se ocultan con los indicadores.')
print(f'OK: {count} comprobaciones; estados técnicos separados, ceros iniciales preservados y ninguna escritura SQL.')
