const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const workers = Array.from({length:151}, (_,index)=>({personal_id:index+1,nombre_completo:`Personal ${index+1}`,dni:null,codigo_empleado:null,area_id:7,servicio_id:3,cargo_nombre:null,servicio_nombre:null}));
async function checkSelector(page, functionName, selectorId, expectedContext) {
  const calls = [];
  const select = {innerHTML:'',value:'',disabled:false,options:[],addEventListener(){},appendChild(option){this.options.push(option);},add(option){this.options.push(option);},replaceChildren(...options){this.options=options;}};
  const context = vm.createContext({console, URLSearchParams,
    window:{location:{search:''}},
    Option: class {constructor(text,value){this.text=text;this.value=value;}},
    document:{addEventListener(){},getElementById(id){return id===selectorId?select:null;},createElement(){return {}; }},
    API:{async get(endpoint,params){calls.push([endpoint,params]);return {ok:true,data:workers};}}
  });
  vm.runInContext(fs.readFileSync(`assets/js/pages/${page}.js`,'utf8'),context);
  if(page==='reportes') vm.runInContext('cargarRecordIndividual=()=>{};',context);
  if(page==='horarios') vm.runInContext('recalculateModalWorkload=()=>{};',context);
  await vm.runInContext(functionName+'()',context);
  assert.equal(calls.length,1,`${page}: debe usar una petición`);
  assert.equal(calls[0][0],'personal/opciones.php');
  assert.equal(calls[0][1].contexto,expectedContext);
  if(page==='reportes') assert.equal(vm.runInContext('repState.personalLista.length',context),151);
  if(page==='horarios') assert.equal(select.options.length,151);
  if(page==='ausencias') assert.equal(vm.runInContext('ausState.personalCatalogo.length',context),151);
  if(page==='personal') assert.equal(select.options.length,152);
}
(async()=>{
 await checkSelector('reportes','cargarListaPersonalSelect','repPersonalSelect','reportes');
 await checkSelector('horarios','loadWorkersForScheduleAssign','assignWorkerSelect','horarios');
 await checkSelector('ausencias','cargarPersonalParaSelect','solPersonalId','ausencias');
 await checkSelector('personal','cargarPersonalParaVinculo','bioWorkerSelect','personal');
 console.log('OK: 151 trabajadores incompletos, una petición por selector y contexto de permisos por módulo.');
})().catch(error=>{console.error(error);process.exitCode=1;});
