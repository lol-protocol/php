/*
 * Procesamiento de señal de los instrumentos propios: filtros, síntesis aditiva,
 * Karplus-Strong, las recetas de cada instrumento y de la batería, y la respuesta al
 * impulso de la reverberación. Funciones puras (navegador y Node): no tocan Web Audio,
 * salvo `impulso`, que recibe un contexto para crear su AudioBuffer.
 *
 * Las usa sintetizador.js, que es el que las toca; aquí solo se calculan.
 */
(function (raiz) {
  'use strict';

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
      const y = 0.5 * (actual + ant) * perdida;
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
          const a = Math.pow(k, -1.05) * (0.25 + Math.abs(Math.sin((Math.PI * k) / 7.3)));
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


  const API = { frecuencia: frecuencia, receta: receta, PRERENDER: PRERENDER, biquad: biquad, normalizar: normalizar, ruido: ruido, aditivo: aditivo, karplus: karplus, renderizar: renderizar, renderizarBateria: renderizarBateria, impulso: impulso };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Dsp = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
