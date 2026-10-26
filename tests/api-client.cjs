const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const context = vm.createContext({ URL, FormData, Map, Date, console,
  window: { location: { origin: 'http://localhost', pathname: '/index.php' } },
  fetch: async () => ({ ok: true, json: async () => ({ ok: true, value: 1 }) }) });
vm.runInContext(fs.readFileSync('assets/js/api.js', 'utf8') + '\nglobalThis.client = API;', context);
(async () => {
  const api = context.client;
  await api.get('personal/listar.php');
  assert.equal(api._cache.size, 1);
  context.fetch = async () => ({ ok: false, status: 403, statusText: 'Forbidden' });
  await assert.rejects(api.get('personal/listar.php', {}, { useCache: false }), e => e.status === 403);
  context.fetch = async () => ({ ok: true, json: async () => ({ ok: true }) });
  await api.post('personal/crear.php', {});
  assert.equal(api._cache.size, 0);
  context.fetch = async () => ({ ok: false, status: 503, json: async () => ({ message: 'No se guardó' }) });
  await assert.rejects(api.post('turnos/guardar.php', {}), /No se guardó/);
  console.log('OK: 4 comprobaciones de caché y errores del cliente.');
})().catch(e => { console.error(e); process.exitCode = 1; });
