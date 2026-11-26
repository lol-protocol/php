/*
 * El panel de sesiones: favoritos (una semilla con sus ajustes y sus canciones), listas de
 * reproducción, historial de lo que sonó, y exportar e importar la sesión. Solo navegador.
 *
 * La lógica (validar, fusionar, elegir el siguiente de una lista…) está en sesiones.js, con
 * sus pruebas. Aquí solo se une con la aplicación y se dibuja.
 *
 * PanelSesiones.crear(app) recibe de la aplicación: el estado, $, avisar, descargar,
 * configuracionActual, aplicarConfiguracion, tocarMidi, volverAInfinito, ajustesApp, aplicarAjustesApp,
 * agregarMidiImportado, refrescarBiblioteca, y las listas de estilos y modos.
 */
(function (raiz) {
  'use strict';

  const S = raiz.Sesiones;
  const Al = raiz.Almacen;
  const VP = raiz.VisualPuro;
  const P = raiz.Planificador;
  const G = raiz.Generador;

  function crear(app) {
    const $ = app.$;
    const estado = app.estado;
    const opciones = { estilos: app.estilos, modos: app.modos };

    // ---- lo guardado, saneado
    const crudo = Al.cargarSesiones();
    const datos = {
      favoritos: (Array.isArray(crudo.favoritos) ? crudo.favoritos : []).slice(0, S.MAX.favoritos).map(function (f) { return S.validarFavorito(f, opciones); }).filter(Boolean),
      listas: (Array.isArray(crudo.listas) ? crudo.listas : []).slice(0, S.MAX.listas).map(S.validarLista).filter(Boolean),
      historial: (Array.isArray(crudo.historial) ? crudo.historial : []).slice(-S.MAX.historial).map(function (h) { return S.validarCorrida(h, opciones); }).filter(Boolean),
    };
    let listaActual = datos.listas.length ? datos.listas[0].id : null;
    let reproduccion = null; // {lista, rep, indice, hasta, midiId, ocupado}

    function guardar() {
      if (!Al.guardarSesiones(datos)) app.avisar('No se pudo guardar en el navegador (¿sin espacio?).');
    }

    const fecha = function (iso) {
      const d = new Date(iso);
      if (isNaN(d)) return '';
      const p = function (n) { return String(n).padStart(2, '0'); };
      return d.getDate() + '/' + (d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    };
    const duracion = function (seg) {
      return seg >= 3600 ? Math.floor(seg / 3600) + ' h ' + Math.floor((seg % 3600) / 60) + ' min' : seg >= 60 ? Math.floor(seg / 60) + ' min' : seg + ' s';
    };
    function boton(texto, titulo, fn) {
      const b = document.createElement('button');
      b.textContent = texto;
      b.title = titulo;
      b.addEventListener('click', fn);
      return b;
    }
    function fila(clase) {
      const li = document.createElement('li');
      if (clase) li.className = clase;
      return li;
    }
    function celda(clase, texto, titulo) {
      const s = document.createElement('span');
      s.className = clase;
      s.textContent = texto;
      if (titulo) s.title = titulo;
      return s;
    }

    function nombreEstilo(k) {
      return k === 'auto' ? 'automático' : G.ESTILOS[k] ? G.ESTILOS[k].nombre : k;
    }

    // ---- aplicar una configuración y avisar de lo que falte
    async function tocarConfiguracion(conf, nombre) {
      const r = await app.aplicarConfiguracion(conf);
      const avisos = [];
      if (r.faltan.length) avisos.push(r.faltan.length + ' canción(es) de esta configuración ya no están en la biblioteca: no suenan');
      if (r.otraVersion) avisos.push('se guardó con otra versión del compositor: puede sonar distinto');
      if (avisos.length) app.avisar('«' + nombre + '»: ' + avisos.join('; ') + '.');
      return r;
    }

    // ================================================================ favoritos

    function pintarFavoritos() {
      const ul = $('lista-fav');
      ul.textContent = '';
      $('s-n-fav').textContent = datos.favoritos.length ? '(' + datos.favoritos.length + ')' : '';
      for (const f of datos.favoritos) {
        const li = fila();
        const meta = nombreEstilo(f.ajustes.estilo || 'auto') + ' · ' + f.canciones.length + ' canciones · ' + fecha(f.creado);
        li.append(
          boton('▶', 'Tocar desde el principio', function () { tocarConfiguracion(f, f.nombre); }),
          celda('nombre', f.nombre, 'Semilla: ' + f.semilla),
          celda('meta', (f.generador && f.generador !== G.VERSION ? '⚠ ' : '') + meta)
        );
        li.append(
          boton('✎', 'Cambiar el nombre', function () {
            const n = raiz.prompt('Nombre del favorito:', f.nombre);
            if (n == null) return;
            datos.favoritos = S.renombrarFavorito(datos.favoritos, f.id, n);
            guardar();
            pintarTodo();
          }),
          boton('✕', 'Quitar de favoritos', function () {
            if (!raiz.confirm('¿Quitar «' + f.nombre + '» de los favoritos?')) return;
            const q = S.quitarFavorito(datos.favoritos, datos.listas, f.id);
            datos.favoritos = q.favoritos;
            datos.listas = q.listas;
            guardar();
            pintarTodo();
          })
        );
        ul.append(li);
      }
    }

    function guardarFavorito(conf, nombreSugerido) {
      if (!conf) {
        app.avisar('Empieza la música primero: un favorito guarda lo que está sonando.');
        return;
      }
      const nombre = raiz.prompt('Nombre del favorito:', nombreSugerido || 'Semilla ' + conf.semilla);
      if (nombre == null) return;
      const f = S.crearFavorito({ nombre: nombre, semilla: conf.semilla, ajustes: conf.ajustes, canciones: conf.canciones, generador: conf.generador }, datos.favoritos, opciones);
      if (!f) return;
      const r = S.agregarFavorito(datos.favoritos, f);
      if (r.duplicado) {
        app.avisar('Ya tienes esa configuración guardada como «' + r.duplicado.nombre + '».');
        return;
      }
      if (r.lleno) {
        app.avisar('Llegaste al tope de ' + S.MAX.favoritos + ' favoritos: quita alguno.');
        return;
      }
      datos.favoritos = r.lista;
      guardar();
      pintarTodo();
      app.avisar('Favorito «' + f.nombre + '» guardado.');
    }

    $('fav-guardar').addEventListener('click', function () { guardarFavorito(app.configuracionActual()); });

    // ================================================================ historial

    function pintarHistorial() {
      const ul = $('lista-hist');
      ul.textContent = '';
      $('s-n-hist').textContent = datos.historial.length ? '(' + datos.historial.length + ')' : '';
      for (const h of datos.historial.slice().reverse()) {
        const li = fila();
        const resumen = (h.limpia ? '' : '≈ ') + 'semilla ' + h.semilla;
        const detalle = h.estilos.join(', ') + (h.estilos.length ? ' · ' : '') + h.secciones + (h.secciones === 1 ? ' sección' : ' secciones') + ' · ' + duracion(S.duracionCorrida(h)) + ' · ' + fecha(h.desde);
        li.append(
          boton('▶', 'Volver a oírla desde el principio', function () { tocarConfiguracion(h, 'semilla ' + h.semilla); }),
          celda('nombre', resumen, h.citas.length ? 'Citó: ' + h.citas.join(', ') : ''),
          celda('meta', detalle),
          boton('☆', 'Guardar como favorito', function () { guardarFavorito(h, 'Semilla ' + h.semilla); })
        );
        ul.append(li);
      }
    }

    $('hist-borrar').addEventListener('click', function () {
      if (datos.historial.length && !raiz.confirm('¿Borrar el historial?')) return;
      datos.historial = [];
      guardar();
      pintarHistorial();
    });

    // ================================================================ listas

    function lista() {
      return datos.listas.find(function (l) { return l.id === listaActual; }) || null;
    }

    function reemplazarLista(nueva) {
      datos.listas = datos.listas.map(function (l) { return l.id === nueva.id ? nueva : l; });
      guardar();
      pintarListas();
    }

    function nombreItem(r) {
      if (r.item.tipo === 'favorito') return r.favorito ? r.favorito.nombre : 'favorito borrado';
      return r.cancion ? r.cancion.nombre : r.item.ref;
    }

    function resolver(l) {
      return S.resolverItems(l, datos.favoritos, estado.canciones.map(function (c) { return { id: c.id, nombre: c.nombre, tieneMidi: !!c.midi }; }));
    }

    function pintarListas() {
      $('s-n-listas').textContent = datos.listas.length ? '(' + datos.listas.length + ')' : '';
      const sel = $('lista-sel');
      sel.textContent = '';
      for (const l of datos.listas) {
        const o = document.createElement('option');
        o.value = l.id;
        o.textContent = l.nombre + ' (' + l.items.length + ')';
        sel.append(o);
      }
      if (!lista() && datos.listas.length) listaActual = datos.listas[0].id;
      if (listaActual) sel.value = listaActual;
      const l = lista();
      $('lista-aleatorio').checked = !!(l && l.modo === 'aleatorio');
      $('lista-repetir').checked = !!(l && l.repetir);
      for (const id of ['lista-renombrar', 'lista-borrar', 'lista-aleatorio', 'lista-repetir', 'lista-tocar', 'lista-agregar-fav-btn', 'lista-agregar-midi-btn']) $(id).disabled = !l;
      $('lista-parar').disabled = !reproduccion;

      const selF = $('lista-agregar-fav');
      selF.textContent = '';
      for (const f of datos.favoritos) {
        const o = document.createElement('option');
        o.value = f.id;
        o.textContent = f.nombre;
        selF.append(o);
      }
      const selM = $('lista-agregar-midi');
      selM.textContent = '';
      for (const c of estado.canciones) {
        if (!c.midi) continue;
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre;
        selM.append(o);
      }

      const ol = $('lista-items');
      ol.textContent = '';
      if (!l) return;
      const res = resolver(l);
      l.items.forEach(function (item, i) {
        const r = res[i];
        const li = fila((r.ok ? '' : 'roto ') + (reproduccion && reproduccion.lista === l.id && reproduccion.indice === i ? 'sonando' : ''));
        li.append(
          celda('nombre', nombreItem(r), r.ok ? '' : r.motivo),
          celda('meta', item.tipo === 'favorito' ? item.minutos + ' min' : 'tal cual'),
          boton('↑', 'Subir', function () { reemplazarLista(S.moverItem(l, i, -1)); }),
          boton('↓', 'Bajar', function () { reemplazarLista(S.moverItem(l, i, 1)); }),
          boton('✕', 'Quitar de la lista', function () { reemplazarLista(S.quitarItem(l, i)); })
        );
        ol.append(li);
      });
    }

    $('lista-sel').addEventListener('change', function (e) {
      listaActual = e.target.value;
      pintarListas();
    });
    $('lista-nueva').addEventListener('click', function () {
      const n = raiz.prompt('Nombre de la lista:', 'Mi lista');
      if (n == null) return;
      if (datos.listas.length >= S.MAX.listas) {
        app.avisar('Llegaste al tope de ' + S.MAX.listas + ' listas.');
        return;
      }
      const l = S.crearLista(n, datos.listas);
      datos.listas = datos.listas.concat([l]);
      listaActual = l.id;
      guardar();
      pintarListas();
    });
    $('lista-renombrar').addEventListener('click', function () {
      const l = lista();
      const n = raiz.prompt('Nombre de la lista:', l.nombre);
      if (n == null) return;
      reemplazarLista(S.validarLista(Object.assign({}, l, { nombre: n })));
    });
    $('lista-borrar').addEventListener('click', function () {
      const l = lista();
      if (!raiz.confirm('¿Borrar la lista «' + l.nombre + '»?')) return;
      if (reproduccion && reproduccion.lista === l.id) reproduccion = null;
      datos.listas = datos.listas.filter(function (x) { return x.id !== l.id; });
      listaActual = datos.listas.length ? datos.listas[0].id : null;
      guardar();
      pintarListas();
    });
    $('lista-aleatorio').addEventListener('change', function (e) {
      reemplazarLista(Object.assign({}, lista(), { modo: e.target.checked ? 'aleatorio' : 'orden' }));
    });
    $('lista-repetir').addEventListener('change', function (e) {
      reemplazarLista(Object.assign({}, lista(), { repetir: e.target.checked }));
    });
    $('lista-agregar-fav-btn').addEventListener('click', function () {
      const ref = $('lista-agregar-fav').value;
      if (!ref) {
        app.avisar('Primero guarda algún favorito.');
        return;
      }
      reemplazarLista(S.agregarItem(lista(), { tipo: 'favorito', ref: ref, minutos: Number($('lista-minutos').value) || 5 }));
    });
    $('lista-agregar-midi-btn').addEventListener('click', function () {
      const ref = $('lista-agregar-midi').value;
      if (ref) reemplazarLista(S.agregarItem(lista(), { tipo: 'midi', ref: ref }));
    });

    // ---- el reproductor de listas

    function textoEstado() {
      if (!reproduccion) return '';
      const l = datos.listas.find(function (x) { return x.id === reproduccion.lista; });
      if (!l) return '';
      let t = 'Lista «' + l.nombre + '»: elemento ' + (reproduccion.indice + 1) + ' de ' + l.items.length;
      if (reproduccion.hasta != null && estado.ctx) t += ' · quedan ' + duracion(Math.max(0, Math.round(reproduccion.hasta - estado.ctx.currentTime)));
      else if (reproduccion.midiId) t += ' · hasta que termine el MIDI';
      return t;
    }

    /** Pasa al siguiente elemento que se pueda tocar; si no queda ninguno, termina la lista. */
    async function avanzar() {
      if (!reproduccion || reproduccion.ocupado) return;
      reproduccion.ocupado = true;
      try {
        const l = datos.listas.find(function (x) { return x.id === reproduccion.lista; });
        let saltados = 0;
        for (let intento = 0; l && intento <= l.items.length; intento++) {
          const i = S.siguiente(l, reproduccion.rep, Math.random);
          if (i === null) break;
          const r = resolver(l)[i];
          if (!r.ok) {
            saltados++;
            continue;
          }
          reproduccion.indice = i;
          reproduccion.midiId = null;
          reproduccion.hasta = null;
          if (r.favorito) {
            await tocarConfiguracion(r.favorito, r.favorito.nombre);
            if (!reproduccion) return; // la pararon mientras arrancaba
            reproduccion.hasta = estado.ctx.currentTime + r.minutos * 60;
          } else {
            reproduccion.midiId = r.cancion.id;
            const c = estado.canciones.find(function (x) { return x.id === r.cancion.id; });
            await app.tocarMidi(c);
            if (!reproduccion) return;
          }
          if (saltados) app.avisar(saltados + ' elemento(s) de la lista no se pudieron tocar y se saltaron.');
          pintarListas();
          $('lista-estado').textContent = textoEstado();
          return;
        }
        // se acabó la lista (o no había nada que tocar)
        const termino = l && l.items.length ? 'La lista terminó; sigue la música infinita.' : 'La lista está vacía.';
        reproduccion = null;
        // si el último fue un MIDI, la música infinita se reanuda (el gancho ya no está de por medio)
        if (estado.modo === 'midi') app.volverAInfinito();
        app.avisar(saltados && l && l.items.length ? 'Ningún elemento de la lista se pudo tocar.' : termino);
        pintarListas();
        $('lista-estado').textContent = '';
      } finally {
        if (reproduccion) reproduccion.ocupado = false;
      }
    }

    $('lista-tocar').addEventListener('click', function () {
      const l = lista();
      if (!l || !l.items.length) {
        app.avisar('Agrega algo a la lista primero.');
        return;
      }
      reproduccion = { lista: l.id, rep: S.nuevaReproduccion(), indice: -1, hasta: null, midiId: null, ocupado: false };
      avanzar();
    });
    $('lista-parar').addEventListener('click', function () {
      reproduccion = null;
      $('lista-estado').textContent = '';
      pintarListas();
    });

    // ================================================================ exportar e importar

    $('ses-exportar').addEventListener('click', function () {
      const presets = estado.vis ? estado.vis.presetsUsuario : Al.cargarPresets().map(VP.validar).filter(Boolean);
      const incluir = $('ses-incluir-midi').checked;
      const json = S.exportarSesion({
        ajustes: app.ajustesApp(),
        favoritos: datos.favoritos,
        listas: datos.listas,
        presets: presets,
        canciones: estado.canciones.map(function (c) { return { id: c.id, nombre: c.nombre, activo: c.activo, bytes: c.bytes }; }),
        incluirMidi: incluir,
        estilos: app.estilos,
        modos: app.modos,
      });
      app.descargar(new Blob([json], { type: 'application/json' }), P.nombreArchivo('json', 'sesion'));
      app.avisar('Sesión exportada: ' + datos.favoritos.length + ' favoritos, ' + datos.listas.length + ' listas' + (incluir ? ', con tus MIDI' : '') + '.');
    });

    $('ses-importar').addEventListener('change', async function (e) {
      const f = e.target.files[0];
      e.target.value = '';
      if (!f) return;
      try {
        const r = S.importarSesion(await f.text(), opciones);
        const presetsActuales = estado.vis ? estado.vis.presetsUsuario : Al.cargarPresets().map(VP.validar).filter(Boolean);
        const u = S.fusionar({ favoritos: datos.favoritos, listas: datos.listas, presets: presetsActuales }, { favoritos: r.favoritos, listas: r.listas, presets: r.presets });
        datos.favoritos = u.favoritos;
        datos.listas = u.listas;
        guardar();
        // ajustes de la MilkDrop
        Al.guardarPresets(u.presets);
        if (estado.vis) {
          estado.vis.presetsUsuario = u.presets;
          if (estado.panelVisual) estado.panelVisual.actualizar();
        }
        // MIDI que venían en el archivo, y cuáles están en uso
        let nuevos = 0;
        for (const c of r.canciones) if (c.bytes && (await app.agregarMidiImportado(c))) nuevos++;
        let marcadas = 0;
        for (const c of r.canciones) {
          const propia = estado.canciones.find(function (x) { return x.id === c.id && x.analisis; });
          if (propia && propia.activo !== c.activo) {
            propia.activo = c.activo;
            marcadas++;
          }
        }
        if (marcadas || nuevos) app.refrescarBiblioteca();
        if ($('ses-aplicar-ajustes').checked) app.aplicarAjustesApp(r.ajustes);
        pintarTodo();
        const partes = [u.resumen.favoritosNuevos + ' favoritos nuevos' + (u.resumen.favoritosRepetidos ? ' (' + u.resumen.favoritosRepetidos + ' ya estaban)' : ''), u.resumen.listasNuevas + ' listas', u.resumen.presetsNuevos + ' ajustes de la MilkDrop'];
        if (nuevos) partes.push(nuevos + ' MIDI');
        app.avisar('Sesión importada: ' + partes.join(', ') + '.' + (r.avisos.length ? ' ' + r.avisos.join('; ') + '.' : ''));
      } catch (err) {
        app.avisar('No se pudo importar: ' + err.message);
      }
    });

    // ================================================================ ganchos de la aplicación

    let ultimoEstado = 0;
    estado.alInfo = function (info) {
      const conf = app.configuracionActual();
      if (!conf) return;
      datos.historial = S.registrarSeccion(datos.historial, conf, info);
      guardar();
      if ($('s-historial').open) pintarHistorial();
    };
    estado.alTick = function (ahora) {
      if (!reproduccion) return;
      if (reproduccion.hasta != null && ahora >= reproduccion.hasta) avanzar();
      if (ahora - ultimoEstado > 1) {
        ultimoEstado = ahora;
        $('lista-estado').textContent = textoEstado();
      }
    };
    estado.alTerminarMidi = function () {
      if (!reproduccion || !reproduccion.midiId || !estado.midiActual || estado.midiActual.id !== reproduccion.midiId) return false;
      avanzar();
      return true;
    };
    estado.alBiblioteca = function () {
      if ($('s-listas').open) pintarListas();
    };

    function pintarTodo() {
      pintarFavoritos();
      pintarListas();
      pintarHistorial();
    }
    for (const id of ['s-listas', 's-historial']) $(id).addEventListener('toggle', pintarTodo);
    pintarTodo();

    return {
      datos: datos,
      pintar: pintarTodo,
      tocarLista: function (id) {
        listaActual = id;
        pintarListas();
        $('lista-tocar').click();
      },
      lista: function () { return reproduccion; },
    };
  }

  raiz.PanelSesiones = { crear: crear };
})(window);
