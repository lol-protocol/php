/*
 * Teoría musical mínima: escalas, grados, tonalidad y acordes.
 *
 * Funciones puras (navegador y Node). Las alturas son números MIDI (60 = do central),
 * las clases de altura van de 0 (do) a 11 (si).
 *
 * La melodía se guarda en grados de la escala y no en semitonos: así una frase de una
 * canción en mi menor se puede citar en re mayor o en la menor y sigue sonando a sí misma,
 * ajustada a la escala de destino.
 */
(function (raiz) {
  'use strict';

  const NOMBRES = ['do', 'do♯', 're', 'mi♭', 'mi', 'fa', 'fa♯', 'sol', 'la♭', 'la', 'si♭', 'si'];

  const ESCALAS = {
    mayor: [0, 2, 4, 5, 7, 9, 11],
    menor: [0, 2, 3, 5, 7, 8, 10],
    dorica: [0, 2, 3, 5, 7, 9, 10],
    mixolidia: [0, 2, 4, 5, 7, 9, 10],
    lidia: [0, 2, 4, 6, 7, 9, 11],
    frigia: [0, 1, 3, 5, 7, 8, 10],
  };

  // Perfiles de Krumhansl y Kessler (1982): cuánto "pertenece" cada grado a la tonalidad.
  const PERFIL_MAYOR = [6.35, 2.23, 3.48, 2.33, 4.38, 4.09, 2.52, 5.19, 2.39, 3.66, 2.29, 2.88];
  const PERFIL_MENOR = [6.33, 2.68, 3.52, 5.38, 2.6, 3.53, 2.54, 4.75, 3.98, 2.69, 3.34, 3.17];

  function mod(a, n) {
    return ((a % n) + n) % n;
  }

  function nombreNota(nota) {
    return NOMBRES[mod(nota, 12)] + (Math.floor(nota / 12) - 1);
  }

  function nombreTonalidad(tonica, modo) {
    return NOMBRES[mod(tonica, 12)] + ' ' + modo;
  }

  function correlacion(a, b) {
    let ma = 0;
    let mb = 0;
    for (let i = 0; i < 12; i++) {
      ma += a[i];
      mb += b[i];
    }
    ma /= 12;
    mb /= 12;
    let num = 0;
    let da = 0;
    let db = 0;
    for (let i = 0; i < 12; i++) {
      num += (a[i] - ma) * (b[i] - mb);
      da += (a[i] - ma) * (a[i] - ma);
      db += (b[i] - mb) * (b[i] - mb);
    }
    if (da === 0 || db === 0) return 0;
    return num / Math.sqrt(da * db);
  }

  /** Histograma de clases de altura, ponderado por duración. notas: [{nota, dur}] */
  function histograma(notas) {
    const h = new Array(12).fill(0);
    for (const n of notas) h[mod(n.nota, 12)] += n.dur > 0 ? n.dur : 1;
    return h;
  }

  /**
   * Tonalidad por el algoritmo de Krumhansl-Schmuckler, con una ayuda: casi todas las
   * canciones terminan (y muchas empiezan) en la tónica, así que la nota más grave del
   * último ataque y la del primero suman a su tonalidad. Sin esa ayuda, una melodía en do
   * mayor con mucho sol en el bajo sale en sol mayor.
   * pistas = {final: clase de altura, inicial: clase de altura} (opcionales).
   */
  function detectarTonalidad(notas, pistas) {
    const h = histograma(notas);
    pistas = pistas || {};
    let mejor = { tonica: 0, modo: 'mayor', correlacion: -2 };
    for (let t = 0; t < 12; t++) {
      const rot = new Array(12);
      for (let i = 0; i < 12; i++) rot[i] = h[mod(i + t, 12)];
      let extra = 0;
      if (pistas.final != null && mod(pistas.final, 12) === t) extra += 0.12;
      if (pistas.inicial != null && mod(pistas.inicial, 12) === t) extra += 0.05;
      const cM = correlacion(rot, PERFIL_MAYOR) + extra;
      const cm = correlacion(rot, PERFIL_MENOR) + extra;
      if (cM > mejor.correlacion) mejor = { tonica: t, modo: 'mayor', correlacion: cM };
      if (cm > mejor.correlacion) mejor = { tonica: t, modo: 'menor', correlacion: cm };
    }
    return mejor;
  }

  function escala(modo) {
    return ESCALAS[modo] || ESCALAS.mayor;
  }

  /**
   * Grado de una nota en una tonalidad: {grado, alt}. grado cuenta pasos diatónicos desde
   * la tónica de la octava 4 (grado 0 = tónica en 60..71, 7 = tónica una octava arriba,
   * -1 = séptimo grado de abajo). alt es -1, 0 o +1 si la nota está fuera de la escala.
   */
  function gradoDe(nota, tonica, modo) {
    const esc = escala(modo);
    const rel = nota - (60 + mod(tonica, 12));
    const oct = Math.floor(rel / 12);
    const pc = mod(rel, 12);
    const i = esc.indexOf(pc);
    if (i >= 0) return { grado: oct * 7 + i, alt: 0 };
    // fuera de la escala: se toma el grado de abajo con alteración +1 (o el de arriba con -1
    // si así queda más cerca, como en la menor el si♮ = 7.º grado elevado)
    for (let k = 0; k < 7; k++) {
      if (esc[k] === pc - 1) return { grado: oct * 7 + k, alt: 1 };
    }
    for (let k = 0; k < 7; k++) {
      if (esc[k] === pc + 1) return { grado: oct * 7 + k, alt: -1 };
    }
    return { grado: oct * 7, alt: pc }; // no debería pasar con escalas de 7 notas
  }

  function notaDeGrado(grado, alt, tonica, modo) {
    const esc = escala(modo);
    const oct = Math.floor(grado / 7);
    const i = mod(grado, 7);
    return 60 + mod(tonica, 12) + oct * 12 + esc[i] + (alt || 0);
  }

  // ---------------------------------------------------------------- acordes

  const TIPOS_ACORDE = {
    M: [0, 4, 7],
    m: [0, 3, 7],
    dim: [0, 3, 6],
    aug: [0, 4, 8],
    sus4: [0, 5, 7],
    '7': [0, 4, 7, 10],
    maj7: [0, 4, 7, 11],
    m7: [0, 3, 7, 10],
  };
  // Penalización leve a los acordes raros, para preferir M y m cuando empatan.
  const PESO_TIPO = { M: 1, m: 1, dim: 0.86, aug: 0.78, sus4: 0.8, '7': 0.9, maj7: 0.82, m7: 0.86 };

  /** Acorde que mejor explica un histograma de clases de altura: {raiz, tipo, puntaje}. */
  function detectarAcorde(h, bajo) {
    let total = 0;
    for (let i = 0; i < 12; i++) total += h[i];
    if (total <= 0) return null;
    let mejor = null;
    for (let r = 0; r < 12; r++) {
      for (const tipo in TIPOS_ACORDE) {
        const tpl = TIPOS_ACORDE[tipo];
        let dentro = 0;
        for (const iv of tpl) dentro += h[mod(r + iv, 12)];
        const fuera = total - dentro;
        let p = (dentro - 0.6 * fuera) / total;
        p *= PESO_TIPO[tipo];
        if (h[r] > 0) p += 0.08; // la raíz suena
        if (bajo != null && mod(bajo, 12) === r) p += 0.12; // y está en el bajo
        p -= 0.05 * (tpl.length - 3);
        if (!mejor || p > mejor.puntaje) mejor = { raiz: r, tipo: tipo, puntaje: p };
      }
    }
    return mejor;
  }

  function notasAcorde(raizPc, tipo) {
    return (TIPOS_ACORDE[tipo] || TIPOS_ACORDE.M).map(function (iv) {
      return mod(raizPc + iv, 12);
    });
  }

  /** Acorde diatónico sobre un grado (0..6) de la escala: tríada o cuatríada. */
  function acordeDiatonico(gradoEsc, modo, conSeptima) {
    const esc = escala(modo);
    const g = mod(gradoEsc, 7);
    const r = esc[g];
    const t = mod(esc[(g + 2) % 7] - r, 12);
    const q = mod(esc[(g + 4) % 7] - r, 12);
    let tipo = t === 4 ? (q === 8 ? 'aug' : 'M') : q === 6 ? 'dim' : 'm';
    if (conSeptima) {
      const s = mod(esc[(g + 6) % 7] - r, 12);
      if (tipo === 'M') tipo = s === 11 ? 'maj7' : '7';
      else if (tipo === 'm') tipo = 'm7';
    }
    return { raizRel: r, tipo: tipo };
  }

  const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

  function nombreAcorde(raizPc, tipo) {
    const n = NOMBRES[mod(raizPc, 12)];
    const suf = { M: '', m: 'm', dim: 'º', aug: '+', sus4: 'sus4', '7': '7', maj7: 'maj7', m7: 'm7' }[tipo];
    return n.charAt(0).toUpperCase() + n.slice(1) + (suf == null ? tipo : suf);
  }

  /**
   * Cifrado romano relativo a la tonalidad, sin depender de la escala: un acorde se guarda
   * por la distancia de su raíz a la tónica (0..11) y su tipo. Así una progresión de una
   * canción en la menor se puede tocar en re menor sin cambiar su color.
   */
  function romano(raizRel, tipo) {
    const mayor = [0, 2, 4, 5, 7, 9, 11];
    let i = mayor.indexOf(mod(raizRel, 12));
    let pre = '';
    if (i < 0) {
      i = mayor.indexOf(mod(raizRel + 1, 12));
      pre = '♭';
    }
    let r = ROMANOS[i];
    if (tipo === 'm' || tipo === 'm7' || tipo === 'dim') r = r.toLowerCase();
    const suf = { M: '', m: '', dim: 'º', aug: '+', sus4: 'sus4', '7': '7', maj7: 'maj7', m7: '7' }[tipo];
    return pre + r + (suf || '');
  }

  const API = {
    NOMBRES: NOMBRES,
    ESCALAS: ESCALAS,
    TIPOS_ACORDE: TIPOS_ACORDE,
    mod: mod,
    nombreNota: nombreNota,
    nombreTonalidad: nombreTonalidad,
    histograma: histograma,
    detectarTonalidad: detectarTonalidad,
    escala: escala,
    gradoDe: gradoDe,
    notaDeGrado: notaDeGrado,
    detectarAcorde: detectarAcorde,
    notasAcorde: notasAcorde,
    acordeDiatonico: acordeDiatonico,
    nombreAcorde: nombreAcorde,
    romano: romano,
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Teoria = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
