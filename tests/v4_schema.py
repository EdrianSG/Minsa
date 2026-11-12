"""Contrato estático entre fuentes V4 y consultas productivas; no conecta a SQL Server."""
from pathlib import Path
import csv,re
root=Path(__file__).resolve().parents[1]
rows=list(csv.DictReader((root/'BD_HOSPITAL_V4/DICCIONARIO_DE_CAMPOS_V4.csv').open(encoding='utf-8-sig')))
schema={}
for row in rows: schema.setdefault((row['Esquema']+'.'+row['Tabla']).lower(),set()).add(row['Campo'].lower())
errors=[]
files=[p for folder in ['api','includes','config'] for p in (root/folder).rglob('*.php')]
for p in files:
    text=p.read_text()
    if re.search(r'core\.Persona|\bPersonaID\b|\bpersona_id\b',text,re.I): errors.append(f'{p}: identidad antigua')
    for table in re.findall(r'\b(?:FROM|JOIN|INTO|UPDATE)\s+((?:catalogo|rrhh|seguridad|organizacion|auditoria|clinico|laboratorio)\.\w+)',text,re.I):
        if table.lower() not in schema: errors.append(f'{p}: tabla ausente {table}')
    for table,columns in re.findall(r'INSERT\s+INTO\s+(\w+\.\w+)\s*\(([^)]+)\)',text,re.I):
        if table.lower() not in schema: continue
        for col in columns.split(','):
            col=col.strip().lower()
            if re.fullmatch(r'\w+',col) and col not in schema[table.lower()]: errors.append(f'{p}: columna ausente {table}.{col}')
sql=(root/'BD_HOSPITAL_V4/01_BD_HOSPITAL_V4_ESTRUCTURA.sql').read_text()
# Identidad de cada dominio y relaciones profesionales según la fuente oficial.
for column,table,key in [('PacienteID','clinico','Paciente'),('PersonalSolicitanteID','rrhh','Personal'),('ProfesionalResponsableID','rrhh','Personal'),('ProfesionalValidaID','rrhh','Personal')]:
    pattern=rf'FOREIGN KEY \(\[{column}\]\) REFERENCES \[{table}\]\.\[{key}\]'
    if not re.search(pattern,sql): errors.append(f'FK clínica/laboratorio ausente: {column}')
assert not errors,'\n'.join(errors)
print(f'OK: tablas e INSERT de {len(files)} archivos contrastados con {len(schema)} tablas V4; cuatro FK de pacientes/profesionales verificadas en DDL. No verifica aplicaciones ausentes ni SQL dinámico.')
