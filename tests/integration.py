#!/usr/bin/env python3
"""Pruebas de API aisladas con contexto HTTP simulado en PHP CLI. No conectan a SQL Server ni modifican storage real.
Ejecutar: python3 tests/integration.py
"""
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
checks = 0

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1

with tempfile.TemporaryDirectory(prefix='minsa-tests-') as tmp:
    path = Path(tmp)
    snapshots = path / 'snapshots'
    snapshots.mkdir()
    sessions = path / 'sessions'
    sessions.mkdir()
    env = dict(os.environ, SNAPSHOT_PATH=str(snapshots), DB_HOST='127.0.0.1', DB_PORT='1',
               DB_USER='test', DB_PASS='test', TMPDIR=tmp)
    for role in ['ADMIN', 'CONSULTA', 'JEFE_SERVICIO', 'OPERADOR_ASISTENCIA', 'JEFE_RRHH']:
        sid = 'test' + role.replace('_', '').lower()
        code = 'session_id($argv[1]); session_start(); $_SESSION["user"] = ["usuario_id"=>1,"username"=>"test","rol"=>$argv[2]]; session_write_close();'
        subprocess.run(['php', '-d', f'session.save_path={sessions}', '-r', code, sid, role], check=True, env=env)
    staff = [{'personal_id': 1, 'servicio_id': 7, 'nombre_completo': 'Trabajador de prueba',
              'dni': '00000001', 'cargo_nombre': 'Enfermería', 'condicion_laboral': 'CAS',
              'regimen_laboral': 'ASISTENCIAL', 'aplica_seti': 1, 'patron': ['LIB'] * 30}]
    for service in [0, 7]:
        key = f'programacion_serv_{service}_2026_9'
        (snapshots / f'{key}.json').write_text(json.dumps({'_meta': {'version': 1},
            'data': {'personal_servicio': staff, 'servicio_id': service, 'mes': 9, 'anio': 2026}}))
    # Only yesterday is available: requesting today must never substitute it.
    (snapshots / 'asistencia_diaria_2026-09-21.json').write_text(json.dumps({'data': [{'personal_id': 1}]}))
    def request(endpoint, data=None, role=None, method=None, headers=None, service_id=None, report_case=None):
        # Ejecuta el punto de entrada real con contexto HTTP simulado en PHP CLI.
        from urllib.parse import urlsplit, parse_qs
        parsed = urlsplit(endpoint)
        payload = {'endpoint': str(ROOT / parsed.path), 'query': {k: v[-1] for k, v in parse_qs(parsed.query).items()},
                   'data': data or {}, 'role': role, 'method': method or ('POST' if data is not None else 'GET'),
                   'origin': (headers or {}).get('Origin', ''), 'service_id': service_id, 'report_case': report_case}
        wrapper = r'''$p=json_decode(stream_get_contents(STDIN),true);
$_GET=$p['query']; $_POST=$p['data'];
$_SERVER['REQUEST_METHOD']=$p['method']; $_SERVER['HTTP_HOST']='localhost';
$_SERVER['HTTP_ORIGIN']=$p['origin'];
if ($p['role']) { session_id('test'.strtolower(str_replace('_','',$p['role']))); session_start(); $_SESSION['user']['servicio_id'] = $p['service_id']; }
ob_start();
register_shutdown_function(function() { $out=ob_get_clean(); echo json_encode(['status'=>http_response_code() ?: 200,'body'=>$out]); });
if ($p['report_case']) {
    require dirname($p['endpoint'], 3).'/config/database.php';
    require dirname($p['endpoint'], 3).'/tests/ReportFixturePDO.php';
    $db = new ReflectionProperty(Database::class, 'instance');
    $db->setValue(null, new ReportFixturePDO($p['report_case']));
}
require $p['endpoint'];'''
        result = subprocess.run(['php', '-d', f'session.save_path={sessions}', '-r', wrapper],
                                input=json.dumps(payload), text=True, capture_output=True, env=env)
        if result.returncode:
            raise AssertionError(result.stderr + result.stdout)
        envelope = json.loads(result.stdout)
        return envelope['status'], json.loads(envelope['body'])
    # All API entry points reject an anonymous request, including binary exports.
    for endpoint in (ROOT / 'api').rglob('*.php'):
        if endpoint.parent.name == 'auth':
            continue
        status, data = request(str(endpoint.relative_to(ROOT)))
        check(status == 401 and data['ok'] is False, f'Anónimo: {endpoint.name}')
    status, _ = request('api/administracion/usuarios.php', role='CONSULTA')
    check(status == 403, 'Consulta no administra cuentas')
    status, _ = request('api/asistencia/regularizar.php', {}, role='CONSULTA')
    check(status == 403, 'Consulta no modifica asistencia')
    status, _ = request('api/asistencia/regularizar.php', role='ADMIN')
    check(status == 405, 'GET no modifica datos')
    status, _ = request('api/programacion/guardar.php', {}, role='ADMIN', headers={'Origin': 'https://otro.test'})
    check(status == 403, 'Rechaza origen externo')
    for username, password in [('admin', 'admin123'), ('curcia', 'minsa2026'), ('atorres', 'minsa2026'), ('admin', 'incorrecta')]:
        status, data = request('api/auth/login.php', {'usuario': username, 'password': password})
        check(status == 401 and not data['ok'], 'Sin SQL no hay acceso de contingencia: ' + username)
    for endpoint in ['api/asistencia/diaria.php', 'api/personal/listar.php', 'api/reportes/individual.php?personal_id=1',
                     'api/reportes/consolidado.php', 'api/reportes/seti.php', 'api/programacion/mensual.php?servicio_id=7']:
        status, data = request(endpoint, role='JEFE_SERVICIO')
        check(status == 403 and not data['ok'], 'Jefatura sin servicio no accede: ' + endpoint)
    for endpoint in ['api/reportes/individual.php?personal_id=1', 'api/reportes/consolidado.php', 'api/reportes/seti.php']:
        status, data = request(endpoint, role='ADMIN')
        check(status == 503 and not data['ok'], 'Reporte sin SQL no inventa datos: ' + endpoint)
    for endpoint in ['api/reportes/individual.php?mes=13', 'api/reportes/consolidado.php?mes=13', 'api/reportes/seti.php?mes=13']:
        status, _ = request(endpoint, role='ADMIN')
        check(status == 400, 'Rechaza periodo inválido: ' + endpoint)
    for endpoint in ['api/asistencia/diaria.php?servicio_id=8', 'api/programacion/mensual.php?servicio_id=8',
                     'api/reportes/consolidado.php?servicio_id=8', 'api/reportes/seti.php?servicio_id=8']:
        status, _ = request(endpoint, role='JEFE_SERVICIO', service_id=7)
        check(status == 403, 'Jefatura no consulta otro servicio: ' + endpoint)
    status, data = request('api/programacion/mensual.php?mes=9&anio=2026&servicio_id=7', role='JEFE_SERVICIO', service_id=7)
    check(status == 200 and data['ok'], 'Jefatura con servicio conserva acceso a su rol')
    for endpoint in ['api/asistencia/biometrico/historial.php', 'api/asistencia/biometrico/incidencias.php?importacion_id=1']:
        status, _ = request(endpoint, role='JEFE_SERVICIO', service_id=7)
        check(status == 403, 'Jefatura no consulta historial técnico global: ' + endpoint)
    (snapshots / 'asistencia_diaria_2026-09-23.json').write_text(json.dumps({'data': [
        {'personal_id': 1, 'servicio_id': 7, 'turno_codigo': 'M', 'estado_evaluado': 'ASISTIO'},
        {'personal_id': 2, 'servicio_id': 8, 'turno_codigo': 'M', 'estado_evaluado': 'FALTA'}
    ]}))
    status, data = request('api/asistencia/diaria.php?fecha=2026-09-23', role='JEFE_SERVICIO', service_id=7)
    check(status == 200 and len(data['asistencias']) == 1 and data['resumen']['faltas'] == 0
          and data['resumen']['total_evaluados'] == 1, 'KPIs y registros del snapshot respetan el servicio')
    (snapshots / 'turnos_catalogo_oficial.json').write_text(json.dumps({'data': [
        {'codigo': 'M', 'turno_id': 1, 'nombre': 'Mañana', 'hora_inicio': '08:00', 'hora_fin': '14:00'}
    ]}))
    (snapshots / 'programacion_serv_7_2020_1.json').write_text(json.dumps({'data': {'personal_servicio': [
        {'personal_id': 1, 'patron': ['M'] * 31}
    ]}}))
    individual = 'api/reportes/individual.php?personal_id=1&mes=1&anio=2020'
    status, data = request(individual, role='ADMIN', report_case='empty')
    check(status == 200 and all(day['estado'] == 'SIN_DATOS' for day in data['dias'])
          and data['totales']['total_faltas'] == 0 and data['totales']['dias_laborados'] == 0,
          'Reporte individual con SQL simulado distingue sin datos de faltas y asistencia')
    status, data = request(individual, role='ADMIN', report_case='medical')
    check(status == 200 and data['totales']['total_permisos'] == 31
          and all(day['estado'] == 'CITT' for day in data['dias']), 'CITT que cubre el mes se refleja día a día')
    status, _ = request(individual, role='ADMIN', report_case='missing_worker')
    check(status == 404, 'No sustituye un trabajador inexistente por otro')
    status, _ = request(individual, role='ADMIN', report_case='attendance_failure')
    check(status == 503, 'Fallo de consulta no se convierte en faltas del mes')
    for endpoint, key in [('api/personal/listar.php','data'), ('api/horarios/listar.php','data'), ('api/turnos/listar.php','turnos'), ('api/permisos/listar.php','permisos')]:
        status, data = request(endpoint, role='ADMIN', report_case='sql_empty')
        # Algunos listados usan data como nombre de colección.
        collection = data.get(key, data.get('data'))
        check(status == 200 and data['conexion_sql'] and collection == [], 'SQL vacío no recupera registros antiguos: ' + endpoint)
    status, data = request('api/programacion/mensual.php?mes=9&anio=2026&servicio_id=7', role='ADMIN', report_case='sql_primary')
    check(status == 200 and data['matriz'][0]['programacion']['1']['codigo'] == 'N'
          and data['resiliencia']['conexion_sql'] and not data['resiliencia']['es_snapshot']
          and data['resiliencia']['snapshot_meta']['version'] == 7, 'Rol SQL prevalece sobre snapshot anterior')
    cases = [
        ('api/asistencia/regularizar.php', {'personal_id': 1, 'fecha': '2026-09-22', 'hora_entrada': '08:00', 'motivo': 'Prueba', 'documento_sustento': 'TEST'}),
        ('api/permisos/guardar.php', {'personal_id': 1, 'tipo_permiso_id': 2, 'fecha_inicio': '2026-09-22', 'fecha_fin': '2026-09-23', 'motivo': 'Prueba'}),
        ('api/permisos/dictaminar.php', {'permiso_id': 1, 'accion': 'APROBAR'}),
        ('api/turnos/guardar.php', {'nombre': 'Prueba', 'hora_inicio': '20:00', 'hora_fin': '08:00'}),
        ('api/horarios/asignar.php', {'personal_id': 1, 'fecha_inicio': '2026-09-22', 'dias': {'1': 1}}),
    ]
    for endpoint, body in cases:
        status, data = request(endpoint, body, role='ADMIN')
        check(status == 503 and not data['ok'], f'No finge guardado offline: {endpoint}')
    status, _ = request('api/permisos/guardar.php', dict(cases[1][1], fecha_fin='2026-02-30'), role='ADMIN')
    check(status == 400, 'Rechaza fecha imposible')
    status, _ = request('api/permisos/dictaminar.php', {'permiso_id': 1, 'accion': 'ERROR'}, role='ADMIN')
    check(status == 400, 'Acción desconocida no aprueba')
    for endpoint in ['api/asistencia/diaria.php', 'api/dashboard/resumen.php']:
        status, data = request(endpoint + '?fecha=2026-09-22', role='ADMIN')
        check(status == 503 and not data['ok'], f'No sustituye fecha: {endpoint}')
    (snapshots / 'programacion_serv_7_2026_9.json').write_text(json.dumps({'_meta': {'version': 1}, 'data': {'personal_servicio': staff}}))
    base = {'servicio_id': 7, 'mes': 9, 'anio': 2026, 'version_base': 1,
            'cambios': [{'personal_id': 1, 'dia': 1, 'codigo_turno': 'M'}]}
    for change in [{'personal_id': 1, 'dia': 31, 'codigo_turno': 'M'},
                   {'personal_id': 1, 'dia': 1, 'codigo_turno': 'INVALIDO'},
                   {'personal_id': 99, 'dia': 1, 'codigo_turno': 'M'}]:
        status, _ = request('api/programacion/guardar.php', dict(base, cambios=[change]), role='ADMIN')
        check(status in [400, 422, 503], 'Rechaza cambio inválido o SQL no disponible sin escribir')
    status, _ = request('api/programacion/guardar.php', dict(base, forzar=True), role='JEFE_SERVICIO')
    check(status == 403, 'Supervisor no fuerza conflictos')
    status, data = request('api/programacion/guardar.php', base, role='ADMIN')
    check(status == 503 and not data['ok'], 'No confirma programación guardada solo en archivos')
    for service in [0, 7]:
        saved = json.loads((snapshots / f'programacion_serv_{service}_2026_9.json').read_text())
        check(saved['data']['personal_servicio'][0]['patron'][0] == 'LIB', 'SQL no disponible conserva respaldo sin modificar')
    status, data = request('api/programacion/mensual.php?mes=9&anio=2026&servicio_id=7', role='ADMIN')
    check(status == 200 and data['resiliencia']['solo_lectura'] and not data['resiliencia']['conexion_sql'],
          'Programación de contingencia se identifica como consulta')
    print(f'OK: {checks} comprobaciones de API (PHP CLI); snapshots y sesiones aislados.')
