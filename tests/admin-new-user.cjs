const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elements = new Map();
function element(id) {
  const classes = new Set();
  const item = {id, value:'', disabled:false, hidden:false, textContent:'', options:[], listeners:{},
    classList:{add:name=>classes.add(name),remove:name=>classes.delete(name),contains:name=>classes.has(name)},
    addEventListener(name, fn) { this.listeners[name] = fn; },
    replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; },
    add(option) {this.options.push(option);}, reset() {}};
  elements.set(id, item); return item;
}
for (const id of ['btnNuevoUsuario','modalCrearUsuario','modalUserPersonalSelect','modalUserRolSelect','modalUserDni','modalUserNombre','modalUserServicio','modalUserCargo','modalUserUsername','formCrearUsuario','btnCrearCuenta','modalUserEstado','modalUserRegistrarPersonal']) element(id);
const worker = {personal_id:'2',nombre_completo:'Prueba Nueva',dni:'12345678',servicio_nombre:'Servicio',cargo_nombre:'Cargo'};
let staff = [{...worker,personal_id:'1'}], requests = 0, fail = false, release;
const alerts = [];
const context = vm.createContext({console,Date,setTimeout,clearTimeout,
  document:{body:{style:{}},getElementById:id=>elements.get(id)||null,addEventListener(){}},
  window:{},Option:class {constructor(text,value){this.text=text;this.value=value;}},
  alert:message=>alerts.push(message),
  API:{
    async get(endpoint) {
      if (fail) throw new Error('Sesión vencida');
      if (endpoint==='personal/opciones.php') return {ok:true,data:staff,total_paginas:1};
      if (endpoint==='administracion/usuarios.php') return {ok:true,usuarios:[{personal_id:1}]};
      if (endpoint==='administracion/roles.php') return {ok:true,roles:[{rol_id:7,codigo:'RRHH_ADMIN',nombre:'Administrador'}]};
      return {ok:true,eventos:[],totales:{}};
    },
    async post(endpoint,payload) {
      requests++;
      assert.equal(payload.personal_id,2); assert.equal(payload.rol_id,7);
      await new Promise(resolve=>{release=resolve;});
      return {ok:true,clave_temporal:'clave-generada'};
    }
  }
});
vm.runInContext(fs.readFileSync('assets/js/app.js','utf8'),context);
vm.runInContext(fs.readFileSync('assets/js/pages/administracion.js','utf8'),context);
context.showToast = () => {};
const flush = () => new Promise(resolve=>setImmediate(resolve));
(async()=>{
  context.initAdminControls();
  elements.get('btnNuevoUsuario').listeners.click(); await flush();
  assert.ok(elements.get('modalCrearUsuario').classList.contains('open'),'El botón no abrió el modal');
  assert.equal(elements.get('btnCrearCuenta').disabled,false,'Debe permitir cuentas técnicas administrativas sin trabajador');
  assert.match(elements.get('modalUserEstado').textContent,/cuentas técnicas/);
  assert.equal(elements.get('modalUserRegistrarPersonal').hidden,false);
  staff = [worker];
  elements.get('btnNuevoUsuario').listeners.click(); await flush();
  assert.equal(elements.get('btnCrearCuenta').disabled,false);
  const select=elements.get('modalUserPersonalSelect');select.value='2';select.listeners.change({target:select});
  assert.equal(elements.get('modalUserNombre').value,'Prueba Nueva','No se completó el trabajador con ID SQL como texto');
  elements.get('modalUserRolSelect').value='7';
  const pending=context.guardarNuevoUsuario();
  await flush(); await context.guardarNuevoUsuario();
  assert.equal(requests,1,'Un doble clic creó dos solicitudes');
  assert.equal(elements.get('btnCrearCuenta').disabled,true);
  release(); await pending; await flush();
  assert.match(alerts[0],/clave-generada/);
  assert.equal(elements.get('modalCrearUsuario').classList.contains('open'),false);
  fail=true;elements.get('btnNuevoUsuario').listeners.click();await flush();
  assert.match(elements.get('modalUserEstado').textContent,/Sesión vencida/);
  assert.ok(elements.get('btnCrearCuenta').disabled);
  console.log('OK: botón Nuevo Usuario, modal, lista vacía, autocompletado, creación, doble clic y error de sesión.');
})().catch(error=>{console.error(error);process.exitCode=1;});
