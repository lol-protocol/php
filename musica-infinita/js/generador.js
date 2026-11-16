/*
 * Generador de música infinita a partir de canciones analizadas (analisis.js).
 *
 * Funciones puras y deterministas (misma semilla, misma música): no toca Web Audio. Entrega
 * eventos de nota con su tiempo en segundos; quien los toca es sintetizador.js.
 *
 * Cómo compone:
 *   - La música avanza en secciones (de 4 a 6 bloques de 2 a 4 compases), cada una con su
 *     tonalidad, su tempo, su estilo (instrumentos y patrones) y una forma (A A' B A, ...).
 *   - Cada bloque nuevo es una cita o una frase libre. Una cita toma una frase real de una
 *     de las canciones, con sus acordes, y la lleva a la tonalidad de la sección (en grados,
 *     así suena igual de reconocible); según la «variación», la adorna o le cambia el ritmo.
 *     Una frase libre sale de una cadena de Markov (de orden 1 a 3) entrenada con los
 *     intervalos y ritmos de todas las melodías, sobre una progresión que sale de otra cadena
 *     entrenada con los acordes.
 *   - La «fidelidad» decide cuántos bloques son citas: 1 suena casi a las canciones, 0 es
 *     música nueva con su estilo.
 *   - Entre secciones modula (a la relativa, a la dominante, un tono arriba...), cambia el
 *     tempo de a poco y, si el estilo es automático, de instrumentos.
 */
(function (raiz) {
  'use strict';

  const enNode = typeof module !== 'undefined' && module.exports;
  const T = enNode ? require('./teoria.js') : raiz.Teoria;

  // ------------------------------------------------------------ azar con semilla

  function crearAzar(semilla) {
    let s = semilla >>> 0 || 1;
    function sig() {
      // mulberry32
      s = (s + 0x6d2b79f5) >>> 0;
      let t = s;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    }
    return {
      num: sig,
      entre: function (a, b) {
        return a + (b - a) * sig();
      },
      entero: function (a, b) {
        return a + Math.floor(sig() * (b - a + 1));
      },
      prob: function (p) {
        return sig() < p;
      },
      elegir: function (arr) {
        return arr[Math.floor(sig() * arr.length)];
      },
      /** Elige una clave de un Map o un objeto {clave: peso}. */
      ponderado: function (pesos) {
        const pares = pesos instanceof Map ? Array.from(pesos.entries()) : Object.entries(pesos);
        let total = 0;
        for (const p of pares) total += p[1];
        let r = sig() * total;
        for (const p of pares) {
          r -= p[1];
          if (r <= 0) return p[0];
        }
        return pares.length ? pares[pares.length - 1][0] : null;
      },
    };
  }

  function semillaDeTexto(s) {
    let h = 2166136261;
    for (let i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return h >>> 0;
  }

  // ------------------------------------------------------------ estilos

  // Programas General MIDI: el sintetizador interno los agrupa por familia, y con una
  // SoundFont suena el instrumento real.
  const ESTILOS = {
    ambiente: {
      nombre: 'Ambiente',
      melodia: [73, 11, 0, 52, 89],
      acordes: [{ programa: 89, patron: 'sostenido' }, { programa: 88, patron: 'sostenido' }, { programa: 95, patron: 'sostenido' }],
      bajo: [{ programa: 38, patron: 'redonda' }, { programa: 39, patron: 'redonda' }],
      arpegio: [{ programa: 8, patron: 'lento' }, { programa: 11, patron: 'lento' }, null],
      bateria: null,
      tempo: 0.82,
      septimas: 0.6,
    },
    pop: {
      nombre: 'Pop',
      melodia: [0, 4, 80, 25, 73],
      acordes: [{ programa: 4, patron: 'contratiempo' }, { programa: 0, patron: 'pulsos' }, { programa: 48, patron: 'sostenido' }],
      bajo: [{ programa: 33, patron: 'raiz-quinta' }, { programa: 38, patron: 'corcheas' }],
      arpegio: [null, { programa: 25, patron: 'corcheas' }],
      bateria: 'pop',
      tempo: 1.05,
      septimas: 0.2,
    },
    orquestal: {
      nombre: 'Orquestal',
      melodia: [40, 73, 68, 71, 56],
      acordes: [{ programa: 48, patron: 'sostenido' }, { programa: 49, patron: 'sostenido' }],
      bajo: [{ programa: 43, patron: 'blancas' }, { programa: 42, patron: 'blancas' }],
      arpegio: [{ programa: 46, patron: 'arpa' }, { programa: 45, patron: 'pulsos-arpegio' }, null],
      bateria: null,
      tempo: 0.92,
      septimas: 0.1,
    },
    chip: {
      nombre: 'Chiptune',
      melodia: [80, 81],
      acordes: [{ programa: 80, patron: 'arpegio-rapido' }],
      bajo: [{ programa: 38, patron: 'corcheas' }],
      arpegio: [null],
      bateria: 'chip',
      tempo: 1.15,
      septimas: 0,
    },
    lofi: {
      nombre: 'Lo-fi',
      melodia: [4, 11, 0, 26],
      acordes: [{ programa: 4, patron: 'perezoso' }, { programa: 0, patron: 'perezoso' }],
      bajo: [{ programa: 33, patron: 'raiz-quinta' }],
      arpegio: [null, { programa: 11, patron: 'lento' }],
      bateria: 'lofi',
      tempo: 0.8,
      septimas: 0.9,
      swing: 0.55,
    },
    cajita: {
      nombre: 'Cajita de música',
      melodia: [10, 8, 9],
      acordes: [{ programa: 89, patron: 'sostenido' }],
      bajo: [{ programa: 10, patron: 'redonda-alta' }],
      arpegio: [{ programa: 10, patron: 'lento' }, { programa: 46, patron: 'arpa' }],
      bateria: null,
      tempo: 0.9,
      septimas: 0.15,
    },
  };

  // Patrones de batería: 16 pasos por compás de 4/4 (12 en compases de 3).
  const BATERIAS = {
    pop: {
      4: { 36: 'x.......x.x.....', 38: '....x.......x...', 42: 'x.x.x.x.x.x.x.x.', 46: '..............x.' },
      3: { 36: 'x...........', 38: '....x...x...', 42: 'x.x.x.x.x.x.' },
    },
    lofi: {
      4: { 36: 'x......x..x.....', 38: '....x.......x...', 42: 'x.x.x.x.x.x.x.xx' },
      3: { 36: 'x.....x.....', 38: '....x.......', 42: 'x.x.x.x.x.x.' },
    },
    chip: {
      4: { 36: 'x...x...x...x...', 38: '....x.......x...', 42: '..x...x...x...x.' },
      3: { 36: 'x...........', 38: '....x...x...', 42: '..x...x...x.' },
    },
  };
  const VEL_BATERIA = { 36: 100, 38: 88, 42: 58, 46: 64, 49: 96 };

  const CANAL = { melodia: 0, acordes: 1, bajo: 2, arpegio: 3, bateria: 9 };

  // Progresiones por omisión si no hay acordes aprendidos (raíz relativa : tipo).
  const PROGRESIONES = {
    mayor: [['0:M', '7:M', '9:m', '5:M'], ['0:M', '5:M', '7:M', '0:M'], ['0:M', '9:m', '5:M', '7:M'], ['5:M', '7:M', '4:m', '9:m']],
    menor: [['0:m', '8:M', '3:M', '10:M'], ['0:m', '5:m', '7:M', '0:m'], ['0:m', '10:M', '8:M', '7:M']],
  };

  // Rango de la melodía en grados (0 = tónica de la octava 4).
  const GRADO_MIN = -4;
  const GRADO_MAX = 11;

  // ------------------------------------------------------------ modelos aprendidos

  function claveToken(dg, ioi) {
    return dg + '|' + Math.round(ioi * 1000) / 1000;
  }

  function leerToken(t) {
    const i = t.indexOf('|');
    return { dg: Number(t.slice(0, i)), ioi: Number(t.slice(i + 1)) };
  }

  function sumar(mapa, clave, valor, peso) {
    let m = mapa.get(clave);
    if (!m) {
      m = new Map();
      mapa.set(clave, m);
    }
    m.set(valor, (m.get(valor) || 0) + peso);
  }

  function entrenar(canciones) {
    const melodico = new Map(); // contexto -> Map(token -> peso)
    const armonico = { mayor: new Map(), menor: new Map() }; // contexto -> Map(acorde -> peso)
    const ritmoArmonico = new Map(); // pulsos -> peso
    for (const c of canciones) {
      const peso = c.peso == null ? 1 : c.peso;
      if (peso <= 0) continue;
      for (const f of c.frases) {
        const hist = ['^'];
        for (let i = f.desde; i < f.hasta; i++) {
          const m = c.melodia[i];
          const dg = i === f.desde ? 0 : Math.max(-9, Math.min(9, m.g - c.melodia[i - 1].g));
          const tok = i === f.desde ? claveToken('i' + T.mod(m.g, 7), m.ioi) : claveToken(dg, m.ioi);
          for (let o = 1; o <= 3 && o <= hist.length; o++) {
            sumar(melodico, hist.slice(-o).join(','), tok, peso);
          }
          hist.push(tok);
        }
      }
      const cad = armonico[c.modo === 'menor' ? 'menor' : 'mayor'];
      const hist = ['^'];
      for (const a of c.acordes) {
        const tok = a.raizRel + ':' + simplificarTipo(a.tipo);
        if (hist[hist.length - 1] === tok) continue;
        for (let o = 1; o <= 2 && o <= hist.length; o++) sumar(cad, hist.slice(-o).join(','), tok, peso);
        hist.push(tok);
        const p = Math.min(8, a.pulsos);
        ritmoArmonico.set(p, (ritmoArmonico.get(p) || 0) + peso);
      }
    }
    return { melodico: melodico, armonico: armonico, ritmoArmonico: ritmoArmonico };
  }

  function simplificarTipo(t) {
    return t === 'maj7' ? 'M' : t === 'm7' ? 'm' : t === 'aug' ? 'M' : t;
  }

  // ------------------------------------------------------------ el generador

  function Generador(opciones) {
    opciones = opciones || {};
    this.semilla = opciones.semilla == null ? Date.now() >>> 0 : typeof opciones.semilla === 'string' ? semillaDeTexto(opciones.semilla) : opciones.semilla >>> 0;
    this.azar = crearAzar(this.semilla);
    this.fidelidad = opciones.fidelidad == null ? 0.6 : opciones.fidelidad;
    this.variacion = opciones.variacion == null ? 0.4 : opciones.variacion;
    this.estiloFijo = opciones.estilo && opciones.estilo !== 'auto' ? opciones.estilo : null;
    this.tempoFactor = opciones.tempo || 1;
    this.humanizar = opciones.humanizar == null ? true : opciones.humanizar;
    this.cola = [];
    this.t = opciones.inicio || 0; // segundos: fin de lo ya generado
    this.seccion = null;
    this.numSeccion = 0;
    this.voicing = null;
    this.ultimoTono = null;
    this.ponerCanciones(opciones.canciones || []);
  }

  Generador.prototype.ponerCanciones = function (canciones) {
    this.canciones = canciones.filter(function (c) {
      return c && c.melodia && c.melodia.length && c.frases && c.frases.length;
    });
    this.modelo = entrenar(this.canciones);
  };

  Generador.prototype.configurar = function (o) {
    if (o.fidelidad != null) this.fidelidad = o.fidelidad;
    if (o.variacion != null) this.variacion = o.variacion;
    if (o.estilo !== undefined) this.estiloFijo = o.estilo && o.estilo !== 'auto' ? o.estilo : null;
    if (o.tempo != null) this.tempoFactor = o.tempo;
    if (o.canciones) this.ponerCanciones(o.canciones);
  };

  /** Eventos con inicio antes de tHasta (segundos), en orden. Genera lo que haga falta. */
  Generador.prototype.eventosHasta = function (tHasta) {
    let guarda = 0;
    while (this.t < tHasta + 0.5 && guarda++ < 64) this.generarBloque();
    this.cola.sort(function (a, b) {
      return a.t - b.t;
    });
    let n = 0;
    while (n < this.cola.length && this.cola[n].t < tHasta) n++;
    return this.cola.splice(0, n);
  };

  /** Corta lo que venga después de tCorte y empieza una sección nueva ahí. */
  Generador.prototype.saltar = function (tCorte) {
    this.cola = this.cola.filter(function (e) {
      return e.t < tCorte;
    });
    for (const e of this.cola) {
      if (e.t + e.dur > tCorte) e.dur = Math.max(0.05, tCorte - e.t);
    }
    this.t = tCorte;
    this.seccion = null;
  };

  Generador.prototype.nuevaSeccion = function () {
    const az = this.azar;
    const previa = this.seccion;
    this.numSeccion++;

    // ¿De qué canción se ancla? (su modo, compás y tempo guían la sección)
    const ancla = this.canciones.length ? this.elegirCancion(previa && previa.ancla) : null;

    // tonalidad: la primera, la de la canción; después, modula de a poco
    let tonica;
    let modo = ancla ? ancla.modo : 'mayor';
    if (!previa) {
      tonica = ancla ? ancla.tonica : 0;
    } else {
      const r = az.num();
      tonica = previa.tonica;
      if (r < 0.35) {
        // misma tónica
      } else if (r < 0.5) {
        // relativa
        if (previa.modo === 'mayor') {
          tonica = previa.tonica + 9;
          modo = 'menor';
        } else {
          tonica = previa.tonica + 3;
          modo = 'mayor';
        }
      } else if (r < 0.65) tonica = previa.tonica + 7;
      else if (r < 0.8) tonica = previa.tonica + 5;
      else if (r < 0.93) tonica = previa.tonica + 2;
      else tonica = previa.tonica + 1;
      tonica = T.mod(tonica, 12);
    }

    let estilo = this.estiloFijo;
    if (!estilo) {
      const nombres = Object.keys(ESTILOS);
      estilo = previa && az.prob(0.6) ? previa.estilo : az.elegir(nombres);
    }
    const E = ESTILOS[estilo] || ESTILOS.ambiente;

    const bpmBase = ancla ? ancla.bpm : 96;
    let bpm = Math.max(56, Math.min(150, bpmBase * E.tempo * az.entre(0.94, 1.06)));
    if (previa) bpm = previa.bpm + (bpm - previa.bpm) * 0.5; // sin saltos bruscos
    bpm *= this.tempoFactor;

    const pulsosCompas = ancla && ancla.pulsosCompas === 3 ? 3 : 4;

    const arpegio = az.elegir(E.arpegio);
    const sec = {
      numero: this.numSeccion,
      inicio: this.t,
      tonica: tonica,
      modo: modo,
      estilo: estilo,
      bpm: Math.round(bpm * 10) / 10,
      pulsosCompas: pulsosCompas,
      ancla: ancla,
      voces: {
        melodia: az.elegir(E.melodia),
        acordes: az.elegir(E.acordes),
        bajo: az.elegir(E.bajo),
        arpegio: arpegio,
        bateria: E.bateria,
      },
      septimas: E.septimas,
      swing: E.swing || 0,
      forma: az.elegir([['A', 'A2', 'B', 'A'], ['A', 'B', 'A', 'B2'], ['A', 'B', 'C', 'A'], ['A', 'A2', 'B', 'B2', 'A'], ['A', 'B', 'A2', 'C', 'A']]),
      materiales: {},
      paso: 0,
      siguienteFrase: null,
    };
    this.seccion = sec;
    return sec;
  };

  Generador.prototype.elegirCancion = function (previa) {
    const az = this.azar;
    if (previa && this.canciones.indexOf(previa) >= 0 && az.prob(0.45)) return previa;
    const pesos = new Map();
    for (const c of this.canciones) pesos.set(c, c.peso == null ? 1 : c.peso);
    return az.ponderado(pesos) || this.canciones[0];
  };

  Generador.prototype.generarBloque = function () {
    if (!this.seccion || this.seccion.paso >= this.seccion.forma.length) this.nuevaSeccion();
    const sec = this.seccion;
    const etiqueta = sec.forma[sec.paso];
    const base = etiqueta.charAt(0);
    const esUltimo = sec.paso === sec.forma.length - 1;
    let mat = sec.materiales[etiqueta];
    if (!mat) {
      if (etiqueta.length > 1 && sec.materiales[base]) {
        mat = this.variar(sec.materiales[base], Math.max(0.35, this.variacion), sec);
      } else {
        mat = this.azar.prob(this.fidelidad) && this.canciones.length ? this.cita(sec) : this.fraseLibre(sec);
      }
      sec.materiales[etiqueta] = mat;
    }
    sec.paso++;
    this.realizar(mat, sec, esUltimo);
  };

  // ------------------------------------------------------------ materiales

  /** Una frase real de una canción, con sus acordes, en grados y pulsos relativos. */
  Generador.prototype.cita = function (sec) {
    const az = this.azar;
    let c = sec.ancla && az.prob(0.75) ? sec.ancla : az.elegir(this.canciones);
    // seguir la canción en orden la hace reconocible; a veces salta a otra frase
    let fi;
    if (sec.siguienteFrase && sec.siguienteFrase.cancion === c && az.prob(0.7)) fi = sec.siguienteFrase.indice;
    else fi = az.entero(0, c.frases.length - 1);
    fi = fi % c.frases.length;
    sec.siguienteFrase = { cancion: c, indice: fi + 1 };
    const f = c.frases[fi];
    const pc = sec.pulsosCompas;
    const pcOrig = c.pulsosCompas || 4;
    // conserva la posición dentro del compás (anacrusas)
    const desfase = T.mod(f.inicio, pcOrig) % pc;
    const inicioBloque = f.inicio - desfase;
    let pulsos = Math.ceil((desfase + f.pulsos) / pc) * pc;
    pulsos = Math.max(2 * pc, Math.min(6 * pc, pulsos));
    const notas = [];
    for (let i = f.desde; i < f.hasta; i++) {
      const m = c.melodia[i];
      const ini = m.inicio - inicioBloque;
      if (ini >= pulsos) break;
      notas.push({ inicio: ini, dur: Math.min(m.dur, pulsos - ini), g: m.g, alt: m.alt, vel: m.vel || 90 });
    }
    // centra la frase en el rango
    centrar(notas);
    const acordes = [];
    for (const a of c.acordes) {
      const ini = Math.max(a.inicio, inicioBloque);
      const fin = Math.min(a.inicio + a.pulsos, inicioBloque + pulsos);
      if (fin <= ini) continue;
      acordes.push({ inicio: ini - inicioBloque, pulsos: fin - ini, raizRel: a.raizRel, tipo: simplificarTipo(a.tipo) });
    }
    if (!acordes.length || acordes[0].inicio > 0) {
      acordes.unshift({ inicio: 0, pulsos: acordes.length ? acordes[0].inicio : pulsos, raizRel: 0, tipo: sec.modo === 'menor' ? 'm' : 'M' });
    }
    let mat = { tipo: 'cita', cancion: c.nombre, frase: fi + 1, pulsos: pulsos, notas: notas, acordes: acordes };
    if (this.variacion > 0 && az.prob(this.variacion)) mat = this.variar(mat, this.variacion * 0.6, sec);
    return mat;
  };

  /** Una frase nueva: progresión de la cadena armónica y melodía de la melódica. */
  Generador.prototype.fraseLibre = function (sec) {
    const az = this.azar;
    const pc = sec.pulsosCompas;
    const compases = az.prob(0.75) ? 4 : 2;
    const pulsos = compases * pc;
    const acordes = this.progresion(sec, pulsos);
    const notas = this.melodiaMarkov(sec, pulsos, acordes);
    return { tipo: 'libre', pulsos: pulsos, notas: notas, acordes: acordes };
  };

  Generador.prototype.progresion = function (sec, pulsos) {
    const az = this.azar;
    const cad = this.modelo.armonico[sec.modo] && this.modelo.armonico[sec.modo].size ? this.modelo.armonico[sec.modo] : this.modelo.armonico[sec.modo === 'mayor' ? 'menor' : 'mayor'];
    const pc = sec.pulsosCompas;
    // ritmo armónico: un acorde por compás, o dos
    const porCompas = pc === 4 && az.prob(0.35) ? 2 : 1;
    const dur = pc / porCompas;
    const n = Math.round(pulsos / dur);
    let toks = [];
    if (cad && cad.size > 2 && az.prob(0.85)) {
      const hist = ['^'];
      for (let i = 0; i < n; i++) {
        let opciones = null;
        for (let o = 2; o >= 1 && !opciones; o--) {
          if (hist.length >= o) opciones = cad.get(hist.slice(-o).join(','));
        }
        if (!opciones) opciones = cad.get('^');
        const t = az.ponderado(opciones);
        toks.push(t);
        hist.push(t);
      }
    } else {
      const prog = az.elegir(PROGRESIONES[sec.modo] || PROGRESIONES.mayor);
      for (let i = 0; i < n; i++) toks.push(prog[i % prog.length]);
    }
    // cadencia: el último acorde tiende a la dominante o a la tónica
    if (az.prob(0.5)) toks[n - 1] = az.prob(0.5) ? '7:M' : sec.modo === 'menor' ? '0:m' : '0:M';
    return toks.map(function (t, i) {
      const p = t.split(':');
      return { inicio: i * dur, pulsos: dur, raizRel: Number(p[0]), tipo: p[1] };
    });
  };

  Generador.prototype.melodiaMarkov = function (sec, pulsos, acordes) {
    const az = this.azar;
    const mel = this.modelo.melodico;
    const notas = [];
    let t = 0;
    let g = null;
    const hist = ['^'];
    let guarda = 0;
    while (t < pulsos - 0.2 && guarda++ < 200) {
      let tok = null;
      for (let o = 3; o >= 1 && !tok; o--) {
        if (hist.length < o) continue;
        const ops = mel.get(hist.slice(-o).join(','));
        if (ops && ops.size) {
          for (let intento = 0; intento < 6; intento++) {
            const cand = az.ponderado(ops);
            const lt = leerToken(cand);
            const ng = g === null ? null : g + (isNaN(lt.dg) ? 0 : lt.dg);
            if (ng === null || (ng >= GRADO_MIN && ng <= GRADO_MAX)) {
              tok = cand;
              break;
            }
          }
        }
      }
      let dg;
      let ioi;
      if (tok) {
        const lt = leerToken(tok);
        ioi = lt.ioi;
        if (tok.charAt(0) === 'i') {
          dg = null;
          g = g === null ? Number(tok.slice(1, tok.indexOf('|'))) : g;
        } else dg = lt.dg;
      } else {
        // sin modelo: paso a paso por la escala
        dg = az.elegir([-2, -1, -1, 0, 1, 1, 2, 3, -3]);
        ioi = az.elegir([0.5, 1, 1, 1, 2]);
      }
      if (g === null) g = notaDeAcordeCercana(az.elegir([2, 4, 4, 7]), acordes[0], sec);
      else if (dg != null) g += dg;
      if (g < GRADO_MIN) g += 7;
      if (g > GRADO_MAX) g -= 7;
      if (t + ioi > pulsos) ioi = pulsos - t;
      // en tiempo fuerte, a una nota del acorde
      const ac = acordeEnLista(acordes, t);
      if (ac && esTiempoFuerte(t, sec.pulsosCompas) && az.prob(0.75)) g = notaDeAcordeCercana(g, ac, sec);
      notas.push({ inicio: t, dur: ioi > 1.5 ? ioi * 0.9 : ioi, g: g, alt: 0, vel: 88 });
      hist.push(tok || claveToken(dg || 0, ioi));
      t += ioi;
    }
    // cierre: la última nota, larga y del acorde
    if (notas.length) {
      const ult = notas[notas.length - 1];
      const ac = acordeEnLista(acordes, ult.inicio);
      if (ac) ult.g = notaDeAcordeCercana(ult.g, ac, sec);
      ult.dur = Math.max(ult.dur, pulsos - ult.inicio);
    }
    return notas;
  };

  /** Variación de un material: adornos, ritmo con puntillo, octava, final distinto. */
  Generador.prototype.variar = function (mat, intensidad, sec) {
    const az = this.azar;
    let notas = mat.notas.map(function (n) {
      return Object.assign({}, n);
    });
    const ops = [];
    if (az.prob(intensidad)) ops.push('adornos');
    if (az.prob(intensidad * 0.7)) ops.push('puntillo');
    if (az.prob(intensidad * 0.3)) ops.push('octava');
    if (az.prob(intensidad * 0.5)) ops.push('final');
    if (az.prob(intensidad * 0.15)) ops.push('espejo');
    if (!ops.length) ops.push('final');
    for (const op of ops) {
      if (op === 'adornos') {
        const out = [];
        for (let i = 0; i < notas.length; i++) {
          const n = notas[i];
          const s = notas[i + 1];
          out.push(n);
          if (s && Math.abs(s.g - n.g) === 2 && n.dur >= 1 && s.inicio - n.inicio >= 1 && az.prob(0.6)) {
            const mitad = (s.inicio - n.inicio) / 2;
            n.dur = mitad;
            out.push({ inicio: n.inicio + mitad, dur: mitad, g: (n.g + s.g) / 2, alt: 0, vel: n.vel * 0.85 });
          } else if (s && Math.abs(s.g - n.g) === 0 && n.dur >= 1 && az.prob(0.4)) {
            const mitad = (s.inicio - n.inicio) / 2;
            n.dur = mitad;
            out.push({ inicio: n.inicio + mitad, dur: mitad, g: n.g + 1, alt: 0, vel: n.vel * 0.8 });
          }
        }
        notas = out;
      } else if (op === 'puntillo') {
        for (let i = 0; i + 1 < notas.length; i++) {
          const a = notas[i];
          const b = notas[i + 1];
          const ioi = b.inicio - a.inicio;
          if (Math.abs(ioi - 1) < 1e-6 && Math.abs(a.dur - 1) < 0.1 && T.mod(a.inicio, 1) === 0 && az.prob(0.5)) {
            b.inicio = a.inicio + 0.75;
            a.dur = 0.75;
            b.dur += 0.25;
            i++;
          }
        }
      } else if (op === 'octava') {
        const sube = az.prob(0.5) ? 7 : -7;
        if (notas.every(function (n) { return n.g + sube >= GRADO_MIN && n.g + sube <= GRADO_MAX + 3; })) {
          for (const n of notas) n.g += sube;
        }
      } else if (op === 'final' && notas.length) {
        const ult = notas[notas.length - 1];
        ult.g = Math.round(ult.g / 7) * 7 + (az.prob(0.5) ? 0 : 4);
        ult.alt = 0;
      } else if (op === 'espejo' && notas.length > 2) {
        const eje = notas[0].g;
        for (const n of notas) {
          n.g = eje - (n.g - eje);
          n.alt = -n.alt;
        }
        centrar(notas);
      }
    }
    for (const n of notas) n.g = Math.round(n.g);
    return Object.assign({}, mat, { notas: notas, variado: true });
  };

  // ------------------------------------------------------------ arreglo

  /** Convierte un material en eventos de las cinco voces y los pone en la cola. */
  Generador.prototype.realizar = function (mat, sec, esUltimo) {
    const az = this.azar;
    const seg = 60 / sec.bpm;
    const t0 = this.t;
    const self = this;
    const swing = sec.swing;
    function tiempo(p) {
      // swing: las corcheas a contratiempo se atrasan
      if (swing && Math.abs(T.mod(p, 1) - 0.5) < 1e-6) p += swing * (1 / 6);
      return t0 + p * seg;
    }
    function hum(x) {
      return self.humanizar ? x + az.entre(-0.008, 0.008) : x;
    }
    function velo(v) {
      v = self.humanizar ? v + az.entre(-6, 6) : v;
      return Math.max(1, Math.min(127, Math.round(v)));
    }
    function emitir(voz, programa, nota, p, durP, vel) {
      self.cola.push({
        t: Math.max(t0, hum(tiempo(p))),
        dur: Math.max(0.04, durP * seg),
        nota: nota,
        vel: velo(vel),
        voz: voz,
        canal: CANAL[voz],
        programa: programa,
      });
    }

    // información para la pantalla
    this.cola.push({
      t: t0,
      dur: mat.pulsos * seg,
      tipo: 'info',
      seccion: sec.numero,
      estilo: ESTILOS[sec.estilo].nombre,
      tonalidad: T.nombreTonalidad(sec.tonica, sec.modo),
      bpm: sec.bpm,
      material: mat.tipo,
      cancion: mat.cancion || null,
      frase: mat.frase || null,
      variado: !!mat.variado,
      acordes: mat.acordes.map(function (a) { return T.nombreAcorde(sec.tonica + a.raizRel, a.tipo); }),
    });

    // melodía
    const pm = sec.voces.melodia;
    const n = mat.notas.length;
    for (let i = 0; i < n; i++) {
      const m = mat.notas[i];
      let nota = T.notaDeGrado(m.g, m.alt, sec.tonica, sec.modo);
      // en menor, la sensible sube en las cadencias que van a la dominante
      const arco = 0.85 + 0.25 * Math.sin((Math.PI * i) / Math.max(1, n - 1));
      const acento = esTiempoFuerte(m.inicio, sec.pulsosCompas) ? 8 : 0;
      emitir('melodia', pm, nota, m.inicio, m.dur, Math.min(118, (m.vel || 90) * arco + acento));
    }

    // armonía
    const pc = sec.pulsosCompas;
    for (const a of mat.acordes) {
      let tipo = a.tipo;
      if (sec.septimas && az.prob(sec.septimas)) tipo = tipo === 'M' ? (a.raizRel === 7 ? '7' : 'maj7') : tipo === 'm' ? 'm7' : tipo;
      const raizPc = T.mod(sec.tonica + a.raizRel, 12);
      const pcs = T.notasAcorde(raizPc, tipo);
      this.voicing = conducir(this.voicing, pcs);
      const v = this.voicing;
      const ac = sec.voces.acordes;
      if (ac) patronAcordes(ac, v, a, pc, emitir);
      const bj = sec.voces.bajo;
      if (bj) patronBajo(bj, raizPc, pcs, a, pc, emitir);
      const ar = sec.voces.arpegio;
      if (ar) patronArpegio(ar, v, a, pc, emitir);
    }

    // batería
    if (sec.voces.bateria) {
      const pat = BATERIAS[sec.voces.bateria][pc === 3 ? 3 : 4];
      const pasos = pc === 3 ? 12 : 16;
      const compases = Math.round(mat.pulsos / pc);
      const suave = sec.voces.bateria === 'lofi' ? 0.7 : 1;
      for (let k = 0; k < compases; k++) {
        const relleno = esUltimo && k === compases - 1;
        for (const nota in pat) {
          const s = pat[nota];
          for (let i = 0; i < pasos; i++) {
            if (relleno && i >= pasos / 2 && nota !== '36') continue;
            if (s.charAt(i) !== 'x') continue;
            emitir('bateria', 0, Number(nota), k * pc + i / 4, 0.25, VEL_BATERIA[nota] * suave * (i % 4 === 0 ? 1 : 0.85));
          }
        }
        if (relleno) {
          for (let i = pasos / 2; i < pasos; i++) {
            emitir('bateria', 0, i < pasos - 4 ? 38 : az.elegir([45, 47, 50]), k * pc + i / 4, 0.25, (60 + (i - pasos / 2) * 6) * suave);
          }
        }
        if (k === 0 && sec.paso === 1) emitir('bateria', 0, 49, 0, 1, VEL_BATERIA[49] * suave);
      }
    }

    this.t = t0 + mat.pulsos * seg;
  };

  function patronAcordes(conf, v, a, pc, emitir) {
    const p = conf.programa;
    const vel = 62;
    switch (conf.patron) {
      case 'contratiempo':
        for (let b = 0; b < a.pulsos; b += 1) {
          for (const n of v) emitir('acordes', p, n, a.inicio + b + 0.5, 0.4, vel);
        }
        break;
      case 'pulsos':
        for (let b = 0; b < a.pulsos; b += 1) {
          for (const n of v) emitir('acordes', p, n, a.inicio + b, 0.9, b === 0 ? vel + 6 : vel - 6);
        }
        break;
      case 'perezoso':
        // el acorde se toca un poco tarde, arpegiado hacia arriba
        v.forEach(function (n, i) {
          emitir('acordes', p, n, a.inicio + 0.06 * i + 0.04, a.pulsos - 0.1, vel - 4);
        });
        break;
      case 'arpegio-rapido':
        for (let b = 0, i = 0; b < a.pulsos - 1e-6; b += 0.25, i++) {
          emitir('acordes', p, v[i % v.length] + 12, a.inicio + b, 0.22, vel - 10);
        }
        break;
      default: // sostenido
        for (const n of v) emitir('acordes', p, n, a.inicio, a.pulsos, vel - 8);
    }
  }

  function patronBajo(conf, raizPc, pcs, a, pc, emitir) {
    const p = conf.programa;
    const r = 36 + raizPc; // do2..si2
    const quinta = r + 7;
    switch (conf.patron) {
      case 'raiz-quinta':
        for (let b = 0; b < a.pulsos; b += 2) emitir('bajo', p, b % 4 === 0 ? r : quinta > 52 ? quinta - 12 : quinta, a.inicio + b, Math.min(1.8, a.pulsos - b), 92);
        break;
      case 'corcheas':
        for (let b = 0; b < a.pulsos - 1e-6; b += 0.5) emitir('bajo', p, b % 2 === 1.5 ? r + 12 : r, a.inicio + b, 0.45, b % 1 === 0 ? 92 : 76);
        break;
      case 'blancas':
        for (let b = 0; b < a.pulsos; b += pc === 3 ? 3 : 2) emitir('bajo', p, b === 0 ? r : quinta - 12 >= 28 ? quinta - 12 : quinta, a.inicio + b, Math.min(pc === 3 ? 3 : 2, a.pulsos - b), 80);
        break;
      case 'redonda-alta':
        emitir('bajo', p, r + 24, a.inicio, a.pulsos, 70);
        break;
      default: // redonda
        emitir('bajo', p, r, a.inicio, a.pulsos, 84);
    }
  }

  function patronArpegio(conf, v, a, pc, emitir) {
    const p = conf.programa;
    const notas = v.concat(v.map(function (n) { return n + 12; }));
    switch (conf.patron) {
      case 'corcheas':
        for (let b = 0, i = 0; b < a.pulsos - 1e-6; b += 0.5, i++) emitir('arpegio', p, notas[i % notas.length], a.inicio + b, 0.45, 54);
        break;
      case 'arpa': {
        const sube = notas.concat(notas.slice(1, -1).reverse());
        for (let b = 0, i = 0; b < a.pulsos - 1e-6; b += 0.5, i++) emitir('arpegio', p, sube[i % sube.length], a.inicio + b, 1.2, 56);
        break;
      }
      case 'pulsos-arpegio':
        for (let b = 0; b < a.pulsos; b += 1) emitir('arpegio', p, notas[(b * 2) % notas.length], a.inicio + b, 0.5, 50);
        break;
      default: // lento
        for (let b = 0, i = 0; b < a.pulsos - 1e-6; b += 1, i++) emitir('arpegio', p, notas[(i * 2 + 1) % notas.length] + 12, a.inicio + b, 1.8, 44);
    }
  }

  // ------------------------------------------------------------ utilidades musicales

  function esTiempoFuerte(p, pc) {
    if (Math.abs(p - Math.round(p)) > 1e-6) return false;
    const b = T.mod(Math.round(p), pc);
    return b === 0 || (pc === 4 && b === 2);
  }

  function acordeEnLista(acordes, p) {
    let r = null;
    for (const a of acordes) if (a.inicio <= p + 1e-6) r = a;
    return r;
  }

  /** El grado más cercano a g cuya nota pertenece al acorde. */
  function notaDeAcordeCercana(g, ac, sec) {
    const pcs = T.notasAcorde(T.mod(sec.tonica + ac.raizRel, 12), ac.tipo);
    for (let d = 0; d <= 3; d++) {
      for (const s of d === 0 ? [0] : [-d, d]) {
        const cand = g + s;
        if (cand < GRADO_MIN || cand > GRADO_MAX) continue;
        if (pcs.indexOf(T.mod(T.notaDeGrado(cand, 0, sec.tonica, sec.modo), 12)) >= 0) return cand;
      }
    }
    return g;
  }

  function centrar(notas) {
    if (!notas.length) return;
    let s = 0;
    for (const n of notas) s += n.g;
    const media = s / notas.length;
    const objetivo = 4;
    const despl = Math.round((objetivo - media) / 7) * 7;
    if (despl) for (const n of notas) n.g += despl;
  }

  /** Voicing del acorde cerca del anterior (conducción de voces), entre mi3 y sol4. */
  function conducir(previo, pcs) {
    const candidatos = [];
    for (let inv = 0; inv < pcs.length; inv++) {
      const orden = pcs.slice(inv).concat(pcs.slice(0, inv));
      for (const base of [52, 55, 58]) {
        const v = [];
        let n = base;
        for (const pc of orden) {
          while (T.mod(n, 12) !== pc) n++;
          v.push(n);
          n++;
        }
        if (v[v.length - 1] <= 79) candidatos.push(v);
      }
    }
    if (!previo) return candidatos.sort(function (a, b) { return Math.abs(a[0] - 57) - Math.abs(b[0] - 57); })[0];
    let mejor = null;
    let costo = Infinity;
    for (const v of candidatos) {
      let c = 0;
      for (let i = 0; i < v.length; i++) {
        const q = previo[Math.min(i, previo.length - 1)];
        c += Math.abs(v[i] - q);
      }
      c += Math.abs(v[0] - 57) * 0.3; // que no se aleje del centro
      if (c < costo) {
        costo = c;
        mejor = v;
      }
    }
    return mejor;
  }

  /**
   * Toca una canción tal cual (sin generar): los eventos de un MIDI leído, desde t0.
   * Útil para escuchar un MIDI propio con el sintetizador y la visualización.
   */
  function eventosDeMidi(midi, t0) {
    return midi.notas.map(function (n) {
      return {
        t: t0 + n.inicio,
        dur: Math.max(0.03, n.dur),
        nota: n.nota,
        vel: n.vel,
        canal: n.canal,
        voz: n.canal === 9 ? 'bateria' : 'midi',
        programa: n.programa,
      };
    });
  }

  const API = {
    Generador: Generador,
    ESTILOS: ESTILOS,
    crearAzar: crearAzar,
    semillaDeTexto: semillaDeTexto,
    entrenar: entrenar,
    conducir: conducir,
    eventosDeMidi: eventosDeMidi,
    GRADO_MIN: GRADO_MIN,
    GRADO_MAX: GRADO_MAX,
  };

  if (enNode) {
    module.exports = API;
  } else {
    raiz.Generador = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
