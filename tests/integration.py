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
    def request(endpoint, data=None, role=None, method=None, headers=None):
        # Ejecuta el punto de entrada real con contexto HTTP simulado en PHP CLI.
        from urllib.parse import urlsplit, parse_qs
        parsed = urlsplit(endpoint)
        payload = {'endpoint': str(ROOT / parsed.path), 'query': {k: v[-1] for k, v in parse_qs(parsed.query).items()},
                   'data': data or {}, 'role': role, 'method': method or ('POST' if data is not None else 'GET'),
                   'origin': (headers or {}).get('Origin', '')}
        wrapper = r'''$p=json_decode(stream_get_contents(STDIN),true);
$_GET=$p['query']; $_POST=$p['data'];
$_SERVER['REQUEST_METHOD']=$p['method']; $_SERVER['HTTP_HOST']='localhost';
$_SERVER['HTTP_ORIGIN']=$p['origin'];
if ($p['role']) { session_id('test'.strtolower(str_replace('_','',$p['role']))); session_start(); }
ob_start();
register_shutdown_function(function() { $out=ob_get_clean(); echo json_encode(['status'=>http_response_code() ?: 200,'body'=>$out]); });
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
    status, data = request('api/auth/login.php', {'usuario': 'admin', 'password': 'admin123'})
    check(status == 200 and data['ok'] and data['usuario']['rol'] == 'ADMIN', 'Acceso admin de desarrollo sin SQL')
    for username, expected_role in [('curcia', 'JEFE_RRHH'), ('atorres', 'OPERADOR_ASISTENCIA')]:
        status, data = request('api/auth/login.php', {'usuario': username, 'password': 'minsa2026'})
        check(status == 200 and data['ok'] and data['usuario']['rol'] == expected_role, 'Cuenta rápida: ' + username)
    status, data = request('api/auth/login.php', {'usuario': 'admin', 'password': 'incorrecta'})
    check(status == 401 and not data['ok'], 'Rechaza clave incorrecta de desarrollo')
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
    base = {'servicio_id': 7, 'mes': 9, 'anio': 2026, 'version_base': 1,
            'cambios': [{'personal_id': 1, 'dia': 1, 'codigo_turno': 'M'}]}
    for change in [{'personal_id': 1, 'dia': 31, 'codigo_turno': 'M'},
                   {'personal_id': 1, 'dia': 1, 'codigo_turno': 'INVALIDO'},
                   {'personal_id': 99, 'dia': 1, 'codigo_turno': 'M'}]:
        status, _ = request('api/programacion/guardar.php', dict(base, cambios=[change]), role='ADMIN')
        check(status in [400, 422], 'Rechaza cambio inválido sin escribir')
    status, _ = request('api/programacion/guardar.php', dict(base, forzar=True), role='JEFE_SERVICIO')
    check(status == 403, 'Supervisor no fuerza conflictos')
    status, data = request('api/programacion/guardar.php', base, role='ADMIN')
    check(status == 200 and data['nueva_version'] == 2 and data['sincronizado_sql'] is False, 'Guardado local honesto')
    for service in [0, 7]:
        saved = json.loads((snapshots / f'programacion_serv_{service}_2026_9.json').read_text())
        check(saved['data']['personal_servicio'][0]['patron'][0] == 'M', 'Sincroniza rol global y servicio')
    status, _ = request('api/programacion/guardar.php', base, role='ADMIN')
    check(status == 409, 'Versión obsoleta genera conflicto')
    concurrent_body = dict(base, version_base=2)
    with concurrent.futures.ThreadPoolExecutor() as executor:
        results = list(executor.map(lambda role: request('api/programacion/guardar.php', concurrent_body, role=role)[0], ['ADMIN', 'JEFE_RRHH']))
    check(sorted(results) == [200, 409], 'Dos escritores: solo uno confirma la misma versión')
    status, data = request('api/programacion/mensual.php?mes=9&anio=2026&servicio_id=7', role='ADMIN')
    check(status == 200 and data['matriz'][0]['programacion']['1']['codigo'] == 'M', 'Recarga conserva el turno')
    print(f'OK: {checks} comprobaciones de API (PHP CLI); snapshots y sesiones aislados.')
