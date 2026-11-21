/*
 * Sintetizador en Web Audio: toca los eventos del generador (o de un MIDI) con
 * instrumentos propios o con una SoundFont (.sf2 o .sf3), y los pasa por una cadena de mezcla:
 * reverberación de convolución, eco, compresor y limitador.
 *
 * Solo navegador (usa AudioContext u OfflineAudioContext). Necesita sf2.js y dsp.js antes.
 * Los instrumentos propios se calculan en dsp.js.
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
  const D = raiz.Dsp;
  const frecuencia = D.frecuencia;
  const receta = D.receta;
  const PRERENDER = D.PRERENDER;
  const renderizar = D.renderizar;
  const renderizarBateria = D.renderizarBateria;
  const impulso = D.impulso;
  const ruido = D.ruido;

  // Configuración de cada canal (por voz del generador).
  const CANALES = {
    0: { vol: 0.8, pan: 0, rev: 0.3, eco: 0.14 }, // melodía
    1: { vol: 0.4, pan: -0.22, rev: 0.42, eco: 0 }, // acordes
    2: { vol: 0.72, pan: 0, rev: 0.08, eco: 0 }, // bajo
    3: { vol: 0.36, pan: 0.28, rev: 0.36, eco: 0.16 }, // arpegio
    4: { vol: 0.42, pan: 0.18, rev: 0.34, eco: 0.1 }, // contramelodía
    9: { vol: 0.62, pan: 0, rev: 0.1, eco: 0 }, // batería
  };
  const CANAL_GENERICO = { vol: 0.55, pan: 0, rev: 0.22, eco: 0 };

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
    let liberacion;

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
    // SoundFont 3: ya decodificado (o todavía no: precargarSf lo pide con tiempo)
    if (reg.comprimida) return SF.bufferDecodificado(this.sf, reg.muestra) || null;
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

  /**
   * Decodifica de antemano las muestras comprimidas (SoundFont 3) que van a necesitar estos
   * eventos. Con una SoundFont 2 no hace nada. Devuelve una promesa.
   */
  Motor.prototype.precargarSf = function (eventos) {
    if (!this.sf || !this.sf.esSf3) return Promise.resolve();
    const vistos = new Set();
    let regs = [];
    for (const ev of eventos) {
      if (ev.tipo === 'info') continue;
      const clave = ev.canal + ':' + ev.programa + ':' + ev.nota + ':' + ev.vel;
      if (vistos.has(clave)) continue;
      vistos.add(clave);
      regs = regs.concat(SF.regiones(this.sf, ev.programa, ev.nota, ev.vel, ev.canal === 9));
    }
    return SF.precargar(this.sf, regs, this.ctx).catch(function (e) {
      console.warn('No se pudo decodificar una muestra de la SoundFont:', e);
    });
  };

  /** Un LFO (triangular, como pide la norma) hacia un AudioParam, con retardo. */
  Motor.prototype.lfo = function (frecuencia, retardo, profundidad, destino, t, fin, fuentes) {
    const ctx = this.ctx;
    const o = ctx.createOscillator();
    o.type = 'triangle';
    o.frequency.value = frecuencia;
    const g = ctx.createGain();
    g.gain.setValueAtTime(0, t);
    g.gain.setValueAtTime(profundidad, t + Math.max(0, retardo));
    o.connect(g);
    g.connect(destino);
    o.start(t);
    o.stop(fin);
    fuentes.push(o);
  };

  /** Envolvente de modulación (retardo, ataque, mantenimiento, caída, sostén, liberación) hacia un AudioParam. */
  function envolventeMod(param, e, cents, t, suelta) {
    const t0 = t + e.retardo;
    param.setValueAtTime(0, t);
    param.setValueAtTime(0, t0);
    param.linearRampToValueAtTime(cents, t0 + Math.max(0.001, e.ataque));
    const tDec = t0 + e.ataque + e.sostenido;
    param.setValueAtTime(cents, tDec);
    if (e.decaimiento > 0) param.setTargetAtTime(cents * e.sustain, tDec, e.decaimiento / 5);
    param.setTargetAtTime(0, Math.max(suelta, tDec), Math.min(4, e.liberacion) / 5);
  }

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
    const fin = t + ev.dur;
    let faltaba = false;
    let tocadas = 0;
    for (const reg of regs) {
      const b = this.bufferSf(reg);
      if (!b) {
        // muestra comprimida que aún no está decodificada: se pide para la próxima vez
        faltaba = true;
        SF.decodificar(this.sf, reg.muestra, ctx).catch(function () {});
        continue;
      }
      if (reg.claseExclusiva) {
        for (const v of this.voces) {
          if (v.clase === 'sf' + reg.claseExclusiva && v.canal === ev.canal && v.fin > t) this.apagar(v, t, 0.01);
        }
      }
      const src = ctx.createBufferSource();
      src.buffer = b;
      src.playbackRate.value = reg.relacion;
      if (reg.bucle === 1 || reg.bucle === 3) {
        const ls = reg.comprimida ? reg.loopIni / reg.frecuencia : (reg.loopIni - reg.inicio) / b.sampleRate;
        const le = reg.comprimida ? reg.loopFin / reg.frecuencia : (reg.loopFin - reg.inicio) / b.sampleRate;
        if (le > ls && ls >= 0 && le <= b.duration + 1e-6) {
          src.loop = true;
          src.loopStart = ls;
          src.loopEnd = le;
        }
      }
      tocadas++;
      const e = reg.env;
      const rel = Math.min(4, e.liberacion);
      const t0 = t + e.retardo;
      const suelta = Math.max(fin, t0 + 0.01);
      let finVoz;
      if (!src.loop) finVoz = Math.min(suelta + rel, t + b.duration / reg.relacion + 0.01);
      else finVoz = suelta + rel;
      const fuentes = [src];

      // filtro (con la velocidad ya aplicada en sf2.js); se crea también si algo lo modula
      let nodo = src;
      const filtraMod = (reg.lfoMod && reg.lfoMod.centsFiltro) || (reg.envMod && reg.envMod.centsFiltro);
      let filtro = null;
      if (reg.filtroHz || filtraMod) {
        filtro = ctx.createBiquadFilter();
        filtro.type = 'lowpass';
        filtro.frequency.value = Math.max(30, Math.min(20000, reg.filtroHz || 18000));
        filtro.Q.value = Math.max(0.5, Math.pow(10, reg.filtroQ / 20) * 0.707);
        nodo.connect(filtro);
        nodo = filtro;
      }
      // trémolo del LFO de modulación
      if (reg.lfoMod && reg.lfoMod.cbVolumen) {
        const tg = ctx.createGain();
        tg.gain.value = 1;
        const prof = (reg.lfoMod.cbVolumen < 0 ? -1 : 1) * Math.min(0.95, 1 - Math.pow(10, -Math.abs(reg.lfoMod.cbVolumen) / 200));
        this.lfo(reg.lfoMod.frecuencia, reg.lfoMod.retardo, prof, tg.gain, t, finVoz + 0.05, fuentes);
        nodo.connect(tg);
        nodo = tg;
      }
      const g = ctx.createGain();
      const pan = ctx.createStereoPanner();
      pan.pan.value = reg.pan;
      nodo.connect(g);
      g.connect(pan);
      pan.connect(canal.entrada);

      // vibrato y LFO de modulación a la afinación; LFO y envolvente de modulación al filtro
      if (reg.vibrato) this.lfo(reg.vibrato.frecuencia, reg.vibrato.retardo, reg.vibrato.cents, src.detune, t, finVoz + 0.05, fuentes);
      if (reg.lfoMod && reg.lfoMod.centsPitch) this.lfo(reg.lfoMod.frecuencia, reg.lfoMod.retardo, reg.lfoMod.centsPitch, src.detune, t, finVoz + 0.05, fuentes);
      if (filtro && reg.lfoMod && reg.lfoMod.centsFiltro) this.lfo(reg.lfoMod.frecuencia, reg.lfoMod.retardo, reg.lfoMod.centsFiltro, filtro.detune, t, finVoz + 0.05, fuentes);
      if (reg.envMod && reg.envMod.centsPitch) envolventeMod(src.detune, reg.envMod, reg.envMod.centsPitch, t, suelta);
      if (filtro && reg.envMod && reg.envMod.centsFiltro) envolventeMod(filtro.detune, reg.envMod, reg.envMod.centsFiltro, t, suelta);

      // envolvente de volumen
      const pico = reg.ganancia * 0.9;
      const gn = g.gain;
      gn.setValueAtTime(0, t);
      gn.setValueAtTime(0, t0);
      gn.linearRampToValueAtTime(pico, t0 + Math.max(0.001, e.ataque));
      const tDec = t0 + e.ataque + e.sostenido;
      gn.setValueAtTime(pico, tDec);
      if (e.decaimiento > 0) gn.setTargetAtTime(pico * e.sustain, tDec, e.decaimiento / 5);
      gn.setTargetAtTime(0, suelta, rel / 5);
      src.start(t);
      src.stop(finVoz + 0.05);
      this.registrar({ fuentes: fuentes, gain: g, inicio: t, fin: finVoz, canal: ev.canal, clase: reg.claseExclusiva ? 'sf' + reg.claseExclusiva : null });
    }
    // sin ninguna muestra lista (SoundFont 3 aún sin decodificar): la nota suena con el interno
    if (faltaba && !tocadas) {
      if (percusion) this.tocarBateria(ev, t, canal);
      else this.tocarInterno(ev, t, canal);
    }
  };

  /** Calcula de antemano los instrumentos de una lista de eventos (evita cortes al tocar). */
  Motor.prototype.precalentar = function (eventos) {
    if (this.sf) return this.precargarSf(eventos);
    for (const ev of eventos) {
      if (ev.tipo === 'info') continue;
      if (ev.canal === 9) this.bufferInterno('bateria', { 35: 36, 44: 42, 40: 38 }[ev.nota] || ev.nota);
      else {
        const tipo = receta(ev.programa);
        if (PRERENDER.has(tipo)) this.bufferInterno(tipo, ev.nota);
      }
    }
  };

  raiz.Sintetizador = { Motor: Motor, CANALES: CANALES };
})(typeof globalThis !== 'undefined' ? globalThis : this);
