#!/usr/bin/env python3
"""Plan autorizado de personal: datos reales, faltantes NULL, sin ejecutar SQL."""
import csv,hashlib,importlib.util,json,re
from collections import defaultdict
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('clean',ROOT/'database/preparar_personal_limpio_v3.py');clean=importlib.util.module_from_spec(spec);spec.loader.exec_module(clean)
OUT=ROOT/'private-audits/carga-personal-v3'

def build():
    capture=json.loads((OUT/'esquema_y_datos_v3.json').read_text());source=ROOT/'personal_limpio_asistencia.txt';bio=ROOT/'Report_20261015T070613_1.csv'
    sha=hashlib.sha256(source.read_bytes()).hexdigest();bio_sha=hashlib.sha256(bio.read_bytes()).hexdigest()
    review=json.loads((ROOT/'private-audits/personal-limpio-v3/resumen.json').read_text())
    if sha!=capture['archivo_sha256'] or sha!=review['sha256'] or bio_sha!=review['sha256_csv']:raise ValueError('Las fuentes cambiaron desde la revisión.')
    with source.open(encoding='utf-8-sig',newline='') as f:rows=list(csv.DictReader(f,delimiter='\t'))
    with (ROOT/'private-audits/personal-limpio-v3/personal_preparado_revision.csv').open() as f:prepared={int(r['LINEA_ORIGEN']):r for r in csv.DictReader(f)}
    doc=[r for r in capture['data']['catalogo.TipoDocumento'] if r['Codigo']=='DNI' and int(r['Activo'])==1]
    if len(doc)!=1:raise ValueError('Tipo DNI no unívoco.')
    catalogs={}
    for label,table,pk in [('area','organizacion.Area','AreaID'),('cargo','rrhh.Cargo','CargoID')]:
        items=defaultdict(list)
        for row in capture['data'][table]:
            if int(row['Activo']):items[clean.comparison.normalize(row['Nombre'])].append({'id':int(row[pk]),'nombre':row['Nombre']})
        catalogs[label]=items
    plan=[]
    for line,row in enumerate(rows,2):
        p=prepared[line];dni=row['DNI'];valid=bool(re.fullmatch('[0-9]{8}',dni));pat,mat,status=clean.surname_proposal(row['APELLIDOS'])
        original={'fuente':source.name,'sha256':sha,'linea':line,'area_origen':row['AREA'],'cargo_origen':row['CARGO'],'estado_datos':p['ESTADO_DATOS'],'estado_biometrico':p['ESTADO_BIOMETRICO']}
        if not valid:original['documento_origen_no_validado']=dni
        if status=='REVISAR_APELLIDOS_COMPUESTOS':original['apellidos_pendientes']=row['APELLIDOS']
        data={'CodigoEmpleado':None,'TipoDocumentoID':int(doc[0]['TipoDocumentoID']) if valid else None,'NumeroDocumento':dni if valid else None,'Nombres':row['NOMBRE_PILA'],'ApellidoPaterno':pat or None,'ApellidoMaterno':mat or None,'NombreCompletoOriginal':row['NOMBRE_COMPLETO'],'FechaIngreso':None,'CargoOrigen':row['CARGO'],'Observacion':json.dumps(original,ensure_ascii=False,separators=(',',':'))}
        for field,limit in [('Observacion',500),('Nombres',100),('ApellidoPaterno',100),('ApellidoMaterno',100),('NombreCompletoOriginal',250),('CargoOrigen',150)]:
            if data[field] is not None and len(data[field].encode('utf-16-le'))//2>limit:raise ValueError(f'Campo largo en línea {line}: {field}')
        a=catalogs['area'][clean.comparison.normalize(row['AREA'])];c=catalogs['cargo'][clean.comparison.normalize(row['CARGO'])]
        relation={'AreaID':a[0]['id'],'CargoID':c[0]['id'],'AreaNombre':a[0]['nombre'],'CargoNombre':c[0]['nombre']} if len(a)==len(c)==1 else None
        bid=p['BIOSTAR_USER_ID_CANDIDATO'] or None
        if bid and (not valid or p['CRITERIO_COINCIDENCIA'] not in ('ID_EXACTO_Y_NOMBRE','CEROS_INICIALES_CANDIDATO')):raise ValueError('Vínculo no revisado.')
        plan.append({'linea':line,'datos':data,'asignacion':relation,'biostar_user_id':bid,'criterio_biostar':p['CRITERIO_COINCIDENCIA'],'revision_apellidos':status})
    result={'formato':'CARGA_PERSONAL_V3_1','base':'BD_HOSPITAL_V3','servidor':'192.168.11.38','fuente':source.name,'fuente_sha256':sha,'csv_biostar':bio.name,'csv_sha256':bio_sha,'batch_id':'PERSONAL_TXT_'+sha,'autorizacion':'Usuario solicita introducir personal y confirma backup manual; no cargar marcaciones ni inventar datos.','estado_tecnico_no_mapea_activo':True,'filas':plan,'resumen':{'personal':len(plan),'asignaciones_univocas':sum(r['asignacion'] is not None for r in plan),'vinculos_biostar':sum(r['biostar_user_id'] is not None for r in plan),'documentos_pendientes':sum(r['datos']['NumeroDocumento'] is None for r in plan),'apellidos_sin_desglosar':sum(r['datos']['ApellidoPaterno'] is None for r in plan),'fechas_ingreso_nulas':len(plan),'codigos_empleado_nulos':len(plan)}}
    path=OUT/'plan_autorizado.json';path.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');path.chmod(0o600)
    print(json.dumps({'plan':str(path),'resumen':result['resumen'],'fuente_sha256':sha},ensure_ascii=False,indent=2));return result
if __name__=='__main__':build()
