/*
 * Grabación en vivo a FLAC y exportación de N minutos (FLAC, WAV o MIDI). Solo navegador.
 *
 * Exportador.crear(app) recibe lo que necesita de la aplicación (el estado, el generador,
 * los avisos…) y devuelve las acciones. El audio se calcula por tramos con
 * OfflineAudioContext (ver planificador.js) y se codifica a medida con flac.js.
 */
(function (raiz) {
  'use strict';

  const M = raiz.MidiArchivo;
  const F = raiz.Flac;
  const S = raiz.Sintetizador;
  const P = raiz.Planificador;

  function crear(app) {
    const estado = app.estado;
    const $ = app.$;
    const avisar = app.avisar;
    const alternar = app.alternar;
    const iniciarAudio = app.iniciarAudio;
    const crearGenerador = app.crearGenerador;
    const semillaActual = app.semillaActual;

    // ------------------------------------------------------------ grabación en vivo

    const CODIGO_GRABADOR = [
      'class Grabador extends AudioWorkletProcessor {',
      '  constructor() { super(); this.buf = [new Float32Array(8192), new Float32Array(8192)]; this.n = 0; this.activo = true;',
      '    this.port.onmessage = (e) => { if (e.data === "fin") { this.vaciar(); this.port.postMessage("fin"); this.activo = false; } }; }',
      '  vaciar() { if (this.n) { this.port.postMessage([this.buf[0].slice(0, this.n), this.buf[1].slice(0, this.n)]); this.n = 0; } }',
      '  process(inputs) {',
      '    const e = inputs[0];',
      '    if (this.activo && e && e.length) {',
      '      const l = e[0], r = e[1] || e[0];',
      '      for (let i = 0; i < l.length; i++) { this.buf[0][this.n] = l[i]; this.buf[1][this.n] = r[i]; this.n++; if (this.n === 8192) this.vaciar(); }',
      '    }',
      '    return this.activo;',
      '  }',
      '}',
      'registerProcessor("grabador-musica-infinita", Grabador);',
    ].join('\n');

    async function alternarGrabacion() {
      if (estado.grabacion) return terminarGrabacion();
      await iniciarAudio();
      if (!estado.tocando) await alternar();
      const ctx = estado.ctx;
      const bits = Number(estado.ajustes.bits) === 24 ? 24 : 16;
      const enc = new F.CodificadorFlac({ frecuencia: ctx.sampleRate, canales: 2, bits: bits, etiquetas: { TITLE: 'Música infinita', COMMENT: 'semilla ' + estado.semillaSesion, DATE: new Date().toISOString().slice(0, 10) } });
      const g = { enc: enc, inicio: performance.now(), bits: bits };
      const silencio = ctx.createGain();
      silencio.gain.value = 0;
      silencio.connect(ctx.destination);
      try {
        if (!estado.workletListo) {
          const url = URL.createObjectURL(new Blob([CODIGO_GRABADOR], { type: 'application/javascript' }));
          await ctx.audioWorklet.addModule(url);
          estado.workletListo = true;
        }
        const nodo = new AudioWorkletNode(ctx, 'grabador-musica-infinita', { numberOfInputs: 1, numberOfOutputs: 1, channelCount: 2, channelCountMode: 'explicit' });
        nodo.port.onmessage = function (e) {
          if (e.data === 'fin') {
            if (g.alTerminar) g.alTerminar();
            return;
          }
          enc.agregar(e.data);
        };
        estado.motor.salida.connect(nodo);
        nodo.connect(silencio);
        g.nodo = nodo;
      } catch (e) {
        // sin AudioWorklet (o bloqueado en file://): ScriptProcessor, que está obsoleto pero funciona
        const sp = ctx.createScriptProcessor(4096, 2, 2);
        sp.onaudioprocess = function (ev) {
          const b = ev.inputBuffer;
          enc.agregar([b.getChannelData(0).slice(0), b.getChannelData(b.numberOfChannels > 1 ? 1 : 0).slice(0)]);
        };
        estado.motor.salida.connect(sp);
        sp.connect(silencio);
        g.nodo = sp;
      }
      g.silencio = silencio;
      estado.grabacion = g;
      $('grabar').classList.add('grabando');
      $('grabar').textContent = '■ Detener';
      g.timer = setInterval(function () {
        const s = Math.floor((performance.now() - g.inicio) / 1000);
        $('tiempo-grabacion').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
      }, 500);
    }

    async function terminarGrabacion() {
      const g = estado.grabacion;
      estado.grabacion = null;
      clearInterval(g.timer);
      $('grabar').classList.remove('grabando');
      $('grabar').textContent = '● Grabar';
      $('tiempo-grabacion').textContent = '';
      if (g.nodo.port) {
        await new Promise(function (ok) {
          g.alTerminar = ok;
          g.nodo.port.postMessage('fin');
          setTimeout(ok, 1000);
        });
      }
      try { estado.motor.salida.disconnect(g.nodo); } catch (e) { /* ya estaba */ }
      g.nodo.disconnect();
      const partes = g.enc.terminar();
      descargar(new Blob(partes, { type: 'audio/flac' }), P.nombreArchivo('flac'));
      avisar('Grabación lista: ' + Math.round(g.enc.totalMuestras / g.enc.frecuencia) + ' s en FLAC de ' + g.bits + ' bits.');
    }

    // ------------------------------------------------------------ exportación

    async function exportar() {
      const minutos = Math.max(1, Math.min(120, Number($('minutos').value) || 10));
      const formato = $('formato').value;
      const semilla = estado.ajustes.semilla.trim() || estado.semillaSesion || semillaActual();
      const boton = $('exportar');
      const prog = $('progreso');
      boton.disabled = true;
      prog.hidden = false;
      prog.value = 0;
      try {
        if (formato === 'midi') {
          exportarMidi(minutos, semilla);
        } else {
          await exportarAudio(minutos, formato, semilla, function (p) { prog.value = p; });
        }
      } catch (e) {
        console.error(e);
        avisar('No se pudo exportar: ' + e.message);
      } finally {
        boton.disabled = false;
        prog.hidden = true;
      }
    }

    function exportarMidi(minutos, semilla) {
      const gen = crearGenerador(semilla);
      gen.humanizar = false;
      const evs = gen.eventosHasta(minutos * 60);
      // el MIDI va a 120 bpm fijos: un pulso es medio segundo, así los tiempos quedan exactos
      const pistas = new Map();
      for (const ev of evs) {
        if (ev.tipo === 'info') continue;
        const k = ev.voz;
        if (!pistas.has(k)) pistas.set(k, { nombre: k.charAt(0).toUpperCase() + k.slice(1), canal: ev.canal, programa: ev.canal === 9 ? null : ev.programa, notas: [] });
        pistas.get(k).notas.push({ nota: ev.nota, inicio: ev.t * 2, dur: ev.dur * 2, vel: ev.vel, programa: ev.canal === 9 ? null : ev.programa });
      }
      const bytes = M.escribir({ ppq: 480, bpm: 120, pistas: Array.from(pistas.values()) });
      descargar(new Blob([bytes], { type: 'audio/midi' }), P.nombreArchivo('mid', semilla));
      avisar('MIDI exportado: ' + minutos + ' min, semilla ' + semilla + '.');
    }

    /**
     * Calcula el audio por tramos de 2 minutos, cada uno en su OfflineAudioContext con unos
     * segundos de arranque (las notas y la reverberación que vienen del tramo anterior), y lo
     * codifica a medida: así una hora no necesita una hora de audio en memoria.
     */
    async function exportarAudio(minutos, formato, semilla, progreso) {
      const sr = 48000;
      const bits = formato === 'flac24' ? 24 : 16;
      const esWav = formato.indexOf('wav') === 0;
      const total = minutos * 60;
      const gen = crearGenerador(semilla);
      const cache = new Map();
      const enc = esWav ? null : new F.CodificadorFlac({ frecuencia: sr, canales: 2, bits: bits, etiquetas: { TITLE: 'Música infinita', COMMENT: 'semilla ' + semilla } });
      const partesWav = esWav ? [F.cabeceraWav(2, sr, bits, Math.round((total + P.COLA) * sr))] : null;
      let pendientes = [];
      let bpm = 100;
      for (const tr of P.tramos(total)) {
        const s = tr.inicio;
        const previo = tr.previo;
        const ctx = new OfflineAudioContext(2, Math.round((previo + tr.largo) * sr), sr);
        const motor = new S.Motor(ctx, { cache: cache, maxVoces: 128, volumen: 1 });
        if (estado.sf) motor.ponerSoundFont(estado.sf);
        motor.ponerReverb(Number(estado.ajustes.reverb));
        const desde = s - previo;
        pendientes = pendientes.concat(gen.eventosHasta(s + P.TRAMO)).filter(function (ev) { return ev.t >= desde - 1; });
        motor.ponerTempo(bpm, 0);
        await motor.precargarSf(pendientes); // SoundFont 3: decodifica de antemano lo que se va a tocar
        // Las notas se programan de a poco (cada 2 s, como en vivo): Chrome procesa todo nodo
        // conectado aunque su nota no haya empezado, y programar todo de una vez hace que el
        // costo crezca con el cuadrado del largo del tramo.
        const prog = P.programador(pendientes, desde, s + P.TRAMO);
        const programarHasta = function (lim) {
          for (const ev of prog.hasta(lim)) {
            const t = Math.max(ctx.currentTime, ev.t - desde);
            if (ev.tipo === 'info') {
              bpm = ev.bpm;
              motor.ponerTempo(ev.bpm, t);
            } else motor.tocar(ev, t);
          }
        };
        programarHasta(P.VENTANA * 1.5);
        for (let tk = P.VENTANA; tk < previo + tr.largo; tk += P.VENTANA) {
          ctx.suspend(tk).then(function () {
            programarHasta(tk + P.VENTANA * 1.5);
            ctx.resume();
          });
        }
        pendientes = pendientes.filter(function (ev) { return ev.t >= s + P.TRAMO - P.PREVIO - 1; });
        const buf = await ctx.startRendering();
        const ini = Math.round(previo * sr);
        const L = buf.getChannelData(0).subarray(ini);
        const R = buf.getChannelData(1).subarray(ini);
        // en trozos, para que la página no se congele
        for (let k = 0; k < L.length; k += sr * 10) {
          const l = L.subarray(k, Math.min(L.length, k + sr * 10));
          const r = R.subarray(k, Math.min(R.length, k + sr * 10));
          if (enc) enc.agregar([l, r]);
          else partesWav.push(F.pcm([l, r], bits));
          progreso(Math.min(0.99, (s + (k / sr)) / total));
          await new Promise(function (ok) { setTimeout(ok, 0); });
        }
      }
      progreso(1);
      if (enc) descargar(new Blob(enc.terminar(), { type: 'audio/flac' }), P.nombreArchivo('flac', semilla));
      else descargar(new Blob(partesWav, { type: 'audio/wav' }), P.nombreArchivo('wav', semilla));
      avisar('Exportado: ' + minutos + ' min, semilla ' + semilla + '.');
    }

    function descargar(blob, nombre) {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = nombre;
      document.body.append(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 60000);
    }

    return { alternarGrabacion: alternarGrabacion, exportar: exportar, exportarAudio: exportarAudio };
  }

  raiz.Exportador = { crear: crear };
})(window);
