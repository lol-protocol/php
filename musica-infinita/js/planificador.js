/*
 * Planificación pura de la reproducción y la exportación: cuánto se programa por delante,
 * cómo se parte una exportación en tramos y a qué hora entra cada nota de un tramo.
 * Funciones puras (navegador y Node), para poder probarlas sin audio.
 */
(function (raiz) {
  'use strict';

  const TRAMO = 120; // segundos de cada tramo de exportación
  const PREVIO = 12; // arranque de cada tramo: lo que viene del anterior
  const COLA = 4; // cola de reverberación al final
  const VENTANA = 2; // cada cuántos segundos se programan notas en un tramo
  const ADELANTO = 1.2; // segundos que se programan por delante al tocar en vivo

  /** Partes de una exportación de `total` segundos: [{inicio, previo, largo, ultimo}]. */
  function tramos(total, opciones) {
    const o = Object.assign({ tramo: TRAMO, previo: PREVIO, cola: COLA }, opciones);
    const out = [];
    for (let s = 0; s < total; s += o.tramo) {
      const previo = Math.min(s, o.previo);
      const ultimo = s + o.tramo >= total;
      out.push({ inicio: s, previo: previo, largo: Math.min(o.tramo, total - s) + (ultimo ? o.cola : 0), ultimo: ultimo });
    }
    return out;
  }

  /** Segundos por delante que se programan: más con la pestaña oculta (los temporizadores se espacian). */
  function adelanto(pestanaOculta) {
    return pestanaOculta ? ADELANTO * 3 : ADELANTO;
  }

  /**
   * Programador de un tramo: `hasta(lim)` devuelve, una sola vez cada uno y en orden, los
   * eventos que caen antes de `lim` segundos desde el comienzo del tramo (que empieza en
   * `desde`, en tiempo del generador). Los eventos fuera de [desde, fin) se ignoran.
   */
  function programador(eventos, desde, fin) {
    const lista = eventos.filter(function (ev) { return ev.t >= desde && ev.t < fin; });
    let idx = 0;
    return {
      total: lista.length,
      hasta: function (lim) {
        const out = [];
        while (idx < lista.length && lista[idx].t - desde < lim) out.push(lista[idx++]);
        return out;
      },
      pendientes: function () { return lista.length - idx; },
    };
  }

  /** Nombre de archivo para descargas: musica-infinita-AAAAMMDD-HHMM[-semilla].ext */
  function nombreArchivo(ext, semilla, fecha) {
    const d = fecha || new Date();
    const p = function (n) { return String(n).padStart(2, '0'); };
    const sem = semilla ? '-' + String(semilla).replace(/[^\w-]+/g, '_').slice(0, 24) : '';
    return 'musica-infinita-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes()) + sem + '.' + ext;
  }

  const API = { TRAMO: TRAMO, PREVIO: PREVIO, COLA: COLA, VENTANA: VENTANA, ADELANTO: ADELANTO, tramos: tramos, adelanto: adelanto, programador: programador, nombreArchivo: nombreArchivo };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Planificador = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
