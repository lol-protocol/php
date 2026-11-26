/*
 * La aplicación: une la biblioteca (ejemplos y MIDI propios guardados en IndexedDB), el
 * generador, el sintetizador y la visualización. Grabar y exportar viven en exportar.js, el
 * almacenamiento del navegador en almacen.js y la planificación pura en planificador.js.
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
  const S = window.Sintetizador;
  const V = window.Visualizador;
  const P = window.Planificador;
  const BD = window.Almacen.BD;

  const $ = function (id) { return document.getElementById(id); };

  const AJUSTES_POR_OMISION = { fidelidad: 0.6, variacion: 0.4, colorModal: 0.3, contra: 0.6, cambiosCompas: 0.15, estilo: 'auto', tempo: 1, volumen: 0.8, reverb: 0.55, modoVisual: 'milkdrop', autoVisual: true, bits: '16', semilla: '' };

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
    semillaPersistida: null,
    semillaSesion: null,
    // ganchos que ponen las sesiones (panel-sesiones.js)
    alInfo: null,
    alTick: null,
    alTerminarMidi: null,
    alBiblioteca: null,
    panelVisual: null,
  };

  // La semilla que se guarda con los ajustes es la que la persona escribió, no la de un favorito
  // que está sonando: si no, al abrir la aplicación otra vez sonaría siempre esa misma música.
  function guardarAjustes() {
    const a = Object.assign({}, estado.ajustes);
    if (estado.semillaPersistida != null) a.semilla = estado.semillaPersistida;
    window.Almacen.guardarAjustes(a);
  }
  function cargarAjustes() {
    window.Almacen.cargarAjustes(estado.ajustes);
    estado.semillaPersistida = estado.ajustes.semilla;
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
      if (ext === 'sf2' || ext === 'sf3') {
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
    if (estado.alBiblioteca) estado.alBiblioteca();
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
      colorModal: Number(a.colorModal),
      contra: Number(a.contra),
      cambiosCompas: Number(a.cambiosCompas),
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
    estado.panelVisual = window.PanelVisual.crear({ $: $, avisar: avisar, descargar: descargar }, estado.vis);
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
      while (cola.i < cola.eventos.length && cola.eventos[cola.i].t < ahora + P.adelanto(document.hidden)) {
        const ev = cola.eventos[cola.i++];
        if (ev.t >= ahora - 0.05) estado.motor.tocar(ev, Math.max(ahora, ev.t));
      }
      if (cola.i >= cola.eventos.length && ahora > cola.fin && !cola.cerrada) {
        cola.cerrada = true; // una sola vez, aunque quien sigue (una lista) tarde en arrancar
        volverAInfinito();
      }
      return;
    }
    // con la pestaña oculta el navegador espacia los temporizadores: se programa más por delante
    const adelanto = P.adelanto(document.hidden);
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
        if (estado.alInfo) estado.alInfo(i);
      }
      if (estado.alTick) estado.alTick(ahora);
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
    $('detalle').textContent = i.estilo + ' · ' + i.tonalidad + ' · ' + i.compas + ' · ' + Math.round(i.bpm) + ' bpm' + (i.contra ? ' · contramelodía' : '') + ' · sección ' + i.seccion + ' · semilla ' + estado.semillaSesion;
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
    // una lista de reproducción que está tocando este MIDI sigue con su siguiente elemento
    if (estado.alTerminarMidi && estado.alTerminarMidi()) return;
    estado.motor.silenciar();
    estado.motor.modoMidi(false);
    estado.modo = 'infinito';
    estado.midiActual = null;
    estado.gen.saltar(estado.ctx.currentTime - estado.base + 0.12);
    pintarLista();
  }

  /** Empieza de nuevo desde el principio con esa semilla (y sale del modo «tal cual» si estaba). */
  function reiniciarConSemilla(semilla) {
    estado.motor.silenciar();
    if (estado.modo === 'midi') {
      estado.motor.modoMidi(false);
      estado.modo = 'infinito';
      estado.midiActual = null;
    }
    estado.infos = [];
    estado.semillaSesion = semilla;
    estado.gen = crearGenerador(semilla);
    estado.base = estado.ctx.currentTime + 0.15;
    pintarLista();
  }

  // ------------------------------------------------------------ sesiones (favoritos, listas, historial)

  const CLAVES_GENERACION = ['fidelidad', 'variacion', 'colorModal', 'contra', 'cambiosCompas', 'tempo', 'estilo'];

  /** Semilla, ajustes del compositor y canciones en uso: lo que hace falta para volver a oír esta música. */
  function configuracionActual() {
    if (!estado.semillaSesion) return null;
    const ajustes = {};
    for (const k of CLAVES_GENERACION) ajustes[k] = k === 'estilo' ? estado.ajustes.estilo : Number(estado.ajustes[k]);
    return {
      semilla: estado.semillaSesion,
      ajustes: ajustes,
      canciones: estado.canciones.filter(function (c) { return c.activo && c.analisis; }).map(function (c) { return c.id; }),
      generador: G.VERSION,
    };
  }

  /** Pone un ajuste en el estado y en su control (sin pasar por el generador). */
  function ponerAjuste(clave, valor) {
    estado.ajustes[clave] = valor;
    const c = controles[clave];
    if (c) {
      c.el.value = valor;
      c.pintar();
    } else if (clave === 'estilo') $('estilo').value = valor;
    else if (clave === 'bits') $('bits-grabacion').value = valor;
  }

  /**
   * Toca una configuración desde el principio: pone sus ajustes, deja en uso solo sus canciones
   * y empieza con su semilla. Devuelve {faltan: ids que ya no están en la biblioteca,
   * otraVersion: se guardó con otra versión del compositor}.
   */
  async function aplicarConfiguracion(conf) {
    for (const k of CLAVES_GENERACION) if (conf.ajustes[k] !== undefined) ponerAjuste(k, conf.ajustes[k]);
    const usar = new Set(conf.canciones);
    const faltan = conf.canciones.filter(function (id) { return !estado.canciones.some(function (c) { return c.id === id && c.analisis; }); });
    for (const c of estado.canciones) if (c.analisis) c.activo = usar.has(c.id);
    guardarApagados();
    guardarAjustes();
    // la semilla se muestra pero no se guarda con los ajustes (ver guardarAjustes)
    estado.ajustes.semilla = conf.semilla;
    $('semilla').value = conf.semilla;
    await iniciarAudio();
    if (!estado.tocando) await alternar();
    reiniciarConSemilla(conf.semilla);
    return { faltan: faltan, otraVersion: !!conf.generador && conf.generador !== G.VERSION };
  }

  /** Los ajustes de toda la aplicación, tal como se exportan (con la semilla que escribió la persona). */
  function ajustesApp() {
    return Object.assign({}, estado.ajustes, { semilla: estado.semillaPersistida == null ? estado.ajustes.semilla : estado.semillaPersistida });
  }

  /** Aplica ajustes importados: los controles, el motor y la visualización. */
  function aplicarAjustesApp(a) {
    for (const k of Object.keys(a)) {
      if (k === 'semilla') {
        estado.ajustes.semilla = a.semilla;
        estado.semillaPersistida = a.semilla;
        $('semilla').value = a.semilla;
      } else if (k === 'modoVisual') {
        estado.ajustes.modoVisual = a.modoVisual;
        $('modo-visual').value = a.modoVisual;
        if (estado.vis) estado.vis.ponerModo(a.modoVisual);
      } else if (k === 'autoVisual') {
        estado.ajustes.autoVisual = a.autoVisual;
        $('auto-visual').checked = a.autoVisual;
      } else {
        ponerAjuste(k, a[k]);
      }
    }
    if (a.volumen !== undefined && estado.motor) estado.motor.ponerVolumen(Number(a.volumen));
    if (a.reverb !== undefined && estado.motor) estado.motor.ponerReverb(Number(a.reverb));
    guardarAjustes();
  }

  /** Agrega a la biblioteca un MIDI que viene de una sesión importada (y lo guarda en el navegador). */
  async function agregarMidiImportado(c) {
    if (estado.canciones.some(function (x) { return x.id === c.id; })) return false;
    const nueva = { id: c.id, nombre: c.nombre, origen: 'propio', bytes: c.bytes, activo: c.activo };
    agregarCancion(nueva);
    try {
      await BD.guardarMidi({ id: c.id, nombre: c.nombre, bytes: c.bytes.buffer.slice(c.bytes.byteOffset, c.bytes.byteOffset + c.bytes.byteLength) });
    } catch (e) { /* queda solo en esta sesión */ }
    return true;
  }

  function cambiarVisual(modo) {
    estado.ajustes.modoVisual = modo;
    $('modo-visual').value = modo;
    guardarAjustes();
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

  const controles = {}; // los deslizadores por clave de ajuste, para ponerlos desde fuera (favoritos, importar)

  function enlazarRango(id, clave, alCambiar, formato) {
    const el = $(id);
    const v = $('v-' + id);
    el.value = estado.ajustes[clave];
    const pintar = function () {
      if (v) v.textContent = formato ? formato(Number(el.value)) : el.value;
    };
    pintar();
    controles[clave] = { el: el, pintar: pintar };
    el.addEventListener('input', function () {
      estado.ajustes[clave] = el.value;
      pintar();
      guardarAjustes();
      if (alCambiar) alCambiar(Number(el.value));
    });
  }

  let exportador = null;
  const alternarGrabacion = function () { return exportador.alternarGrabacion(); };
  const exportar = function () { return exportador.exportar(); };

  function iniciarInterfaz() {
    exportador = window.Exportador.crear({ estado: estado, $: $, avisar: avisar, alternar: alternar, iniciarAudio: iniciarAudio, crearGenerador: crearGenerador, semillaActual: semillaActual, descargar: descargar });
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
    enlazarRango('colorModal', 'colorModal', function (v) { if (estado.gen) estado.gen.configurar({ colorModal: v }); }, pct);
    enlazarRango('contra', 'contra', function (v) { if (estado.gen) estado.gen.configurar({ contra: v }); }, pct);
    enlazarRango('cambiosCompas', 'cambiosCompas', function (v) { if (estado.gen) estado.gen.configurar({ cambiosCompas: v }); }, pct);
    enlazarRango('tempo', 'tempo', function (v) { if (estado.gen) estado.gen.configurar({ tempo: v }); }, function (x) { return '×' + x.toFixed(2); });
    enlazarRango('volumen', 'volumen', function (v) { if (estado.motor) estado.motor.ponerVolumen(v); });
    enlazarRango('reverb', 'reverb', function (v) { if (estado.motor) estado.motor.ponerReverb(v); });

    $('semilla').value = estado.ajustes.semilla;
    $('semilla').addEventListener('change', function (e) {
      estado.ajustes.semilla = e.target.value;
      estado.semillaPersistida = e.target.value;
      guardarAjustes();
      // empezar de nuevo con esa semilla
      if (estado.gen) reiniciarConSemilla(semillaActual());
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

    estado.panelSesiones = window.PanelSesiones.crear({
      estado: estado,
      $: $,
      avisar: avisar,
      descargar: descargar,
      configuracionActual: configuracionActual,
      aplicarConfiguracion: aplicarConfiguracion,
      tocarMidi: tocarMidi,
      volverAInfinito: volverAInfinito,
      ajustesApp: ajustesApp,
      aplicarAjustesApp: aplicarAjustesApp,
      agregarMidiImportado: agregarMidiImportado,
      refrescarBiblioteca: function () { guardarApagados(); pintarLista(); actualizarGenerador(); },
      estilos: Object.keys(G.ESTILOS),
      modos: V.MODOS,
    });
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

  function descargar(blob, nombre) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nombre;
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 60000);
  }

  function pantallaCompleta() {
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen().catch(function () {});
  }

  // para las pruebas en el navegador
  window.MusicaInfinita = { estado: estado, exportarAudio: function () { return exportador.exportarAudio.apply(null, arguments); }, siguienteSeccion: siguienteSeccion, cargarArchivos: cargarArchivos, configuracionActual: configuracionActual, aplicarConfiguracion: aplicarConfiguracion, tocarMidi: tocarMidi };

  document.addEventListener('DOMContentLoaded', iniciarInterfaz);
})();
