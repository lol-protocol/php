/*
 * Sintetizador en Web Audio: toca los eventos del generador (o de un MIDI) con
 * instrumentos propios o con una SoundFont (.sf2), y los pasa por una cadena de mezcla:
 * reverberación de convolución, eco, compresor y limitador.
 *
 * Solo navegador (usa AudioContext u OfflineAudioContext). Necesita sf2.js antes.
 *
 * Instrumentos propios, sin muestras grabadas:
 *   - percutidos (piano, piano eléctrico, campanas, mazos, cuerdas pulsadas, bajo, batería):
 *     se calculan una vez por nota en JavaScript (síntesis aditiva con parciales que se
 *     apagan a su ritmo, FM, Karplus-Strong) y se guardan en caché como AudioBuffer, así
 *     que suenan como una muestra y cuestan poco al tocarlos;
 *   - sostenidos (cuerdas, pads, coro, órgano, vientos, leads): osciladores con formas de
 *     onda propias, filtros, vibrato y envolventes, en tiempo real.
 */
(function (raiz) {
  'use strict';

  const SF = raiz.SoundFont;

  function frecuencia(nota) {
    return 440 * Math.pow(2, (nota - 69) / 12);
  }

  // ------------------------------------------------------------ familias GM

  function receta(programa) {
    const p = programa | 0;
    if (p <= 3) return 'piano';
    if (p <= 5) return 'epiano';
    if (p <= 7) return 'clave';
    if (p === 11) return 'vibrafono';
    if (p === 12 || p === 13) return 'marimba';
    if (p <= 15) return 'campana';
    if (p <= 23) return 'organo';
    if (p <= 31) return 'guitarra';
    if (p <= 37) return 'bajo';
    if (p <= 39) return 'bajoSinte';
    if (p === 45) return 'pizzicato';
    if (p === 46) return 'arpa';
    if (p === 47) return 'bajo';
    if (p <= 51) return 'cuerdas';
    if (p <= 54) return 'coro';
    if (p <= 63) return 'metal';
    if (p <= 71) return 'cana';
    if (p <= 79) return 'flauta';
    if (p === 80) return 'cuadrada';
    if (p <= 87) return 'sierra';
    return 'pad';
  }

  const PRERENDER = new Set(['piano', 'epiano', 'clave', 'vibrafono', 'marimba', 'campana', 'guitarra', 'bajo', 'pizzicato', 'arpa']);

  // Configuración de cada canal (por voz del generador).
  const CANALES = {
    0: { vol: 0.8, pan: 0, rev: 0.3, eco: 0.14 }, // melodía
    1: { vol: 0.4, pan: -0.22, rev: 0.42, eco: 0 }, // acordes
    2: { vol: 0.72, pan: 0, rev: 0.08, eco: 0 }, // bajo
    3: { vol: 0.36, pan: 0.28, rev: 0.36, eco: 0.16 }, // arpegio
    9: { vol: 0.62, pan: 0, rev: 0.1, eco: 0 }, // batería
  };
  const CANAL_GENERICO = { vol: 0.55, pan: 0, rev: 0.22, eco: 0 };

  // ------------------------------------------------------------ DSP auxiliar (para pre-render)

  /** Biquad RBJ en JS (pasa-bajos, pasa-altos o pasa-banda), aplicado en el lugar. */
  function biquad(x, sr, tipo, f, q) {
    const w = (2 * Math.PI * Math.min(f, sr * 0.45)) / sr;
    const cs = Math.cos(w);
    const al = Math.sin(w) / (2 * (q || 0.707));
    let b0;
    let b1;
    let b2;
    if (tipo === 'altos') {
      b0 = (1 + cs) / 2;
      b1 = -(1 + cs);
      b2 = b0;
    } else if (tipo === 'banda') {
      b0 = al;
      b1 = 0;
      b2 = -al;
    } else {
      b0 = (1 - cs) / 2;
      b1 = 1 - cs;
      b2 = b0;
    }
    const a0 = 1 + al;
    const a1 = -2 * cs;
    const a2 = 1 - al;
    let x1 = 0;
    let x2 = 0;
    let y1 = 0;
    let y2 = 0;
    for (let i = 0; i < x.length; i++) {
      const y = (b0 * x[i] + b1 * x1 + b2 * x2 - a1 * y1 - a2 * y2) / a0;
      x2 = x1;
      x1 = x[i];
      y2 = y1;
      y1 = y;
      x[i] = y;
    }
    return x;
  }

  function normalizar(x, pico) {
    let m = 0;
    for (let i = 0; i < x.length; i++) m = Math.max(m, Math.abs(x[i]));
    if (m > 0) {
      const k = pico / m;
      for (let i = 0; i < x.length; i++) x[i] *= k;
    }
    return x;
  }

  function ruido(semilla) {
    let s = semilla >>> 0 || 1;
    return function () {
      s ^= s << 13;
      s ^= s >>> 17;
      s ^= s << 5;
      return ((s >>> 0) / 4294967296) * 2 - 1;
    };
  }

  /** Suma de parciales: [{r: razón, a: amplitud, d: decaimiento en s}] con un ataque corto. */
  function aditivo(sr, f, dur, parciales, ataque, opciones) {
    const n = Math.floor(sr * dur);
    const x = new Float32Array(n);
    const ny = sr * 0.45;
    const trem = opciones && opciones.tremolo;
    for (const p of parciales) {
      const fp = f * p.r;
      if (fp >= ny) continue;
      const w = (2 * Math.PI * fp) / sr;
      const k = Math.exp(-1 / (p.d * sr));
      let env = p.a;
      const fase = p.fase || 0;
      // oscilador por rotación (rápido y estable)
      let c = Math.cos(fase);
      let s = Math.sin(fase);
      const cw = Math.cos(w);
      const sw = Math.sin(w);
      for (let i = 0; i < n; i++) {
        x[i] += s * env;
        const c2 = c * cw - s * sw;
        s = s * cw + c * sw;
        c = c2;
        env *= k;
        if (env < 1e-5) break;
      }
    }
    const na = Math.max(1, Math.floor(sr * ataque));
    for (let i = 0; i < na && i < n; i++) x[i] *= i / na;
    if (trem) {
      for (let i = 0; i < n; i++) x[i] *= 1 - trem.prof * (0.5 + 0.5 * Math.sin((2 * Math.PI * trem.f * i) / sr));
    }
    return x;
  }

  /** Karplus-Strong: cuerda pulsada. brillo 0..1 (cuánto ruido agudo tiene el ataque). */
  function karplus(sr, f, dur, brillo, decaimiento, semilla) {
    const n = Math.floor(sr * dur);
    const x = new Float32Array(n);
    const largo = sr / f;
    const N = Math.max(2, Math.floor(largo));
    const frac = largo - N; // afinación fina con un pasa-todo de primer orden
    const coef = (1 - frac) / (1 + frac);
    const linea = new Float32Array(N);
    const r = ruido(semilla);
    let prev = 0;
    for (let i = 0; i < N; i++) {
      const v = r();
      prev = prev + brillo * (v - prev); // excitación filtrada
      linea[i] = prev;
    }
    // quita el promedio para que no quede corriente continua
    let m = 0;
    for (let i = 0; i < N; i++) m += linea[i];
    m /= N;
    for (let i = 0; i < N; i++) linea[i] -= m;
    // pérdida por vuelta: el tono dura ~"decaimiento" segundos a 440 Hz
    const perdida = Math.pow(0.001, 1 / (decaimiento * f));
    let idx = 0;
    let apX = 0;
    let apY = 0;
    let ant = linea[N - 1];
    for (let i = 0; i < n; i++) {
      const actual = linea[idx];
      let y = 0.5 * (actual + ant) * perdida;
      ant = actual;
      // pasa-todo para la fracción de muestra
      const ap = coef * y + apX - coef * apY;
      apX = y;
      apY = ap;
      linea[idx] = ap;
      x[i] = actual;
      idx = idx + 1 === N ? 0 : idx + 1;
    }
    return x;
  }

  // ------------------------------------------------------------ recetas pre-renderizadas

  function renderizar(tipo, nota, sr) {
    const f = frecuencia(nota);
    let x;
    switch (tipo) {
      case 'piano': {
        const dur = Math.max(1.6, Math.min(6, 6.5 - (nota - 36) * 0.07));
        const B = 0.00008 * Math.pow(2, (nota - 60) / 18); // inarmonicidad de la cuerda
        const ps = [];
        for (let k = 1; k <= 24; k++) {
          const r = k * Math.sqrt(1 + B * k * k);
          let a = Math.pow(k, -1.05) * (0.25 + Math.abs(Math.sin((Math.PI * k) / 7.3)));
          // los parciales agudos se apagan antes, y más en las notas agudas (nunca negativo)
          const d = Math.min(dur, (dur * 0.55) / (1 + 0.45 * (k - 1)) / Math.exp((nota - 60) * 0.006 * k));
          ps.push({ r: r, a: a * 0.65, d: d });
          ps.push({ r: r * 1.0006, a: a * 0.35, d: d * 1.6, fase: 1.3 }); // segunda cuerda: batido
          ps.push({ r: r, a: a * 0.4, d: d * 0.12 }); // golpe inicial más brillante
        }
        x = aditivo(sr, f, dur, ps, 0.002);
        // martillo: un golpecito de ruido filtrado
        const r = ruido(nota * 7 + 1);
        const nm = Math.floor(sr * 0.012);
        const golpe = new Float32Array(nm);
        for (let i = 0; i < nm; i++) golpe[i] = r() * (1 - i / nm);
        biquad(golpe, sr, 'banda', Math.min(4000, f * 6), 0.8);
        normalizar(x, 1);
        for (let i = 0; i < nm; i++) x[i] += golpe[i] * 0.12;
        break;
      }
      case 'epiano': {
        // FM tipo Rhodes: el índice de modulación cae rápido (la "campanita" del ataque)
        const dur = 3.2;
        const n = Math.floor(sr * dur);
        x = new Float32Array(n);
        const w = (2 * Math.PI * f) / sr;
        const wt = (2 * Math.PI * f * 14) / sr;
        const tine = f * 14 < sr * 0.45;
        for (let i = 0; i < n; i++) {
          const t = i / sr;
          const I = 1.6 * Math.exp(-t / 0.25) + 0.15;
          const env = Math.exp(-t / (1.6 - (nota - 60) * 0.01)) * Math.min(1, t / 0.003);
          let v = Math.sin(w * i + I * Math.sin(w * i));
          if (tine) v += 0.18 * Math.sin(wt * i) * Math.exp(-t / 0.04);
          x[i] = v * env;
        }
        break;
      }
      case 'clave': {
        x = karplus(sr, f, 2.2, 0.95, 1.4, nota * 13 + 5);
        biquad(x, sr, 'altos', f * 0.8, 0.7);
        break;
      }
      case 'vibrafono':
        x = aditivo(sr, f, 4, [{ r: 1, a: 1, d: 2.2 }, { r: 4, a: 0.25, d: 0.5 }, { r: 10, a: 0.05, d: 0.1 }], 0.002, { tremolo: { f: 5.2, prof: 0.35 } });
        break;
      case 'marimba':
        x = aditivo(sr, f, 1.4, [{ r: 1, a: 1, d: 0.45 }, { r: 3.93, a: 0.3, d: 0.08 }, { r: 9.9, a: 0.08, d: 0.02 }], 0.003);
        break;
      case 'campana':
        // caja de música / celesta: parciales casi armónicos que se apagan pronto
        x = aditivo(sr, f, 3, [{ r: 1, a: 1, d: 1.1 }, { r: 2.01, a: 0.35, d: 0.5 }, { r: 3.94, a: 0.22, d: 0.25 }, { r: 5.33, a: 0.1, d: 0.12 }, { r: 8.1, a: 0.05, d: 0.05 }], 0.001);
        break;
      case 'guitarra':
        x = karplus(sr, f, 3.5, 0.55, 2.4, nota * 31 + 7);
        break;
      case 'arpa':
        x = karplus(sr, f, 4, 0.3, 3.2, nota * 17 + 3);
        break;
      case 'pizzicato':
        x = karplus(sr, f, 1.2, 0.4, 0.5, nota * 19 + 11);
        break;
      case 'bajo': {
        const k = karplus(sr, f, 2.5, 0.25, 2.2, nota * 23 + 9);
        const s = aditivo(sr, f, 2.5, [{ r: 1, a: 0.6, d: 1.2 }, { r: 2, a: 0.15, d: 0.5 }], 0.004);
        normalizar(k, 1);
        x = new Float32Array(k.length);
        for (let i = 0; i < x.length; i++) x[i] = k[i] * 0.6 + s[i];
        biquad(x, sr, 'bajos', 1800, 0.7);
        break;
      }
      default:
        x = aditivo(sr, f, 1, [{ r: 1, a: 1, d: 0.5 }], 0.005);
    }
    return normalizar(x, 0.9);
  }

  function renderizarBateria(nota, sr) {
    const r = ruido(nota * 101 + 3);
    let x;
    const n = function (s) { return new Float32Array(Math.floor(sr * s)); };
    if (nota === 35 || nota === 36) {
      x = n(0.6);
      let fase = 0;
      for (let i = 0; i < x.length; i++) {
        const t = i / sr;
        const f = 45 + 110 * Math.exp(-t / 0.035);
        fase += (2 * Math.PI * f) / sr;
        x[i] = Math.sin(fase) * Math.exp(-t / 0.28) + (i < sr * 0.003 ? r() * 0.4 : 0);
      }
    } else if (nota === 38 || nota === 40 || nota === 37 || nota === 39) {
      x = n(0.4);
      const ruidoS = n(0.4);
      for (let i = 0; i < x.length; i++) ruidoS[i] = r();
      biquad(ruidoS, sr, 'altos', nota === 39 ? 900 : 1500, 0.7);
      let fase = 0;
      for (let i = 0; i < x.length; i++) {
        const t = i / sr;
        fase += (2 * Math.PI * (175 + 30 * Math.exp(-t / 0.02))) / sr;
        const ton = nota === 39 ? 0 : Math.sin(fase) * Math.exp(-t / 0.06) * 0.7;
        const dec = nota === 37 ? 0.03 : nota === 39 ? 0.09 : 0.15;
        x[i] = ton + ruidoS[i] * Math.exp(-t / dec);
      }
    } else if (nota === 42 || nota === 44 || nota === 46 || nota === 54 || nota === 49 || nota === 57 || nota === 51 || nota === 59 || nota === 53 || nota === 52 || nota === 55) {
      // platillos: suma de ondas cuadradas inarmónicas + ruido, filtradas arriba
      const largo = nota === 46 ? 0.5 : nota === 49 || nota === 57 || nota === 52 || nota === 55 ? 2.2 : nota === 51 || nota === 59 || nota === 53 ? 1.4 : 0.12;
      const dec = nota === 46 ? 0.2 : largo > 2 ? 0.9 : largo > 1 ? 0.5 : 0.035;
      x = n(largo);
      const fs = [205.3, 304.4, 369.6, 522.7, 540, 800];
      for (let i = 0; i < x.length; i++) {
        const t = i / sr;
        let m = 0;
        for (const f of fs) m += Math.sin(2 * Math.PI * f * 2.3 * t) > 0 ? 1 : -1;
        x[i] = (m / 6) * 0.6 + r() * 0.6;
        x[i] *= Math.exp(-t / dec);
      }
      biquad(x, sr, 'altos', nota === 51 || nota === 59 ? 5000 : 7000, 0.7);
      biquad(x, sr, 'altos', 6000, 0.7);
    } else if (nota >= 41 && nota <= 50) {
      // timbales (toms)
      const base = 70 + (nota - 41) * 18;
      x = n(0.6);
      let fase = 0;
      for (let i = 0; i < x.length; i++) {
        const t = i / sr;
        fase += (2 * Math.PI * (base + base * 0.6 * Math.exp(-t / 0.05))) / sr;
        x[i] = Math.sin(fase) * Math.exp(-t / 0.25) + r() * 0.15 * Math.exp(-t / 0.02);
      }
    } else if (nota === 56) {
      x = aditivo(sr, 560, 0.4, [{ r: 1, a: 1, d: 0.08 }, { r: 1.48, a: 0.8, d: 0.08 }], 0.001);
    } else {
      // percusión que no conocemos: un golpe corto de ruido
      x = n(0.15);
      for (let i = 0; i < x.length; i++) x[i] = r() * Math.exp(-i / (sr * 0.03));
      biquad(x, sr, 'banda', 2000 + nota * 20, 1.2);
    }
    return normalizar(x, 0.9);
  }

  // ------------------------------------------------------------ respuesta al impulso

  function impulso(ctx, segundos, semilla) {
    const sr = ctx.sampleRate;
    const n = Math.floor(sr * segundos);
    const buf = ctx.createBuffer(2, n, sr);
    for (let c = 0; c < 2; c++) {
      const d = buf.getChannelData(c);
      const r = ruido(semilla + c * 977);
      let lp = 0;
      const pre = Math.floor(sr * 0.018);
      for (let i = pre; i < n; i++) {
        const t = (i - pre) / sr;
        // se oscurece con el tiempo, como una sala de verdad
        const a = 0.9 * Math.exp(-t / (segundos * 0.35)) + 0.08;
        lp = lp + a * (r() - lp);
        d[i] = lp * Math.exp((-6.9 * t) / segundos);
      }
      // primeras reflexiones
      for (let k = 0; k < 8; k++) {
        const pos = pre + Math.floor(sr * (0.007 + k * 0.011 + c * 0.003));
        if (pos < n) d[pos] += (0.5 - k * 0.05) * (k % 2 ? -1 : 1);
      }
    }
    return buf;
  }

  // ------------------------------------------------------------ motor

  function Motor(ctx, opciones) {
    opciones = opciones || {};
    this.ctx = ctx;
    this.sf = null;
    // pre-render por receta y nota; se puede compartir entre contextos (exportación por tramos)
    this.buffers = opciones.cache || new Map();
    this.buffersSf = new Map(); // AudioBuffer por muestra de la SoundFont
    this.voces = [];
    this.maxVoces = opciones.maxVoces || 96;
    this.alTocar = null;
    this.ondas = {};

    // salida: mezcla -> compresor -> limitador -> [analizador, grabación] -> volumen -> destino
    this.mezcla = ctx.createGain();
    this.compresor = ctx.createDynamicsCompressor();
    this.compresor.threshold.value = -16;
    this.compresor.knee.value = 12;
    this.compresor.ratio.value = 3;
    this.compresor.attack.value = 0.006;
    this.compresor.release.value = 0.25;
    this.limitador = ctx.createDynamicsCompressor();
    this.limitador.threshold.value = -2;
    this.limitador.knee.value = 0;
    this.limitador.ratio.value = 20;
    this.limitador.attack.value = 0.001;
    this.limitador.release.value = 0.1;
    this.postMezcla = ctx.createGain();
    this.postMezcla.gain.value = 0.9;
    this.volumen = ctx.createGain();
    this.volumen.gain.value = opciones.volumen == null ? 0.8 : opciones.volumen;
    this.mezcla.connect(this.compresor);
    this.compresor.connect(this.limitador);
    this.limitador.connect(this.postMezcla);
    this.postMezcla.connect(this.volumen);
    this.volumen.connect(ctx.destination);
    // el punto de donde se graba y se analiza (antes del volumen: no depende de él)
    this.salida = this.postMezcla;

    // reverberación
    this.reverb = ctx.createConvolver();
    this.reverb.buffer = impulso(ctx, 3.2, 17);
    this.reverbVuelta = ctx.createGain();
    this.reverbVuelta.gain.value = 0.55;
    this.reverbEnvio = ctx.createGain();
    this.reverbEnvio.connect(this.reverb);
    this.reverb.connect(this.reverbVuelta);
    this.reverbVuelta.connect(this.mezcla);

    // eco estéreo (ping-pong), sincronizado con el tempo
    this.ecoEnvio = ctx.createGain();
    this.ecoI = ctx.createDelay(2);
    this.ecoD = ctx.createDelay(2);
    this.ecoFiltro = ctx.createBiquadFilter();
    this.ecoFiltro.type = 'lowpass';
    this.ecoFiltro.frequency.value = 2800;
    this.ecoRealim = ctx.createGain();
    this.ecoRealim.gain.value = 0.32;
    const merger = ctx.createChannelMerger(2);
    this.ecoEnvio.connect(this.ecoFiltro);
    this.ecoFiltro.connect(this.ecoI);
    this.ecoI.connect(this.ecoD);
    this.ecoD.connect(this.ecoRealim);
    this.ecoRealim.connect(this.ecoFiltro);
    this.ecoI.connect(merger, 0, 0);
    this.ecoD.connect(merger, 0, 1);
    const ecoVuelta = ctx.createGain();
    ecoVuelta.gain.value = 0.5;
    merger.connect(ecoVuelta);
    ecoVuelta.connect(this.mezcla);
    ecoVuelta.connect(this.reverbEnvio);
    this.ponerTempo(100);

    this.canales = [];
    for (let c = 0; c < 16; c++) this.canales.push(this.crearCanal(CANALES[c] || CANAL_GENERICO));
  }

  Motor.prototype.crearCanal = function (conf) {
    const ctx = this.ctx;
    const entrada = ctx.createGain();
    entrada.gain.value = conf.vol;
    const pan = ctx.createStereoPanner();
    pan.pan.value = conf.pan;
    const rev = ctx.createGain();
    rev.gain.value = conf.rev;
    const eco = ctx.createGain();
    eco.gain.value = conf.eco;
    entrada.connect(pan);
    pan.connect(this.mezcla);
    pan.connect(rev);
    rev.connect(this.reverbEnvio);
    pan.connect(eco);
    eco.connect(this.ecoEnvio);
    return { entrada: entrada, pan: pan, rev: rev, eco: eco, conf: conf };
  };

  /** Modo «MIDI tal cual»: todos los canales iguales, como un reproductor normal. */
  Motor.prototype.modoMidi = function (activo) {
    for (let c = 0; c < 16; c++) {
      const conf = activo ? CANAL_GENERICO : CANALES[c] || CANAL_GENERICO;
      const k = this.canales[c];
      k.entrada.gain.value = activo ? (c === 9 ? 0.6 : 0.5) : conf.vol;
      k.pan.pan.value = activo ? 0 : conf.pan;
      k.rev.gain.value = conf.rev;
      k.eco.gain.value = activo ? 0 : conf.eco;
    }
  };

  Motor.prototype.ponerTempo = function (bpm, cuando) {
    const t = cuando == null ? this.ctx.currentTime : cuando;
    const pulso = 60 / bpm;
    this.ecoI.delayTime.setTargetAtTime(Math.min(1.9, pulso * 0.75), t, 0.2);
    this.ecoD.delayTime.setTargetAtTime(Math.min(1.9, pulso * 0.75), t, 0.2);
  };

  Motor.prototype.ponerReverb = function (cantidad) {
    this.reverbVuelta.gain.setTargetAtTime(cantidad, this.ctx.currentTime, 0.5);
  };

  Motor.prototype.ponerVolumen = function (v) {
    this.volumen.gain.setTargetAtTime(v, this.ctx.currentTime, 0.05);
  };

  Motor.prototype.ponerSoundFont = function (sf) {
    this.sf = sf;
    this.buffersSf.clear();
  };

  /** Toca un evento del generador en el tiempo de audio t. */
  Motor.prototype.tocar = function (ev, t) {
    if (ev.tipo === 'info') return;
    const canal = this.canales[ev.canal & 15];
    if (this.sf) this.tocarSf(ev, t, canal);
    else if (ev.canal === 9) this.tocarBateria(ev, t, canal);
    else this.tocarInterno(ev, t, canal);
    if (this.alTocar) this.alTocar(ev, t);
    this.limpiar(t);
  };

  Motor.prototype.registrar = function (voz) {
    this.voces.push(voz);
    if (this.voces.length > this.maxVoces) {
      // roba la voz más vieja
      const vieja = this.voces.shift();
      this.apagar(vieja, Math.max(this.ctx.currentTime, voz.inicio - 0.01), 0.03);
    }
  };

  Motor.prototype.limpiar = function (t) {
    if (this.voces.length < 32) return;
    this.voces = this.voces.filter(function (v) { return v.fin > t - 0.1; });
  };

  Motor.prototype.apagar = function (voz, t, rel) {
    try {
      voz.gain.gain.cancelScheduledValues(t);
      voz.gain.gain.setTargetAtTime(0, t, rel / 4);
      for (const f of voz.fuentes) f.stop(t + rel);
    } catch (e) {
      // la fuente ya terminó
    }
    voz.fin = Math.min(voz.fin, t + rel);
  };

  /** Silencia todo lo que suena o está programado. */
  Motor.prototype.silenciar = function () {
    const t = this.ctx.currentTime;
    for (const v of this.voces) this.apagar(v, t, 0.06);
    this.voces = [];
  };

  Motor.prototype.bufferInterno = function (tipo, nota) {
    const clave = tipo + ':' + nota;
    let b = this.buffers.get(clave);
    if (!b) {
      const x = tipo === 'bateria' ? renderizarBateria(nota, this.ctx.sampleRate) : renderizar(tipo, nota, this.ctx.sampleRate);
      b = this.ctx.createBuffer(1, x.length, this.ctx.sampleRate);
      b.copyToChannel ? b.copyToChannel(x, 0) : b.getChannelData(0).set(x);
      this.buffers.set(clave, b);
    }
    return b;
  };

  Motor.prototype.tocarBateria = function (ev, t, canal) {
    const ctx = this.ctx;
    const nota = { 35: 36, 44: 42, 40: 38 }[ev.nota] || ev.nota;
    const b = this.bufferInterno('bateria', nota);
    const src = ctx.createBufferSource();
    src.buffer = b;
    const g = ctx.createGain();
    const vel = ev.vel / 127;
    g.gain.value = vel * vel * 0.9;
    const pan = ctx.createStereoPanner();
    pan.pan.value = nota === 42 || nota === 46 ? 0.3 : nota >= 41 && nota <= 50 && nota !== 42 && nota !== 46 ? (nota - 45) / 12 : 0;
    src.connect(g);
    g.connect(pan);
    pan.connect(canal.entrada);
    // el charles abierto se corta al cerrarlo (clase exclusiva)
    if (nota === 42) {
      for (const v of this.voces) if (v.clase === 'charles' && v.fin > t) this.apagar(v, t, 0.02);
    }
    src.start(t);
    const voz = { fuentes: [src], gain: g, inicio: t, fin: t + b.duration, clase: nota === 46 ? 'charles' : null };
    this.registrar(voz);
  };

  Motor.prototype.tocarInterno = function (ev, t, canal) {
    const ctx = this.ctx;
    const tipo = receta(ev.programa);
    const vel = ev.vel / 127;
    const f = frecuencia(ev.nota);
    const fin = t + ev.dur;
    const g = ctx.createGain();
    g.connect(canal.entrada);
    let fuentes = [];
    let liberacion = 0.2;

    if (PRERENDER.has(tipo)) {
      const b = this.bufferInterno(tipo, ev.nota);
      const src = ctx.createBufferSource();
      src.buffer = b;
      // la velocidad cambia el brillo, no solo el volumen
      const filtro = ctx.createBiquadFilter();
      filtro.type = 'lowpass';
      filtro.frequency.value = Math.min(18000, f * (2.5 + vel * vel * 22));
      filtro.Q.value = 0.5;
      src.connect(filtro);
      filtro.connect(g);
      const amp = 0.25 + 0.75 * vel * vel;
      liberacion = { piano: 0.35, epiano: 0.3, clave: 0.12, guitarra: 0.25, bajo: 0.12, pizzicato: 0.1 }[tipo] || 1.2;
      g.gain.setValueAtTime(amp, t);
      const fuera = Math.min(fin, t + b.duration);
      g.gain.setValueAtTime(amp, fuera);
      g.gain.setTargetAtTime(0, fuera, liberacion / 4);
      src.start(t);
      src.stop(fuera + liberacion + 0.05);
      fuentes = [src];
      this.registrar({ fuentes: fuentes, gain: g, inicio: t, fin: fuera + liberacion });
      return;
    }

    // sostenidos en tiempo real
    const env = {
      cuerdas: [0.18, 0.3, 0.85, 0.5],
      pad: [0.7, 1, 0.8, 1.6],
      coro: [0.3, 0.4, 0.8, 0.8],
      organo: [0.01, 0.1, 0.95, 0.08],
      metal: [0.05, 0.25, 0.7, 0.2],
      cana: [0.04, 0.2, 0.8, 0.12],
      flauta: [0.06, 0.2, 0.85, 0.15],
      cuadrada: [0.005, 0.15, 0.6, 0.06],
      sierra: [0.01, 0.2, 0.7, 0.1],
      bajoSinte: [0.005, 0.25, 0.6, 0.08],
    }[tipo] || [0.02, 0.2, 0.7, 0.2];
    const ataque = env[0];
    const decae = env[1];
    const sustain = env[2];
    liberacion = env[3];
    const amp = (0.2 + 0.6 * vel) * 0.55;

    const salida = ctx.createBiquadFilter();
    salida.type = 'lowpass';
    salida.Q.value = 0.7;
    salida.connect(g);

    const osc = (forma, detune, nivel) => {
      const o = ctx.createOscillator();
      if (typeof forma === 'string') o.type = forma;
      else o.setPeriodicWave(forma);
      o.frequency.value = f;
      o.detune.value = detune || 0;
      const og = ctx.createGain();
      og.gain.value = nivel;
      o.connect(og);
      og.connect(salida);
      fuentes.push(o);
      return o;
    };

    const vibrato = (prof, retardo, velocidad) => {
      const l = ctx.createOscillator();
      l.frequency.value = velocidad || 5.3;
      const lg = ctx.createGain();
      lg.gain.setValueAtTime(0, t);
      lg.gain.linearRampToValueAtTime(prof, t + retardo + 0.4);
      l.connect(lg);
      for (const o of fuentes) if (o.detune) lg.connect(o.detune);
      fuentes.push(l);
    };

    switch (tipo) {
      case 'cuerdas':
        osc('sawtooth', -7, 0.4);
        osc('sawtooth', 6, 0.4);
        osc('sawtooth', 0, 0.3);
        salida.frequency.setValueAtTime(f * 2, t);
        salida.frequency.linearRampToValueAtTime(Math.min(9000, f * (4 + vel * 4)), t + ataque * 1.5);
        vibrato(9, 0.3, 5.5);
        break;
      case 'pad': {
        osc('sawtooth', -11, 0.3);
        osc('sawtooth', 9, 0.3);
        osc(this.onda('suave'), 0, 0.5);
        salida.frequency.setValueAtTime(400, t);
        salida.frequency.linearRampToValueAtTime(Math.min(4000, f * 3 + 600), t + ataque * 2);
        const lfo = ctx.createOscillator();
        lfo.frequency.value = 0.15;
        const lg = ctx.createGain();
        lg.gain.value = 250;
        lfo.connect(lg);
        lg.connect(salida.frequency);
        fuentes.push(lfo);
        break;
      }
      case 'coro': {
        osc('sawtooth', -8, 0.35);
        osc('sawtooth', 8, 0.35);
        // formantes de "a"
        salida.type = 'bandpass';
        salida.frequency.value = 800;
        salida.Q.value = 2;
        const f2 = ctx.createBiquadFilter();
        f2.type = 'bandpass';
        f2.frequency.value = 1200;
        f2.Q.value = 3;
        const g2 = ctx.createGain();
        g2.gain.value = 0.6;
        // segunda vía en paralelo para el segundo formante
        const o3 = ctx.createOscillator();
        o3.type = 'sawtooth';
        o3.frequency.value = f;
        o3.connect(f2);
        f2.connect(g2);
        g2.connect(g);
        fuentes.push(o3);
        vibrato(7, 0.2, 5);
        break;
      }
      case 'organo':
        osc(this.onda('organo'), 0, 0.8);
        osc(this.onda('organo'), 4, 0.3);
        salida.frequency.value = 9000;
        vibrato(4, 0, 6.5);
        break;
      case 'metal':
        osc('sawtooth', 0, 0.6);
        osc('sawtooth', 5, 0.3);
        salida.frequency.setValueAtTime(f * 1.2, t);
        salida.frequency.linearRampToValueAtTime(Math.min(8000, f * (3 + vel * 5)), t + 0.08);
        salida.frequency.setTargetAtTime(f * 3, t + 0.1, 0.3);
        vibrato(6, 0.3, 5);
        break;
      case 'cana':
        osc(this.onda('clarinete'), 0, 0.8);
        salida.frequency.value = Math.min(7000, f * 5);
        vibrato(6, 0.25, 5.2);
        break;
      case 'flauta': {
        osc('sine', 0, 0.8);
        osc('triangle', 0, 0.18);
        salida.frequency.value = Math.min(9000, f * 6);
        vibrato(10, 0.2, 5);
        // soplo
        const nb = this.bufferRuido();
        const ns = ctx.createBufferSource();
        ns.buffer = nb;
        ns.loop = true;
        const nf = ctx.createBiquadFilter();
        nf.type = 'bandpass';
        nf.frequency.value = f * 2;
        nf.Q.value = 3;
        const ng = ctx.createGain();
        ng.gain.value = 0.05;
        ns.connect(nf);
        nf.connect(ng);
        ng.connect(salida);
        fuentes.push(ns);
        break;
      }
      case 'cuadrada':
        osc(this.onda('pulso25'), 0, 0.5);
        salida.frequency.value = 12000;
        vibrato(8, 0.35, 6);
        break;
      case 'bajoSinte':
        osc('sawtooth', 0, 0.55);
        osc('square', -1200, 0.35);
        salida.Q.value = 4;
        salida.frequency.setValueAtTime(2200, t);
        salida.frequency.setTargetAtTime(280, t + 0.005, 0.09);
        break;
      default: // sierra
        osc('sawtooth', -5, 0.45);
        osc('sawtooth', 5, 0.45);
        salida.frequency.value = Math.min(6000, f * 6);
        vibrato(10, 0.3, 5.5);
    }

    // envolvente ADSR
    const gn = g.gain;
    gn.setValueAtTime(0, t);
    gn.linearRampToValueAtTime(amp, t + ataque);
    gn.setTargetAtTime(amp * sustain, t + ataque, decae / 3);
    const suelta = Math.max(fin, t + ataque);
    gn.setTargetAtTime(0, suelta, liberacion / 4);
    for (const o of fuentes) {
      o.start(t);
      o.stop(suelta + liberacion * 1.5 + 0.05);
    }
    this.registrar({ fuentes: fuentes, gain: g, inicio: t, fin: suelta + liberacion * 1.5 });
  };

  Motor.prototype.onda = function (nombre) {
    if (this.ondas[nombre]) return this.ondas[nombre];
    const N = 64;
    const re = new Float32Array(N);
    const im = new Float32Array(N);
    for (let k = 1; k < N; k++) {
      let a = 0;
      if (nombre === 'organo') a = { 1: 1, 2: 0.7, 3: 0.5, 4: 0.4, 6: 0.25, 8: 0.2 }[k] || 0;
      else if (nombre === 'clarinete') a = k % 2 ? 1 / k : 0.04 / k;
      else if (nombre === 'pulso25') a = (2 / (Math.PI * k)) * Math.sin(Math.PI * k * 0.25);
      else if (nombre === 'suave') a = k <= 6 ? 1 / (k * k) : 0;
      im[k] = a;
    }
    this.ondas[nombre] = this.ctx.createPeriodicWave(re, im);
    return this.ondas[nombre];
  };

  Motor.prototype.bufferRuido = function () {
    if (this._ruido) return this._ruido;
    const sr = this.ctx.sampleRate;
    const b = this.ctx.createBuffer(1, sr, sr);
    const d = b.getChannelData(0);
    const r = ruido(99);
    for (let i = 0; i < d.length; i++) d[i] = r();
    this._ruido = b;
    return b;
  };

  // ------------------------------------------------------------ SoundFont

  Motor.prototype.bufferSf = function (reg) {
    const clave = reg.muestra + ':' + reg.inicio + ':' + reg.fin;
    let b = this.buffersSf.get(clave);
    if (!b) {
      const datos = SF.datosMuestra(this.sf, reg.muestra, reg.inicio, reg.fin);
      const fr = Math.max(3000, Math.min(192000, reg.frecuencia || 44100));
      b = this.ctx.createBuffer(1, Math.max(1, datos.length), fr);
      b.getChannelData(0).set(datos);
      this.buffersSf.set(clave, b);
    }
    return b;
  };

  Motor.prototype.tocarSf = function (ev, t, canal) {
    const ctx = this.ctx;
    const percusion = ev.canal === 9;
    const regs = SF.regiones(this.sf, ev.programa, ev.nota, ev.vel, percusion);
    if (!regs.length) {
      // la SoundFont no tiene ese instrumento: el interno lo cubre
      if (percusion) this.tocarBateria(ev, t, canal);
      else this.tocarInterno(ev, t, canal);
      return;
    }
    const vel = ev.vel / 127;
    const fin = t + ev.dur;
    for (const reg of regs) {
      if (reg.claseExclusiva) {
        for (const v of this.voces) {
          if (v.clase === 'sf' + reg.claseExclusiva && v.canal === ev.canal && v.fin > t) this.apagar(v, t, 0.01);
        }
      }
      const b = this.bufferSf(reg);
      const src = ctx.createBufferSource();
      src.buffer = b;
      src.playbackRate.value = reg.relacion;
      if (reg.bucle === 1 || reg.bucle === 3) {
        const ls = (reg.loopIni - reg.inicio) / b.sampleRate;
        const le = (reg.loopFin - reg.inicio) / b.sampleRate;
        if (le > ls && ls >= 0 && le <= b.duration + 1e-6) {
          src.loop = true;
          src.loopStart = ls;
          src.loopEnd = le;
        }
      }
      let nodo = src;
      // modulador por omisión de la norma: menos velocidad, filtro más cerrado
      const fcCents = (reg.filtroHz ? 1200 * Math.log2(reg.filtroHz / 8.176) : 13500) - 2400 * (1 - vel);
      if (fcCents < 13400) {
        const fl = ctx.createBiquadFilter();
        fl.type = 'lowpass';
        fl.frequency.value = Math.max(30, Math.min(20000, 8.176 * Math.pow(2, fcCents / 1200)));
        fl.Q.value = Math.max(0.5, Math.pow(10, reg.filtroQ / 20) * 0.707);
        nodo.connect(fl);
        nodo = fl;
      }
      const g = ctx.createGain();
      const pan = ctx.createStereoPanner();
      pan.pan.value = reg.pan;
      nodo.connect(g);
      g.connect(pan);
      pan.connect(canal.entrada);
      const pico = reg.ganancia * vel * vel * 0.9;
      const e = reg.env;
      const gn = g.gain;
      const t0 = t + e.retardo;
      gn.setValueAtTime(0, t);
      gn.setValueAtTime(0, t0);
      gn.linearRampToValueAtTime(pico, t0 + Math.max(0.001, e.ataque));
      const tDec = t0 + e.ataque + e.sostenido;
      gn.setValueAtTime(pico, tDec);
      if (e.decaimiento > 0) gn.setTargetAtTime(pico * e.sustain, tDec, e.decaimiento / 5);
      const rel = Math.min(4, e.liberacion);
      const suelta = Math.max(fin, t0 + 0.01);
      let finVoz;
      if (!src.loop) finVoz = Math.min(suelta + rel, t + b.duration / reg.relacion + 0.01);
      else finVoz = suelta + rel;
      gn.setTargetAtTime(0, suelta, rel / 5);
      src.start(t);
      src.stop(finVoz + 0.05);
      this.registrar({ fuentes: [src], gain: g, inicio: t, fin: finVoz, canal: ev.canal, clase: reg.claseExclusiva ? 'sf' + reg.claseExclusiva : null });
    }
  };

  /** Calcula de antemano los instrumentos de una lista de eventos (evita cortes al tocar). */
  Motor.prototype.precalentar = function (eventos) {
    if (this.sf) return;
    for (const ev of eventos) {
      if (ev.tipo === 'info') continue;
      if (ev.canal === 9) this.bufferInterno('bateria', { 35: 36, 44: 42, 40: 38 }[ev.nota] || ev.nota);
      else {
        const tipo = receta(ev.programa);
        if (PRERENDER.has(tipo)) this.bufferInterno(tipo, ev.nota);
      }
    }
  };

  const API = { Motor: Motor, receta: receta, renderizar: renderizar, renderizarBateria: renderizarBateria, karplus: karplus, frecuencia: frecuencia, PRERENDER: PRERENDER };
  // en Node solo sirven las funciones puras (las recetas), para las pruebas
  if (typeof module !== 'undefined' && module.exports) module.exports = API;
  else raiz.Sintetizador = API;
})(typeof globalThis !== 'undefined' ? globalThis : this);
