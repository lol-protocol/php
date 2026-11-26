/*
 * Almacenamiento en el navegador: IndexedDB para los MIDI y la SoundFont del usuario, y
 * localStorage para los ajustes. Nada sale del navegador. Solo navegador.
 */
(function (raiz) {
  'use strict';

  const BD = {
    abrir: function () {
      if (this._p) return this._p;
      this._p = new Promise(function (ok, mal) {
        if (!raiz.indexedDB) return mal(new Error('Sin IndexedDB'));
        const r = raiz.indexedDB.open('musica-infinita', 1);
        r.onupgradeneeded = function () {
          const db = r.result;
          if (!db.objectStoreNames.contains('midis')) db.createObjectStore('midis', { keyPath: 'id' });
          if (!db.objectStoreNames.contains('cosas')) db.createObjectStore('cosas');
        };
        r.onsuccess = function () { ok(r.result); };
        r.onerror = function () { mal(r.error); };
      });
      return this._p;
    },
    op: function (almacen, modo, fn) {
      return this.abrir().then(function (db) {
        return new Promise(function (ok, mal) {
          const tx = db.transaction(almacen, modo);
          const r = fn(tx.objectStore(almacen));
          tx.oncomplete = function () { ok(r && r.result); };
          tx.onerror = function () { mal(tx.error); };
          tx.onabort = function () { mal(tx.error); };
        });
      });
    },
    todosMidis: function () { return this.op('midis', 'readonly', function (s) { return s.getAll(); }); },
    guardarMidi: function (r) { return this.op('midis', 'readwrite', function (s) { return s.put(r); }); },
    borrarMidi: function (id) { return this.op('midis', 'readwrite', function (s) { return s.delete(id); }); },
    leer: function (k) { return this.op('cosas', 'readonly', function (s) { return s.get(k); }); },
    escribir: function (k, v) { return this.op('cosas', 'readwrite', function (s) { return s.put(v, k); }); },
    borrar: function (k) { return this.op('cosas', 'readwrite', function (s) { return s.delete(k); }); },
  };

  function guardarAjustes(ajustes) {
    try {
      raiz.localStorage.setItem('musica-infinita:ajustes', JSON.stringify(ajustes));
    } catch (e) { /* sin almacenamiento */ }
  }

  function cargarAjustes(ajustes) {
    try {
      Object.assign(ajustes, JSON.parse(raiz.localStorage.getItem('musica-infinita:ajustes') || '{}'));
    } catch (e) { /* ajustes dañados: los de siempre */ }
  }

  /** Los ajustes propios de la MilkDrop (una lista de objetos ya validados al leerlos). */
  function guardarPresets(lista) {
    try {
      raiz.localStorage.setItem('musica-infinita:presets', JSON.stringify(lista));
    } catch (e) { /* sin almacenamiento */ }
  }

  function cargarPresets() {
    try {
      const l = JSON.parse(raiz.localStorage.getItem('musica-infinita:presets') || '[]');
      return Array.isArray(l) ? l : [];
    } catch (e) {
      return [];
    }
  }

  /** Favoritos, listas e historial en bruto: quien los lee los sanea (sesiones.js). */
  function guardarSesiones(datos) {
    try {
      raiz.localStorage.setItem('musica-infinita:sesiones', JSON.stringify(datos));
      return true;
    } catch (e) {
      return false;
    }
  }

  function cargarSesiones() {
    try {
      const d = JSON.parse(raiz.localStorage.getItem('musica-infinita:sesiones') || '{}');
      return d && typeof d === 'object' ? d : {};
    } catch (e) {
      return {};
    }
  }

  raiz.Almacen = { BD: BD, guardarAjustes: guardarAjustes, cargarAjustes: cargarAjustes, guardarPresets: guardarPresets, cargarPresets: cargarPresets, guardarSesiones: guardarSesiones, cargarSesiones: cargarSesiones };
})(window);
