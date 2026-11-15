/*
 * Texturas procedurales de los astros. Funciones puras: no tocan el DOM ni WebGL,
 * así que corren igual en el navegador (se cargan como <script>) y en Node (require).
 *
 * Cada textura es un mapa equirectangular RGBA (una imagen de ancho x alto, con la
 * longitud en x y la latitud en y). El ruido se muestrea sobre la esfera, no sobre el
 * plano, así que la textura no tiene costura en el meridiano ni se estira en los polos.
 */
(function (raiz) {
  'use strict';

  function clamp(x, lo, hi) {
    return x < lo ? lo : x > hi ? hi : x;
  }

  function suavizar(t) {
    return t * t * (3 - 2 * t);
  }

  // Generador pseudoaleatorio con semilla (mulberry32): mismo número, mismo dibujo.
  function crearRng(semilla) {
    let s = semilla >>> 0;
    return function () {
      s = (s + 0x6d2b79f5) >>> 0;
      let t = s;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  // Semilla estable a partir del id del astro (FNV-1a sobre el texto).
  function semillaDe(texto) {
    let h = 2166136261;
    for (let i = 0; i < texto.length; i++) {
      h ^= texto.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return h >>> 0;
  }

  function hashCelda(ix, iy, iz, semilla) {
    let h = Math.imul(ix, 374761393) + Math.imul(iy, 668265263) +
      Math.imul(iz, 2147483647) + Math.imul(semilla, 1274126177);
    h = Math.imul(h ^ (h >>> 13), 1103515245);
    h ^= h >>> 16;
    return (h >>> 0) / 4294967296;
  }

  // Ruido de valor en 3D, en [0, 1).
  function ruido3(x, y, z, semilla) {
    const ix = Math.floor(x), iy = Math.floor(y), iz = Math.floor(z);
    const fx = suavizar(x - ix), fy = suavizar(y - iy), fz = suavizar(z - iz);
    let r = 0;
    for (let dz = 0; dz < 2; dz++) {
      for (let dy = 0; dy < 2; dy++) {
        for (let dx = 0; dx < 2; dx++) {
          const w = (dx ? fx : 1 - fx) * (dy ? fy : 1 - fy) * (dz ? fz : 1 - fz);
          r += w * hashCelda(ix + dx, iy + dy, iz + dz, semilla);
        }
      }
    }
    return r;
  }

  // Suma de octavas de ruido, normalizada a [0, 1).
  function fbm(x, y, z, octavas, semilla) {
    let suma = 0, amp = 0.5, frec = 1, norma = 0;
    for (let i = 0; i < octavas; i++) {
      suma += amp * ruido3(x * frec, y * frec, z * frec, semilla + i * 101);
      norma += amp;
      amp *= 0.5;
      frec *= 2;
    }
    return suma / norma;
  }

  // Ruido fbm muestreado en la esfera a partir de (u, v) de la textura equirectangular.
  function muestraEsfera(u, v, escala, semilla, octavas) {
    const lon = u * 2 * Math.PI;
    const lat = (v - 0.5) * Math.PI;
    const cl = Math.cos(lat);
    return fbm(
      Math.cos(lon) * cl * escala,
      Math.sin(lon) * cl * escala,
      Math.sin(lat) * escala,
      octavas,
      semilla
    );
  }

  function hexARgb(hex) {
    const h = hex.replace('#', '');
    return [
      parseInt(h.slice(0, 2), 16),
      parseInt(h.slice(2, 4), 16),
      parseInt(h.slice(4, 6), 16)
    ];
  }

  // Interpola una paleta (lista de colores RGB) con t en [0, 1].
  function rampa(paleta, t) {
    const n = paleta.length - 1;
    const p = clamp(t, 0, 0.999999) * n;
    const i = Math.floor(p);
    const f = p - i;
    const a = paleta[i];
    const b = paleta[Math.min(i + 1, n)];
    return [
      a[0] + (b[0] - a[0]) * f,
      a[1] + (b[1] - a[1]) * f,
      a[2] + (b[2] - a[2]) * f
    ];
  }

  // Color de un cuerpo negro a la temperatura dada en kelvin (aproximación de Tanner Helland).
  function temperaturaARgb(kelvin) {
    const t = clamp(kelvin, 1000, 40000) / 100;
    let r, g, b;
    if (t <= 66) {
      r = 255;
      g = 99.4708025861 * Math.log(t) - 161.1195681661;
    } else {
      r = 329.698727446 * Math.pow(t - 60, -0.1332047592);
      g = 288.1221695283 * Math.pow(t - 60, -0.0755148492);
    }
    if (t >= 66) {
      b = 255;
    } else if (t <= 19) {
      b = 0;
    } else {
      b = 138.5177312231 * Math.log(t - 10) - 305.0447927307;
    }
    return [clamp(Math.round(r), 0, 255), clamp(Math.round(g), 0, 255), clamp(Math.round(b), 0, 255)];
  }

  // Estampa un cráter o una marca circular en un mapa, con wrap horizontal (la textura da la vuelta).
  function estampar(mapa, ancho, alto, cx, cy, r, valorDe) {
    const alcance = r * 1.3;
    const x0 = Math.floor(cx - alcance), x1 = Math.ceil(cx + alcance);
    const y0 = Math.max(0, Math.floor(cy - alcance));
    const y1 = Math.min(alto - 1, Math.ceil(cy + alcance));
    for (let y = y0; y <= y1; y++) {
      for (let x = x0; x <= x1; x++) {
        let dx = x - cx;
        if (dx > ancho / 2) dx -= ancho;
        else if (dx < -ancho / 2) dx += ancho;
        const dy = y - cy;
        const d = Math.sqrt(dx * dx + dy * dy) / r;
        if (d > 1.3) continue;
        const xw = ((x % ancho) + ancho) % ancho;
        mapa[y * ancho + xw] += valorDe(d);
      }
    }
  }

  // Cráter: cuenco oscuro dentro y un borde claro afuera. Tamaños con pocos grandes y muchos pequeños.
  function valorCrater(d) {
    if (d < 1) return -0.22 * (1 - d * d);
    return 0.05 * (1.3 - d) / 0.3;
  }

  function estamparCrateres(mapa, ancho, alto, cantidad, rng) {
    for (let i = 0; i < cantidad; i++) {
      const cx = rng() * ancho;
      const cy = rng() * alto;
      const r = 1 + (ancho / 36) * Math.pow(rng(), 3);
      estampar(mapa, ancho, alto, cx, cy, r, valorCrater);
    }
  }

  // Mancha elíptica (Gran Mancha Roja, Gran Mancha Oscura): devuelve la mezcla 0..1 en (u, v).
  function mezclaMancha(u, v, mancha) {
    const mu = (mancha.lon / 360 + 1) % 1;
    const mv = 0.5 + mancha.lat / 180;
    let du = Math.abs(u - mu);
    if (du > 0.5) du = 1 - du;
    const dx = du / mancha.rx;
    const dy = (v - mv) / mancha.ry;
    const d = Math.sqrt(dx * dx + dy * dy);
    return suavizar(clamp(1 - d, 0, 1));
  }

  function mezclar(color, otro, t) {
    return [
      color[0] + (otro[0] - color[0]) * t,
      color[1] + (otro[1] - color[1]) * t,
      color[2] + (otro[2] - color[2]) * t
    ];
  }

  // Color de una estrella: el de su temperatura, con granulación de superficie.
  function pixelEstrella(u, v, base, tx, semilla) {
    const granulos = muestraEsfera(u, v, tx.escala || 5, semilla, 5);
    // Centrado en 1: la estrella conserva su color de cuerpo negro y la granulación solo lo modula.
    const factor = 1 + 0.5 * (granulos - 0.5) * (0.5 + tx.granulos / 10);
    return [base[0] * factor, base[1] * factor, base[2] * factor];
  }

  // Dibuja un astro en un buffer RGBA (Uint8Array de ancho * alto * 4).
  function generarTextura(astro, ancho, alto) {
    ancho = ancho || 512;
    alto = alto || 256;
    const tx = astro.textura;
    const semilla = semillaDe(astro.id);
    const rng = crearRng(semilla);
    const salida = new Uint8Array(ancho * alto * 4);
    const paleta = (tx.colores || []).map(hexARgb);
    const relieve = new Float32Array(ancho * alto);
    const marcas = new Float32Array(ancho * alto);

    if (tx.craters) {
      estamparCrateres(relieve, ancho, alto, tx.craters, rng);
    }
    const oceano = (tx.oceano || []).map(hexARgb);
    const tierraFirme = (tx.tierra || []).map(hexARgb);
    if (tx.tipo === 'volcanico') {
      for (let i = 0; i < (tx.volcanes || 0); i++) {
        estampar(marcas, ancho, alto, rng() * ancho, rng() * alto, 2 + rng() * 5,
          function (d) { return d < 1 ? 1 - d : 0; });
      }
    }
    for (let y = 0; y < alto; y++) {
      const v = (y + 0.5) / alto;
      const lat = Math.abs(v - 0.5) * 2; // 0 en el ecuador, 1 en los polos
      for (let x = 0; x < ancho; x++) {
        const u = (x + 0.5) / ancho;
        const i = y * ancho + x;
        let c;

        switch (tx.tipo) {
          case 'rocoso': {
            const h = muestraEsfera(u, v, tx.escala || 3, semilla, 5) + relieve[i];
            c = rampa(paleta, clamp(h, 0, 1));
            if (tx.maria) {
              const m = muestraEsfera(u, v, 1.6, semilla + 7, 3);
              if (m > 0.55) c = mezclar(c, paleta[0], 0.45 * suavizar((m - 0.55) / 0.2));
            }
            if (tx.casquetes && lat > 0.8) {
              c = mezclar(c, hexARgb(tx.casquetes), 0.9 * suavizar((lat - 0.8) / 0.12));
            }
            break;
          }
          case 'bandas': {
            const deformacion = (muestraEsfera(u, v, 2, semilla, 3) - 0.5) * 0.8;
            const onda = Math.sin((v * tx.bandas + deformacion) * 2 * Math.PI);
            const grano = (muestraEsfera(u, v, 8, semilla + 3, 3) - 0.5) * 0.12;
            c = rampa(paleta, clamp(0.5 + 0.5 * tx.contraste * onda + grano, 0, 1));
            if (tx.mancha) {
              c = mezclar(c, hexARgb(tx.mancha.color), mezclaMancha(u, v, tx.mancha));
            }
            break;
          }
          case 'nubes': {
            const amplitud = tx.bandas_amplitud == null ? 0.12 : tx.bandas_amplitud;
            const bandas = tx.bandas ? amplitud * Math.sin((v * tx.bandas) * 2 * Math.PI) : 0;
            const n = muestraEsfera(u, v, 2.5, semilla, 5) + bandas;
            c = rampa(paleta, clamp(n, 0, 1));
            break;
          }
          case 'tierra': {
            const h = muestraEsfera(u, v, 2.2, semilla, 6);
            if (h > 0.5) {
              const detalle = muestraEsfera(u, v, 9, semilla + 5, 3);
              c = rampa(tierraFirme, detalle);
            } else {
              const profundidad = clamp((0.5 - h) * 2.4, 0, 1);
              c = rampa(oceano, profundidad);
            }
            if (lat > 0.82) {
              c = mezclar(c, hexARgb(tx.hielo), suavizar((lat - 0.82) / 0.1));
            }
            const nube = muestraEsfera(u, v, 3.2, semilla + 11, 5);
            const cobertura = suavizar(clamp((nube - 0.52) / 0.12, 0, 1)) * 0.85;
            c = mezclar(c, hexARgb(tx.nubes), cobertura);
            break;
          }
          case 'volcanico': {
            const h = muestraEsfera(u, v, tx.escala || 4, semilla, 4);
            c = rampa(paleta.slice(0, 3), clamp(h * 1.4, 0, 1));
            const marca = clamp(marcas[i], 0, 1);
            if (marca > 0) {
              c = mezclar(c, paleta[4] || paleta[paleta.length - 1], marca * 0.9);
            }
            break;
          }
          case 'hielo': {
            const h = muestraEsfera(u, v, tx.escala || 4, semilla, 5) + relieve[i];
            c = rampa(paleta, clamp(h, 0, 1));
            if (tx.lineas) {
              // Grietas: líneas finas donde el ruido cruza un valor, como las de Europa.
              // Pocas octavas y un umbral fino dan curvas largas, no puntos.
              const grieta = Math.abs(muestraEsfera(u, v, (tx.escala || 4) * 1.2, semilla + 9, 2) - 0.5);
              if (grieta < 0.02) {
                c = mezclar(c, hexARgb(tx.lineas), 0.75 * (1 - grieta / 0.02));
              }
            }
            break;
          }
          case 'estrella': {
            const base = temperaturaARgb(astro.temperatura_superficial_k);
            c = pixelEstrella(u, v, base, tx, semilla);
            break;
          }
          default:
            c = [128, 128, 128];
        }

        salida[i * 4] = clamp(Math.round(c[0]), 0, 255);
        salida[i * 4 + 1] = clamp(Math.round(c[1]), 0, 255);
        salida[i * 4 + 2] = clamp(Math.round(c[2]), 0, 255);
        salida[i * 4 + 3] = 255;
      }
    }
    return salida;
  }

  // Anillo: franja 1D de radios interior..exterior, con su propio canal alfa.
  // Devuelve un buffer RGBA de ancho x 1 (la vista lo estira radialmente).
  function generarAnillo(anillos, ancho, opacidadMaxima) {
    ancho = ancho || 512;
    opacidadMaxima = opacidadMaxima == null ? 1 : opacidadMaxima;
    const paleta = anillos.colores.map(hexARgb);
    const semilla = semillaDe('anillo' + anillos.interior + anillos.exterior);
    const salida = new Uint8Array(ancho * 4);
    for (let x = 0; x < ancho; x++) {
      const t = (x + 0.5) / ancho;
      const grano = fbm(t * 60, 0, 0, 4, semilla);
      const c = rampa(paleta, grano);
      salida[x * 4] = Math.round(c[0]);
      salida[x * 4 + 1] = Math.round(c[1]);
      salida[x * 4 + 2] = Math.round(c[2]);
      // Bordes del anillo más transparentes; la franja central es la más densa.
      const borde = Math.min(t, 1 - t) * 4;
      const alfa = clamp(0.35 + 0.65 * grano, 0, 1) * clamp(borde, 0, 1) * opacidadMaxima;
      salida[x * 4 + 3] = Math.round(255 * alfa);
    }
    return salida;
  }

  const API = {
    crearRng: crearRng,
    semillaDe: semillaDe,
    ruido3: ruido3,
    fbm: fbm,
    muestraEsfera: muestraEsfera,
    hexARgb: hexARgb,
    rampa: rampa,
    temperaturaARgb: temperaturaARgb,
    generarTextura: generarTextura,
    generarAnillo: generarAnillo
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.AstrosTexturas = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
