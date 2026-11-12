// Regresión de la tabla: un estado incompleto nunca debe mostrarse como asistencia.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const tbody = { innerHTML: '' };
const context = vm.createContext({
  document: {
    addEventListener() {},
    getElementById(id) { return id === 'dashTableBody' ? tbody : null; }
  }
});
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/pages/dashboard.js'), 'utf8'), context);
const cases = [
  ['ASISTIO', 'Asistió', 'asistio'],
  ['TARDANZA', 'Tardanza (+8m)', 'tardanza'],
  ['FALTA', 'Falta', 'falta'],
  ['FALTA_ADMINISTRATIVA', 'Falta administrativa', 'falta'],
  ['PERMISO', 'Permiso', 'permiso'],
  ['LICENCIA', 'Licencia', 'permiso'],
  ['JUSTIFICADO', 'Justificado', 'permiso'],
  ['VACACIONES', 'Vacaciones', 'permiso'],
  ['VAC', 'Vacaciones', 'permiso'],
  ['DESCANSO', 'Descanso', 'neutral'],
  ['SIN_DATOS', 'Sin programación confirmada', 'neutral'],
  ['PROGRAMADO', 'Programado / sin marcación', 'neutral'],
  ['EN_CURSO', 'En curso / sin marcación', 'neutral'],
  [null, 'Pendiente de verificación', 'neutral'],
  ['DESCONOCIDO', 'Pendiente de verificación', 'neutral'],
  ['constructor', 'Pendiente de verificación', 'neutral']
];
for (const [state, text, css] of cases) {
  context.record = { estado_evaluado: state, minutos_tardanza: 8, hora_entrada: null };
  vm.runInContext('dashState.todosRegistros = [record]; renderTablaAsistencia();', context);
  assert.ok(tbody.innerHTML.includes(`class="badge-clean ${css}">${text}</span>`), String(state));
  if (state !== 'ASISTIO') assert.ok(!tbody.innerHTML.includes('>Asistió</span>'), String(state));
  assert.ok(tbody.innerHTML.includes('Sin programación confirmada'));
}
context.record = { estado_evaluado: 'ASISTIO', hora_entrada: '07:03:00', hora_salida: '13:00:00', hora_prog_inicio: '07:00:00', hora_prog_fin: '13:00:00' };
vm.runInContext('dashState.todosRegistros = [record]; renderTablaAsistencia();', context);
assert.ok(tbody.innerHTML.includes('>07:03</span>'));
assert.ok(tbody.innerHTML.includes('>13:00</span>'));
assert.ok(tbody.innerHTML.includes('07:00 - 13:00'));
console.log('OK: 16 estados y presentación de horas reales y programadas.');
