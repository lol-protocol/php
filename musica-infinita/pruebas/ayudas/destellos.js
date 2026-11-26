'use strict';

// Análisis de destellos para las pruebas de seguridad visual, según el criterio WCAG 2.3.1
// («tres destellos o menos por segundo»), en su versión simplificada y conservadora:
//
//   - Un cambio es el de una celda de la imagen cuya luminancia relativa sube o baja 0,1 o
//     más (el 10 % del máximo) y cuyo estado más oscuro está por debajo de 0,8.
//   - Un cambio cuenta solo si lo hacen a la vez al menos `minCeldas` celdas (la norma pide
//     un área de al menos el 25 % de 341 × 256 píxeles en una pantalla de 1024 × 768, o sea
//     cerca del 2,8 % de la pantalla; con una rejilla de 16 × 9 son 4 celdas).
//   - Un destello es un par de cambios opuestos (varios cambios seguidos en el mismo sentido
//     son uno solo): en cada ventana deslizante de un segundo se cuenta la mitad de los cambios.
//
// Es conservadora: no distingue el rojo saturado (que la norma trata aparte) ni mira la
// distancia a la pantalla; cuenta como destello cualquier inversión que cumpla lo de arriba.
//
// UMD: `require` en Node, y `window.Destellos` si se inyecta con addScriptTag en el navegador.

(function (raiz) {
  'use strict';

  /** Luminancia relativa (0..1) de un color sRGB de 8 bits, como la define la WCAG. */
  function luminancia(r, g, b) {
    const f = function (c) {
      const v = c / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  }

  /**
   * frames: [{t (segundos), lum (Float32Array o arreglo de luminancias por celda)}].
   * Devuelve {maxPorSegundo, cambios, alternados, en}: los destellos de la peor ventana de un
   * segundo, cuántos cambios hubo, cuántos alternados (sin repetir sentido), y en qué momento empieza lo peor.
   */
  function analizar(frames, opciones) {
    const o = Object.assign({ umbral: 0.1, minCeldas: 4, ventana: 1, claroMax: 0.8 }, opciones);
    const cambios = [];
    for (let i = 1; i < frames.length; i++) {
      const a = frames[i - 1].lum;
      const b = frames[i].lum;
      let sube = 0;
      let baja = 0;
      for (let k = 0; k < b.length; k++) {
        const d = b[k] - a[k];
        if (Math.abs(d) >= o.umbral && Math.min(a[k], b[k]) < o.claroMax) {
          if (d > 0) sube++;
          else baja++;
        }
      }
      if (sube >= o.minCeldas || baja >= o.minCeldas) cambios.push({ t: frames[i].t, dir: sube >= baja ? 1 : -1 });
    }
    // varios cambios seguidos en el mismo sentido son una sola subida o bajada
    const alternados = [];
    for (let i = 0; i < cambios.length; i++) if (!alternados.length || cambios[i].dir !== alternados[alternados.length - 1].dir) alternados.push(cambios[i]);
    // un destello es un par de cambios opuestos: en cada ventana de un segundo (medio abierta: el
    // cambio que cae justo al final ya es del segundo siguiente), la mitad de los cambios
    let max = 0;
    let en = 0;
    let fin = 0;
    for (let i = 0; i < alternados.length; i++) {
      if (fin < i) fin = i;
      while (fin + 1 < alternados.length && alternados[fin + 1].t - alternados[i].t < o.ventana - 1e-9) fin++;
      const destellos = Math.floor((fin - i + 1) / 2);
      if (destellos > max) {
        max = destellos;
        en = alternados[i].t;
      }
    }
    return { maxPorSegundo: max, cambios: cambios.length, alternados: alternados.length, en: en };
  }

  const API = { luminancia: luminancia, analizar: analizar };

  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else raiz.Destellos = API;
})(typeof globalThis !== 'undefined' ? globalThis : this);
