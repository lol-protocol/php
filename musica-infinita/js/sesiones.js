/*
 * Sesiones: favoritos, historial, listas de reproducción y la exportación e importación de
 * la biblioteca con su configuración. Lógica pura (navegador y Node): no toca el DOM ni el
 * almacenamiento; panel-sesiones.js la usa y almacen.js guarda lo que sale de aquí.
 *
 * Qué es cada cosa:
 *   - Favorito: una semilla con los ajustes del compositor y las canciones que estaban en
 *     uso. Tocarlo reproduce la música desde el principio, igual que la primera vez, siempre
 *     que las canciones sigan en la biblioteca y el compositor sea de la misma versión.
 *   - Historial: lo que sonó, agrupado por «corrida» (una semilla con su configuración). Se
 *     puede volver a escuchar una corrida desde el principio o guardarla como favorita. Si
 *     se movieron los controles mientras sonaba, la corrida queda marcada como «con cambios
 *     en vivo»: ya no sale de su semilla sola.
 *   - Lista de reproducción: favoritos (cada uno unos minutos) y MIDI «tal cual», en orden o
 *     al azar, con o sin repetición.
 *   - Sesión exportada: un JSON con los ajustes, los favoritos, las listas, los ajustes
 *     propios de la MilkDrop y la lista de canciones (con sus bytes, si se pide).
 *
 * Todo lo que viene de fuera (un archivo importado, lo guardado en el navegador) se sanea:
 * los números se recortan a sus rangos, los textos se acotan y lo inválido se descarta.
 */
(function (raiz) {
  'use strict';

  const enNode = typeof module !== 'undefined' && module.exports;
  const VP = enNode ? require('./visual-puro.js') : raiz.VisualPuro;

  const VERSION_FORMATO = 1;
  const FORMATO = 'musica-infinita-sesion';

  // Los ajustes que cambian la música (los que un favorito guarda) y sus rangos.
  const GENERACION = ['fidelidad', 'variacion', 'colorModal', 'contra', 'cambiosCompas', 'tempo', 'estilo'];
  const RANGOS = { fidelidad: [0, 1], variacion: [0, 1], colorModal: [0, 1], contra: [0, 1], cambiosCompas: [0, 1], tempo: [0.6, 1.4], volumen: [0, 1], reverb: [0, 1.2] };

  const MAX = {
    favoritos: 200,
    listas: 50,
    itemsLista: 200,
    historial: 200,
    canciones: 500,
    nombre: 60,
    semilla: 40,
    estilosPorCorrida: 8,
    citasPorCorrida: 8,
    midi: 4 * 1024 * 1024, // bytes de un MIDI importado
    midiTotal: 64 * 1024 * 1024, // bytes de todos los MIDI de una sesión importada
    texto: 120 * 1024 * 1024, // largo del archivo importado
    minutos: 120,
  };

  function acotar(x, lo, hi) {
    return x < lo ? lo : x > hi ? hi : x;
  }

  function texto(s, max, porOmision) {
    // sin caracteres de control ni < > (los nombres se muestran en la interfaz)
    // eslint-disable-next-line no-control-regex
    const t = String(s == null ? '' : s).replace(/[\u0000-\u001f<>]/g, '').trim().slice(0, max);
    return t || porOmision || '';
  }

  // ------------------------------------------------------------ ajustes

  /**
   * Los ajustes que cambian la música, saneados: solo claves conocidas, los números dentro de
   * sus rangos y el estilo entre los permitidos (opciones.estilos) o «auto». Lo que falta o
   * no vale se omite (no se inventa).
   */
  function sanearAjustes(a, opciones) {
    const o = opciones || {};
    const out = {};
    if (!a || typeof a !== 'object') return out;
    for (const k of GENERACION) {
      if (k === 'estilo') {
        if (typeof a.estilo === 'string' && (a.estilo === 'auto' || !o.estilos || o.estilos.indexOf(a.estilo) >= 0)) out.estilo = a.estilo;
        continue;
      }
      const v = Number(a[k]);
      if (a[k] != null && a[k] !== '' && Number.isFinite(v)) out[k] = acotar(v, RANGOS[k][0], RANGOS[k][1]);
    }
    return out;
  }

  /** Todos los ajustes de la aplicación (los de la música más el sonido, la visualización y la grabación). */
  function sanearAjustesApp(a, opciones) {
    const o = opciones || {};
    const out = sanearAjustes(a, o);
    if (!a || typeof a !== 'object') return out;
    for (const k of ['volumen', 'reverb']) {
      const v = Number(a[k]);
      if (a[k] != null && a[k] !== '' && Number.isFinite(v)) out[k] = acotar(v, RANGOS[k][0], RANGOS[k][1]);
    }
    if (typeof a.modoVisual === 'string' && (!o.modos || o.modos.indexOf(a.modoVisual) >= 0)) out.modoVisual = a.modoVisual;
    if (typeof a.autoVisual === 'boolean') out.autoVisual = a.autoVisual;
    if (a.bits === '16' || a.bits === '24' || a.bits === 16 || a.bits === 24) out.bits = String(a.bits);
    if (typeof a.semilla === 'string') out.semilla = texto(a.semilla, MAX.semilla);
    return out;
  }

  function mismosAjustes(a, b) {
    for (const k of GENERACION) {
      if (a[k] === undefined && b[k] === undefined) continue;
      if (typeof a[k] === 'number' && typeof b[k] === 'number') {
        if (Math.abs(a[k] - b[k]) > 1e-9) return false;
      } else if (a[k] !== b[k]) return false;
    }
    return true;
  }

  function mismasCanciones(a, b) {
    if (a.length !== b.length) return false;
    const x = a.slice().sort();
    const y = b.slice().sort();
    for (let i = 0; i < x.length; i++) if (x[i] !== y[i]) return false;
    return true;
  }

  function sanearCanciones(l) {
    if (!Array.isArray(l)) return [];
    const vistos = new Set();
    const out = [];
    for (const c of l) {
      if (typeof c !== 'string' || !c || c.length > 300 || vistos.has(c)) continue;
      vistos.add(c);
      out.push(c);
      if (out.length >= MAX.canciones) break;
    }
    return out;
  }

  /**
   * La pista de melodía elegida a mano en cada canción: {idDeLaCanción: clave} (la clave es `pista:canal`
   * o `todas`). Una canción que no aparece usa la melodía automática. Solo claves bien formadas.
   */
  function sanearPistas(p) {
    const out = {};
    if (!p || typeof p !== 'object' || Array.isArray(p)) return out;
    let n = 0;
    for (const id of Object.keys(p)) {
      if (n >= MAX.canciones) break;
      if (id.length > 300 || typeof p[id] !== 'string') continue;
      if (p[id] === 'todas' || /^\d{1,4}:\d{1,2}$/.test(p[id])) {
        out[id] = p[id];
        n++;
      }
    }
    return out;
  }

  function mismasPistas(a, b) {
    const x = Object.keys(a || {});
    const y = Object.keys(b || {});
    if (x.length !== y.length) return false;
    for (const k of x) if (a[k] !== b[k]) return false;
    return true;
  }

  /** ¿Dos configuraciones (semilla, ajustes, canciones y pistas de melodía) dan la misma música? */
  function mismaConfiguracion(a, b) {
    return a.semilla === b.semilla && mismosAjustes(a.ajustes || {}, b.ajustes || {}) && mismasCanciones(a.canciones || [], b.canciones || []) && mismasPistas(a.pistas, b.pistas);
  }

  // ------------------------------------------------------------ identificadores

  /** Un identificador que no está en `existentes`: prefijo, la hora en base 36 y un contador si hace falta. */
  function nuevoId(prefijo, existentes, ahora) {
    const usados = new Set((existentes || []).map(function (x) { return typeof x === 'string' ? x : x.id; }));
    const base = prefijo + (ahora == null ? Date.now() : ahora).toString(36);
    let id = base;
    for (let i = 2; usados.has(id); i++) id = base + '-' + i;
    return id;
  }

  // ------------------------------------------------------------ favoritos

  /** Un favorito saneado, o null si no sirve (sin id, sin semilla o con una versión que no es número). */
  function validarFavorito(f, opciones) {
    if (!f || typeof f !== 'object') return null;
    const id = texto(f.id, 80);
    const semilla = texto(f.semilla, MAX.semilla);
    if (!id || !semilla) return null;
    return {
      id: id,
      nombre: texto(f.nombre, MAX.nombre, 'Sin nombre'),
      semilla: semilla,
      ajustes: sanearAjustes(f.ajustes, opciones),
      canciones: sanearCanciones(f.canciones),
      pistas: sanearPistas(f.pistas),
      generador: Number.isFinite(Number(f.generador)) ? Number(f.generador) : 0,
      creado: typeof f.creado === 'string' ? f.creado.slice(0, 40) : '',
    };
  }

  /**
   * Crea un favorito nuevo con la semilla, los ajustes y las canciones dadas. datos:
   * {nombre, semilla, ajustes, canciones, generador, ahora (milisegundos)}.
   */
  function crearFavorito(datos, existentes, opciones) {
    const ahora = datos.ahora == null ? Date.now() : datos.ahora;
    return validarFavorito({
      id: nuevoId('f', existentes, ahora),
      nombre: datos.nombre,
      semilla: datos.semilla,
      ajustes: datos.ajustes,
      canciones: datos.canciones,
      pistas: datos.pistas,
      generador: datos.generador,
      creado: new Date(ahora).toISOString(),
    }, opciones);
  }

  /**
   * Agrega un favorito a la lista (sin repetir una configuración que ya está) y respeta el tope.
   * Devuelve {lista, duplicado: el favorito existente o null, lleno: bool}.
   */
  function agregarFavorito(lista, f) {
    const igual = lista.find(function (x) { return mismaConfiguracion(x, f); });
    if (igual) return { lista: lista, duplicado: igual, lleno: false };
    if (lista.length >= MAX.favoritos) return { lista: lista, duplicado: null, lleno: true };
    return { lista: lista.concat([f]), duplicado: null, lleno: false };
  }

  function renombrarFavorito(lista, id, nombre) {
    return lista.map(function (f) { return f.id === id ? Object.assign({}, f, { nombre: texto(nombre, MAX.nombre, f.nombre) }) : f; });
  }

  /** Quita un favorito, y de las listas, sus apariciones (que ya no apuntan a nada). */
  function quitarFavorito(favoritos, listas, id) {
    return {
      favoritos: favoritos.filter(function (f) { return f.id !== id; }),
      listas: listas.map(function (l) {
        return Object.assign({}, l, { items: l.items.filter(function (it) { return !(it.tipo === 'favorito' && it.ref === id); }) });
      }),
    };
  }

  // ------------------------------------------------------------ historial

  /**
   * Anota una sección que empezó a sonar. conf: {semilla, ajustes, canciones, generador};
   * info: el aviso de sección del generador. La misma semilla seguida es la misma corrida
   * (si cambiaron los ajustes o las canciones, la corrida queda «con cambios en vivo» y toma
   * la configuración nueva); otra semilla abre una corrida nueva. Devuelve la lista nueva.
   */
  function registrarSeccion(historial, conf, info, ahora) {
    const hora = new Date(ahora == null ? Date.now() : ahora).toISOString();
    const lista = historial.slice();
    const ultima = lista[lista.length - 1];
    const citas = info && info.material === 'cita' && info.cancion ? [info.cancion] : [];
    const estilos = info && info.estilo ? [info.estilo] : [];
    if (ultima && ultima.semilla === conf.semilla) {
      const e = Object.assign({}, ultima);
      if (!mismosAjustes(e.ajustes, conf.ajustes) || !mismasCanciones(e.canciones, conf.canciones) || !mismasPistas(e.pistas, conf.pistas)) {
        e.limpia = false;
        e.ajustes = Object.assign({}, conf.ajustes);
        e.canciones = conf.canciones.slice();
        e.pistas = Object.assign({}, conf.pistas);
      }
      e.hasta = hora;
      e.secciones = Math.max(e.secciones, info && info.seccion ? info.seccion : 0);
      e.bloques = e.bloques + 1;
      e.estilos = union(e.estilos, estilos, MAX.estilosPorCorrida);
      e.citas = union(e.citas, citas, MAX.citasPorCorrida);
      lista[lista.length - 1] = e;
      return lista;
    }
    lista.push({
      id: nuevoId('h', lista, ahora),
      semilla: conf.semilla,
      ajustes: Object.assign({}, conf.ajustes),
      canciones: conf.canciones.slice(),
      pistas: Object.assign({}, conf.pistas),
      generador: conf.generador,
      limpia: true,
      desde: hora,
      hasta: hora,
      secciones: info && info.seccion ? info.seccion : 1,
      bloques: 1,
      estilos: estilos.slice(),
      citas: citas.slice(),
    });
    return lista.slice(-MAX.historial);
  }

  function union(a, b, max) {
    const out = a.slice();
    for (const x of b) if (out.indexOf(x) < 0 && out.length < max) out.push(x);
    return out;
  }

  /** Una corrida del historial saneada (viene del almacenamiento del navegador), o null. */
  function validarCorrida(h, opciones) {
    if (!h || typeof h !== 'object') return null;
    const semilla = texto(h.semilla, MAX.semilla);
    const id = texto(h.id, 80);
    if (!semilla || !id) return null;
    const lista = function (l, max) { return Array.isArray(l) ? l.slice(0, max).map(function (x) { return texto(x, 120); }).filter(Boolean) : []; };
    return {
      id: id,
      semilla: semilla,
      ajustes: sanearAjustes(h.ajustes, opciones),
      canciones: sanearCanciones(h.canciones),
      pistas: sanearPistas(h.pistas),
      generador: Number.isFinite(Number(h.generador)) ? Number(h.generador) : 0,
      limpia: h.limpia !== false,
      desde: typeof h.desde === 'string' ? h.desde.slice(0, 40) : '',
      hasta: typeof h.hasta === 'string' ? h.hasta.slice(0, 40) : '',
      secciones: Math.max(0, Math.floor(Number(h.secciones)) || 0),
      bloques: Math.max(0, Math.floor(Number(h.bloques)) || 0),
      estilos: lista(h.estilos, MAX.estilosPorCorrida),
      citas: lista(h.citas, MAX.citasPorCorrida),
    };
  }

  /** Cuánto duró una corrida, en segundos (de su primera a su última sección anotadas). */
  function duracionCorrida(h) {
    const a = Date.parse(h.desde);
    const b = Date.parse(h.hasta);
    return Number.isFinite(a) && Number.isFinite(b) && b >= a ? Math.round((b - a) / 1000) : 0;
  }

  // ------------------------------------------------------------ listas de reproducción

  /** Una lista saneada, o null. Un elemento es {tipo: 'favorito', ref, minutos} o {tipo: 'midi', ref}. */
  function validarLista(l) {
    if (!l || typeof l !== 'object') return null;
    const id = texto(l.id, 80);
    if (!id) return null;
    const items = [];
    for (const it of Array.isArray(l.items) ? l.items : []) {
      if (!it || typeof it !== 'object' || typeof it.ref !== 'string' || !it.ref || it.ref.length > 300) continue;
      if (it.tipo === 'favorito') items.push({ tipo: 'favorito', ref: it.ref, minutos: acotar(Math.round(Number(it.minutos) || 5), 1, MAX.minutos) });
      else if (it.tipo === 'midi') items.push({ tipo: 'midi', ref: it.ref });
      if (items.length >= MAX.itemsLista) break;
    }
    return { id: id, nombre: texto(l.nombre, MAX.nombre, 'Sin nombre'), modo: l.modo === 'aleatorio' ? 'aleatorio' : 'orden', repetir: l.repetir === true, items: items };
  }

  function crearLista(nombre, existentes, ahora) {
    return validarLista({ id: nuevoId('l', existentes, ahora), nombre: nombre, modo: 'orden', repetir: false, items: [] });
  }

  function agregarItem(lista, item) {
    if (lista.items.length >= MAX.itemsLista) return lista;
    const v = validarLista({ id: lista.id, items: [item] }).items[0];
    return v ? Object.assign({}, lista, { items: lista.items.concat([v]) }) : lista;
  }

  function quitarItem(lista, indice) {
    return Object.assign({}, lista, { items: lista.items.filter(function (_, i) { return i !== indice; }) });
  }

  function moverItem(lista, indice, delta) {
    const j = indice + delta;
    if (indice < 0 || indice >= lista.items.length || j < 0 || j >= lista.items.length) return lista;
    const items = lista.items.slice();
    const t = items[indice];
    items[indice] = items[j];
    items[j] = t;
    return Object.assign({}, lista, { items: items });
  }

  /**
   * Siguiente elemento a tocar. rep es el estado de la reproducción ({indice, jugados}), que
   * se modifica. En orden: el que sigue, y al final vuelve al primero si se repite. Al azar:
   * uno de los que aún no sonaron en esta vuelta; al agotarse, otra vuelta si se repite
   * (sin empezar por el último que sonó). Devuelve el índice o null si la lista terminó.
   */
  function siguiente(lista, rep, azar) {
    const n = lista.items.length;
    if (!n) return null;
    if (lista.modo === 'orden') {
      let i = rep.indice + 1;
      if (i >= n) {
        if (!lista.repetir) return null;
        i = 0;
      }
      rep.indice = i;
      return i;
    }
    let quedan = [];
    for (let i = 0; i < n; i++) if (rep.jugados.indexOf(i) < 0) quedan.push(i);
    if (!quedan.length) {
      if (!lista.repetir) return null;
      const ultimo = rep.indice;
      rep.jugados = [];
      quedan = [];
      for (let i = 0; i < n; i++) if (n === 1 || i !== ultimo) quedan.push(i);
    }
    const i = quedan[Math.floor((azar || Math.random)() * quedan.length)];
    rep.jugados.push(i);
    rep.indice = i;
    return i;
  }

  function nuevaReproduccion() {
    return { indice: -1, jugados: [] };
  }

  /**
   * Cada elemento de la lista con lo que hace falta para tocarlo: {item, ok, motivo, favorito,
   * cancion, minutos}. Un favorito borrado o un MIDI que ya no está dan ok: false con su motivo.
   * canciones: [{id, nombre, tieneMidi}].
   */
  function resolverItems(lista, favoritos, canciones) {
    return lista.items.map(function (item) {
      if (item.tipo === 'favorito') {
        const f = favoritos.find(function (x) { return x.id === item.ref; });
        return f ? { item: item, ok: true, favorito: f, minutos: item.minutos } : { item: item, ok: false, motivo: 'el favorito ya no existe' };
      }
      const c = canciones.find(function (x) { return x.id === item.ref; });
      if (!c) return { item: item, ok: false, motivo: 'el MIDI ya no está en la biblioteca' };
      if (c.tieneMidi === false) return { item: item, ok: false, motivo: 'el MIDI no se pudo leer' };
      return { item: item, ok: true, cancion: c };
    });
  }

  // ------------------------------------------------------------ base64

  /** Bytes a base64, por trozos (un MIDI grande no cabe en una sola llamada). */
  function aBase64(bytes) {
    let s = '';
    for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return raiz.btoa(s);
  }

  /** Base64 a bytes. Lanza un error si el texto no es base64 válido. */
  function deBase64(s) {
    if (typeof s !== 'string' || !/^[A-Za-z0-9+/]*={0,2}$/.test(s) || s.length % 4 !== 0) throw new Error('base64 inválido');
    const bin = raiz.atob(s);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out;
  }

  // ------------------------------------------------------------ exportar e importar

  /**
   * El JSON de una sesión. datos: {ajustes, favoritos, listas, presets, canciones:
   * [{id, nombre, activo, bytes?}], incluirMidi, ahora}. Con incluirMidi van los bytes de los
   * MIDI propios (los ejemplos nunca: ya vienen con la aplicación).
   */
  function exportarSesion(datos) {
    const canciones = (datos.canciones || []).map(function (c) {
      const o = { id: c.id, nombre: c.nombre, activo: c.activo !== false };
      if (datos.incluirMidi && c.bytes && c.id.indexOf('propio:') === 0) o.midi = aBase64(c.bytes);
      return o;
    });
    return JSON.stringify({
      formato: FORMATO,
      version: VERSION_FORMATO,
      creado: new Date(datos.ahora == null ? Date.now() : datos.ahora).toISOString(),
      incluyeMidi: !!datos.incluirMidi,
      ajustes: sanearAjustesApp(datos.ajustes, datos),
      favoritos: (datos.favoritos || []).map(function (f) { return validarFavorito(f, datos); }).filter(Boolean),
      listas: (datos.listas || []).map(validarLista).filter(Boolean),
      presets: (datos.presets || []).map(VP.validar).filter(Boolean),
      canciones: canciones,
    }, null, 1);
  }

  /**
   * Lee una sesión exportada. Devuelve {ajustes, favoritos, listas, presets, canciones, avisos}
   * con todo saneado (las canciones traen `bytes` si el archivo los incluía). Lanza un error
   * claro si el texto no es una sesión de esta aplicación, es de una versión más nueva o pesa demasiado.
   */
  function importarSesion(textoJson, opciones) {
    const o = opciones || {};
    if (typeof textoJson !== 'string' || textoJson.length > MAX.texto) throw new Error('El archivo es demasiado grande');
    let d;
    try {
      d = JSON.parse(textoJson);
    } catch (e) {
      throw new Error('El archivo no es un JSON válido', { cause: e });
    }
    if (!d || typeof d !== 'object' || d.formato !== FORMATO) throw new Error('El archivo no es una sesión de Música infinita');
    if (!(Number(d.version) >= 1) || Number(d.version) > VERSION_FORMATO) throw new Error('La sesión es de una versión más nueva de la aplicación');
    const avisos = [];
    const descartar = function (cuantos, que) {
      if (cuantos) avisos.push(cuantos + ' ' + que + ' descartados por inválidos');
    };

    const favoritos = [];
    const ids = new Set();
    const lf = Array.isArray(d.favoritos) ? d.favoritos : [];
    for (const f of lf.slice(0, MAX.favoritos)) {
      const v = validarFavorito(f, o);
      if (v && !ids.has(v.id)) {
        ids.add(v.id);
        favoritos.push(v);
      }
    }
    descartar(lf.length - favoritos.length, 'favoritos');

    const listas = [];
    const idl = new Set();
    const ll = Array.isArray(d.listas) ? d.listas : [];
    for (const l of ll.slice(0, MAX.listas)) {
      const v = validarLista(l);
      if (v && !idl.has(v.id)) {
        idl.add(v.id);
        listas.push(v);
      }
    }
    descartar(ll.length - listas.length, 'listas');

    const presets = [];
    const lp = Array.isArray(d.presets) ? d.presets : [];
    for (const p of lp.slice(0, 50)) {
      const v = VP.validar(p);
      if (v) presets.push(v);
    }
    descartar(lp.length - presets.length, 'ajustes de la MilkDrop');

    const canciones = [];
    const idc = new Set();
    let total = 0;
    let sinBytes = 0;
    const lc = Array.isArray(d.canciones) ? d.canciones : [];
    for (const c of lc.slice(0, MAX.canciones)) {
      if (!c || typeof c.id !== 'string' || !c.id || c.id.length > 300 || idc.has(c.id)) continue;
      idc.add(c.id);
      const cancion = { id: c.id, nombre: texto(c.nombre, 200, c.id), activo: c.activo !== false };
      if (typeof c.midi === 'string' && c.id.indexOf('propio:') === 0) {
        try {
          const bytes = deBase64(c.midi);
          if (bytes.length > MAX.midi || total + bytes.length > MAX.midiTotal) sinBytes++;
          else {
            total += bytes.length;
            cancion.bytes = bytes;
          }
        } catch (e) {
          sinBytes++;
        }
      }
      canciones.push(cancion);
    }
    descartar(Math.max(0, lc.length - canciones.length), 'canciones');
    if (sinBytes) avisos.push(sinBytes + ' MIDI no se pudieron leer o son demasiado grandes');

    return { ajustes: sanearAjustesApp(d.ajustes, o), favoritos: favoritos, listas: listas, presets: presets, canciones: canciones, avisos: avisos, incluyeMidi: d.incluyeMidi === true };
  }

  /**
   * Une lo importado con lo que ya hay. actual y importado: {favoritos, listas, presets}.
   * Un favorito que ya está (misma configuración) no se repite; si el id choca con otro favorito
   * distinto, el importado toma un id nuevo y las listas importadas se ajustan a él. Si el nombre
   * choca, el importado lleva « (importado)». Las listas y los ajustes de la MilkDrop con el mismo
   * nombre/id se reemplazan o se renombran del mismo modo. Devuelve {favoritos, listas, presets, resumen}.
   */
  function fusionar(actual, importado, ahora) {
    const favoritos = actual.favoritos.slice();
    const mapa = new Map(); // id importado -> id final
    const resumen = { favoritosNuevos: 0, favoritosRepetidos: 0, favoritosRenombrados: 0, listasNuevas: 0, presetsNuevos: 0 };
    for (const f of importado.favoritos) {
      const igual = favoritos.find(function (x) { return mismaConfiguracion(x, f); });
      if (igual) {
        mapa.set(f.id, igual.id);
        resumen.favoritosRepetidos++;
        continue;
      }
      if (favoritos.length >= MAX.favoritos) continue;
      const nuevo = Object.assign({}, f);
      if (favoritos.some(function (x) { return x.id === f.id; })) nuevo.id = nuevoId('f', favoritos.concat(importado.favoritos), ahora);
      if (favoritos.some(function (x) { return x.nombre === nuevo.nombre; })) {
        nuevo.nombre = texto(nuevo.nombre, MAX.nombre - 12) + ' (importado)';
        resumen.favoritosRenombrados++;
      }
      mapa.set(f.id, nuevo.id);
      favoritos.push(nuevo);
      resumen.favoritosNuevos++;
    }
    const listas = actual.listas.slice();
    for (const l of importado.listas) {
      if (listas.length >= MAX.listas) break;
      const nueva = Object.assign({}, l, {
        items: l.items.map(function (it) { return it.tipo === 'favorito' && mapa.has(it.ref) ? Object.assign({}, it, { ref: mapa.get(it.ref) }) : it; }),
      });
      if (listas.some(function (x) { return x.id === nueva.id; })) nueva.id = nuevoId('l', listas.concat(importado.listas), ahora);
      if (listas.some(function (x) { return x.nombre === nueva.nombre; })) nueva.nombre = texto(nueva.nombre, MAX.nombre - 12) + ' (importada)';
      listas.push(nueva);
      resumen.listasNuevas++;
    }
    let presets = actual.presets.slice();
    for (const p of importado.presets) {
      const q = VP.PRESETS.some(function (x) { return x.nombre === p.nombre; }) ? Object.assign({}, p, { nombre: p.nombre + ' (mío)' }) : p;
      presets = presets.filter(function (x) { return x.nombre !== q.nombre; }).concat([q]);
      resumen.presetsNuevos++;
    }
    return { favoritos: favoritos, listas: listas, presets: presets, resumen: resumen };
  }

  const API = {
    VERSION_FORMATO: VERSION_FORMATO,
    FORMATO: FORMATO,
    GENERACION: GENERACION,
    MAX: MAX,
    sanearAjustes: sanearAjustes,
    sanearAjustesApp: sanearAjustesApp,
    mismaConfiguracion: mismaConfiguracion,
    sanearPistas: sanearPistas,
    nuevoId: nuevoId,
    validarFavorito: validarFavorito,
    crearFavorito: crearFavorito,
    agregarFavorito: agregarFavorito,
    renombrarFavorito: renombrarFavorito,
    quitarFavorito: quitarFavorito,
    registrarSeccion: registrarSeccion,
    validarCorrida: validarCorrida,
    duracionCorrida: duracionCorrida,
    validarLista: validarLista,
    crearLista: crearLista,
    agregarItem: agregarItem,
    quitarItem: quitarItem,
    moverItem: moverItem,
    siguiente: siguiente,
    nuevaReproduccion: nuevaReproduccion,
    resolverItems: resolverItems,
    aBase64: aBase64,
    deBase64: deBase64,
    exportarSesion: exportarSesion,
    importarSesion: importarSesion,
    fusionar: fusionar,
  };

  if (enNode) {
    module.exports = API;
  } else {
    raiz.Sesiones = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
