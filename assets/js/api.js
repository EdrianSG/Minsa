/** SQL confirma cambios; la caché de sesión se reutiliza mientras su revisión siga vigente. */
const API = {
  baseUrl: 'api',
  _cache: new Map(),
  _statePromise: null,
  _revision: undefined,
  _generation: 0,

  _url(endpoint, params = {}) {
    const url = new URL(`${this.baseUrl}/${endpoint}`, window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/'));
    Object.keys(params).sort().forEach(key => url.searchParams.append(key, params[key]));
    return url.toString();
  },
  _prefix() {
    return window.currentUser?.cache_scope ? `sigh-cache-v2:${window.currentUser.cache_scope}:` : null;
  },
  _storageRead(key) {
    try { return JSON.parse(window.sessionStorage?.getItem(key) || 'null'); } catch (_) { return null; }
  },
  _storageWrite(key, value) {
    try { window.sessionStorage?.setItem(key, JSON.stringify(value)); } catch (_) { /* Memoria si el almacenamiento está lleno o bloqueado. */ }
  },
  _entry(key) {
    if (this._cache.has(key)) return this._cache.get(key);
    const prefix = this._prefix();
    const entry = prefix ? this._storageRead(prefix + key) : null;
    if (entry) this._cache.set(key, entry);
    return entry;
  },
  invalidate() {
    this._generation++;
    this._cache.clear();
    const prefix = this._prefix();
    if (!prefix) return;
    try {
      const storage = window.sessionStorage;
      for (let i = storage.length - 1; i >= 0; i--) {
        const key = storage.key(i);
        if (key?.startsWith(prefix)) storage.removeItem(key);
      }
    } catch (_) {}
  },
  _event(name, detail) {
    if (typeof window.dispatchEvent === 'function' && typeof CustomEvent !== 'undefined') {
      window.dispatchEvent(new CustomEvent(name, { detail }));
    }
  },
  async _error(response) {
    let data = null;
    try { data = await response.json(); } catch (_) {}
    const error = new Error(data?.mensaje || data?.message || `Error HTTP ${response.status}: ${response.statusText}`);
    error.status = response.status;
    error.data = data;
    if (response.status === 401 || response.status === 403) this.invalidate();
    return error;
  },
  async checkRevision() {
    if (this._statePromise) return this._statePromise;
    this._statePromise = (async () => {
      let response;
      try {
        response = await fetch(this._url('data_revision.php'), { cache: 'no-store', headers: { Accept: 'application/json' } });
      } catch (_) {
        this._event('sigh:connection', { online: false });
        return { conexion_sql: false, revision: null, network_error: true };
      }
      if (!response.ok) throw await this._error(response);
      const state = await response.json();
      if (!state.ok) throw new Error(state.mensaje || 'No se pudo verificar el estado de los datos.');
      const expected = window.currentUser?.cache_scope;
      if (expected && state.scope !== expected) {
        this.invalidate();
        const error = new Error('La sesión cambió. Recargue la página.');
        error.status = 401;
        throw error;
      }
      this._event('sigh:connection', { online: state.conexion_sql });
      if (state.conexion_sql) {
        const prefix = this._prefix();
        const previous = this._revision ?? (prefix ? this._storageRead(prefix + 'revision') : null);
        if (state.revision === null || (previous !== null && previous !== undefined && previous !== state.revision)) {
          this.invalidate();
          if (previous !== null && previous !== undefined) this._event('sigh:data-changed', {});
        }
        this._revision = state.revision;
        if (prefix) this._storageWrite(prefix + 'revision', state.revision);
      }
      return state;
    })();
    try { return await this._statePromise; } finally { this._statePromise = null; }
  },
  _emergency(entry) {
    this._event('sigh:connection', { online: false });
    return { ...entry.data, _cache: { origen: 'RESPALDO_EMERGENCIA', guardado_en: entry.time, solo_lectura: true } };
  },
  async get(endpoint, params = {}, options = {}) {
    const useCache = options.useCache !== false;
    const key = this._url(endpoint, params);
    const state = await this.checkRevision();
    const entry = this._entry(key);
    // Las jornadas cambian de estado con el reloj aunque no haya escrituras SQL.
    const temporal = /^(asistencia|dashboard)\//.test(endpoint) || endpoint === 'reportes/individual.php';
    const timeValid = !temporal || (entry && Math.floor(entry.time / 60000) === Math.floor(Date.now() / 60000));
    if (useCache && state.conexion_sql && state.revision !== null && entry?.revision === state.revision && entry?.source === 'SQL_SERVER' && timeValid) {
      return entry.data;
    }
    if (useCache && !state.conexion_sql && entry) return this._emergency(entry);
    const generation = this._generation;
    try {
      let response;
      try { response = await fetch(key, { cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); }
      catch (error) { error.network = true; throw error; }
      if (!response.ok) throw await this._error(response);
      const json = await response.json();
      if (json.ok === false || json.success === false) throw new Error(json.mensaje || json.message || 'No se pudo cargar la información.');
      const emergency = !state.conexion_sql || json.conexion_sql === false || json.resiliencia?.es_snapshot === true;
      const cached = { time: Date.now(), data: json, revision: state.revision, source: emergency ? 'RESPALDO_EMERGENCIA' : 'SQL_SERVER' };
      // Una lectura iniciada antes de un guardado no puede repoblar la caché invalidada.
      if (generation === this._generation) {
        this._cache.set(key, cached);
        const prefix = this._prefix();
        if (prefix) this._storageWrite(prefix + key, cached);
      }
      return emergency ? this._emergency(cached) : json;
    } catch (error) {
      if (error.network && useCache && entry) return this._emergency(entry);
      throw error;
    }
  },
  async post(endpoint, data = {}) {
    const isFormData = data instanceof FormData;
    const response = await fetch(`${this.baseUrl}/${endpoint}`, {
      method: 'POST',
      headers: isFormData ? { 'X-Requested-With': 'XMLHttpRequest' } : {
        'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'
      },
      body: isFormData ? data : JSON.stringify(data)
    });
    if (!response.ok) {
      const error = await this._error(response);
      if (response.status === 409 && error.data) return error.data;
      // Una respuesta perdida o fallida puede ocurrir después del commit SQL.
      if (response.status >= 500) this.invalidate();
      throw error;
    }
    const json = await response.json();
    if (json.ok || json.success) { this.invalidate(); this._revision = undefined; }
    return json;
  }
};
