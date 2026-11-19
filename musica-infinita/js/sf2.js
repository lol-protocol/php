/*
 * Lector de SoundFont 2 y 3 (.sf2, .sf3): los bancos de muestras grabadas que usan los
 * reproductores MIDI de calidad (FluidSynth, TiMidity, los teclados). Con uno bueno
 * (GeneralUser GS, FluidR3_GM, Salamander Grand Piano...) el MIDI suena a instrumentos
 * reales en vez de a sintetizador.
 *
 * Función pura (navegador y Node): lee el archivo y responde qué muestras tocar, con qué
 * afinación, envolventes, LFO, filtro, paneo y bucle, para un banco, programa, nota y
 * velocidad. Quien las toca es sintetizador.js.
 *
 * Se implementan: rangos, muestra, bucle, afinación, atenuación, paneo, envolvente de volumen,
 * filtro, reverberación, clase exclusiva, el LFO de vibrato y el de modulación, la envolvente
 * de modulación (a la afinación y al filtro) y los moduladores explícitos del archivo cuya
 * fuente es la velocidad o la nota (con sus cuatro curvas, polaridad y dirección). Los
 * moduladores por defecto de la norma que dependen de controles MIDI (rueda, volumen,
 * reverberación...) no se aplican, porque la aplicación no genera esos controles; el de la
 * velocidad (atenuación y filtro) sí.
 *
 * Además, las muestras izquierda y derecha enlazadas se separan a los lados si su zona no
 * trae paneo, y las muestras comprimidas en Ogg Vorbis (SoundFont 3, .sf3) se leen: hay que
 * decodificarlas antes de tocar (decodificar y precargar, que necesitan un AudioContext).
 */
(function (raiz) {
  'use strict';

  const G = {
    startAddrsOffset: 0,
    endAddrsOffset: 1,
    startloopAddrsOffset: 2,
    endloopAddrsOffset: 3,
    startAddrsCoarseOffset: 4,
    modLfoToPitch: 5,
    vibLfoToPitch: 6,
    modEnvToPitch: 7,
    initialFilterFc: 8,
    initialFilterQ: 9,
    modLfoToFilterFc: 10,
    modEnvToFilterFc: 11,
    endAddrsCoarseOffset: 12,
    modLfoToVolume: 13,
    chorusEffectsSend: 15,
    reverbEffectsSend: 16,
    pan: 17,
    delayModLFO: 21,
    freqModLFO: 22,
    delayVibLFO: 23,
    freqVibLFO: 24,
    delayModEnv: 25,
    attackModEnv: 26,
    holdModEnv: 27,
    decayModEnv: 28,
    sustainModEnv: 29,
    releaseModEnv: 30,
    keynumToModEnvHold: 31,
    keynumToModEnvDecay: 32,
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

  // Tipos de muestra (shdr.tipo)
  const MUESTRA_DERECHA = 2;
  const MUESTRA_IZQUIERDA = 4;
  const MUESTRA_ROM = 0x8000;
  const MUESTRA_OGG = 0x10;

  // Valores por omisión de la norma (SoundFont 2.04, sección 8.1.3).
  function porOmision() {
    const g = new Int32Array(N_GEN);
    g[G.initialFilterFc] = 13500;
    for (const op of [G.delayModLFO, G.delayVibLFO, G.delayModEnv, G.attackModEnv, G.holdModEnv, G.decayModEnv, G.releaseModEnv, G.delayVolEnv, G.attackVolEnv, G.holdVolEnv, G.decayVolEnv, G.releaseVolEnv]) g[op] = -12000;
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

  // ------------------------------------------------------------ moduladores

  function claveMod(m) {
    return m.src + ':' + m.dest + ':' + m.amtSrc + ':' + m.trans;
  }

  /** Los moduladores del instrumento: los de la zona reemplazan a los globales idénticos. */
  function mezclarMods(global, local) {
    const mapa = new Map();
    for (const m of global || []) mapa.set(claveMod(m), m);
    for (const m of local || []) mapa.set(claveMod(m), m);
    return Array.from(mapa.values());
  }

  // Curva cóncava de la norma (la de la velocidad: da (v/127)^2 en amplitud).
  function concava(x) {
    if (x <= 0) return 0;
    if (x >= 1) return 1;
    return Math.min(1, -(40 / 96) * Math.log10(1 - x));
  }

  function convexa(x) {
    return 1 - concava(1 - x);
  }

  function curva(tipo, x) {
    switch (tipo) {
      case 1: return concava(x);
      case 2: return convexa(x);
      case 3: return x >= 0.5 ? 1 : 0;
      default: return x;
    }
  }

  /**
   * Valor (de -1 a 1) de una fuente de modulador para una nota, o null si la fuente es un
   * control que la aplicación no genera (cualquier CC, la rueda de tono, la presión...).
   */
  function valorFuente(src, nota, vel) {
    const idx = src & 127;
    if ((src & 128) !== 0) return null;
    const negativa = ((src >> 8) & 1) === 1;
    const bipolar = ((src >> 9) & 1) === 1;
    const tipo = (src >> 10) & 63;
    let x;
    if (idx === 0) x = 1; // sin controlador: vale 1
    else if (idx === 2) x = vel / 127;
    else if (idx === 3) x = nota / 127;
    else return null;
    if (negativa) x = 1 - x;
    if (bipolar) {
      const t = 2 * x - 1;
      return (t < 0 ? -1 : 1) * curva(tipo, Math.abs(t));
    }
    return curva(tipo, x);
  }

  function aplicarMods(g, mods, nota, vel) {
    for (const m of mods) {
      if (m.dest & 0x8000 || m.dest >= N_GEN) continue; // enlazados a otro modulador: no
      const s = valorFuente(m.src, nota, vel);
      if (s === null) continue;
      const a = m.amtSrc === 0 ? 1 : valorFuente(m.amtSrc, nota, vel);
      if (a === null) continue;
      let v = m.cant * s * a;
      if (m.trans === 2) v = Math.abs(v);
      g[m.dest] += v;
    }
  }

  function sobreescribeVelocidad(mods, dest) {
    return mods.some(function (m) {
      return m.dest === dest && (m.src & 0x80ff) === 2;
    });
  }

  // ------------------------------------------------------------ lectura

  function leer(datos) {
    const b = aBytes(datos);
    const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);
    if (id4(b, 0) !== 'RIFF' || id4(b, 8) !== 'sfbk') throw new Error('No es una SoundFont (falta RIFF sfbk)');
    const trozos = {};
    const info = { version: 2 };
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
            if (sid === 'ifil' && sl >= 4) info.version = dv.getUint16(p + 8, true);
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

    function registros(t, tam, fn) {
      const out = [];
      if (!trozos[t]) return out;
      const n = Math.floor(trozos[t].largo / tam);
      for (let i = 0; i < n; i++) out.push(fn(trozos[t].pos + i * tam));
      return out;
    }
    const phdr = registros('phdr', 38, function (p) {
      return { nombre: nombre20(b, p), programa: dv.getUint16(p + 20, true), banco: dv.getUint16(p + 22, true), bag: dv.getUint16(p + 24, true) };
    });
    const bagDe = function (p) { return { gen: dv.getUint16(p, true), mod: dv.getUint16(p + 2, true) }; };
    const genDe = function (p) { return { op: dv.getUint16(p, true), lo: b[p + 2], hi: b[p + 3], v: dv.getInt16(p + 2, true) }; };
    const modDe = function (p) {
      return { src: dv.getUint16(p, true), dest: dv.getUint16(p + 2, true), cant: dv.getInt16(p + 4, true), amtSrc: dv.getUint16(p + 6, true), trans: dv.getUint16(p + 8, true) };
    };
    const pbag = registros('pbag', 4, bagDe);
    const pmod = registros('pmod', 10, modDe);
    const pgen = registros('pgen', 4, genDe);
    const inst = registros('inst', 22, function (p) { return { nombre: nombre20(b, p), bag: dv.getUint16(p + 20, true) }; });
    const ibag = registros('ibag', 4, bagDe);
    const imod = registros('imod', 10, modDe);
    const igen = registros('igen', 4, genDe);
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

    // Muestras. SoundFont 2: PCM de 16 bits (y los 8 bits bajos de sm24, si están).
    // SoundFont 3: las muestras son trozos de Ogg Vorbis de este mismo bloque, en bytes.
    const smpl = trozos.smpl;
    const hayOgg = info.version >= 3 || shdr.some(function (m) { return (m.tipo & MUESTRA_OGG) !== 0; });
    let muestras16 = null;
    let sm24 = null;
    let crudo = null;
    if (hayOgg) {
      crudo = b.subarray(smpl.pos, smpl.pos + smpl.largo);
    } else {
      muestras16 = new Int16Array(smpl.largo >> 1);
      for (let i = 0; i < muestras16.length; i++) muestras16[i] = dv.getInt16(smpl.pos + i * 2, true);
      sm24 = trozos.sm24 && trozos.sm24.largo >= muestras16.length ? b.subarray(trozos.sm24.pos, trozos.sm24.pos + muestras16.length) : null;
    }

    // Zonas: cada una es una lista de generadores y de moduladores. La primera puede ser global.
    function zonas(bags, gens, mods, desde, hasta, opFinal) {
      const out = [];
      for (let z = desde; z < hasta; z++) {
        const g0 = bags[z].gen;
        const g1 = z + 1 < bags.length ? bags[z + 1].gen : gens.length;
        const m0 = bags[z].mod;
        const m1 = z + 1 < bags.length ? bags[z + 1].mod : mods.length;
        const zona = { gens: new Map(), mods: mods.slice(m0, m1), rangoTeclas: [0, 127], rangoVel: [0, 127], final: null };
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
      const zs = zonas(ibag, igen, imod, inst[i].bag, inst[i + 1].bag, G.sampleID);
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
      const zs = zonas(pbag, pgen, pmod, h.bag, phdr[i + 1].bag, G.instrument);
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
          if (!m || m.tipo & MUESTRA_ROM) continue; // muestras en ROM: no están en el archivo
          regiones.push({
            teclas: rk,
            vel: rv,
            gens: g,
            muestra: iz.final,
            mods: mezclarMods(ins.global && ins.global.mods, iz.mods),
            modsPreset: mezclarMods(global && global.mods, pz.mods),
          });
        }
      }
      presets.set(h.banco + ':' + h.programa, { nombre: h.nombre, banco: h.banco, programa: h.programa, regiones: regiones });
    }

    return {
      info: info,
      esSf3: hayOgg,
      presets: presets,
      muestras: shdr,
      datos16: muestras16,
      datos24: sm24,
      crudo: crudo,
      _cache: new Map(),
      _decod: new Map(),
      _pendientes: new Map(),
    };
  }

  function buscarPreset(sf, banco, programa) {
    return sf.presets.get(banco + ':' + programa) || (banco === 128 ? sf.presets.get('128:0') : null) || sf.presets.get('0:' + programa) || sf.presets.get('0:0') || sf.presets.values().next().value || null;
  }

  function seg(tc) {
    return tc <= -12000 ? 0 : Math.pow(2, tc / 1200);
  }

  function hzLfo(cents) {
    return 8.176 * Math.pow(2, Math.max(-16000, Math.min(4500, cents)) / 1200);
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
      const m = sf.muestras[r.muestra];
      // generadores con los moduladores aplicados (en coma flotante)
      const g = Float64Array.from(r.gens);
      aplicarMods(g, r.mods, nota, vel);
      aplicarMods(g, r.modsPreset, nota, vel);
      // moduladores por defecto de la velocidad, salvo que el archivo traiga el suyo
      if (!sobreescribeVelocidad(r.mods, G.initialAttenuation) && !sobreescribeVelocidad(r.modsPreset, G.initialAttenuation)) {
        g[G.initialAttenuation] += 960 * concava(1 - vel / 127);
      }
      if (!sobreescribeVelocidad(r.mods, G.initialFilterFc) && !sobreescribeVelocidad(r.modsPreset, G.initialFilterFc)) {
        g[G.initialFilterFc] -= 2400 * (1 - vel / 127);
      }
      const comprimida = (m.tipo & MUESTRA_OGG) !== 0;
      const raizNota = g[G.overridingRootKey] >= 0 ? g[G.overridingRootKey] : m.tono === 255 ? 60 : m.tono;
      let inicio;
      let fin;
      let loopIni;
      let loopFin;
      if (comprimida) {
        // SoundFont 3: inicio y fin son bytes del bloque; los bucles, muestras desde el inicio
        // de la propia muestra ya decodificada.
        inicio = 0;
        fin = 0;
        loopIni = m.loopIni + g[G.startloopAddrsOffset] + 32768 * g[G.startloopAddrsCoarseOffset];
        loopFin = m.loopFin + g[G.endloopAddrsOffset] + 32768 * g[G.endloopAddrsCoarseOffset];
      } else {
        inicio = m.inicio + g[G.startAddrsOffset] + 32768 * g[G.startAddrsCoarseOffset];
        fin = m.fin + g[G.endAddrsOffset] + 32768 * g[G.endAddrsCoarseOffset];
        loopIni = m.loopIni + g[G.startloopAddrsOffset] + 32768 * g[G.startloopAddrsCoarseOffset];
        loopFin = m.loopFin + g[G.endloopAddrsOffset] + 32768 * g[G.endloopAddrsCoarseOffset];
      }
      const teclaEfectiva = g[G.keynum] >= 0 ? g[G.keynum] : nota;
      const cents = (teclaEfectiva - raizNota) * g[G.scaleTuning] + g[G.coarseTune] * 100 + g[G.fineTune] + m.correccion;
      const decaimiento = g[G.decayVolEnv] + (60 - teclaEfectiva) * g[G.keynumToVolEnvDecay];
      const sostenido = g[G.holdVolEnv] + (60 - teclaEfectiva) * g[G.keynumToVolEnvHold];
      const sustainCb = Math.max(0, Math.min(1440, g[G.sustainVolEnv]));
      // muestras enlazadas: la izquierda a la izquierda y la derecha a la derecha, si la zona no dice otra cosa
      let pan = g[G.pan];
      if (pan === 0 && (m.tipo & MUESTRA_IZQUIERDA)) pan = -500;
      else if (pan === 0 && (m.tipo & MUESTRA_DERECHA)) pan = 500;

      const vibCents = g[G.vibLfoToPitch];
      const modLfo = { centsPitch: g[G.modLfoToPitch], centsFiltro: g[G.modLfoToFilterFc], cbVolumen: g[G.modLfoToVolume] };
      const modEnvPitch = g[G.modEnvToPitch];
      const modEnvFiltro = g[G.modEnvToFilterFc];
      const sustainMod = Math.max(0, Math.min(1000, g[G.sustainModEnv]));

      out.push({
        muestra: r.muestra,
        comprimida: comprimida,
        frecuencia: m.frecuencia,
        inicio: inicio,
        fin: fin,
        loopIni: loopIni,
        loopFin: loopFin,
        bucle: g[G.sampleModes] & 3, // 0 sin bucle, 1 continuo, 3 hasta soltar
        relacion: Math.pow(2, cents / 1200), // playbackRate sobre la muestra a su propia frecuencia
        semitonos: cents / 100,
        ganancia: Math.pow(10, -Math.max(0, g[G.initialAttenuation]) / 200),
        pan: Math.max(-1, Math.min(1, pan / 500)),
        env: {
          retardo: seg(g[G.delayVolEnv]),
          ataque: seg(g[G.attackVolEnv]),
          sostenido: seg(sostenido),
          decaimiento: seg(decaimiento),
          sustain: Math.pow(10, -sustainCb / 200),
          liberacion: Math.max(0.01, seg(g[G.releaseVolEnv])),
        },
        filtroHz: g[G.initialFilterFc] >= 13500 ? null : 8.176 * Math.pow(2, Math.max(1500, g[G.initialFilterFc]) / 1200),
        filtroQ: Math.max(0, g[G.initialFilterQ]) / 10, // en dB
        // vibrato: LFO de afinación (cents de pico a cada lado)
        vibrato: vibCents ? { retardo: seg(g[G.delayVibLFO]), frecuencia: hzLfo(g[G.freqVibLFO]), cents: vibCents } : null,
        // LFO de modulación: afinación, filtro y volumen
        lfoMod: modLfo.centsPitch || modLfo.centsFiltro || modLfo.cbVolumen ? Object.assign({ retardo: seg(g[G.delayModLFO]), frecuencia: hzLfo(g[G.freqModLFO]) }, modLfo) : null,
        // envolvente de modulación: afinación y filtro
        envMod: modEnvPitch || modEnvFiltro
          ? {
            retardo: seg(g[G.delayModEnv]),
            ataque: seg(g[G.attackModEnv]),
            sostenido: seg(g[G.holdModEnv] + (60 - teclaEfectiva) * g[G.keynumToModEnvHold]),
            decaimiento: seg(g[G.decayModEnv] + (60 - teclaEfectiva) * g[G.keynumToModEnvDecay]),
            sustain: 1 - sustainMod / 1000,
            liberacion: Math.max(0.01, seg(g[G.releaseModEnv])),
            centsPitch: modEnvPitch,
            centsFiltro: modEnvFiltro,
          }
          : null,
        reverb: Math.max(0, Math.min(1000, g[G.reverbEffectsSend])) / 1000,
        claseExclusiva: g[G.exclusiveClass],
      });
    }
    return out;
  }

  /** Datos de una muestra PCM en Float32 (-1..1), desde su inicio hasta su fin. Con caché. */
  function datosMuestra(sf, indice, inicio, fin) {
    if (!sf.datos16) throw new Error('Esta SoundFont es de muestras comprimidas: usa decodificar() y bufferDecodificado()');
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

  // ------------------------------------------------------------ SoundFont 3 (Ogg Vorbis)

  /** AudioBuffer ya decodificado de una muestra comprimida, o undefined si falta decodificarla. */
  function bufferDecodificado(sf, indice) {
    return sf._decod.get(indice);
  }

  /** Decodifica una muestra comprimida con el AudioContext dado. Una sola vez por muestra. */
  function decodificar(sf, indice, ctx) {
    if (sf._decod.has(indice)) return Promise.resolve(sf._decod.get(indice));
    if (sf._pendientes.has(indice)) return sf._pendientes.get(indice);
    const m = sf.muestras[indice];
    // decodeAudioData se queda con el ArrayBuffer: se le pasa una copia
    const copia = sf.crudo.slice(m.inicio, m.fin).buffer;
    const p = ctx.decodeAudioData(copia).then(function (buf) {
      sf._decod.set(indice, buf);
      sf._pendientes.delete(indice);
      return buf;
    }, function (e) {
      sf._pendientes.delete(indice);
      throw e;
    });
    sf._pendientes.set(indice, p);
    return p;
  }

  /** Decodifica las muestras comprimidas que necesitan estas regiones. Promesa de todas. */
  function precargar(sf, regs, ctx) {
    const ps = [];
    for (const r of regs) if (r.comprimida && !sf._decod.has(r.muestra)) ps.push(decodificar(sf, r.muestra, ctx));
    return Promise.all(ps);
  }

  function listaPresets(sf) {
    return Array.from(sf.presets.values())
      .map(function (p) { return { banco: p.banco, programa: p.programa, nombre: p.nombre, regiones: p.regiones.length }; })
      .sort(function (a, c) { return a.banco - c.banco || a.programa - c.programa; });
  }

  const API = {
    leer: leer,
    regiones: regiones,
    datosMuestra: datosMuestra,
    bufferDecodificado: bufferDecodificado,
    decodificar: decodificar,
    precargar: precargar,
    listaPresets: listaPresets,
    G: G,
    // para las pruebas
    valorFuente: valorFuente,
    concava: concava,
    convexa: convexa,
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.SoundFont = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
