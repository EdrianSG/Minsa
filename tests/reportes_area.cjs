const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const select = {innerHTML:'', value:'', disabled:false};
const roleSelect = {innerHTML:'', value:'', disabled:false};
const ctx = vm.createContext({document:{addEventListener(){},getElementById(id){return id === 'repPersonalSelect' ? select : id === 'repPersonalRolSelect' ? roleSelect : null;}},window:{location:{href:''}}});
vm.runInContext(fs.readFileSync(path.join(__dirname,'../assets/js/pages/reportes.js'),'utf8'),ctx);
vm.runInContext(`repState.personalLista = [
 {personal_id:1,area_id:7,servicio_id:null,nombre_completo:'Uno'},
 {personal_id:2,area_id:8,servicio_id:3,nombre_completo:'Dos'},
 {personal_id:3,area_id:7,servicio_id:3,nombre_completo:'Tres'}
]; repState.areaId='7'; actualizarSelectPersonal();`,ctx);
assert.ok(select.innerHTML.includes('value="1"'));
assert.ok(select.innerHTML.includes('value="3"'));
assert.ok(!select.innerHTML.includes('value="2"'));
vm.runInContext("repState.servicioId='3'; actualizarSelectPersonal();",ctx);
assert.ok(!select.innerHTML.includes('value="1"'));
assert.ok(select.innerHTML.includes('value="3"'));
vm.runInContext("repState.areaId='99'; actualizarSelectPersonal();",ctx);
assert.equal(select.disabled,true);
assert.equal(vm.runInContext('repState.personalId',ctx),0);
assert.ok(!select.innerHTML.includes('value="3"'));
vm.runInContext("repState.areaId=''; repState.servicioId=''; actualizarSelectPersonal();",ctx);
assert.equal(select.disabled,false);
assert.ok(select.innerHTML.includes('value="2"'));
vm.runInContext("repState.areaId='7'; repState.tabActiva='consolidado'; exportarReporteExcel();",ctx);
assert.ok(ctx.window.location.href.includes('area=7'));
vm.runInContext('exportarReporteCsv();',ctx);
assert.ok(ctx.window.location.href.includes('area=7'));
assert.ok(!fs.readFileSync(path.join(__dirname,'../pages/programacion.php'),'utf8').includes('BORRADOR'));
console.log('OK: 13 comprobaciones de área, combinación con servicio, ausencia de coincidencias, exportaciones y título.');

vm.runInContext("repState.areaId='7'; repState.servicioId='3'; repState.rolPersonalId='3'; actualizarSelectPersonalRol();",ctx);
assert.ok(roleSelect.innerHTML.includes('value="3"'));
assert.ok(!roleSelect.innerHTML.includes('value="1"'));
assert.ok(!roleSelect.innerHTML.includes('value="2"'));
assert.equal(roleSelect.value,'3');
vm.runInContext("repState.tabActiva='rol'; exportarReporteExcel();",ctx);
assert.ok(ctx.window.location.href.includes('personal_id=3'));
assert.ok(ctx.window.location.href.includes('area=7'));
assert.ok(ctx.window.location.href.includes('servicio_id=3'));
vm.runInContext("repState.areaId='8'; actualizarSelectPersonalRol();",ctx);
assert.equal(roleSelect.value,'');
assert.equal(vm.runInContext('repState.rolPersonalId',ctx),'');
assert.ok(roleSelect.innerHTML.includes('value="2"'));
vm.runInContext("repState.areaId='99'; actualizarSelectPersonalRol();",ctx);
assert.equal(roleSelect.disabled,true);
assert.ok(!roleSelect.innerHTML.includes('value="3"'));
console.log('OK: selector del rol dependiente de área/servicio, reinicio de selección y exportación del trabajador.');
