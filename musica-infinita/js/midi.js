/*
 * Lectura y escritura de archivos MIDI estándar (SMF, formatos 0, 1 y 2).
 *
 * Funciones puras: no tocan el DOM ni Web Audio, así que corren igual en el navegador
 * (se cargan como <script>) y en Node (require).
 *
 * leer(bytes) devuelve las notas ya emparejadas (encendido con su apagado), con su
 * inicio y duración en ticks, en pulsos (negras) y en segundos según el mapa de tempos,
 * y además los tempos, los compases, el programa (instrumento GM) de cada canal y el
 * nombre de cada pista.
 *
 * escribir(cancion) arma un SMF de formato 1 a partir de pistas de notas: sirve para
 * exportar la música generada y para las pruebas (ida y vuelta).
 */
(function (raiz) {
  'use strict';

  const TEMPO_POR_OMISION = 500000; // microsegundos por negra: 120 bpm

  function aBytes(datos) {
    if (datos instanceof Uint8Array) return datos;
    if (datos instanceof ArrayBuffer) return new Uint8Array(datos);
    if (ArrayBuffer.isView(datos)) return new Uint8Array(datos.buffer, datos.byteOffset, datos.byteLength);
    throw new TypeError('Se esperaba un ArrayBuffer o un Uint8Array');
  }

  function texto(b, desde, largo) {
    let s = '';
    for (let i = 0; i < largo; i++) s += String.fromCharCode(b[desde + i]);
    return s;
  }

  // Texto de un meta-evento: casi siempre Latin-1 o UTF-8. Si es UTF-8 válido, se lee así.
  function textoMeta(datos) {
    try {
      if (typeof TextDecoder !== 'undefined') {
        return new TextDecoder('utf-8', { fatal: true }).decode(datos);
      }
    } catch (e) {
      // no era UTF-8: se lee como Latin-1
    }
    let s = '';
    for (let i = 0; i < datos.length; i++) s += String.fromCharCode(datos[i]);
    return s;
  }

  function Lector(bytes, pos, fin) {
    this.b = bytes;
    this.pos = pos;
    this.fin = fin;
  }
  Lector.prototype.u8 = function () {
    if (this.pos >= this.fin) throw new Error('MIDI truncado');
    return this.b[this.pos++];
  };
  Lector.prototype.vlq = function () {
    let v = 0;
    for (let i = 0; i < 4; i++) {
      const c = this.u8();
      v = (v << 7) | (c & 0x7f);
      if (!(c & 0x80)) return v;
    }
    throw new Error('Número de largo variable inválido');
  };
  Lector.prototype.trozo = function (n) {
    if (this.pos + n > this.fin) throw new Error('MIDI truncado');
    const t = this.b.subarray(this.pos, this.pos + n);
    this.pos += n;
    return t;
  };

  function leerPista(b, desde, fin, indice) {
    const r = new Lector(b, desde, fin);
    const eventos = [];
    let tick = 0;
    let estado = 0;
    while (r.pos < r.fin) {
      tick += r.vlq();
      let st = r.b[r.pos];
      if (st & 0x80) {
        r.pos++;
      } else {
        if (!estado) throw new Error('Running status sin estado previo en la pista ' + indice);
        st = estado;
      }
      if (st === 0xff) {
        const tipo = r.u8();
        const largo = r.vlq();
        const datos = r.trozo(largo);
        eventos.push({ tick: tick, tipo: 'meta', meta: tipo, datos: datos });
        if (tipo === 0x2f) break; // fin de pista
        // La norma dice que meta-eventos y sysex anulan el running status, pero hay
        // archivos que lo siguen usando después de ellos: se conserva, que es más tolerante.
      } else if (st === 0xf0 || st === 0xf7) {
        const largo = r.vlq();
        r.trozo(largo);
      } else {
        const hi = st & 0xf0;
        const canal = st & 0x0f;
        estado = st;
        const d1 = r.u8();
        const d2 = hi === 0xc0 || hi === 0xd0 ? 0 : r.u8();
        eventos.push({ tick: tick, tipo: hi, canal: canal, d1: d1, d2: d2 });
      }
    }
    return eventos;
  }

  // Convierte ticks a segundos con un mapa de tempos ordenado.
  function crearReloj(tempos, division) {
    const tramos = [];
    let seg = 0;
    let tickPrevio = 0;
    let uspq = TEMPO_POR_OMISION;
    const porSmpte = division < 0;
    let ticksPorSegundo = 0;
    if (porSmpte) {
      const fps = -(division >> 8);
      const tpf = division & 0xff;
      ticksPorSegundo = (fps === 29 ? 29.97 : fps) * tpf;
    }
    for (const t of tempos) {
      if (!porSmpte) seg += ((t.tick - tickPrevio) * uspq) / 1e6 / division;
      tramos.push({ tick: t.tick, seg: seg, uspq: t.uspq });
      tickPrevio = t.tick;
      uspq = t.uspq;
    }
    return function (tick) {
      if (porSmpte) return tick / ticksPorSegundo;
      let tramo = null;
      // búsqueda binaria del último tramo que empieza antes de tick
      let lo = 0;
      let hi = tramos.length - 1;
      while (lo <= hi) {
        const m = (lo + hi) >> 1;
        if (tramos[m].tick <= tick) {
          tramo = tramos[m];
          lo = m + 1;
        } else {
          hi = m - 1;
        }
      }
      if (!tramo) return (tick * TEMPO_POR_OMISION) / 1e6 / division;
      return tramo.seg + ((tick - tramo.tick) * tramo.uspq) / 1e6 / division;
    };
  }

  function leer(datos) {
    const b = aBytes(datos);
    let pos = 0;
    // Algunos .mid vienen envueltos en RIFF (RMID): se busca el trozo MThd.
    if (texto(b, 0, 4) === 'RIFF') {
      const i = buscarTexto(b, 'MThd');
      if (i < 0) throw new Error('Archivo RIFF sin datos MIDI');
      pos = i;
    }
    if (texto(b, pos, 4) !== 'MThd') throw new Error('No es un archivo MIDI (falta la cabecera MThd)');
    const largoCab = u32(b, pos + 4);
    const formato = u16(b, pos + 8);
    const nPistas = u16(b, pos + 10);
    let division = u16(b, pos + 12);
    if (division & 0x8000) division = division - 0x10000; // SMPTE: negativo
    if (division === 0) throw new Error('División de tiempo inválida');
    pos += 8 + largoCab;

    const pistasCrudas = [];
    while (pos + 8 <= b.length && pistasCrudas.length < nPistas) {
      const id = texto(b, pos, 4);
      const largo = u32(b, pos + 4);
      const ini = pos + 8;
      const fin = Math.min(b.length, ini + largo);
      if (id === 'MTrk') pistasCrudas.push(leerPista(b, ini, fin, pistasCrudas.length));
      pos = ini + largo;
    }
    if (!pistasCrudas.length) throw new Error('El MIDI no tiene pistas');

    // Tempos y compases (en formato 1 suelen estar en la pista 0, pero se aceptan en todas).
    const tempos = [];
    const compases = [];
    const nombres = [];
    for (let p = 0; p < pistasCrudas.length; p++) {
      nombres[p] = '';
      for (const e of pistasCrudas[p]) {
        if (e.tipo !== 'meta') continue;
        if (e.meta === 0x51 && e.datos.length >= 3) {
          const uspq = (e.datos[0] << 16) | (e.datos[1] << 8) | e.datos[2];
          if (uspq > 0) tempos.push({ tick: e.tick, uspq: uspq });
        } else if (e.meta === 0x58 && e.datos.length >= 2) {
          compases.push({ tick: e.tick, num: e.datos[0], den: Math.pow(2, e.datos[1]) });
        } else if (e.meta === 0x03 && !nombres[p]) {
          nombres[p] = textoMeta(e.datos).replace(/\0/g, '').trim();
        }
      }
    }
    tempos.sort(function (a, c) { return a.tick - c.tick; });
    compases.sort(function (a, c) { return a.tick - c.tick; });
    const aSegundos = crearReloj(tempos, division);
    const ppq = division > 0 ? division : 480;

    // Notas: se empareja cada encendido con su apagado (FIFO por canal y nota).
    const notas = [];
    let ultimoTick = 0;
    const programas = []; // [{tick, canal, programa, pista}]
    for (let p = 0; p < pistasCrudas.length; p++) {
      const abiertas = new Map();
      const programaCanal = new Array(16).fill(0);
      for (const e of pistasCrudas[p]) {
        if (e.tick > ultimoTick) ultimoTick = e.tick;
        if (e.tipo === 0xc0) {
          programaCanal[e.canal] = e.d1;
          programas.push({ tick: e.tick, canal: e.canal, programa: e.d1, pista: p });
          continue;
        }
        const esOn = e.tipo === 0x90 && e.d2 > 0;
        const esOff = e.tipo === 0x80 || (e.tipo === 0x90 && e.d2 === 0);
        if (!esOn && !esOff) continue;
        const clave = e.canal * 128 + e.d1;
        if (esOn) {
          if (!abiertas.has(clave)) abiertas.set(clave, []);
          abiertas.get(clave).push({ tick: e.tick, vel: e.d2, programa: programaCanal[e.canal] });
        } else {
          const cola = abiertas.get(clave);
          if (cola && cola.length) {
            const on = cola.shift();
            notas.push(crearNota(on, e.tick, e.canal, e.d1, p));
          }
        }
      }
      // Notas que nunca se apagaron: se cierran al final de la pista.
      abiertas.forEach(function (cola, clave) {
        for (const on of cola) {
          const fin = Math.max(on.tick + 1, ultimoTick);
          notas.push(crearNota(on, fin, clave >> 7, clave & 127, p));
        }
      });
    }
    function crearNota(on, tickFin, canal, nota, pista) {
      const durTicks = Math.max(1, tickFin - on.tick);
      const ini = aSegundos(on.tick);
      return {
        nota: nota,
        vel: on.vel,
        canal: canal,
        programa: on.programa,
        pista: pista,
        inicioTick: on.tick,
        durTicks: durTicks,
        inicio: ini,
        dur: aSegundos(on.tick + durTicks) - ini,
        inicioPulso: on.tick / ppq,
        durPulsos: durTicks / ppq,
      };
    }
    notas.sort(function (a, c) { return a.inicioTick - c.inicioTick || a.nota - c.nota; });

    return {
      formato: formato,
      division: division,
      ppq: ppq,
      pistas: nombres.map(function (n, i) { return { nombre: n, eventos: pistasCrudas[i].length }; }),
      tempos: tempos.length ? tempos : [{ tick: 0, uspq: TEMPO_POR_OMISION }],
      compases: compases.length ? compases : [{ tick: 0, num: 4, den: 4 }],
      programas: programas,
      notas: notas,
      duracion: aSegundos(ultimoTick),
      aSegundos: aSegundos,
    };
  }

  function buscarTexto(b, s) {
    outer: for (let i = 0; i + s.length <= b.length; i++) {
      for (let j = 0; j < s.length; j++) if (b[i + j] !== s.charCodeAt(j)) continue outer;
      return i;
    }
    return -1;
  }

  function u16(b, i) {
    return (b[i] << 8) | b[i + 1];
  }

  function u32(b, i) {
    return ((b[i] << 24) >>> 0) + ((b[i + 1] << 16) | (b[i + 2] << 8) | b[i + 3]);
  }

  // ---------------------------------------------------------------- escritura

  function vlqBytes(v) {
    const pila = [v & 0x7f];
    v >>>= 7;
    while (v > 0) {
      pila.unshift((v & 0x7f) | 0x80);
      v >>>= 7;
    }
    return pila;
  }

  function trozoPista(eventos) {
    // eventos: [{tick, bytes: [...]}], ordenados
    const out = [];
    let previo = 0;
    for (const e of eventos) {
      const d = Math.max(0, Math.round(e.tick) - previo);
      previo += d;
      Array.prototype.push.apply(out, vlqBytes(d));
      Array.prototype.push.apply(out, e.bytes);
    }
    Array.prototype.push.apply(out, [0x00, 0xff, 0x2f, 0x00]);
    return out;
  }

  function bytesTexto(s) {
    const r = [];
    if (typeof TextEncoder !== 'undefined') {
      const u = new TextEncoder().encode(s);
      for (let i = 0; i < u.length; i++) r.push(u[i]);
    } else {
      for (let i = 0; i < s.length; i++) r.push(s.charCodeAt(i) & 0xff);
    }
    return r;
  }

  /**
   * cancion = {
   *   ppq: 480, bpm: 120 | tempos: [{pulso, bpm}], compas: [4, 4],
   *   pistas: [{ nombre, canal, programa, notas: [{nota, inicio, dur, vel, programa?}] }]  // inicio y dur en pulsos
   * }
   */
  function escribir(cancion) {
    const ppq = cancion.ppq || 480;
    const tempos = cancion.tempos || [{ pulso: 0, bpm: cancion.bpm || 120 }];
    const compas = cancion.compas || [4, 4];
    const cab = [0x4d, 0x54, 0x68, 0x64, 0, 0, 0, 6, 0, 1];
    const pistas = [];

    const conductor = [];
    for (const t of tempos) {
      const us = Math.round(60e6 / t.bpm);
      conductor.push({ tick: t.pulso * ppq, orden: 0, bytes: [0xff, 0x51, 0x03, (us >> 16) & 0xff, (us >> 8) & 0xff, us & 0xff] });
    }
    const exp = Math.round(Math.log2(compas[1]));
    conductor.push({ tick: 0, orden: 0, bytes: [0xff, 0x58, 0x04, compas[0], exp, 24, 8] });
    conductor.sort(function (a, c) { return a.tick - c.tick; });
    pistas.push(trozoPista(conductor));

    for (const p of cancion.pistas) {
      const canal = (p.canal || 0) & 15;
      const ev = [];
      if (p.nombre) {
        const n = bytesTexto(p.nombre);
        ev.push({ tick: 0, orden: 0, bytes: [0xff, 0x03].concat(vlqBytes(n.length), n) });
      }
      let programaActual = p.programa;
      if (p.programa != null && canal !== 9) ev.push({ tick: 0, orden: 1, bytes: [0xc0 | canal, p.programa & 127] });
      for (const n of p.notas) {
        const ini = Math.round(n.inicio * ppq);
        // una nota puede traer su propio programa (cambio de instrumento a mitad de pista)
        if (n.programa != null && n.programa !== programaActual && canal !== 9) {
          programaActual = n.programa;
          ev.push({ tick: ini, orden: 1, bytes: [0xc0 | canal, n.programa & 127] });
        }
        const fin = Math.max(ini + 1, Math.round((n.inicio + n.dur) * ppq));
        const vel = Math.max(1, Math.min(127, Math.round(n.vel == null ? 90 : n.vel)));
        ev.push({ tick: ini, orden: 3, bytes: [0x90 | canal, n.nota & 127, vel] });
        ev.push({ tick: fin, orden: 2, bytes: [0x80 | canal, n.nota & 127, 0] });
      }
      // a igual tick, los apagados antes que los encendidos (una nota repetida no se corta)
      ev.sort(function (a, c) { return a.tick - c.tick || a.orden - c.orden; });
      pistas.push(trozoPista(ev));
    }

    const n = pistas.length;
    cab.push((n >> 8) & 0xff, n & 0xff, (ppq >> 8) & 0xff, ppq & 0xff);
    let total = cab.length;
    for (const p of pistas) total += 8 + p.length;
    const out = new Uint8Array(total);
    out.set(cab, 0);
    let pos = cab.length;
    for (const p of pistas) {
      out.set([0x4d, 0x54, 0x72, 0x6b, (p.length >>> 24) & 0xff, (p.length >> 16) & 0xff, (p.length >> 8) & 0xff, p.length & 0xff], pos);
      out.set(p, pos + 8);
      pos += 8 + p.length;
    }
    return out;
  }

  const API = { leer: leer, escribir: escribir, TEMPO_POR_OMISION: TEMPO_POR_OMISION };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.MidiArchivo = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
