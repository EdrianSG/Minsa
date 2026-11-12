#!/usr/bin/env python3
"""Valida el plan autorizado local, sin ejecutar SQL."""
import json,hashlib,re
from collections import Counter
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=json.loads((ROOT/'private-audits/carga-personal-v3/plan_autorizado.json').read_text());checks=0

def check(ok,label):
 global checks
 if not ok:raise AssertionError(label)
 checks+=1

rows=p['filas']
check(len(rows)==450,'450 fichas de fuente.')
check(p['fuente_sha256']==hashlib.sha256((ROOT/p['fuente']).read_bytes()).hexdigest(),'Fuente intacta.')
check(p['csv_sha256']==hashlib.sha256((ROOT/p['csv_biostar']).read_bytes()).hexdigest(),'CSV contrastado intacto.')
check(all('Activo' not in r['datos'] and 'FechaCese' not in r['datos'] for r in rows),'No derivar actividad o cese de estados técnicos.')
check(all(r['datos']['FechaIngreso'] is None and r['datos']['CodigoEmpleado'] is None for r in rows),'No inventar contratación o código de empleado.')
check(sum(r['datos']['NumeroDocumento'] is None and r['datos']['TipoDocumentoID'] is None for r in rows)==1,'Documento inválido preservado como pendiente, no DNI ficticio.')
check(all(r['datos']['NumeroDocumento'] is None or re.fullmatch('[0-9]{8}',r['datos']['NumeroDocumento']) for r in rows),'Solo DNI con formato admitido.')
check(sum(r['datos']['ApellidoPaterno'] is None for r in rows)==10,'No inventar reparto de apellidos compuestos.')
check(sum(r['asignacion'] is not None for r in rows)==192,'Solo catálogos con coincidencia única.')
check(sum(r['biostar_user_id'] is not None for r in rows)==269,'269 vínculos contrastados.')
check(sum(r['biostar_user_id'] is not None and r['biostar_user_id']!=r['datos']['NumeroDocumento'] for r in rows)==3,'Conservar los tres ID sin cero inicial.')
check(len({r['biostar_user_id'] for r in rows if r['biostar_user_id']})==269,'Sin IDs biométricos duplicados en el plan.')
check(all('ProfesionID' not in r['datos'] and 'EspecialidadID' not in r['datos'] and 'RegimenLaboral' not in r['datos'] for r in rows),'Sin inferir acreditación ni régimen legal.')
check(all(r['datos']['NombreCompletoOriginal'] and r['datos']['Nombres'] for r in rows),'Nombres reales y originales conservados.')
check(all(len(r['datos']['Observacion'].encode('utf-16-le'))//2<=500 for r in rows),'Trazabilidad cabe sin truncarse.')
print(f'OK: {checks} verificaciones del plan; ninguna escritura SQL.')
