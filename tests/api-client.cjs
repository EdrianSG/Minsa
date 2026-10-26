const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const storage = new Map();
const sessionStorage = { getItem:k=>storage.get(k) ?? null, setItem:(k,v)=>storage.set(k,v), removeItem:k=>storage.delete(k), key:i=>[...storage.keys()][i], get length(){ return storage.size; } };
let revision='1', online=true, scope='session-a', value=1, reads=0, checks=0, denyState=false, network=false, getStatus=200, postStatus=200, deferred=null;
const response = (data, status=200) => ({ok:status===200,status,statusText:String(status),json:async()=>data});
async function fetch(url, options={}) {
  if (String(url).endsWith('data_revision.php')) {
    if (network) throw new TypeError('Sin red');
    return denyState ? response({},403) : response({ok:true,conexion_sql:online,revision,scope});
  }
  if (options.method==='POST') return response(postStatus===200 ? {ok:true} : {message:'No se guardó'}, postStatus);
  reads++;
  if (deferred) return deferred;
  return response({ok:true,value,conexion_sql:online},getStatus);
}
function client(user='session-a') {
  const context=vm.createContext({URL,FormData,Map,Date,console,fetch,window:{sessionStorage,currentUser:{cache_scope:user},location:{origin:'http://localhost',pathname:'/index.php'}}});
  vm.runInContext(fs.readFileSync('assets/js/api.js','utf8')+'\nglobalThis.client=API;',context);
  return context.client;
}
function check(ok,label){assert.ok(ok,label);checks++;}
(async()=>{
  let api=client();
  check((await api.get('personal/listar.php')).value===1,'Primera consulta SQL');
  api._cache.values().next().value.time-=120000;
  await api.get('personal/listar.php');
  check(reads===1,'Sin cambios SQL no vuelve a descargar datos aunque pasen 45 segundos');
  api=client();
  check((await api.get('personal/listar.php')).value===1 && reads===1,'Persiste al cambiar de página');
  revision='2';value=2;
  check((await api.get('personal/listar.php')).value===2 && reads===2,'Actualización de otro equipo invalida caché');
  await api.post('personal/crear.php',{});
  check(api._cache.size===0,'Un guardado confirmado invalida todas las vistas');
  await api.get('personal/listar.php');
  denyState=true;
  await assert.rejects(api.get('personal/listar.php'),e=>e.status===403); checks++;
  check(api._cache.size===0,'Un permiso denegado elimina la caché');
  denyState=false;
  await api.get('personal/listar.php');
  getStatus=403;
  await assert.rejects(api.get('personal/listar.php',{}, {useCache:false}),e=>e.status===403); checks++;
  getStatus=200;
  await api.get('personal/listar.php');
  network=true;
  const emergency=await api.get('personal/listar.php');
  check(emergency._cache.origen==='RESPALDO_EMERGENCIA' && emergency._cache.solo_lectura,'Respaldo explícito ante corte de red');
  network=false;online=false;
  check((await api.get('personal/listar.php'))._cache.solo_lectura,'SQL caído usa última vista de consulta');
  online=true;revision='3';value=3;
  check((await api.get('personal/listar.php')).value===3,'Recuperación de SQL prioriza datos vigentes');
  postStatus=503;
  await assert.rejects(api.post('turnos/guardar.php',{}),/No se guardó/);checks++;
  check(api._cache.size===0,'Una respuesta fallida tras un posible commit obliga a verificar');
  postStatus=200;
  await api.get('personal/listar.php');
  getStatus=503;
  await assert.rejects(api.get('personal/listar.php',{}, {useCache:false}),e=>e.status===503);checks++;
  getStatus=200;
  scope='session-b';
  await assert.rejects(api.get('personal/listar.php'),e=>e.status===401);checks++;
  check(api._cache.size===0,'Cambio de sesión no reutiliza datos de otro usuario');
  api=client('session-b');
  check((await api.get('personal/listar.php')).value===3,'Caché separada por sesión');
  await api.get('asistencia/diaria.php');
  const key=api._url('asistencia/diaria.php');
  api._cache.get(key).time-=120000;
  const before=reads;
  await api.get('asistencia/diaria.php');
  check(reads===before+1,'Jornadas abiertas también se revalidan por tiempo');
  let release;
  deferred=new Promise(resolve=>{release=resolve;});
  const pending=api.get('personal/listar.php',{}, {useCache:false});
  await new Promise(resolve=>setImmediate(resolve));
  await api.post('personal/crear.php',{});
  release(response({ok:true,value:0}));
  await pending;
  check(api._cache.size===0,'Lectura anterior a un guardado no repuebla caché vieja');
  deferred=null;
  console.log(`OK: ${checks} comprobaciones de caché persistente, revisión SQL y errores.`);
})().catch(e=>{console.error(e);process.exitCode=1;});
