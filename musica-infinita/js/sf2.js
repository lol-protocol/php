/*
 * Lector de SoundFont 2 (.sf2): los bancos de muestras grabadas que usan los
 * reproductores MIDI de calidad (FluidSynth, TiMidity, los teclados). Con uno bueno
 * (GeneralUser GS, FluidR3_GM, Salamander Grand Piano...) el MIDI suena a instrumentos
 * reales en vez de a sintetizador.
 *
 * Función pura (navegador y Node): lee el archivo y responde qué muestras tocar, con qué
 * afinación, envolvente, filtro, paneo y bucle, para un banco, programa, nota y velocidad.
 * Quien las toca es sintetizador.js.
 *
 * Se implementan los generadores que más pesan en el sonido (rangos, muestra, bucle,
 * afinación, atenuación, paneo, envolvente de volumen, filtro, reverberación, clase
 * exclusiva). Los moduladores, los LFO y la envolvente de modulación no: el sonido queda
 * algo más estático que en FluidSynth, pero las muestras son las mismas.
 */
(function (raiz) {
  'use strict';

  const G = {
    startAddrsOffset: 0,
    endAddrsOffset: 1,
    startloopAddrsOffset: 2,
    endloopAddrsOffset: 3,
    startAddrsCoarseOffset: 4,
    initialFilterFc: 8,
    initialFilterQ: 9,
    endAddrsCoarseOffset: 12,
    chorusEffectsSend: 15,
    reverbEffectsSend: 16,
    pan: 17,
    delayVolEnv: 33,
    attackVolEnv: 34,
    holdVolEnv: 35,
    decayVolEnv: 36,
    sustainVolEnv: 37,
    releaseVolEnv: 38,
    keynumToVolEnvHold: 39,
    keynumToVolEnvDecay: 40,
    instrument: 41,
    keyRange: 43,
    velRange: 44,
    startloopAddrsCoarseOffset: 45,
    keynum: 46,
    velocity: 47,
    initialAttenuation: 48,
    endloopAddrsCoarseOffset: 50,
    coarseTune: 51,
    fineTune: 52,
    sampleID: 53,
    sampleModes: 54,
    scaleTuning: 56,
    exclusiveClass: 57,
    overridingRootKey: 58,
  };
  const N_GEN = 61;

  // Valores por omisión de la norma (SoundFont 2.04, sección 8.1.3).
  function porOmision() {
    const g = new Int32Array(N_GEN);
    g[G.initialFilterFc] = 13500;
    g[G.delayVolEnv] = -12000;
    g[G.attackVolEnv] = -12000;
    g[G.holdVolEnv] = -12000;
    g[G.decayVolEnv] = -12000;
    g[G.releaseVolEnv] = -12000;
    g[G.scaleTuning] = 100;
    g[G.overridingRootKey] = -1;
    g[G.keynum] = -1;
    g[G.velocity] = -1;
    return g;
  }

  // Generadores que no se suman entre preset e instrumento.
  const NO_ADITIVOS = new Set([G.startAddrsOffset, G.endAddrsOffset, G.startloopAddrsOffset, G.endloopAddrsOffset, G.startAddrsCoarseOffset, G.endAddrsCoarseOffset, G.startloopAddrsCoarseOffset, G.endloopAddrsCoarseOffset, G.instrument, G.keyRange, G.velRange, G.keynum, G.velocity, G.sampleID, G.sampleModes, G.exclusiveClass, G.overridingRootKey]);

  function aBytes(d) {
    if (d instanceof Uint8Array) return d;
    if (d instanceof ArrayBuffer) return new Uint8Array(d);
    return new Uint8Array(d.buffer, d.byteOffset, d.byteLength);
  }

  function id4(b, i) {
    return String.fromCharCode(b[i], b[i + 1], b[i + 2], b[i + 3]);
  }

  function nombre20(b, i) {
    let s = '';
    for (let k = 0; k < 20 && b[i + k]; k++) s += String.fromCharCode(b[i + k]);
    return s.trim();
  }

  function leer(datos) {
    const b = aBytes(datos);
    const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);
    if (id4(b, 0) !== 'RIFF' || id4(b, 8) !== 'sfbk') throw new Error('No es una SoundFont 2 (falta RIFF sfbk)');
    const trozos = {};
    let info = {};
    // recorre las tres listas: INFO, sdta y pdta
    let pos = 12;
    const fin = Math.min(b.length, 8 + dv.getUint32(4, true));
    while (pos + 8 <= fin) {
      const id = id4(b, pos);
      const largo = dv.getUint32(pos + 4, true);
      if (id === 'LIST') {
        const tipo = id4(b, pos + 8);
        let p = pos + 12;
        const finLista = pos + 8 + largo;
        while (p + 8 <= finLista) {
          const sid = id4(b, p);
          const sl = dv.getUint32(p + 4, true);
          if (tipo === 'INFO') {
            if (sid === 'INAM') info.nombre = nombre20Largo(b, p + 8, sl);
            if (sid === 'ICOP') info.copyright = nombre20Largo(b, p + 8, sl);
          } else {
            trozos[sid] = { pos: p + 8, largo: sl };
          }
          p += 8 + sl + (sl & 1);
        }
      }
      pos += 8 + largo + (largo & 1);
    }
    for (const req of ['smpl', 'phdr', 'pbag', 'pgen', 'inst', 'ibag', 'igen', 'shdr']) {
      if (!trozos[req]) throw new Error('SoundFont incompleta: falta el trozo ' + req);
    }

    // Muestras de 16 bits (y los 8 bits bajos de sm24, si están).
    const smpl = trozos.smpl;
    const muestras16 = new Int16Array(smpl.largo >> 1);
    for (let i = 0; i < muestras16.length; i++) muestras16[i] = dv.getInt16(smpl.pos + i * 2, true);
    const sm24 = trozos.sm24 && trozos.sm24.largo >= muestras16.length ? b.subarray(trozos.sm24.pos, trozos.sm24.pos + muestras16.length) : null;

    function registros(t, tam, fn) {
      const out = [];
      const n = Math.floor(trozos[t].largo / tam);
      for (let i = 0; i < n; i++) out.push(fn(trozos[t].pos + i * tam));
      return out;
    }
    const phdr = registros('phdr', 38, function (p) {
      return { nombre: nombre20(b, p), programa: dv.getUint16(p + 20, true), banco: dv.getUint16(p + 22, true), bag: dv.getUint16(p + 24, true) };
    });
    const pbag = registros('pbag', 4, function (p) { return dv.getUint16(p, true); });
    const pgen = registros('pgen', 4, function (p) { return { op: dv.getUint16(p, true), lo: b[p + 2], hi: b[p + 3], v: dv.getInt16(p + 2, true) }; });
    const inst = registros('inst', 22, function (p) { return { nombre: nombre20(b, p), bag: dv.getUint16(p + 20, true) }; });
    const ibag = registros('ibag', 4, function (p) { return dv.getUint16(p, true); });
    const igen = registros('igen', 4, function (p) { return { op: dv.getUint16(p, true), lo: b[p + 2], hi: b[p + 3], v: dv.getInt16(p + 2, true) }; });
    const shdr = registros('shdr', 46, function (p) {
      return {
        nombre: nombre20(b, p),
        inicio: dv.getUint32(p + 20, true),
        fin: dv.getUint32(p + 24, true),
        loopIni: dv.getUint32(p + 28, true),
        loopFin: dv.getUint32(p + 32, true),
        frecuencia: dv.getUint32(p + 36, true),
        tono: b[p + 40],
        correccion: dv.getInt8(p + 41),
        tipo: dv.getUint16(p + 44, true),
      };
    });

    // Zonas: cada una es una lista de generadores. La primera puede ser global.
    function zonas(bags, gens, desde, hasta, opFinal) {
      const out = [];
      for (let z = desde; z < hasta; z++) {
        const g0 = bags[z];
        const g1 = z + 1 < bags.length ? bags[z + 1] : gens.length;
        const zona = { gens: new Map(), rangoTeclas: [0, 127], rangoVel: [0, 127], final: null };
        for (let k = g0; k < g1 && k < gens.length; k++) {
          const g = gens[k];
          if (g.op === G.keyRange) zona.rangoTeclas = [g.lo, g.hi];
          else if (g.op === G.velRange) zona.rangoVel = [g.lo, g.hi];
          else if (g.op === opFinal) zona.final = g.v & 0xffff;
          else zona.gens.set(g.op, g.v);
        }
        out.push(zona);
      }
      return out;
    }

    const instrumentos = [];
    for (let i = 0; i + 1 < inst.length; i++) {
      const zs = zonas(ibag, igen, inst[i].bag, inst[i + 1].bag, G.sampleID);
      let global = null;
      const locales = [];
      zs.forEach(function (z, k) {
        if (z.final == null) {
          if (k === 0) global = z;
        } else locales.push(z);
      });
      instrumentos.push({ nombre: inst[i].nombre, global: global, zonas: locales });
    }

    const presets = new Map();
    for (let i = 0; i + 1 < phdr.length; i++) {
      const h = phdr[i];
      const zs = zonas(pbag, pgen, h.bag, phdr[i + 1].bag, G.instrument);
      let global = null;
      const locales = [];
      zs.forEach(function (z, k) {
        if (z.final == null) {
          if (k === 0) global = z;
        } else locales.push(z);
      });
      const regiones = [];
      for (const pz of locales) {
        const ins = instrumentos[pz.final];
        if (!ins) continue;
        const pk = interseccion(pz.rangoTeclas, global ? global.rangoTeclas : [0, 127]);
        const pv = interseccion(pz.rangoVel, global ? global.rangoVel : [0, 127]);
        for (const iz of ins.zonas) {
          const rk = interseccion(pk, interseccion(iz.rangoTeclas, ins.global ? ins.global.rangoTeclas : [0, 127]));
          const rv = interseccion(pv, interseccion(iz.rangoVel, ins.global ? ins.global.rangoVel : [0, 127]));
          if (!rk || !rv) continue;
          const g = porOmision();
          if (ins.global) ins.global.gens.forEach(function (v, op) { if (op < N_GEN) g[op] = v; });
          iz.gens.forEach(function (v, op) { if (op < N_GEN) g[op] = v; });
          // del preset: valores relativos que se suman (el local reemplaza al global)
          const suma = new Map();
          if (global) global.gens.forEach(function (v, op) { suma.set(op, v); });
          pz.gens.forEach(function (v, op) { suma.set(op, v); });
          suma.forEach(function (v, op) {
            if (op < N_GEN && !NO_ADITIVOS.has(op)) g[op] += v;
          });
          const m = shdr[iz.final];
          if (!m || m.tipo & 0x8000) continue; // muestras en ROM: no están en el archivo
          regiones.push({ teclas: rk, vel: rv, gens: g, muestra: iz.final });
        }
      }
      presets.set(h.banco + ':' + h.programa, { nombre: h.nombre, banco: h.banco, programa: h.programa, regiones: regiones });
    }

    return {
      info: info,
      presets: presets,
      muestras: shdr,
      datos16: muestras16,
      datos24: sm24,
      _cache: new Map(),
    };
  }

  function nombre20Largo(b, p, l) {
    let s = '';
    for (let k = 0; k < l && b[p + k]; k++) s += String.fromCharCode(b[p + k]);
    return s.trim();
  }

  function interseccion(a, c) {
    if (!a || !c) return null;
    const lo = Math.max(a[0], c[0]);
    const hi = Math.min(a[1], c[1]);
    return lo <= hi ? [lo, hi] : null;
  }

  function buscarPreset(sf, banco, programa) {
    return sf.presets.get(banco + ':' + programa) || (banco === 128 ? sf.presets.get('128:0') : null) || sf.presets.get('0:' + programa) || sf.presets.get('0:0') || sf.presets.values().next().value || null;
  }

  function seg(tc) {
    return tc <= -12000 ? 0 : Math.pow(2, tc / 1200);
  }

  /**
   * Las regiones que suenan para una nota, ya con todos sus parámetros resueltos.
   * percusion = true usa el banco 128 (la batería GM).
   */
  function regiones(sf, programa, nota, vel, percusion) {
    const p = buscarPreset(sf, percusion ? 128 : 0, percusion ? 0 : programa);
    if (!p) return [];
    const out = [];
    for (const r of p.regiones) {
      if (nota < r.teclas[0] || nota > r.teclas[1] || vel < r.vel[0] || vel > r.vel[1]) continue;
      const g = r.gens;
      const m = sf.muestras[r.muestra];
      const raizNota = g[G.overridingRootKey] >= 0 ? g[G.overridingRootKey] : m.tono === 255 ? 60 : m.tono;
      const inicio = m.inicio + g[G.startAddrsOffset] + 32768 * g[G.startAddrsCoarseOffset];
      const fin = m.fin + g[G.endAddrsOffset] + 32768 * g[G.endAddrsCoarseOffset];
      const loopIni = m.loopIni + g[G.startloopAddrsOffset] + 32768 * g[G.startloopAddrsCoarseOffset];
      const loopFin = m.loopFin + g[G.endloopAddrsOffset] + 32768 * g[G.endloopAddrsCoarseOffset];
      const teclaEfectiva = g[G.keynum] >= 0 ? g[G.keynum] : nota;
      const cents = (teclaEfectiva - raizNota) * g[G.scaleTuning] + g[G.coarseTune] * 100 + g[G.fineTune] + m.correccion;
      const decaimiento = g[G.decayVolEnv] + (60 - teclaEfectiva) * g[G.keynumToVolEnvDecay];
      const sostenido = g[G.holdVolEnv] + (60 - teclaEfectiva) * g[G.keynumToVolEnvHold];
      const sustainCb = Math.max(0, Math.min(1440, g[G.sustainVolEnv]));
      out.push({
        muestra: r.muestra,
        frecuencia: m.frecuencia,
        inicio: inicio,
        fin: fin,
        loopIni: loopIni,
        loopFin: loopFin,
        bucle: g[G.sampleModes] & 3, // 0 sin bucle, 1 continuo, 3 hasta soltar
        relacion: Math.pow(2, cents / 1200), // playbackRate sobre la muestra a su propia frecuencia
        semitonos: cents / 100,
        ganancia: Math.pow(10, -Math.max(0, g[G.initialAttenuation]) / 200),
        pan: Math.max(-1, Math.min(1, g[G.pan] / 500)),
        env: {
          retardo: seg(g[G.delayVolEnv]),
          ataque: seg(g[G.attackVolEnv]),
          sostenido: seg(sostenido),
          decaimiento: seg(decaimiento),
          sustain: Math.pow(10, -sustainCb / 200),
          liberacion: Math.max(0.01, seg(g[G.releaseVolEnv])),
        },
        filtroHz: g[G.initialFilterFc] >= 13500 ? null : 8.176 * Math.pow(2, g[G.initialFilterFc] / 1200),
        filtroQ: Math.max(0, g[G.initialFilterQ]) / 10, // en dB
        reverb: Math.max(0, Math.min(1000, g[G.reverbEffectsSend])) / 1000,
        claseExclusiva: g[G.exclusiveClass],
      });
    }
    return out;
  }

  /** Datos de una muestra en Float32 (-1..1), desde su inicio hasta su fin. Con caché. */
  function datosMuestra(sf, indice, inicio, fin) {
    const clave = indice + ':' + inicio + ':' + fin;
    let r = sf._cache.get(clave);
    if (r) return r;
    const a = Math.max(0, inicio);
    const z = Math.min(sf.datos16.length, Math.max(a + 1, fin));
    r = new Float32Array(z - a);
    if (sf.datos24) {
      for (let i = 0; i < r.length; i++) r[i] = (sf.datos16[a + i] * 256 + sf.datos24[a + i]) / 8388608;
    } else {
      for (let i = 0; i < r.length; i++) r[i] = sf.datos16[a + i] / 32768;
    }
    sf._cache.set(clave, r);
    return r;
  }

  function listaPresets(sf) {
    return Array.from(sf.presets.values())
      .map(function (p) { return { banco: p.banco, programa: p.programa, nombre: p.nombre, regiones: p.regiones.length }; })
      .sort(function (a, c) { return a.banco - c.banco || a.programa - c.programa; });
  }

  const API = { leer: leer, regiones: regiones, datosMuestra: datosMuestra, listaPresets: listaPresets, G: G };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.SoundFont = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
