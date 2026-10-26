/**
 * Cliente HTTP Centralizado - Hospital Distrital de Pacasmayo
 * Maneja peticiones asíncronas a la API PHP con manejo uniforme de errores
 */

const API = {
  baseUrl: 'api',

  _cache: new Map(),

  /**
   * Realiza una petición GET a un endpoint JSON con aceleración por caché instantánea
   */
  async get(endpoint, params = {}, options = { useCache: true }) {
    const url = new URL(`${this.baseUrl}/${endpoint}`, window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/'));
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
    const cacheKey = url.toString();

    // 1. Si tenemos datos en memoria o sessionStorage, podemos retornarlos de inmediato si se solicita
    if (options.useCache && this._cache.has(cacheKey)) {
      const cachedItem = this._cache.get(cacheKey);
      // Si tiene menos de 45 segundos, retorno ultraveloz (0ms)
      if (Date.now() - cachedItem.time < 45000) {
        return cachedItem.data;
      }
    }

    try {
      const response = await fetch(url.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        const error = new Error(`Error HTTP ${response.status}: ${response.statusText}`);
        error.status = response.status;
        throw error;
      }

      const json = await response.json();
      this._cache.set(cacheKey, { time: Date.now(), data: json });
      return json;
    } catch (error) {
      // Si la red falla pero teníamos un caché previo, salvaguardar con el último estado real
      if (!error.status && this._cache.has(cacheKey)) {
        return this._cache.get(cacheKey).data;
      }
      console.warn(`[API GET Warning] Fallback en endpoint ${endpoint}:`, error);
      throw error;
    }
  },

  /**
   * Realiza una petición POST enviando JSON o FormData
   */
  async post(endpoint, data = {}) {
    try {
      const url = `${this.baseUrl}/${endpoint}`;
      const isFormData = data instanceof FormData;

      const response = await fetch(url, {
        method: 'POST',
        headers: isFormData ? { 'X-Requested-With': 'XMLHttpRequest' } : {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: isFormData ? data : JSON.stringify(data)
      });

      if (!response.ok) {
        let errJson = null;
        try {
          errJson = await response.json();
        } catch (_) {}

        if (response.status === 409 && errJson) {
          return errJson; // Devolver información del conflicto para resolverlo amistosamente
        }

        const err = new Error(errJson?.mensaje || errJson?.message || `Error HTTP ${response.status}: ${response.statusText}`);
        err.status = response.status;
        err.data = errJson;
        throw err;
      }

      const json = await response.json();
      if (json.ok || json.success) this._cache.clear();
      return json;
    } catch (error) {
      console.error(`[API POST Error] en ${endpoint}:`, error);
      throw error;
    }
  }
};
