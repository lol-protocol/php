/*
 * La aplicación: une la biblioteca (ejemplos y MIDI propios guardados en IndexedDB), el
 * generador, el sintetizador y la visualización, y se encarga de grabar y exportar.
 *
 * Solo navegador. Se carga después de los demás scripts.
 */
(function () {
  'use strict';

  const M = window.MidiArchivo;
  const A = window.Analisis;
  const T = window.Teoria;
  const E = window.Ejemplos;
  const G = window.Generador;
  const SF = window.SoundFont;
  const F = window.Flac;
  const S = window.Sintetizador;
  const V = window.Visualizador;

  const $ = function (id) { return document.getElementById(id); };

  const ADELANTO = 1.2; // segundos que se programan por delante
  const AJUSTES_POR_OMISION = { fidelidad: 0.6, variacion: 0.4, estilo: 'auto', tempo: 1, volumen: 0.8, reverb: 0.55, modoVisual: 'milkdrop', autoVisual: true, bits: '16', semilla: '' };

  const estado = {
    canciones: [], // {id, nombre, origen: 'ejemplo'|'propio', bytes, midi, analisis, activo}
    ctx: null,
    motor: null,
    vis: null,
    gen: null,
    base: 0, // tiempo de audio en que empieza el tiempo 0 del generador
    reloj: null,
    tocando: false,
    modo: 'infinito', // o 'midi'
    midiActual: null,
    colaMidi: null,
    infos: [],
    sf: null,
    sfNombre: null,
    grabacion: null,
    ajustes: Object.assign({}, AJUSTES_POR_OMISION),
    ultimoCambioVisual: 0,
  };

  // ------------------------------------------------------------ almacenamiento (IndexedDB)

  const BD = {
    abrir: function () {
      if (this._p) return this._p;
      this._p = new Promise(function (ok, mal) {
        if (!window.indexedDB) return mal(new Error('Sin IndexedDB'));
        const r = indexedDB.open('musica-infinita', 1);
        r.onupgradeneeded = function () {
          const db = r.result;
          if (!db.objectStoreNames.contains('midis')) db.createObjectStore('midis', { keyPath: 'id' });
          if (!db.objectStoreNames.contains('cosas')) db.createObjectStore('cosas');
        };
        r.onsuccess = function () { ok(r.result); };
        r.onerror = function () { mal(r.error); };
      });
      return this._p;
    },
    op: function (almacen, modo, fn) {
      return this.abrir().then(function (db) {
        return new Promise(function (ok, mal) {
          const tx = db.transaction(almacen, modo);
          const r = fn(tx.objectStore(almacen));
          tx.oncomplete = function () { ok(r && r.result); };
          tx.onerror = function () { mal(tx.error); };
          tx.onabort = function () { mal(tx.error); };
        });
      });
    },
    todosMidis: function () { return this.op('midis', 'readonly', function (s) { return s.getAll(); }); },
    guardarMidi: function (r) { return this.op('midis', 'readwrite', function (s) { return s.put(r); }); },
    borrarMidi: function (id) { return this.op('midis', 'readwrite', function (s) { return s.delete(id); }); },
    leer: function (k) { return this.op('cosas', 'readonly', function (s) { return s.get(k); }); },
    escribir: function (k, v) { return this.op('cosas', 'readwrite', function (s) { return s.put(v, k); }); },
    borrar: function (k) { return this.op('cosas', 'readwrite', function (s) { return s.delete(k); }); },
  };

  function guardarAjustes() {
    try {
      localStorage.setItem('musica-infinita:ajustes', JSON.stringify(estado.ajustes));
    } catch (e) { /* sin almacenamiento */ }
  }

  function cargarAjustes() {
    try {
      const a = JSON.parse(localStorage.getItem('musica-infinita:ajustes') || '{}');
      Object.assign(estado.ajustes, a);
    } catch (e) { /* ajustes dañados: los de siempre */ }
  }

  // ------------------------------------------------------------ biblioteca

  function agregarCancion(c) {
    try {
      c.midi = M.leer(c.bytes);
      c.analisis = A.analizar(c.midi, c.nombre);
    } catch (e) {
      c.error = e.message;
    }
    estado.canciones.push(c);
  }

  function cancionesActivas() {
    return estado.canciones.filter(function (c) { return c.activo && c.analisis; }).map(function (c) { return c.analisis; });
  }

  async function cargarBiblioteca() {
    for (const ej of E.EJEMPLOS) {
      agregarCancion({ id: 'ejemplo:' + ej.id, nombre: ej.nombre, origen: 'ejemplo', bytes: E.aMidi(ej), activo: true });
    }
    const desactivados = new Set(JSON.parse(localStorage.getItem('musica-infinita:apagados') || '[]'));
    try {
      const propios = await BD.todosMidis();
      propios.sort(function (a, b) { return a.nombre.localeCompare(b.nombre, 'es'); });
      for (const r of propios) {
        agregarCancion({ id: r.id, nombre: r.nombre, origen: 'propio', bytes: new Uint8Array(r.bytes), activo: true });
      }
      const sf = await BD.leer('soundfont');
      if (sf && sf.bytes) usarSoundFont(new Uint8Array(sf.bytes), sf.nombre, false);
    } catch (e) {
      avisar('No se pudo abrir el almacenamiento del navegador: los MIDI que cargues no se guardarán.');
    }
    // si hay MIDI propios, los ejemplos empiezan apagados (la música es la tuya)
    const hayPropios = estado.canciones.some(function (c) { return c.origen === 'propio'; });
    for (const c of estado.canciones) {
      if (desactivados.has(c.id)) c.activo = false;
      else if (hayPropios && c.origen === 'ejemplo' && !localStorage.getItem('musica-infinita:apagados')) c.activo = false;
    }
    pintarLista();
  }

  function guardarApagados() {
    const ids = estado.canciones.filter(function (c) { return !c.activo; }).map(function (c) { return c.id; });
    localStorage.setItem('musica-infinita:apagados', JSON.stringify(ids));
  }

  async function cargarArchivos(archivos) {
    let nuevos = 0;
    let fallidos = 0;
    for (const f of archivos) {
      const ext = (f.name.split('.').pop() || '').toLowerCase();
      if (ext === 'sf2') {
        const bytes = new Uint8Array(await f.arrayBuffer());
        usarSoundFont(bytes, f.name, true);
        continue;
      }
      if (['mid', 'midi', 'kar', 'rmi'].indexOf(ext) < 0) continue;
      const bytes = new Uint8Array(await f.arrayBuffer());
      const nombre = f.name.replace(/\.(mid|midi|kar|rmi)$/i, '');
      const id = 'propio:' + nombre + ':' + bytes.length;
      if (estado.canciones.some(function (c) { return c.id === id; })) continue;
      const c = { id: id, nombre: nombre, origen: 'propio', bytes: bytes, activo: true };
      agregarCancion(c);
      if (c.error || !c.analisis) fallidos++;
      else nuevos++;
      try {
        await BD.guardarMidi({ id: id, nombre: nombre, bytes: bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) });
      } catch (e) { /* queda solo en esta sesión */ }
    }
    pintarLista();
    actualizarGenerador();
    if (nuevos || fallidos) avisar(nuevos + ' MIDI cargados' + (fallidos ? ', ' + fallidos + ' sin melodía reconocible (se pueden tocar tal cual)' : '') + '.');
  }

  function pintarLista() {
    const ul = $('lista');
    ul.textContent = '';
    for (const c of estado.canciones) {
      const li = document.createElement('li');
      if (estado.midiActual === c) li.className = 'sonando';
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = c.activo;
      chk.disabled = !c.analisis;
      chk.title = 'Usar en la música infinita';
      chk.addEventListener('change', function () {
        c.activo = chk.checked;
        guardarApagados();
        actualizarGenerador();
        contar();
      });
      const nom = document.createElement('span');
      nom.className = 'nombre';
      nom.textContent = c.nombre;
      nom.title = c.nombre;
      const meta = document.createElement('span');
      meta.className = 'meta';
      if (c.analisis) {
        const a = c.analisis;
        meta.textContent = T.nombreTonalidad(a.tonica, a.modo) + ' · ' + a.bpm + ' bpm · ' + a.compas.join('/') + ' · ' + a.frases.length + ' frases';
      } else meta.textContent = c.error ? 'no se pudo leer' : 'sin melodía';
      const tal = document.createElement('button');
      tal.textContent = '▶ tal cual';
      tal.disabled = !c.midi;
      tal.addEventListener('click', function () { tocarMidi(c); });
      li.append(chk, nom, meta, tal);
      if (c.origen === 'propio') {
        const borrar = document.createElement('button');
        borrar.textContent = '✕';
        borrar.title = 'Quitar de la biblioteca';
        borrar.addEventListener('click', async function () {
          if (!confirm('¿Quitar «' + c.nombre + '» de la biblioteca de este navegador?')) return;
          estado.canciones.splice(estado.canciones.indexOf(c), 1);
          try { await BD.borrarMidi(c.id); } catch (e) { /* ya no estaba */ }
          pintarLista();
          actualizarGenerador();
        });
        li.append(borrar);
      } else {
        li.append(document.createElement('span'));
      }
      ul.append(li);
    }
    contar();
  }

  function contar() {
    const act = estado.canciones.filter(function (c) { return c.activo && c.analisis; }).length;
    $('cuenta').textContent = act + ' de ' + estado.canciones.length + ' en uso';
  }

  // ------------------------------------------------------------ SoundFont

  function usarSoundFont(bytes, nombre, guardar) {
    let sf;
    try {
      sf = SF.leer(bytes);
    } catch (e) {
      avisar('No se pudo leer la SoundFont: ' + e.message);
      return;
    }
    estado.sf = sf;
    estado.sfNombre = nombre;
    if (estado.motor) estado.motor.ponerSoundFont(sf);
    const n = sf.presets.size;
    $('estado-sf').textContent = 'SoundFont: ' + (sf.info.nombre || nombre) + ' (' + n + ' instrumentos).';
    $('quitar-sf2').hidden = false;
    if (guardar) {
      BD.escribir('soundfont', { nombre: nombre, bytes: bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) })
        .then(function () { avisar('SoundFont guardada en el navegador: la próxima vez ya estará.'); })
        .catch(function () { avisar('SoundFont cargada (no cupo en el almacenamiento: habrá que cargarla otra vez la próxima).'); });
    }
  }

  function quitarSoundFont() {
    estado.sf = null;
    if (estado.motor) estado.motor.ponerSoundFont(null);
    $('estado-sf').textContent = 'Instrumentos propios (sintetizados).';
    $('quitar-sf2').hidden = true;
    BD.borrar('soundfont').catch(function () {});
  }

  // ------------------------------------------------------------ audio

  function semillaActual() {
    const s = estado.ajustes.semilla.trim();
    return s ? s : String(Math.floor(Math.random() * 1e9));
  }

  function crearGenerador(semilla, inicio) {
    const a = estado.ajustes;
    return new G.Generador({
      canciones: cancionesActivas(),
      semilla: semilla,
      fidelidad: Number(a.fidelidad),
      variacion: Number(a.variacion),
      estilo: a.estilo,
      tempo: Number(a.tempo),
      inicio: inicio || 0,
    });
  }

  function actualizarGenerador() {
    if (estado.gen) estado.gen.configurar({ canciones: cancionesActivas() });
  }

  async function iniciarAudio() {
    if (estado.ctx) return;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    estado.ctx = new Ctx({ latencyHint: 'playback' });
    estado.motor = new S.Motor(estado.ctx, { volumen: Number(estado.ajustes.volumen) });
    if (estado.sf) estado.motor.ponerSoundFont(estado.sf);
    estado.motor.ponerReverb(Number(estado.ajustes.reverb));
    estado.vis = new V($('lienzo'), estado.motor);
    estado.vis.alReemplazar = function (nuevo) { nuevo.id = 'lienzo'; };
    estado.vis.ponerModo(estado.ajustes.modoVisual);
    if (estado.vis.modo !== estado.ajustes.modoVisual) estado.vis.ponerModo('milkdrop');
    estado.motor.alTocar = function (ev, t) { estado.vis.nota(ev, t); };
    estado.vis.iniciar();
    estado.semillaSesion = semillaActual();
    estado.gen = crearGenerador(estado.semillaSesion);
    estado.base = estado.ctx.currentTime + 0.15;
    estado.reloj = setInterval(programar, 100);
    requestAnimationFrame(mostrarInfos);
  }

  function programar() {
    const ctx = estado.ctx;
    if (!ctx || ctx.state !== 'running') return;
    const ahora = ctx.currentTime;
    if (estado.modo === 'midi') {
      const cola = estado.colaMidi;
      while (cola.i < cola.eventos.length && cola.eventos[cola.i].t < ahora + (document.hidden ? ADELANTO * 3 : ADELANTO)) {
        const ev = cola.eventos[cola.i++];
        if (ev.t >= ahora - 0.05) estado.motor.tocar(ev, Math.max(ahora, ev.t));
      }
      if (cola.i >= cola.eventos.length && ahora > cola.fin) volverAInfinito();
      return;
    }
    // con la pestaña oculta el navegador espacia los temporizadores: se programa más por delante
    const adelanto = document.hidden ? ADELANTO * 3 : ADELANTO;
    const evs = estado.gen.eventosHasta(ahora - estado.base + adelanto);
    estado.motor.precalentar(evs);
    for (const ev of evs) {
      const t = estado.base + ev.t;
      if (ev.tipo === 'info') {
        estado.infos.push({ t: t, info: ev });
        estado.motor.ponerTempo(ev.bpm, t);
        continue;
      }
      if (t < ahora - 0.05) continue; // atrasado (pestaña dormida): se salta
      estado.motor.tocar(ev, Math.max(ahora, t));
    }
  }

  function mostrarInfos() {
    if (estado.ctx) {
      const ahora = estado.ctx.currentTime;
      while (estado.infos.length && estado.infos[0].t <= ahora) {
        const i = estado.infos.shift().info;
        pintarInfo(i);
        estado.vis.alInfo(i);
      }
      // con «auto», otra visualización cada minuto
      const ya = performance.now();
      if (!estado.ultimoCambioVisual) estado.ultimoCambioVisual = ya;
      if (estado.ajustes.autoVisual && estado.tocando && ya - estado.ultimoCambioVisual > 60000) {
        estado.ultimoCambioVisual = ya;
        cambiarVisual(estado.vis.siguienteModo());
      }
    }
    requestAnimationFrame(mostrarInfos);
  }

  function pintarInfo(i) {
    const titulo = i.material === 'cita' ? '♪ ' + i.cancion + ' — frase ' + i.frase + (i.variado ? ' (variada)' : '') : '♪ Frase libre, al estilo de tus canciones';
    $('titulo').textContent = titulo;
    $('detalle').textContent = i.estilo + ' · ' + i.tonalidad + ' · ' + Math.round(i.bpm) + ' bpm · sección ' + i.seccion + ' · semilla ' + estado.semillaSesion;
    $('acordes').textContent = i.acordes.join('  ');
    if ('mediaSession' in navigator) {
      try {
        navigator.mediaSession.metadata = new window.MediaMetadata({ title: i.material === 'cita' ? i.cancion : 'Frase libre', artist: 'Música infinita · ' + i.estilo, album: i.tonalidad });
      } catch (e) { /* sin metadatos */ }
    }
  }

  async function alternar() {
    await iniciarAudio();
    const ctx = estado.ctx;
    if (estado.tocando) {
      await ctx.suspend();
      estado.tocando = false;
    } else {
      await ctx.resume();
      estado.tocando = true;
    }
    $('play').textContent = estado.tocando ? '⏸' : '▶';
    if ('mediaSession' in navigator) navigator.mediaSession.playbackState = estado.tocando ? 'playing' : 'paused';
  }

  function siguienteSeccion() {
    if (!estado.ctx) return;
    if (estado.modo === 'midi') {
      volverAInfinito();
      return;
    }
    estado.motor.silenciar();
    estado.infos = [];
    const t = estado.ctx.currentTime - estado.base + 0.12;
    estado.gen.saltar(t);
  }

  async function tocarMidi(c) {
    if (!estado.ctx) await iniciarAudio();
    if (!estado.tocando) await alternar();
    estado.motor.silenciar();
    estado.infos = [];
    estado.modo = 'midi';
    estado.midiActual = c;
    estado.motor.modoMidi(true);
    const t0 = estado.ctx.currentTime + 0.15;
    const eventos = G.eventosDeMidi(c.midi, t0);
    estado.colaMidi = { eventos: eventos, i: 0, fin: t0 + c.midi.duracion + 1 };
    $('titulo').textContent = '♪ ' + c.nombre + ' (tal cual)';
    $('detalle').textContent = c.analisis ? T.nombreTonalidad(c.analisis.tonica, c.analisis.modo) + ' · ' + c.analisis.bpm + ' bpm · al terminar sigue la música infinita' : 'al terminar sigue la música infinita';
    $('acordes').textContent = '';
    pintarLista();
  }

  function volverAInfinito() {
    estado.motor.silenciar();
    estado.motor.modoMidi(false);
    estado.modo = 'infinito';
    estado.midiActual = null;
    estado.gen.saltar(estado.ctx.currentTime - estado.base + 0.12);
    pintarLista();
  }

  function cambiarVisual(modo) {
    estado.ajustes.modoVisual = modo;
    $('modo-visual').value = modo;
    guardarAjustes();
  }

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
    descargar(new Blob(partes, { type: 'audio/flac' }), nombreArchivo('flac'));
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
    descargar(new Blob([bytes], { type: 'audio/midi' }), nombreArchivo('mid', semilla));
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
    const TRAMO = 120;
    const PREVIO = 12;
    const COLA = 4;
    const gen = crearGenerador(semilla);
    const cache = new Map();
    const enc = esWav ? null : new F.CodificadorFlac({ frecuencia: sr, canales: 2, bits: bits, etiquetas: { TITLE: 'Música infinita', COMMENT: 'semilla ' + semilla } });
    const partesWav = esWav ? [F.cabeceraWav(2, sr, bits, Math.round((total + COLA) * sr))] : null;
    let pendientes = [];
    let bpm = 100;
    for (let s = 0; s < total; s += TRAMO) {
      const previo = Math.min(s, PREVIO);
      const ultimo = s + TRAMO >= total;
      const largo = Math.min(TRAMO, total - s) + (ultimo ? COLA : 0);
      const ctx = new OfflineAudioContext(2, Math.round((previo + largo) * sr), sr);
      const motor = new S.Motor(ctx, { cache: cache, maxVoces: 128, volumen: 1 });
      if (estado.sf) motor.ponerSoundFont(estado.sf);
      motor.ponerReverb(Number(estado.ajustes.reverb));
      const desde = s - previo;
      pendientes = pendientes.concat(gen.eventosHasta(s + TRAMO)).filter(function (ev) { return ev.t >= desde - 1; });
      motor.ponerTempo(bpm, 0);
      const delTramo = pendientes.filter(function (ev) { return ev.t < s + TRAMO && ev.t >= desde; });
      // Las notas se programan de a poco (cada 2 s, como en vivo): Chrome procesa todo nodo
      // conectado aunque su nota no haya empezado, y programar todo de una vez hace que el
      // costo crezca con el cuadrado del largo del tramo.
      let idx = 0;
      const programarHasta = function (lim) {
        while (idx < delTramo.length && delTramo[idx].t - desde < lim) {
          const ev = delTramo[idx++];
          const t = ev.t - desde;
          if (ev.tipo === 'info') {
            bpm = ev.bpm;
            motor.ponerTempo(ev.bpm, Math.max(ctx.currentTime, t));
          } else motor.tocar(ev, Math.max(ctx.currentTime, t));
        }
      };
      const VENTANA = 2;
      programarHasta(VENTANA * 1.5);
      for (let tk = VENTANA; tk < previo + largo; tk += VENTANA) {
        ctx.suspend(tk).then(function () {
          programarHasta(tk + VENTANA * 1.5);
          ctx.resume();
        });
      }
      pendientes = pendientes.filter(function (ev) { return ev.t >= s + TRAMO - PREVIO - 1; });
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
    if (enc) descargar(new Blob(enc.terminar(), { type: 'audio/flac' }), nombreArchivo('flac', semilla));
    else descargar(new Blob(partesWav, { type: 'audio/wav' }), nombreArchivo('wav', semilla));
    avisar('Exportado: ' + minutos + ' min, semilla ' + semilla + '.');
  }

  function nombreArchivo(ext, semilla) {
    const d = new Date();
    const p = function (n) { return String(n).padStart(2, '0'); };
    return 'musica-infinita-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes()) + (semilla ? '-' + String(semilla).replace(/[^\w-]+/g, '_').slice(0, 24) : '') + '.' + ext;
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

  // ------------------------------------------------------------ interfaz

  let avisoTimer = null;
  function avisar(texto) {
    const el = $('aviso');
    el.textContent = texto;
    el.hidden = false;
    clearTimeout(avisoTimer);
    avisoTimer = setTimeout(function () { el.hidden = true; }, 6000);
  }

  function enlazarRango(id, clave, alCambiar, formato) {
    const el = $(id);
    const v = $('v-' + id);
    el.value = estado.ajustes[clave];
    const pintar = function () {
      if (v) v.textContent = formato ? formato(Number(el.value)) : el.value;
    };
    pintar();
    el.addEventListener('input', function () {
      estado.ajustes[clave] = el.value;
      pintar();
      guardarAjustes();
      if (alCambiar) alCambiar(Number(el.value));
    });
  }

  function iniciarInterfaz() {
    cargarAjustes();
    const sel = $('estilo');
    const auto = document.createElement('option');
    auto.value = 'auto';
    auto.textContent = 'Automático (cambia solo)';
    sel.append(auto);
    for (const k in G.ESTILOS) {
      const o = document.createElement('option');
      o.value = k;
      o.textContent = G.ESTILOS[k].nombre;
      sel.append(o);
    }
    sel.value = estado.ajustes.estilo;
    sel.addEventListener('change', function () {
      estado.ajustes.estilo = sel.value;
      guardarAjustes();
      if (estado.gen) {
        estado.gen.configurar({ estilo: sel.value });
        siguienteSeccion();
      }
    });

    const mv = $('modo-visual');
    for (const m of V.MODOS) {
      const o = document.createElement('option');
      o.value = m;
      o.textContent = V.NOMBRES_MODOS[m];
      mv.append(o);
    }
    mv.value = estado.ajustes.modoVisual;
    mv.addEventListener('change', function () {
      if (estado.vis) estado.vis.ponerModo(mv.value);
      cambiarVisual(mv.value);
    });
    $('auto-visual').checked = !!estado.ajustes.autoVisual;
    $('auto-visual').addEventListener('change', function (e) {
      estado.ajustes.autoVisual = e.target.checked;
      guardarAjustes();
    });

    const pct = function (x) { return Math.round(x * 100) + ' %'; };
    enlazarRango('fidelidad', 'fidelidad', function (v) { if (estado.gen) estado.gen.configurar({ fidelidad: v }); }, pct);
    enlazarRango('variacion', 'variacion', function (v) { if (estado.gen) estado.gen.configurar({ variacion: v }); }, pct);
    enlazarRango('tempo', 'tempo', function (v) { if (estado.gen) estado.gen.configurar({ tempo: v }); }, function (x) { return '×' + x.toFixed(2); });
    enlazarRango('volumen', 'volumen', function (v) { if (estado.motor) estado.motor.ponerVolumen(v); });
    enlazarRango('reverb', 'reverb', function (v) { if (estado.motor) estado.motor.ponerReverb(v); });

    $('semilla').value = estado.ajustes.semilla;
    $('semilla').addEventListener('change', function (e) {
      estado.ajustes.semilla = e.target.value;
      guardarAjustes();
      if (estado.gen) {
        // empezar de nuevo con esa semilla
        estado.motor.silenciar();
        estado.infos = [];
        estado.semillaSesion = semillaActual();
        estado.gen = crearGenerador(estado.semillaSesion);
        estado.base = estado.ctx.currentTime + 0.15;
      }
    });
    $('bits-grabacion').value = estado.ajustes.bits;
    $('bits-grabacion').addEventListener('change', function (e) {
      estado.ajustes.bits = e.target.value;
      guardarAjustes();
    });

    $('empezar').addEventListener('click', async function () {
      $('inicio').hidden = true;
      await alternar();
    });
    $('play').addEventListener('click', alternar);
    $('siguiente').addEventListener('click', siguienteSeccion);
    $('pantalla').addEventListener('click', pantallaCompleta);
    $('grabar').addEventListener('click', alternarGrabacion);
    $('exportar').addEventListener('click', exportar);
    $('ver-ajustes').addEventListener('click', function () { $('ajustes').hidden = !$('ajustes').hidden; });
    $('archivo-midi').addEventListener('change', function (e) { cargarArchivos(Array.from(e.target.files)); e.target.value = ''; });
    $('carpeta-midi').addEventListener('change', function (e) { cargarArchivos(Array.from(e.target.files)); e.target.value = ''; });
    $('archivo-sf2').addEventListener('change', function (e) { cargarArchivos(Array.from(e.target.files)); e.target.value = ''; });
    $('quitar-sf2').addEventListener('click', quitarSoundFont);
    $('todos').addEventListener('click', function () { marcarTodas(true); });
    $('ninguno').addEventListener('click', function () { marcarTodas(false); });

    // arrastrar y soltar
    let profundidad = 0;
    window.addEventListener('dragenter', function (e) { e.preventDefault(); profundidad++; document.body.classList.add('arrastrando'); });
    window.addEventListener('dragleave', function () { if (--profundidad <= 0) { profundidad = 0; document.body.classList.remove('arrastrando'); } });
    window.addEventListener('dragover', function (e) { e.preventDefault(); });
    window.addEventListener('drop', function (e) {
      e.preventDefault();
      profundidad = 0;
      document.body.classList.remove('arrastrando');
      cargarArchivos(Array.from(e.dataTransfer.files));
    });

    // teclado (como en Winamp: una tecla, una acción)
    window.addEventListener('keydown', function (e) {
      if (e.target.matches('input[type="text"], input[type="number"], select')) return;
      const k = e.key.toLowerCase();
      if (k === ' ') { e.preventDefault(); alternar(); }
      else if (k === 'n') siguienteSeccion();
      else if (k === 'v' && estado.vis) cambiarVisual(estado.vis.siguienteModo());
      else if (k === 'p' && estado.vis) avisar('Ajuste visual: ' + estado.vis.siguientePreset());
      else if (k === 'f') pantallaCompleta();
      else if (k === 'r') alternarGrabacion();
      else if (k === 'h') ocultarPanel();
    });

    // el panel y la pantallita se esconden solos al no mover el ratón
    let quieto = null;
    const despertar = function () {
      $('panel').classList.remove('oculto');
      $('pantallita').classList.remove('oculto');
      clearTimeout(quieto);
      quieto = setTimeout(function () {
        if (estado.tocando && $('ajustes').hidden && !estado.panelFijo) $('panel').classList.add('oculto');
      }, 4000);
    };
    window.addEventListener('mousemove', despertar);
    window.addEventListener('touchstart', despertar, { passive: true });

    if ('mediaSession' in navigator) {
      try {
        navigator.mediaSession.setActionHandler('play', function () { if (!estado.tocando) alternar(); });
        navigator.mediaSession.setActionHandler('pause', function () { if (estado.tocando) alternar(); });
        navigator.mediaSession.setActionHandler('nexttrack', siguienteSeccion);
      } catch (e) { /* sin controles del sistema */ }
    }

    cargarBiblioteca();
  }

  function marcarTodas(v) {
    for (const c of estado.canciones) if (c.analisis) c.activo = v;
    guardarApagados();
    pintarLista();
    actualizarGenerador();
  }

  function ocultarPanel() {
    const p = $('panel');
    p.classList.toggle('oculto');
    $('pantallita').classList.toggle('oculto', p.classList.contains('oculto'));
  }

  function pantallaCompleta() {
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen().catch(function () {});
  }

  // para las pruebas en el navegador
  window.MusicaInfinita = { estado: estado, exportarAudio: exportarAudio, siguienteSeccion: siguienteSeccion, cargarArchivos: cargarArchivos };

  document.addEventListener('DOMContentLoaded', iniciarInterfaz);
})();
