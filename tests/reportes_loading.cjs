const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const elements = new Map();
for (const id of ['recordIndividualContainer','consolidadoPlanillasContainer','reporteSetiContainer','rolMensualContainer']) {
  elements.set(id,{id,innerHTML:'',dataset:{},setAttribute(){}});
}
const requests = [];
const context = vm.createContext({console,setTimeout,clearTimeout,
 document:{addEventListener(){},getElementById(id){return elements.get(id)||null;}},window:{},
 API:{get(endpoint){return new Promise((resolve,reject)=>requests.push({endpoint,resolve,reject}));}}
});
vm.runInContext(fs.readFileSync('assets/js/pages/reportes.js','utf8'),context);
vm.runInContext("repState.personalId=1; renderRecordIndividual=()=>{document.getElementById('recordIndividualContainer').innerHTML='Récord vigente';}; renderConsolidadoPlanillas=()=>{}; renderReporteSeti=()=>{};",context);
(async()=>{
 const loaders=['cargarRecordIndividual','cargarConsolidadoPlanillas','cargarReporteSeti','cargarRolOficialMinsa'];
 let index=0;
 for(const element of elements.values()) {
   const promise=vm.runInContext(loaders[index]+'()',context);
   assert.ok(element.innerHTML.includes('skeleton'),element.id);
   assert.ok(!element.innerHTML.includes('animation: pulse'));
   requests[index].resolve({ok:true,matriz:[]});
   await promise;
   index++;
 }
 const record=elements.get('recordIndividualContainer');
 const cached=vm.runInContext('cargarRecordIndividual()',context);
 assert.equal(record.innerHTML,'Récord vigente');
 requests[4].resolve({ok:true,_cache:{origen:'CACHE_VIGENTE'}});
 await cached;
 assert.equal(record.innerHTML,'Récord vigente');
 const old=vm.runInContext('cargarConsolidadoPlanillas()',context);
 vm.runInContext("repState.areaId='7';",context);
 const recent=vm.runInContext('cargarConsolidadoPlanillas()',context);
 requests[6].reject(new Error('Error reciente'));
 await recent;
 const recentHtml=elements.get('consolidadoPlanillasContainer').innerHTML;
 requests[5].resolve({ok:true});
 await old;
 assert.equal(elements.get('consolidadoPlanillasContainer').innerHTML,recentHtml);
 assert.ok(recentHtml.includes('Error al generar'));
 console.log('OK: skeletons en 4 reportes, vista vigente conservada y respuestas antiguas descartadas.');
})().catch(error=>{console.error(error);process.exitCode=1;});
