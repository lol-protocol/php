/*
 * Análisis de una canción MIDI: su tonalidad, su melodía en grados de la escala, sus
 * frases y su progresión de acordes. Es lo que el generador aprende y lo que cita.
 *
 * Funciones puras (navegador y Node). Necesita teoria.js (en el navegador, cargado antes;
 * en Node, con require).
 */
(function (raiz) {
  'use strict';

  const T = typeof module !== 'undefined' && module.exports ? require('./teoria.js') : raiz.Teoria;

  const CANAL_BATERIA = 9;
  // Duraciones permitidas, en pulsos (negras): de semicorchea a redonda con puntillo, y tresillos.
  const DURACIONES = [0.25, 1 / 3, 0.5, 2 / 3, 0.75, 1, 1.5, 2, 3, 4, 6, 8];
  const PALABRAS_MELODIA = /melod|lead|vocal|voice|voz|canto|solo|tema|theme|sing|right|derech/i;

  function cuantizarDuracion(d) {
    let mejor = DURACIONES[0];
    let err = Infinity;
    for (const x of DURACIONES) {
      const e = Math.abs(Math.log(d / x));
      if (e < err) {
        err = e;
        mejor = x;
      }
    }
    return mejor;
  }

  // Inicio al semicorcheas o a tresillos de corchea, lo que quede más cerca.
  function cuantizarInicio(p) {
    const a = Math.round(p * 4) / 4;
    const b = Math.round(p * 3) / 3;
    return Math.abs(a - p) <= Math.abs(b - p) + 0.02 ? a : b;
  }

  function redondear(x) {
    return Math.round(x * 1000) / 1000;
  }

  /** Puntaje de "cuán melódico" es un grupo de notas (una pista y un canal). */
  function puntajeMelodia(grupo, nombrePista) {
    const ns = grupo.notas;
    if (ns.length < 6) return -Infinity;
    let suma = 0;
    let solapadas = 0;
    for (let i = 0; i < ns.length; i++) {
      suma += ns[i].nota;
      if (i > 0 && ns[i].inicioPulso < ns[i - 1].inicioPulso + ns[i - 1].durPulsos - 0.05) solapadas++;
    }
    const media = suma / ns.length;
    const polifonia = solapadas / ns.length;
    let p = 2 * (1 - polifonia);
    p += 1.5 * Math.max(0, Math.min(1, (media - 50) / 22));
    p += Math.min(1, ns.length / 64);
    if (media < 48) p -= 2; // registro de bajo
    if (nombrePista && PALABRAS_MELODIA.test(nombrePista)) p += 2;
    const prog = ns[0].programa;
    if (prog >= 112) p -= 1; // efectos y percusión melódica rara
    if (prog >= 88 && prog <= 103) p -= 0.6; // pads y efectos de sintetizador
    return p;
  }

  /** Línea superior (skyline): en cada ataque, la nota más aguda, y sin solapes. */
  function lineaSuperior(notas) {
    const orden = notas.slice().sort(function (a, b) {
      return a.inicioPulso - b.inicioPulso || b.nota - a.nota;
    });
    const out = [];
    for (const n of orden) {
      const prev = out[out.length - 1];
      if (prev && n.inicioPulso - prev.inicioPulso < 0.08) {
        if (n.nota > prev.nota) out[out.length - 1] = n;
        continue;
      }
      // una nota que empieza mientras suena otra más aguda y larga es acompañamiento
      if (prev && n.inicioPulso < prev.inicioPulso + prev.durPulsos - 0.1 && n.nota < prev.nota - 4) continue;
      out.push(n);
    }
    return out;
  }

  /**
   * Analiza una canción ya leída con MidiArchivo.leer(). Devuelve null si no hay melodía.
   */
  function analizar(midi, nombre) {
    const tonales = midi.notas.filter(function (n) {
      return n.canal !== CANAL_BATERIA;
    });
    if (tonales.length < 6) return null;

    const ton = T.detectarTonalidad(tonales.map(function (n) {
      return { nota: n.nota, dur: n.durPulsos };
    }), { final: bajoEn(tonales, 'ultimo'), inicial: bajoEn(tonales, 'primero') });
    const compas = midi.compases[0] || { num: 4, den: 4 };
    const pulsosCompas = (compas.num * 4) / compas.den;
    const bpm = Math.round(60e6 / midi.tempos[0].uspq);

    // Grupos por pista y canal; el más melódico da la melodía.
    const grupos = new Map();
    for (const n of tonales) {
      const k = n.pista * 16 + n.canal;
      if (!grupos.has(k)) grupos.set(k, { pista: n.pista, canal: n.canal, notas: [] });
      grupos.get(k).notas.push(n);
    }
    let elegido = null;
    let mejor = -Infinity;
    grupos.forEach(function (g) {
      const nombrePista = midi.pistas[g.pista] ? midi.pistas[g.pista].nombre : '';
      const p = puntajeMelodia(g, nombrePista);
      if (p > mejor) {
        mejor = p;
        elegido = g;
      }
    });
    if (!elegido) elegido = { notas: tonales, programa: 0 };

    const linea = lineaSuperior(elegido.notas);
    const melodia = [];
    for (let i = 0; i < linea.length; i++) {
      const n = linea[i];
      const ini = cuantizarInicio(n.inicioPulso);
      const g = T.gradoDe(n.nota, ton.tonica, ton.modo);
      melodia.push({ g: g.grado, alt: g.alt, inicio: ini, durReal: n.durPulsos, vel: n.vel });
    }
    // Quita duplicados de inicio que dejó la cuantización.
    for (let i = melodia.length - 1; i > 0; i--) {
      if (melodia[i].inicio <= melodia[i - 1].inicio) melodia.splice(i, 1);
    }
    if (melodia.length < 4) return null;
    for (let i = 0; i < melodia.length; i++) {
      const m = melodia[i];
      const sig = melodia[i + 1];
      const ioi = sig ? sig.inicio - m.inicio : cuantizarDuracion(m.durReal);
      m.ioi = ioi > 8 ? 8 : cuantizarDuracion(ioi);
      m.dur = Math.min(m.ioi, cuantizarDuracion(Math.max(0.2, m.durReal)));
      m.inicio = redondear(m.inicio);
      delete m.durReal;
    }

    const frases = segmentarFrases(melodia, pulsosCompas);
    const acordes = extraerAcordes(tonales, ton.tonica, pulsosCompas);

    return {
      nombre: nombre || 'Sin nombre',
      tonica: ton.tonica,
      modo: ton.modo,
      confianzaTonalidad: redondear(ton.correlacion),
      bpm: bpm,
      compas: [compas.num, compas.den],
      pulsosCompas: pulsosCompas,
      programaMelodia: elegido.notas[0] ? elegido.notas[0].programa : 0,
      melodia: melodia,
      frases: frases,
      acordes: acordes,
      duracionPulsos: redondear(Math.max.apply(null, tonales.map(function (n) { return n.inicioPulso + n.durPulsos; }))),
    };
  }

  /** Nota más grave del primer o del último ataque. */
  function bajoEn(notas, cual) {
    let ref = cual === 'ultimo' ? -Infinity : Infinity;
    for (const n of notas) {
      if (cual === 'ultimo' ? n.inicioPulso > ref : n.inicioPulso < ref) ref = n.inicioPulso;
    }
    let bajo = null;
    for (const n of notas) {
      if (Math.abs(n.inicioPulso - ref) < 0.1 && (bajo === null || n.nota < bajo)) bajo = n.nota;
    }
    return bajo;
  }

  /** Corta la melodía en frases: en silencios de un pulso o más, notas largas o cada 4 compases. */
  function segmentarFrases(melodia, pulsosCompas) {
    const frases = [];
    let desde = 0;
    const largoMax = pulsosCompas * 4;
    for (let i = 0; i < melodia.length; i++) {
      const m = melodia[i];
      const silencio = m.ioi - m.dur;
      const transcurrido = m.inicio + m.ioi - melodia[desde].inicio;
      const corte = silencio >= 1 || m.ioi >= 3 || transcurrido >= largoMax || i === melodia.length - 1;
      if (corte && i + 1 - desde >= 3) {
        frases.push({ desde: desde, hasta: i + 1, inicio: melodia[desde].inicio, pulsos: redondear(transcurrido) });
        desde = i + 1;
      } else if (corte && i === melodia.length - 1 && frases.length) {
        const f = frases[frases.length - 1];
        f.hasta = i + 1;
        f.pulsos = redondear(m.inicio + m.ioi - f.inicio);
      }
    }
    if (!frases.length) {
      const ult = melodia[melodia.length - 1];
      frases.push({ desde: 0, hasta: melodia.length, inicio: melodia[0].inicio, pulsos: redondear(ult.inicio + ult.ioi - melodia[0].inicio) });
    }
    return frases;
  }

  /** Un acorde cada medio compás (o cada compás si es de 3), relativo a la tónica. */
  function extraerAcordes(notas, tonica, pulsosCompas) {
    const paso = pulsosCompas % 2 === 0 && pulsosCompas >= 4 ? pulsosCompas / 2 : pulsosCompas;
    let fin = 0;
    for (const n of notas) fin = Math.max(fin, n.inicioPulso + n.durPulsos);
    const tramos = Math.ceil(fin / paso);
    const orden = notas.slice().sort(function (a, b) { return a.inicioPulso - b.inicioPulso; });
    const out = [];
    let j0 = 0;
    for (let t = 0; t < tramos; t++) {
      const a = t * paso;
      const b = a + paso;
      const h = new Array(12).fill(0);
      let bajo = null;
      while (j0 < orden.length && orden[j0].inicioPulso + orden[j0].durPulsos <= a - 16) j0++;
      for (let j = j0; j < orden.length; j++) {
        const n = orden[j];
        if (n.inicioPulso >= b) break;
        const solape = Math.min(b, n.inicioPulso + n.durPulsos) - Math.max(a, n.inicioPulso);
        if (solape <= 0) continue;
        // lo que suena en el tiempo fuerte pesa más
        const fuerte = n.inicioPulso <= a + 0.05 ? 1.5 : 1;
        h[T.mod(n.nota, 12)] += solape * fuerte;
        if (bajo === null || n.nota < bajo) bajo = n.nota;
      }
      const ac = T.detectarAcorde(h, bajo);
      const previo = out[out.length - 1];
      if (!ac) {
        if (previo) previo.pulsos += paso;
        continue;
      }
      const raizRel = T.mod(ac.raiz - tonica, 12);
      if (previo && previo.raizRel === raizRel && previo.tipo === ac.tipo) {
        previo.pulsos += paso;
      } else {
        out.push({ inicio: a, pulsos: paso, raizRel: raizRel, tipo: ac.tipo });
      }
    }
    return out;
  }

  /** Acorde de la canción que suena en un pulso dado (o null). */
  function acordeEn(analisis, pulso) {
    const ac = analisis.acordes;
    let lo = 0;
    let hi = ac.length - 1;
    let r = null;
    while (lo <= hi) {
      const m = (lo + hi) >> 1;
      if (ac[m].inicio <= pulso + 1e-6) {
        r = ac[m];
        lo = m + 1;
      } else hi = m - 1;
    }
    return r;
  }

  const API = {
    analizar: analizar,
    acordeEn: acordeEn,
    cuantizarDuracion: cuantizarDuracion,
    cuantizarInicio: cuantizarInicio,
    lineaSuperior: lineaSuperior,
    segmentarFrases: segmentarFrases,
    extraerAcordes: extraerAcordes,
    DURACIONES: DURACIONES,
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Analisis = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
