#!/usr/bin/env python3
"""Comprueba artefactos locales exportados frente a la inspección SQL; no conecta."""
import hashlib
import json
import re
import sys
import zipfile
from collections import defaultdict
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]

def validate(folder):
    capture=json.loads((ROOT/'private-audits/padron-v3/esquema_y_datos_v3.json').read_text())
    extracted=(folder/'BD_HOSPITAL_V3_ESQUEMA_REAL.sql').read_text(encoding='utf-8-sig')
    installer=(folder/'BD_HOSPITAL_V3_INSTALADOR_BASE_NUEVA.sql').read_text(encoding='utf-8-sig')
    expected=defaultdict(set)
    for c in capture['columns']:expected[c['schema_name']+'.'+c['table_name']].add(c['column_name'])
    actual={}
    for batch in re.split(r'^\s*GO\s*$',extracted,flags=re.M|re.I):
        match=re.search(r'CREATE\s+TABLE\s+\[([^\]]+)\]\.\[([^\]]+)\]',batch,re.I)
        if match:actual[match[1]+'.'+match[2]]=set(re.findall(r'^\s*\[([^\]]+)\]\s+\S+',batch,flags=re.M))
    if set(actual)!=set(expected): raise ValueError('Tablas distintas de la captura real.')
    for table,fields in expected.items():
        if actual[table]!=fields:raise ValueError('Columnas distintas: '+table)
    installer_tables=set('.'.join(m) for m in re.findall(r'CREATE\s+TABLE\s+\[([^\]]+)\]\.\[([^\]]+)\]',installer,re.I))
    if installer_tables!=set(expected):raise ValueError('El instalador no conserva las tablas.')
    fk_count=len({(f['parent_schema'],f['parent_table'],f['name']) for f in capture['foreign_keys']})
    if len(re.findall(r'\bFOREIGN\s+KEY\b',extracted,re.I))!=fk_count:raise ValueError('Conteo de FK distinto.')
    if re.search(r'\bDROP\s+DATABASE\b',installer,re.I):raise ValueError('Instalador con borrado de base.')
    if 'DefaultDataPath' in installer or 'DefaultLogPath' in installer:raise ValueError('Rutas de origen incluidas.')
    if installer.index('THROW 51080')>installer.index('CREATE DATABASE'):raise ValueError('Falta rechazo temprano de destino existente.')
    with zipfile.ZipFile(folder/'BD_HOSPITAL_V3_REAL.dacpac') as package:
        if package.testzip() is not None:raise ValueError('DACPAC corrupto.')
    result={'tablas':len(actual),'columnas':sum(map(len,actual.values())),'foreign_keys':fk_count,
      **{key:len(re.findall(r'\bCREATE\s+'+token+r'\b',extracted,re.I)) for key,token in [('views','VIEW'),('procedures','PROCEDURE'),('triggers','TRIGGER'),('functions','FUNCTION')]},
      'instalador_sin_drop_database':True,'dacpac_integridad_zip':True,'restauracion_ejecutada':False,'padron_cargado':False,
      'sha256_instalador':hashlib.sha256((folder/'BD_HOSPITAL_V3_INSTALADOR_BASE_NUEVA.sql').read_bytes()).hexdigest()}
    return result

if __name__=='__main__':
    if len(sys.argv)>1:
        folder=Path(sys.argv[1])
    else:
        candidates=[p for p in sorted((ROOT/'private-audits/entrega-v3').glob('esquema_*')) if (p/'BD_HOSPITAL_V3_INSTALADOR_BASE_NUEVA.sql').is_file()]
        if not candidates:raise ValueError('Primero debe generarse el instalador.')
        folder=candidates[-1]
    result=validate(folder)
    out=ROOT/'private-audits/entrega-v3/validacion_estructura.json'
    out.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');out.chmod(0o600)
    print(json.dumps(result,ensure_ascii=False,indent=2))
