const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const attack = `O'Connor');globalThis.injected=true;// <img src=x onerror=alert(1)> "`;
const decode = value => value.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
for (const [file, tableId, render, attr, callback, record] of [
  ['asistencia','asistTableBody','renderTablaAsistencia','data-nombre','prepararRegularizacionFila',
    {personal_id:1,dni:attack,nombre_completo:attack,cargo:'Cargo',servicio_nombre:'Servicio',estado_evaluado:'ASISTIO',minutos_tardanza:0,hora_entrada:'08:00',hora_salida:'14:00',hora_prog_inicio:'08:00',hora_prog_fin:'14:00',turno_codigo:'M',turno_nombre:'Mañana'}],
  ['administracion','usuariosTableBody','renderUsuarios','data-username','solicitarResetPassword',
    {usuario_id:1,username:attack,dni:attack,nombre_completo:attack,rol_nombre:'Administrador',rol_codigo:'RRHH_ADMIN',servicio_nombre:'Servicio',cargo_nombre:'Cargo',bloqueado:false}],
  ['ausencias','ausTableBody','renderTablaPermisos','data-dictamen','abrirModalDictamen',
    {permiso_id:1,nombre_completo:attack,dni:attack,cargo:'Cargo',servicio_nombre:'Servicio',condicion_laboral:'CAS',tipo_nombre:attack,estado:'PENDIENTE',fecha_inicio:'2026-10-27',fecha_fin:'2026-10-28',documento_sustento:attack,dias:2}]
]) {
  const table = {innerHTML:''}; const handlers = {};
  const document = {getElementById:id => id === tableId ? table : null, addEventListener:(name, fn) => {handlers[name] = fn;}};
  const context = vm.createContext({document,window:{},console,Date,setTimeout,clearTimeout});
  vm.runInContext(fs.readFileSync(`assets/js/pages/${file}.js`, 'utf8'), context);
  context[render]([record]);
  assert.ok(!table.innerHTML.includes('<img src=x'), `${file}: HTML sin escapar`);
  assert.ok(!/onclick="[^"]*O'Connor/.test(table.innerHTML), `${file}: nombre insertado como JavaScript`);
  const match = table.innerHTML.match(new RegExp(`${attr}="([^"]*)"`));
  assert.ok(match, `${file}: falta el atributo de datos`);
  const value = decode(match[1]);
  let received;
  context[callback] = (...args) => {received = args;};
  const dataset = file === 'asistencia' ? {regularizarId:'1',nombre:value,inicio:'08:00',fin:'14:00'} : file === 'administracion' ? {resetUser:'1',username:value} : {dictamen:value};
  handlers.click({target:{closest:() => ({dataset})}});
  assert.equal(received[1], attack, `${file}: el nombre no llegó íntegro al listener`);
  assert.equal(context.injected, undefined, `${file}: se ejecutó el valor del nombre`);
}
console.log('OK: nombres con apóstrofes, comillas y HTML se transmiten como datos en Asistencia, Administración y Ausencias.');
