/*
 * Lo que las visualizaciones calculan sin dibujar: los ajustes de la MilkDrop (con su
 * validación, su exportación e importación y cuáles van con cada estilo de música), el
 * sistema de partículas, la proyección 3D de la curva de Lissajous, las barras del espectro
 * con sus picos que caen y la posición de cada nota en la lluvia de notas.
 *
 * Funciones puras (navegador y Node): no tocan el canvas ni WebGL. visualizador.js las usa
 * para dibujar, y las pruebas las revisan sin navegador.
 */
(function (raiz) {
  'use strict';

  // ------------------------------------------------------------ ajustes de la MilkDrop

  // Rangos permitidos de cada parámetro (lo que acepta la importación y los controles).
  const LIMITES = {
    zoom: { min: 0.95, max: 1.08, paso: 0.001, etiqueta: 'Zoom' },
    rot: { min: -0.05, max: 0.05, paso: 0.001, etiqueta: 'Giro' },
    warp: { min: 0, max: 0.03, paso: 0.0005, etiqueta: 'Ondulación' },
    warpFrec: { min: 1, max: 16, paso: 0.5, etiqueta: 'Frecuencia de la ondulación' },
    decay: { min: 0.85, max: 0.995, paso: 0.001, etiqueta: 'Estela' },
    tono: { min: -0.01, max: 0.01, paso: 0.0005, etiqueta: 'Cambio de color' },
    brillo: { min: 0.4, max: 1.6, paso: 0.05, etiqueta: 'Brillo' },
  };
  const CLAVES_NUMERICAS = Object.keys(LIMITES);
  const FORMAS_ONDA = ['Círculo', 'Línea', 'Lissajous', 'Espiral'];

  const PRESETS = [
    { nombre: 'Túnel', zoom: 1.035, rot: 0.004, warp: 0.004, warpFrec: 6, decay: 0.965, tono: 0.002, onda: 0, caleido: 0, brillo: 1 },
    { nombre: 'Remolino', zoom: 1.012, rot: 0.02, warp: 0.009, warpFrec: 3, decay: 0.975, tono: 0.004, onda: 1, caleido: 0, brillo: 0.9 },
    { nombre: 'Caleidoscopio', zoom: 1.02, rot: -0.008, warp: 0.006, warpFrec: 9, decay: 0.955, tono: 0.003, onda: 2, caleido: 1, brillo: 1 },
    { nombre: 'Nebulosa', zoom: 0.992, rot: 0.003, warp: 0.012, warpFrec: 2, decay: 0.982, tono: 0.001, onda: 0, caleido: 0, brillo: 0.8 },
    { nombre: 'Espejo', zoom: 1.04, rot: 0, warp: 0.003, warpFrec: 12, decay: 0.94, tono: 0.006, onda: 3, caleido: 1, brillo: 1.1 },
    { nombre: 'Lissajous', zoom: 1.006, rot: -0.015, warp: 0.002, warpFrec: 5, decay: 0.97, tono: 0.003, onda: 2, caleido: 0, brillo: 1 },
    { nombre: 'Aurora', zoom: 0.998, rot: 0.001, warp: 0.014, warpFrec: 1.5, decay: 0.988, tono: 0.0015, onda: 1, caleido: 0, brillo: 0.85 },
    { nombre: 'Pulso', zoom: 1.055, rot: 0.002, warp: 0.002, warpFrec: 8, decay: 0.93, tono: 0.004, onda: 0, caleido: 0, brillo: 1.25 },
    { nombre: 'Vórtice', zoom: 1.02, rot: 0.035, warp: 0.007, warpFrec: 4, decay: 0.972, tono: -0.003, onda: 3, caleido: 0, brillo: 1.05 },
    { nombre: 'Cristal', zoom: 1.015, rot: 0.012, warp: 0.005, warpFrec: 14, decay: 0.95, tono: 0.005, onda: 2, caleido: 1, brillo: 1.15 },
    { nombre: 'Océano', zoom: 1.003, rot: -0.004, warp: 0.016, warpFrec: 2.5, decay: 0.984, tono: 0.002, onda: 1, caleido: 0, brillo: 0.9 },
    { nombre: 'Brasas', zoom: 0.985, rot: -0.006, warp: 0.01, warpFrec: 5, decay: 0.978, tono: -0.002, onda: 0, caleido: 0, brillo: 1.1 },
  ];

  // Qué ajustes y qué color van con cada estilo de música (los nombres son los del generador).
  const ESTILO_PRESETS = {
    Ambiente: ['Nebulosa', 'Aurora', 'Océano'],
    Pop: ['Túnel', 'Pulso', 'Espejo', 'Cristal'],
    Orquestal: ['Remolino', 'Vórtice', 'Nebulosa', 'Brasas'],
    Chiptune: ['Caleidoscopio', 'Lissajous', 'Cristal'],
    'Lo-fi': ['Aurora', 'Océano', 'Remolino'],
    'Cajita de música': ['Cristal', 'Caleidoscopio', 'Nebulosa'],
  };
  const MATIZ_ESTILO = { Ambiente: 190, Pop: 320, Orquestal: 40, Chiptune: 125, 'Lo-fi': 25, 'Cajita de música': 270 };

  function acotar(x, lo, hi) {
    return x < lo ? lo : x > hi ? hi : x;
  }

  /** Un ajuste saneado: los números dentro de sus rangos, la onda entre 0 y 3, el nombre acotado. */
  function validar(p) {
    if (!p || typeof p !== 'object') return null;
    // se quitan los caracteres de control y los < > del nombre, que se muestra en la interfaz
    // eslint-disable-next-line no-control-regex
    const out = { nombre: String(p.nombre == null ? 'Sin nombre' : p.nombre).replace(/[\u0000-\u001f<>]/g, '').trim().slice(0, 40) || 'Sin nombre' };
    for (const k of CLAVES_NUMERICAS) {
      const v = Number(p[k]);
      if (!Number.isFinite(v)) return null;
      out[k] = acotar(v, LIMITES[k].min, LIMITES[k].max);
    }
    const onda = Number(p.onda);
    if (!Number.isFinite(onda)) return null;
    out.onda = acotar(Math.round(onda), 0, FORMAS_ONDA.length - 1);
    out.caleido = Number(p.caleido) > 0.5 ? 1 : 0;
    return out;
  }

  /** Mezcla de dos ajustes: k = 0 es a, k = 1 es b. La onda y el caleidoscopio no se interpolan. */
  function mezclar(a, b, k) {
    const out = { nombre: k < 0.5 ? a.nombre : b.nombre };
    for (const c of CLAVES_NUMERICAS) out[c] = a[c] + (b[c] - a[c]) * k;
    out.onda = k < 0.5 ? a.onda : b.onda;
    out.caleido = k < 0.5 ? a.caleido : b.caleido;
    return out;
  }

  /**
   * El mismo ajuste con menos movimiento, para quien lo pide (la preferencia «reducir movimiento»
   * del sistema o el interruptor de la aplicación). k = 1 deja el ajuste igual y k = 0 lo deja casi
   * quieto: el zoom se acerca a 1, el giro, la ondulación y el cambio de color a 0, el brillo no
   * pasa de 1 y la estela se alarga (la imagen cambia más despacio). No toca la forma de la onda
   * ni el espejo. Devuelve una copia.
   */
  function suavizarPreset(p, k) {
    const f = acotar(k, 0, 1);
    return Object.assign({}, p, {
      zoom: 1 + (p.zoom - 1) * f,
      rot: p.rot * f,
      warp: p.warp * f,
      tono: p.tono * f,
      brillo: Math.min(p.brillo, 1 + (p.brillo - 1) * f),
      decay: p.decay + Math.max(0, 0.985 - p.decay) * (1 - f),
    });
  }

  /** Un ajuste que va con el estilo: del grupo del estilo, distinto de `actual` si se puede. */
  function paraEstilo(estilo, azar, actual, lista) {
    const todos = lista || PRESETS;
    const nombres = ESTILO_PRESETS[estilo] || todos.map(function (p) { return p.nombre; });
    let candidatos = todos.filter(function (p) { return nombres.indexOf(p.nombre) >= 0 && p.nombre !== actual; });
    if (!candidatos.length) candidatos = todos.filter(function (p) { return p.nombre !== actual; });
    if (!candidatos.length) candidatos = todos;
    return candidatos[Math.floor(azar() * candidatos.length)];
  }

  const MAX_IMPORTADOS = 50;

  function serializar(lista) {
    return JSON.stringify({ formato: 'musica-infinita-presets', version: 1, presets: lista.map(validar).filter(Boolean) }, null, 2);
  }

  /**
   * Lee un JSON de ajustes. Devuelve {presets, rechazados}; lanza un error con un mensaje
   * claro si el texto no es un JSON de ajustes. Acepta un arreglo suelto o {presets: [...]}.
   */
  function importar(texto) {
    let d;
    try {
      d = JSON.parse(texto);
    } catch (e) {
      throw new Error('El archivo no es un JSON válido', { cause: e });
    }
    const lista = Array.isArray(d) ? d : d && Array.isArray(d.presets) ? d.presets : null;
    if (!lista) throw new Error('El archivo no trae una lista de ajustes');
    const presets = [];
    let rechazados = 0;
    for (const p of lista.slice(0, MAX_IMPORTADOS)) {
      const v = validar(p);
      if (v) presets.push(v);
      else rechazados++;
    }
    rechazados += Math.max(0, lista.length - MAX_IMPORTADOS);
    return { presets: presets, rechazados: rechazados };
  }

  // ------------------------------------------------------------ partículas

  /** Sistema de partículas de tamaño fijo (un anillo: la más vieja cede su lugar). */
  function crearParticulas(max) {
    const n = max || 700;
    return {
      max: n,
      x: new Float32Array(n),
      y: new Float32Array(n),
      vx: new Float32Array(n),
      vy: new Float32Array(n),
      vida: new Float32Array(n), // segundos que le quedan; 0 es libre
      vidaMax: new Float32Array(n),
      tam: new Float32Array(n),
      matiz: new Float32Array(n),
      siguiente: 0,
    };
  }

  function emitir(s, p) {
    const i = s.siguiente;
    s.siguiente = (i + 1) % s.max;
    s.x[i] = p.x;
    s.y[i] = p.y;
    s.vx[i] = p.vx || 0;
    s.vy[i] = p.vy || 0;
    s.vida[i] = s.vidaMax[i] = Math.max(0.05, p.vida || 1);
    s.tam[i] = p.tam || 3;
    s.matiz[i] = p.matiz || 0;
    return i;
  }

  /** Avanza dt segundos. opciones: {gravedad (px/s², positiva cae), arrastre (0..1 por s), ancho, alto}. */
  function pasoParticulas(s, dt, opciones) {
    const o = opciones || {};
    const g = o.gravedad == null ? 0 : o.gravedad;
    const arr = Math.pow(1 - (o.arrastre == null ? 0.4 : o.arrastre), dt);
    for (let i = 0; i < s.max; i++) {
      if (s.vida[i] <= 0) continue;
      s.vida[i] -= dt;
      if (s.vida[i] <= 0) {
        s.vida[i] = 0;
        continue;
      }
      s.vy[i] += g * dt;
      s.vx[i] *= arr;
      s.vy[i] *= arr;
      s.x[i] += s.vx[i] * dt;
      s.y[i] += s.vy[i] * dt;
      if (o.ancho != null && (s.x[i] < -50 || s.x[i] > o.ancho + 50 || s.y[i] < -50 || s.y[i] > o.alto + 50)) s.vida[i] = 0;
    }
  }

  function vivas(s) {
    let n = 0;
    for (let i = 0; i < s.max; i++) if (s.vida[i] > 0) n++;
    return n;
  }

  /** De 0 a 1: cuánta vida le queda a la partícula i. */
  function vidaRelativa(s, i) {
    return s.vidaMax[i] > 0 ? s.vida[i] / s.vidaMax[i] : 0;
  }

  /** Posición horizontal (0..1) de una nota en la pantalla de partículas: grave a la izquierda. */
  function xDeNota(nota, notaMin, notaMax) {
    return acotar((nota - (notaMin == null ? 28 : notaMin)) / ((notaMax == null ? 96 : notaMax) - (notaMin == null ? 28 : notaMin)), 0, 1);
  }

  // ------------------------------------------------------------ Lissajous 3D

  /**
   * Proyecta un punto 3D (x, y, z) girado angX alrededor de X y angY alrededor de Y, con
   * perspectiva a `dist` unidades de la cámara. Devuelve {x, y, z, escala}: x e y en el
   * plano de la pantalla (centrado en 0), z la profundidad ya girada y escala 1 a la
   * distancia de referencia.
   */
  function proyectar(x, y, z, angX, angY, dist) {
    const cy = Math.cos(angY);
    const sy = Math.sin(angY);
    const x1 = x * cy + z * sy;
    const z1 = -x * sy + z * cy;
    const cx = Math.cos(angX);
    const sx = Math.sin(angX);
    const y1 = y * cx - z1 * sx;
    const z2 = y * sx + z1 * cx;
    const d = dist == null ? 3 : dist;
    const escala = d / Math.max(0.2, d + z2);
    return { x: x1 * escala, y: y1 * escala, z: z2, escala: escala };
  }

  /**
   * La curva de Lissajous 3D de una señal estéreo: x = izquierda, y = derecha y z = la
   * izquierda `retardo` muestras después. Escribe los puntos proyectados en out (x, y, z por
   * punto, en un Float32Array) y devuelve cuántos. `ganancia` normaliza la amplitud.
   */
  function curvaLissajous3d(izq, der, retardo, angX, angY, ganancia, out, dist) {
    const n = izq.length;
    let k = 0;
    for (let i = 0; i < n; i++) {
      const p = proyectar(izq[i] * ganancia, der[i] * ganancia, izq[(i + retardo) % n] * ganancia, angX, angY, dist);
      out[k++] = p.x;
      out[k++] = p.y;
      out[k++] = p.z;
    }
    return n;
  }

  /** Ganancia para que la señal ocupe la pantalla: 1/pico, sin pasar de 6 ni bajar de 1. */
  function gananciaAuto(izq, der) {
    let pico = 0.05;
    for (let i = 0; i < izq.length; i++) {
      const a = Math.abs(izq[i]);
      const b = Math.abs(der[i]);
      if (a > pico) pico = a;
      if (b > pico) pico = b;
    }
    return acotar(0.9 / pico, 1, 6);
  }

  // ------------------------------------------------------------ espectro

  /**
   * Las n barras del espectro, de 0 a 1, repartidas en escala logarítmica entre fMin y fMax.
   * frec es la FFT en bytes (0..255). Cada barra toma el máximo de sus bins.
   */
  function barrasEspectro(frec, sr, fftSize, n, fMin, fMax, out) {
    const res = out || new Float32Array(n);
    const binHz = sr / fftSize;
    const lo = fMin == null ? 30 : fMin;
    const hi = Math.min(fMax == null ? 18000 : fMax, sr / 2);
    for (let i = 0; i < n; i++) {
      const f0 = lo * Math.pow(hi / lo, i / n);
      const f1 = lo * Math.pow(hi / lo, (i + 1) / n);
      const b0 = Math.max(1, Math.floor(f0 / binHz));
      const b1 = Math.max(b0 + 1, Math.ceil(f1 / binHz));
      let v = 0;
      for (let b = b0; b < b1 && b < frec.length; b++) if (frec[b] > v) v = frec[b];
      res[i] = Math.pow(v / 255, 1.4);
    }
    return res;
  }

  function crearBarras(n) {
    return { n: n, alturas: new Float32Array(n), picos: new Float32Array(n), velPicos: new Float32Array(n) };
  }

  /**
   * Barras que suben de golpe y bajan con gravedad, y picos que se quedan arriba y caen
   * acelerando, como en Winamp. valores: 0..1 por barra; alto: píxeles de la pantalla.
   */
  function actualizarBarras(est, valores, alto, gravedad) {
    const g = gravedad == null ? 0.025 : gravedad;
    for (let i = 0; i < est.n; i++) {
      est.alturas[i] = Math.max(valores[i], est.alturas[i] - g);
      const h = est.alturas[i] * alto * 0.92;
      if (h > est.picos[i]) {
        est.picos[i] = h;
        est.velPicos[i] = 0;
      } else {
        est.velPicos[i] += 0.35;
        est.picos[i] = Math.max(0, est.picos[i] - est.velPicos[i]);
      }
    }
    return est;
  }

  // ------------------------------------------------------------ lluvia de notas

  /**
   * Rectángulo de una nota en la lluvia de notas, o null si queda fuera de la pantalla.
   * La línea de «ahora» está al 78 % del ancho; a la derecha viene lo que va a sonar.
   * Las notas de batería no tienen altura propia: se reparten cerca del suelo.
   */
  function rectNota(n, ahora, W, H, o) {
    const op = Object.assign({ segundosPorAncho: 9, notaMin: 28, notaMax: 96, xAhora: 0.78 }, o);
    const px = W / op.segundosPorAncho;
    const xAhora = W * op.xAhora;
    const alto = H / (op.notaMax - op.notaMin);
    const x0 = xAhora + (n.t - ahora) * px;
    const x1 = x0 + Math.max(2, n.dur * px);
    if (x1 < 0 || x0 > W) return null;
    const y = n.canal === 9 ? H - alto * 3 - ((n.nota * 37) % 11) * alto * 0.6 : H - (acotar(n.nota, op.notaMin, op.notaMax) - op.notaMin) * alto;
    const h = Math.max(3, alto * (n.canal === 9 ? 0.5 : 0.85));
    const sonando = n.t <= ahora && n.t + n.dur >= ahora;
    return { x0: x0, x1: x1, y: y, h: h, sonando: sonando, alfa: sonando ? 1 : n.t > ahora ? 0.35 : 0.55 };
  }

  /** Matiz (0..360) hacia el que se mueve la paleta con un estilo (190 si no se conoce). */
  function matizDeEstilo(estilo) {
    return MATIZ_ESTILO[estilo] == null ? 190 : MATIZ_ESTILO[estilo];
  }

  /** Distancia más corta entre dos matices (0..360) con signo: cuánto sumar a `de` para llegar a `a`. */
  function difMatiz(de, a) {
    return ((((a - de) % 360) + 540) % 360) - 180;
  }

  const API = {
    LIMITES: LIMITES,
    CLAVES_NUMERICAS: CLAVES_NUMERICAS,
    FORMAS_ONDA: FORMAS_ONDA,
    PRESETS: PRESETS,
    ESTILO_PRESETS: ESTILO_PRESETS,
    MATIZ_ESTILO: MATIZ_ESTILO,
    validar: validar,
    mezclar: mezclar,
    suavizarPreset: suavizarPreset,
    paraEstilo: paraEstilo,
    serializar: serializar,
    importar: importar,
    crearParticulas: crearParticulas,
    emitir: emitir,
    pasoParticulas: pasoParticulas,
    vivas: vivas,
    vidaRelativa: vidaRelativa,
    xDeNota: xDeNota,
    proyectar: proyectar,
    curvaLissajous3d: curvaLissajous3d,
    gananciaAuto: gananciaAuto,
    barrasEspectro: barrasEspectro,
    crearBarras: crearBarras,
    actualizarBarras: actualizarBarras,
    rectNota: rectNota,
    matizDeEstilo: matizDeEstilo,
    difMatiz: difMatiz,
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.VisualPuro = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
