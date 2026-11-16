/*
 * Melodías de ejemplo, todas de dominio público (tradicionales o de compositores muertos
 * hace más de 70 años), para que la aplicación suene antes de cargar MIDI propios.
 *
 * Están escritas a mano en una notación corta: "nota:duración" con la duración en pulsos
 * (negras), "R" para un silencio, y "|" como separador de compases (se ignora). Los acordes
 * van aparte con la misma notación ("Am:3"). aMidi() las convierte en un MIDI de tres
 * pistas (melodía, acordes y bajo), así pasan por el mismo análisis que un archivo real.
 *
 * Los MIDI propios de la persona no se guardan en el repositorio: se cargan en la
 * aplicación y quedan en el navegador (IndexedDB).
 */
(function (raiz) {
  'use strict';

  const M = typeof module !== 'undefined' && module.exports ? require('./midi.js') : raiz.MidiArchivo;

  const EJEMPLOS = [
    {
      id: 'oda-alegria',
      nombre: 'Himno de la alegría (Beethoven, 1824)',
      bpm: 104,
      compas: [4, 4],
      programa: 73,
      melodia:
        'E4:1 E4:1 F4:1 G4:1 | G4:1 F4:1 E4:1 D4:1 | C4:1 C4:1 D4:1 E4:1 | E4:1.5 D4:0.5 D4:2 |' +
        'E4:1 E4:1 F4:1 G4:1 | G4:1 F4:1 E4:1 D4:1 | C4:1 C4:1 D4:1 E4:1 | D4:1.5 C4:0.5 C4:2 |' +
        'D4:1 D4:1 E4:1 C4:1 | D4:1 E4:0.5 F4:0.5 E4:1 C4:1 | D4:1 E4:0.5 F4:0.5 E4:1 D4:1 | C4:1 D4:1 G3:2 |' +
        'E4:1 E4:1 F4:1 G4:1 | G4:1 F4:1 E4:1 D4:1 | C4:1 C4:1 D4:1 E4:1 | D4:1.5 C4:0.5 C4:2',
      acordes: 'C:4 G:4 C:4 G:4 C:4 G:4 C:4 G:2 C:2 G:4 G:2 C:2 G:2 C:2 G:4 C:4 G:4 C:4 G:2 C:2',
    },
    {
      id: 'greensleeves',
      nombre: 'Greensleeves (tradicional inglesa, s. XVI)',
      bpm: 100,
      compas: [3, 4],
      programa: 24,
      melodia:
        'A3:1 | C4:2 D4:1 | E4:1.5 F4:0.5 E4:1 | D4:2 B3:1 | G3:1.5 A3:0.5 B3:1 | C4:2 A3:1 | A3:1.5 G#3:0.5 A3:1 | B3:2 G#3:1 | E3:2 A3:1 |' +
        'C4:2 D4:1 | E4:1.5 F4:0.5 E4:1 | D4:2 B3:1 | G3:1.5 A3:0.5 B3:1 | C4:1.5 B3:0.5 A3:1 | G#3:1.5 F#3:0.5 G#3:1 | A3:3 |' +
        'G4:3 | G4:1.5 F#4:0.5 E4:1 | D4:2 B3:1 | G3:1.5 A3:0.5 B3:1 | C4:2 A3:1 | A3:1.5 G#3:0.5 A3:1 | B3:2 G#3:1 | E3:3 |' +
        'G4:3 | G4:1.5 F#4:0.5 E4:1 | D4:2 B3:1 | G3:1.5 A3:0.5 B3:1 | C4:1.5 B3:0.5 A3:1 | G#3:1.5 F#3:0.5 G#3:1 | A3:3',
      acordes:
        'R:1 Am:3 C:3 G:3 Em:3 Am:3 E:3 E:3 Am:3 Am:3 C:3 G:3 Em:3 Am:3 E:3 Am:3 ' +
        'C:3 C:3 G:3 Em:3 Am:3 E:3 E:3 E:3 C:3 C:3 G:3 Em:3 Am:3 E:3 Am:3',
    },
    {
      id: 'para-elisa',
      nombre: 'Para Elisa (Beethoven, 1810)',
      bpm: 72,
      compas: [3, 8],
      programa: 0,
      melodia:
        'E5:0.25 D#5:0.25 | E5:0.25 D#5:0.25 E5:0.25 B4:0.25 D5:0.25 C5:0.25 | A4:0.75 C4:0.25 E4:0.25 A4:0.25 |' +
        'B4:0.75 E4:0.25 G#4:0.25 B4:0.25 | C5:0.75 E4:0.25 E5:0.25 D#5:0.25 |' +
        'E5:0.25 D#5:0.25 E5:0.25 B4:0.25 D5:0.25 C5:0.25 | A4:0.75 C4:0.25 E4:0.25 A4:0.25 |' +
        'B4:0.75 E4:0.25 C5:0.25 B4:0.25 | A4:0.75 B4:0.25 C5:0.25 D5:0.25 |' +
        'E5:0.75 G4:0.25 F5:0.25 E5:0.25 | D5:0.75 F4:0.25 E5:0.25 D5:0.25 | C5:0.75 E4:0.25 D5:0.25 C5:0.25 |' +
        'B4:0.75 E4:0.25 E5:0.25 D#5:0.25 | E5:0.25 D#5:0.25 E5:0.25 B4:0.25 D5:0.25 C5:0.25 |' +
        'A4:0.75 C4:0.25 E4:0.25 A4:0.25 | B4:0.75 E4:0.25 C5:0.25 B4:0.25 | A4:1.5',
      acordes:
        'R:0.5 E:1.5 Am:1.5 E:1.5 Am:1.5 E:1.5 Am:1.5 E:1.5 Am:1.5 C:1.5 G:1.5 Am:1.5 E:1.5 E:1.5 Am:1.5 E:1.5 Am:1.5',
    },
    {
      id: 'frere-jacques',
      nombre: 'Martinillo / Frère Jacques (tradicional francesa)',
      bpm: 112,
      compas: [4, 4],
      programa: 10,
      melodia:
        'F4:1 G4:1 A4:1 F4:1 | F4:1 G4:1 A4:1 F4:1 | A4:1 Bb4:1 C5:2 | A4:1 Bb4:1 C5:2 |' +
        'C5:0.5 D5:0.5 C5:0.5 Bb4:0.5 A4:1 F4:1 | C5:0.5 D5:0.5 C5:0.5 Bb4:0.5 A4:1 F4:1 | F4:1 C4:1 F4:2 | F4:1 C4:1 F4:2',
      acordes: 'F:4 F:4 F:2 C:2 F:2 C:2 F:2 C:2 F:2 C:2 F:1 C:1 F:2 F:1 C:1 F:2',
    },
    {
      id: 'canon-pachelbel',
      nombre: 'Canon en re (Pachelbel, c. 1680)',
      bpm: 66,
      compas: [4, 4],
      programa: 40,
      melodia:
        'F#5:2 E5:2 D5:2 C#5:2 B4:2 A4:2 B4:2 C#5:2 |' +
        'D5:2 C#5:2 B4:2 A4:2 G4:2 F#4:2 G4:2 E4:2 |' +
        'D4:1 F#4:1 A4:1 G4:1 F#4:1 D4:1 F#4:1 E4:1 D4:1 B3:1 D4:1 A4:1 G4:1 B4:1 A4:1 G4:1 |' +
        'F#4:1 D4:1 E4:1 C#5:1 D5:1 F#5:1 A5:1 A4:1 B4:1 G4:1 A4:1 F#4:1 D4:1 D5:1 D5:1.5 C#5:0.5',
      acordes: (function () {
        let s = '';
        for (let i = 0; i < 4; i++) s += 'D:2 A:2 Bm:2 F#m:2 G:2 D:2 G:2 A:2 ';
        return s.trim();
      })(),
    },
    {
      id: 'estrellita',
      nombre: 'Estrellita / Ah! vous dirai-je, maman (tradicional francesa)',
      bpm: 100,
      compas: [4, 4],
      programa: 8,
      melodia:
        'C4:1 C4:1 G4:1 G4:1 | A4:1 A4:1 G4:2 | F4:1 F4:1 E4:1 E4:1 | D4:1 D4:1 C4:2 |' +
        'G4:1 G4:1 F4:1 F4:1 | E4:1 E4:1 D4:2 | G4:1 G4:1 F4:1 F4:1 | E4:1 E4:1 D4:2 |' +
        'C4:1 C4:1 G4:1 G4:1 | A4:1 A4:1 G4:2 | F4:1 F4:1 E4:1 E4:1 | D4:1 D4:1 C4:2',
      acordes:
        'C:4 F:2 C:2 F:2 C:2 G:2 C:2 C:2 F:2 C:2 G:2 C:2 F:2 C:2 G:2 C:4 F:2 C:2 F:2 C:2 G:2 C:2',
    },
    {
      id: 'scarborough',
      nombre: 'Scarborough Fair (tradicional inglesa)',
      bpm: 112,
      compas: [3, 4],
      programa: 46,
      melodia:
        'D4:2 D4:1 | A4:2 A4:1 | E4:1.5 F4:0.5 E4:1 | D4:3 | R:1 A4:1 C5:1 | D5:2 C5:1 | A4:1 B4:1 G4:1 | A4:3 |' +
        'R:2 D5:1 | D5:2 D5:1 | C5:2 A4:1 | A4:1 G4:1 F4:1 | E4:1 C4:2 | D4:2 A4:1 | G4:2 F4:1 | E4:1 D4:1 C4:1 | D4:3',
      acordes: 'Dm:3 Dm:3 C:3 Dm:3 Dm:3 F:3 G:3 Am:3 Dm:3 F:3 F:3 Dm:3 C:3 Dm:3 C:3 C:3 Dm:3',
    },
  ];

  const PC = { C: 0, D: 2, E: 4, F: 5, G: 7, A: 9, B: 11 };

  function alturaDe(nombre) {
    const m = /^([A-G])([#b]?)(-?\d)$/.exec(nombre);
    if (!m) throw new Error('Nota inválida: ' + nombre);
    return 12 * (Number(m[3]) + 1) + PC[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0);
  }

  function leerSecuencia(s) {
    const out = [];
    let t = 0;
    for (const tok of s.replace(/\|/g, ' ').split(/\s+/)) {
      if (!tok) continue;
      const i = tok.lastIndexOf(':');
      const cab = tok.slice(0, i);
      const dur = Number(tok.slice(i + 1));
      if (!(dur > 0)) throw new Error('Duración inválida: ' + tok);
      out.push({ simbolo: cab, inicio: t, dur: dur });
      t += dur;
    }
    return out;
  }

  const TIPOS = { '': [0, 4, 7], m: [0, 3, 7], '7': [0, 4, 7, 10], dim: [0, 3, 6] };

  function acordeDe(simbolo) {
    const m = /^([A-G])([#b]?)(m|7|dim)?$/.exec(simbolo);
    if (!m) throw new Error('Acorde inválido: ' + simbolo);
    const raizPc = (PC[m[1]] + (m[2] === '#' ? 1 : m[2] === 'b' ? -1 : 0) + 12) % 12;
    return { raiz: raizPc, intervalos: TIPOS[m[3] || ''] };
  }

  /** Convierte un ejemplo en un MIDI (Uint8Array) de melodía, acordes y bajo. */
  function aMidi(ej) {
    const melodia = leerSecuencia(ej.melodia)
      .filter(function (n) { return n.simbolo !== 'R'; })
      .map(function (n) {
        return { nota: alturaDe(n.simbolo), inicio: n.inicio, dur: n.dur * 0.95, vel: 96 };
      });
    const acordes = [];
    const bajo = [];
    for (const a of leerSecuencia(ej.acordes)) {
      if (a.simbolo === 'R') continue;
      const ac = acordeDe(a.simbolo);
      for (const iv of ac.intervalos) {
        // voicing cerrado alrededor de sol3..fa4
        let n = 55 + ((ac.raiz + iv - 7 + 12) % 12);
        acordes.push({ nota: n, inicio: a.inicio, dur: a.dur, vel: 58 });
      }
      bajo.push({ nota: 36 + ac.raiz, inicio: a.inicio, dur: a.dur, vel: 70 });
    }
    return M.escribir({
      ppq: 480,
      bpm: ej.bpm,
      compas: ej.compas,
      pistas: [
        { nombre: 'Melodía', canal: 0, programa: ej.programa, notas: melodia },
        { nombre: 'Acordes', canal: 1, programa: 48, notas: acordes },
        { nombre: 'Bajo', canal: 2, programa: 32, notas: bajo },
      ],
    });
  }

  const API = { EJEMPLOS: EJEMPLOS, aMidi: aMidi, alturaDe: alturaDe, leerSecuencia: leerSecuencia };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Ejemplos = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
