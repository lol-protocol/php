/*
 * Visor WebGL2 de los astros. Sin dependencias: matrices, geometría y shaders a mano.
 * Usa window.ASTROS_DATOS (js/datos.js) y window.AstrosTexturas (js/texturas.js).
 *
 * Todos los cuerpos se dibujan con radio 1: el visor no está a escala. Los tamaños
 * reales están en la ficha. Los giros tampoco: todos giran igual de rápido para que se vean.
 */
(function () {
  'use strict';

  const T = window.AstrosTexturas;
  const DATOS = window.ASTROS_DATOS;
  const AJUSTES = window.AstrosAjustes;
  const ORIENTACION = window.AstrosOrientacion;
  const SOMBRAS = window.AstrosSombras;

  const canvas = document.getElementById('escena');
  const aviso = document.getElementById('aviso');
  const lista = document.getElementById('lista-astros');
  const ficha = document.getElementById('ficha');
  const botonGirar = document.getElementById('girar');
  const botonReiniciar = document.getElementById('reiniciar');
  const deslizadorVelocidad = document.getElementById('velocidad');
  const textoVelocidad = document.getElementById('velocidad-texto');

  const ANCHO_TEXTURA = 1024;
  const ALTO_TEXTURA = 512;
  const RADIO_HALO = 1.06;
  const RADIO_CORONA = 1.32;
  const RADIO_NUBES = 1.012;     // la capa de nubes de la Tierra, apenas por encima de la superficie
  // Dirección hacia la luz, fija en la escena (el astro gira, la luz no).
  const LUZ = (function () { const v = [-0.6, 0.35, 0.75]; const n = Math.hypot(v[0], v[1], v[2]); return [v[0] / n, v[1] / n, v[2] / n]; })();

  // ---------- Matrices (columna mayor, como las espera WebGL) ----------

  function identidad() {
    return new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1]);
  }

  function mult(a, b) {
    const o = new Float32Array(16);
    for (let c = 0; c < 4; c++) {
      for (let r = 0; r < 4; r++) {
        let s = 0;
        for (let k = 0; k < 4; k++) s += a[k * 4 + r] * b[c * 4 + k];
        o[c * 4 + r] = s;
      }
    }
    return o;
  }

  function rotX(t) {
    const c = Math.cos(t), s = Math.sin(t);
    return new Float32Array([1, 0, 0, 0, 0, c, s, 0, 0, -s, c, 0, 0, 0, 0, 1]);
  }

  function rotY(t) {
    const c = Math.cos(t), s = Math.sin(t);
    return new Float32Array([c, 0, -s, 0, 0, 1, 0, 0, s, 0, c, 0, 0, 0, 0, 1]);
  }

  function escalar(k) {
    return new Float32Array([k, 0, 0, 0, 0, k, 0, 0, 0, 0, k, 0, 0, 0, 0, 1]);
  }

  function trasladar(z) {
    const m = identidad();
    m[14] = z;
    return m;
  }

  function perspectiva(fovy, aspecto, cerca, lejos) {
    const t = 1 / Math.tan(fovy / 2);
    return new Float32Array([
      t / aspecto, 0, 0, 0,
      0, t, 0, 0,
      0, 0, (lejos + cerca) / (cerca - lejos), -1,
      0, 0, (2 * lejos * cerca) / (cerca - lejos), 0
    ]);
  }

  // Parte de rotación 3x3 de una 4x4, en el orden que pide uniform mat3.
  function rotacion3(m) {
    return new Float32Array([m[0], m[1], m[2], m[4], m[5], m[6], m[8], m[9], m[10]]);
  }

  // ---------- WebGL ----------

  let gl = null;
  try {
    gl = canvas.getContext('webgl2', { antialias: true, alpha: false });
  } catch (e) {
    gl = null;
  }
  if (!gl) {
    aviso.hidden = false;
    aviso.textContent = 'Este navegador no tiene WebGL2, así que no se pueden dibujar los astros. La ficha de cada uno sigue disponible.';
    canvas.hidden = true;
  }

  function compilar(tipo, fuente) {
    const s = gl.createShader(tipo);
    gl.shaderSource(s, fuente);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
      throw new Error('Shader: ' + gl.getShaderInfoLog(s));
    }
    return s;
  }

  function programa(vs, fs) {
    const p = gl.createProgram();
    gl.attachShader(p, compilar(gl.VERTEX_SHADER, vs));
    gl.attachShader(p, compilar(gl.FRAGMENT_SHADER, fs));
    gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) {
      throw new Error('Programa: ' + gl.getProgramInfoLog(p));
    }
    return p;
  }

  const VS_CUERPO = `#version 300 es
layout(location=0) in vec3 aPos;
layout(location=1) in vec2 aUv;
uniform mat4 uMVP;
uniform mat4 uModelo;
out vec2 vUv;
out vec3 vNor;
out vec3 vPos;
out vec3 vObj;
void main() {
  vUv = aUv;
  vObj = aPos;
  vNor = mat3(uModelo) * aPos;
  vPos = (uModelo * vec4(aPos, 1.0)).xyz;
  gl_Position = uMVP * vec4(aPos, 1.0);
}`;

  // Cuerpo: color de la textura, iluminado por la luz, con relieve (normal inclinada con el mapa de normales,
  // calculado en la CPU) y con la sombra de sus anillos si los tiene.
  const FS_CUERPO = `#version 300 es
precision highp float;
in vec2 vUv;
in vec3 vNor;
in vec3 vPos;
in vec3 vObj;
uniform sampler2D uTex;
uniform sampler2D uAnillo;
uniform sampler2D uRelieve;
uniform mat4 uModelo;
uniform vec3 uLuz;
uniform float uEmision;
uniform float uRelieveActivo;
uniform float uAnilloActivo;
uniform vec3 uEjeAnillo;
uniform float uAnilloInterior;
uniform float uAnilloExterior;
out vec4 salida;

// Normal del relieve: el mapa guarda la inclinación en el plano tangente (x al este, y al norte, z hacia
// fuera). El este y el norte salen de la posición, porque el eje de giro es Y y la textura es equirectangular.
vec3 normalConRelieve(vec3 pObj, vec2 uv) {
  vec3 nt = texture(uRelieve, uv).xyz * 2.0 - 1.0;
  vec3 p = normalize(pObj);
  vec3 este = cross(p, vec3(0.0, 1.0, 0.0));
  if (length(este) < 0.0001) este = vec3(1.0, 0.0, 0.0);
  este = normalize(este);
  vec3 norte = cross(este, p);
  vec3 nObj = normalize(este * nt.x + norte * nt.y + p * nt.z);
  return normalize(mat3(uModelo) * nObj);
}

// Sombra de los anillos sobre el planeta: el rayo hacia la luz cruza el plano de los anillos a una
// distancia r del centro; si cae entre sus radios, se oscurece según la opacidad del anillo ahí.
// Es la misma cuenta que js/sombras.js (cruceConElPlanoDeAnillos), que es la versión probada.
float sombraAnillos(vec3 p, vec3 l) {
  if (uAnilloActivo < 0.5) return 1.0;
  float lateral = dot(uEjeAnillo, l);
  if (abs(lateral) < 0.000001) return 1.0;
  float t = -dot(uEjeAnillo, p) / lateral;
  if (t <= 0.0) return 1.0;
  float r = length(p + t * l);
  if (r < uAnilloInterior || r > uAnilloExterior) return 1.0;
  float a = textureLod(uAnillo, vec2((r - uAnilloInterior) / (uAnilloExterior - uAnilloInterior), 0.5), 0.0).a;
  return 1.0 - 0.9 * a;
}

void main() {
  vec3 albedo = texture(uTex, vUv).rgb;
  vec3 ng = normalize(vNor);
  vec3 n = ng;
  vec3 l = normalize(uLuz);
  if (uRelieveActivo > 0.5) {
    n = normalConRelieve(vObj, vUv);
  }
  float d = max(dot(n, l), 0.0);
  // Con relieve, la normal inclinada podría iluminar un punto que la superficie da de espaldas a la luz.
  if (uRelieveActivo > 0.5) d *= smoothstep(0.0, 0.08, dot(ng, l));
  d *= sombraAnillos(normalize(vPos), l);
  vec3 iluminado = albedo * (0.06 + 0.94 * d);
  vec3 emitido = albedo * 0.92;
  salida = vec4(mix(iluminado, emitido, uEmision), 1.0);
}`;

  // Capa de nubes (RGBA): se ilumina igual que el cuerpo y su opacidad sale del canal alfa de la textura.
  const FS_NUBES = `#version 300 es
precision highp float;
in vec2 vUv;
in vec3 vNor;
uniform sampler2D uTex;
uniform vec3 uLuz;
out vec4 salida;
void main() {
  vec4 t = texture(uTex, vUv);
  float d = max(dot(normalize(vNor), normalize(uLuz)), 0.0);
  salida = vec4(t.rgb * (0.06 + 0.94 * d), t.a);
}`;

  const VS_HALO = `#version 300 es
layout(location=0) in vec3 aPos;
layout(location=1) in vec2 aUv;
uniform mat4 uMVP;
uniform mat3 uRotVista;
out float vFrente;
void main() {
  vFrente = (uRotVista * aPos).z;
  gl_Position = uMVP * vec4(aPos, 1.0);
}`;

  // Halo de Fresnel: brilla en el borde, donde la superficie se ve de canto.
  const FS_HALO = `#version 300 es
precision highp float;
in float vFrente;
uniform vec3 uColor;
uniform float uPot;
uniform float uIntensidad;
out vec4 salida;
void main() {
  float z = clamp(vFrente, 0.0, 1.0);
  // Se apaga en el borde exterior: sin esa caída, el halo termina en un anillo duro.
  float f = pow(1.0 - z, uPot) * smoothstep(0.0, 0.3, z);
  salida = vec4(uColor * f * uIntensidad, 1.0);
}`;

  const VS_ANILLO = `#version 300 es
layout(location=0) in vec3 aPos;
layout(location=1) in vec2 aUv;
uniform mat4 uMVP;
uniform mat4 uModelo;
out vec2 vUv;
out vec3 vPos;
void main() {
  vUv = aUv;
  vPos = (uModelo * vec4(aPos, 1.0)).xyz;
  gl_Position = uMVP * vec4(aPos, 1.0);
}`;

  // Anillo: su textura con su opacidad, y la sombra que el planeta le echa si queda entre el anillo y la luz
  // (misma cuenta que enSombraDelPlaneta en js/sombras.js, con un borde suave para la penumbra).
  const FS_ANILLO = `#version 300 es
precision highp float;
in vec2 vUv;
in vec3 vPos;
uniform sampler2D uAnillo;
uniform float uBrillo;
uniform vec3 uLuz;
out vec4 salida;
void main() {
  vec4 t = texture(uAnillo, vec2(vUv.x, 0.5));
  vec3 l = normalize(uLuz);
  float tc = -dot(vPos, l);
  float sombra = 1.0;
  if (tc > 0.0) {
    float dist = length(vPos + tc * l);
    sombra = mix(0.15, 1.0, smoothstep(0.96, 1.04, dist));
  }
  salida = vec4(t.rgb * uBrillo * sombra, t.a);
}`;

  let progCuerpo, progHalo, progAnillo, progNubes, esfera, geoHalo, texVaciaRgba;

  function inicializarGL() {
    progCuerpo = programa(VS_CUERPO, FS_CUERPO);
    progHalo = programa(VS_HALO, FS_HALO);
    progAnillo = programa(VS_ANILLO, FS_ANILLO);
    progNubes = programa(VS_CUERPO, FS_NUBES);
    // Texturas de 1 píxel para los samplers que un astro no usa (sin anillos o sin relieve): nunca quedan sin asignar.
    texVaciaRgba = subirTextura(new Uint8Array([0, 0, 0, 0]), 1, 1, false);
    esfera = crearEsfera(96, 192);
  }

  // Esfera unitaria con UV equirectangulares. El eje de giro es Y (los polos están en Y).
  // Los triángulos se orientan para que la cara de fuera sea la de frente (CCW).
  function crearEsfera(nLat, nLon) {
    const datos = [];
    for (let i = 0; i <= nLat; i++) {
      const lat = -Math.PI / 2 + (i / nLat) * Math.PI;
      const cl = Math.cos(lat), sl = Math.sin(lat);
      for (let j = 0; j <= nLon; j++) {
        const lon = (j / nLon) * 2 * Math.PI;
        const x = Math.cos(lon) * cl, y = sl, z = Math.sin(lon) * cl;
        datos.push(x, y, z, j / nLon, i / nLat);
      }
    }
    const indices = [];
    const fila = nLon + 1;
    const pos = (k) => [datos[k * 5], datos[k * 5 + 1], datos[k * 5 + 2]];
    for (let i = 0; i < nLat; i++) {
      for (let j = 0; j < nLon; j++) {
        const a = i * fila + j, b = a + 1, c = a + fila, d = c + 1;
        for (const tri of [[a, c, b], [b, c, d]]) {
          const [p0, p1, p2] = tri.map(pos);
          const u = [p1[0] - p0[0], p1[1] - p0[1], p1[2] - p0[2]];
          const v = [p2[0] - p0[0], p2[1] - p0[1], p2[2] - p0[2]];
          const n = [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
          const dentro = n[0] * p0[0] + n[1] * p0[1] + n[2] * p0[2];
          if (dentro >= 0) indices.push(tri[0], tri[1], tri[2]);
          else indices.push(tri[0], tri[2], tri[1]);
        }
      }
    }
    return subirMalla(new Float32Array(datos), new Uint32Array(indices), 5);
  }

  // Sube vértices intercalados (x, y, z, u, v) y sus índices a la GPU.
  function subirMalla(vertices, indices, paso) {
    const vao = gl.createVertexArray();
    gl.bindVertexArray(vao);
    const vbo = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
    gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0);
    gl.vertexAttribPointer(0, 3, gl.FLOAT, false, paso * 4, 0);
    gl.enableVertexAttribArray(1);
    gl.vertexAttribPointer(1, 2, gl.FLOAT, false, paso * 4, 12);
    const ibo = gl.createBuffer();
    gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ibo);
    gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, indices, gl.STATIC_DRAW);
    gl.bindVertexArray(null);
    return { vao: vao, cuenta: indices.length };
  }

  // Anillo plano en el plano XZ, de radio interior a exterior, con u radial y v = 0.5.
  function crearAnillo(interior, exterior) {
    const n = 256;
    const datos = [];
    for (let i = 0; i <= n; i++) {
      const a = (i / n) * 2 * Math.PI;
      const c = Math.cos(a), s = Math.sin(a);
      datos.push(interior * c, 0, interior * s, 0, 0.5);
      datos.push(exterior * c, 0, exterior * s, 1, 0.5);
    }
    const indices = [];
    for (let i = 0; i < n; i++) {
      const a = i * 2;
      indices.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
    }
    return subirMalla(new Float32Array(datos), new Uint32Array(indices), 5);
  }

  function subirTextura(pixeles, ancho, alto, repetirX) {
    const tex = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, tex);
    gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, ancho, alto, 0, gl.RGBA, gl.UNSIGNED_BYTE, pixeles);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, repetirX ? gl.REPEAT : gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.generateMipmap(gl.TEXTURE_2D);
    return tex;
  }

  // ---------- Astros: texturas y geometría, una vez por astro ----------

  const cache = new Map();

  function colorRgb(hex) {
    return T.hexARgb(hex).map(c => c / 255);
  }

  function prepararAstro(a) {
    if (cache.has(a.id)) return cache.get(a.id);
    const entrada = {};
    const px = T.generarTextura(a, ANCHO_TEXTURA, ALTO_TEXTURA);
    entrada.tex = subirTextura(px, ANCHO_TEXTURA, ALTO_TEXTURA, true);

    const tx = a.textura;
    const normales = T.generarNormales(a, ANCHO_TEXTURA, ALTO_TEXTURA);
    if (normales) entrada.texRelieve = subirTextura(normales, ANCHO_TEXTURA, ALTO_TEXTURA, true);
    const capaNubes = T.generarCapaNubes(a, ANCHO_TEXTURA, ALTO_TEXTURA);
    if (capaNubes) {
      entrada.texNubes = subirTextura(capaNubes, ANCHO_TEXTURA, ALTO_TEXTURA, true);
      entrada.derivaNubes = tx.capa_nubes.deriva == null ? 1 : tx.capa_nubes.deriva;
    }
    if (a.categoria === 'sol') {
      entrada.halo = (tx.color ? T.hexARgb(tx.color) : T.temperaturaARgb(a.temperatura_superficial_k)).map(c => c / 255);
      entrada.intensidadHalo = 0.55;
      entrada.potHalo = 3.0;
      entrada.radioHalo = RADIO_CORONA;
    } else if (tx.halo) {
      entrada.halo = colorRgb(tx.halo);
      entrada.intensidadHalo = 0.55;
      entrada.potHalo = 3.2;
      entrada.radioHalo = RADIO_HALO;
    }
    if (tx.anillos) {
      const anillo = tx.anillos;
      entrada.geoAnillo = crearAnillo(anillo.interior, anillo.exterior);
      entrada.texAnillo = subirTextura(T.generarAnillo(anillo, 512, anillo.opacidad == null ? 1 : anillo.opacidad), 512, 1, false);
      entrada.brilloAnillo = a.id === 'urano' ? 0.7 : 0.95;
    }
    cache.set(a.id, entrada);
    return entrada;
  }

  // ---------- Cámara y estado ----------

  const reducirMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // La elección de giro y velocidad se recuerda en este navegador. Si el almacenamiento no está
  // disponible (modo privado, datos bloqueados), el visor funciona igual con los valores por defecto.
  const CLAVE_AJUSTES = 'astros3d.ajustes';

  function leerGuardado() {
    try {
      const crudo = window.localStorage.getItem(CLAVE_AJUSTES);
      return crudo ? JSON.parse(crudo) : null;
    } catch (e) {
      return null;
    }
  }

  function guardarAjustes() {
    try {
      window.localStorage.setItem(CLAVE_AJUSTES, JSON.stringify({ girando: estado.girando, velocidad: estado.velocidad }));
    } catch (e) {
      // sin almacenamiento: no pasa nada
    }
  }

  const inicial = AJUSTES.ajustesIniciales(leerGuardado(), reducirMovimiento);

  const estado = {
    actual: null,
    yaw: 0.5,
    pitch: 0.25,
    distancia: 3.4,
    giro: 0,
    girando: inicial.girando,
    velocidad: inicial.velocidad,
    arrastrando: false,
    ultimoX: 0,
    ultimoY: 0
  };

  function ajustarTamano() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.round(canvas.clientWidth * dpr);
    const h = Math.round(canvas.clientHeight * dpr);
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
  }

  function dibujar(ahora) {
    if (!gl || !estado.actual) return;
    ajustarTamano();
    gl.viewport(0, 0, canvas.width, canvas.height);
    gl.clearColor(0.02, 0.025, 0.04, 1);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
    gl.enable(gl.DEPTH_TEST);
    gl.enable(gl.CULL_FACE);
    gl.cullFace(gl.BACK);
    gl.disable(gl.BLEND);

    const a = estado.actual;
    const prep = prepararAstro(a);
    const P = perspectiva(0.75, canvas.width / canvas.height, 0.05, 50);
    const R = mult(rotX(estado.pitch), rotY(estado.yaw));
    const V = mult(trasladar(-estado.distancia), R);
    const inclinacion = ((a.inclinacion_eje_grados || 0) * Math.PI) / 180;
    const sentido = ORIENTACION.sentidoDeGiro(a.periodo_rotacion_h, a.inclinacion_eje_grados);
    const M = mult(rotX(inclinacion), rotY(estado.giro * sentido));

    // Cuerpo
    gl.useProgram(progCuerpo);
    gl.uniformMatrix4fv(gl.getUniformLocation(progCuerpo, 'uMVP'), false, mult(P, mult(V, M)));
    gl.uniformMatrix4fv(gl.getUniformLocation(progCuerpo, 'uModelo'), false, M);
    gl.uniform3f(gl.getUniformLocation(progCuerpo, 'uLuz'), LUZ[0], LUZ[1], LUZ[2]);
    gl.uniform1f(gl.getUniformLocation(progCuerpo, 'uEmision'), a.categoria === 'sol' ? 1 : 0);
    gl.uniform1f(gl.getUniformLocation(progCuerpo, 'uRelieveActivo'), prep.texRelieve ? 1 : 0);
    gl.uniform1f(gl.getUniformLocation(progCuerpo, 'uAnilloActivo'), prep.geoAnillo ? 1 : 0);
    if (prep.geoAnillo) {
      const eje = SOMBRAS.ejeDeAnillos(a.inclinacion_eje_grados);
      gl.uniform3f(gl.getUniformLocation(progCuerpo, 'uEjeAnillo'), eje[0], eje[1], eje[2]);
      gl.uniform1f(gl.getUniformLocation(progCuerpo, 'uAnilloInterior'), a.textura.anillos.interior);
      gl.uniform1f(gl.getUniformLocation(progCuerpo, 'uAnilloExterior'), a.textura.anillos.exterior);
    }
    gl.activeTexture(gl.TEXTURE0);
    gl.bindTexture(gl.TEXTURE_2D, prep.tex);
    gl.uniform1i(gl.getUniformLocation(progCuerpo, 'uTex'), 0);
    gl.activeTexture(gl.TEXTURE1);
    gl.bindTexture(gl.TEXTURE_2D, prep.texAnillo || texVaciaRgba);
    gl.uniform1i(gl.getUniformLocation(progCuerpo, 'uAnillo'), 1);
    gl.activeTexture(gl.TEXTURE2);
    gl.bindTexture(gl.TEXTURE_2D, prep.texRelieve || texVaciaRgba);
    gl.uniform1i(gl.getUniformLocation(progCuerpo, 'uRelieve'), 2);
    gl.bindVertexArray(esfera.vao);
    gl.drawElements(gl.TRIANGLES, esfera.cuenta, gl.UNSIGNED_INT, 0);

    // Capa de nubes: una esfera apenas más grande, con su propia deriva (solo se nota si el giro está activo).
    if (prep.texNubes) {
      gl.enable(gl.BLEND);
      gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
      gl.depthMask(false);
      gl.useProgram(progNubes);
      const Mn = mult(mult(rotX(inclinacion), rotY(estado.giro * sentido * prep.derivaNubes)), escalar(RADIO_NUBES));
      gl.uniformMatrix4fv(gl.getUniformLocation(progNubes, 'uMVP'), false, mult(P, mult(V, Mn)));
      gl.uniformMatrix4fv(gl.getUniformLocation(progNubes, 'uModelo'), false, Mn);
      gl.uniform3f(gl.getUniformLocation(progNubes, 'uLuz'), LUZ[0], LUZ[1], LUZ[2]);
      gl.activeTexture(gl.TEXTURE0);
      gl.bindTexture(gl.TEXTURE_2D, prep.texNubes);
      gl.uniform1i(gl.getUniformLocation(progNubes, 'uTex'), 0);
      gl.bindVertexArray(esfera.vao);
      gl.drawElements(gl.TRIANGLES, esfera.cuenta, gl.UNSIGNED_INT, 0);
      gl.depthMask(true);
      gl.disable(gl.BLEND);
    }

    // Anillos (sin cara trasera: se ven por las dos caras)
    if (prep.geoAnillo) {
      gl.disable(gl.CULL_FACE);
      gl.enable(gl.BLEND);
      gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
      gl.useProgram(progAnillo);
      gl.uniform1f(gl.getUniformLocation(progAnillo, 'uBrillo'), prep.brilloAnillo);
      gl.uniform3f(gl.getUniformLocation(progAnillo, 'uLuz'), LUZ[0], LUZ[1], LUZ[2]);
      gl.uniformMatrix4fv(gl.getUniformLocation(progAnillo, 'uModelo'), false, rotX(inclinacion));
      gl.activeTexture(gl.TEXTURE1);
      gl.bindTexture(gl.TEXTURE_2D, prep.texAnillo);
      gl.uniform1i(gl.getUniformLocation(progAnillo, 'uAnillo'), 1);
      // Los anillos siguen la inclinación del planeta pero no su giro.
      gl.uniformMatrix4fv(gl.getUniformLocation(progAnillo, 'uMVP'), false, mult(P, mult(V, rotX(inclinacion))));
      gl.bindVertexArray(prep.geoAnillo.vao);
      gl.drawElements(gl.TRIANGLES, prep.geoAnillo.cuenta, gl.UNSIGNED_INT, 0);
      gl.enable(gl.CULL_FACE);
      gl.disable(gl.BLEND);
    }

    // Halo o corona: aditivo, sin escribir profundidad
    if (prep.halo) {
      gl.enable(gl.BLEND);
      gl.blendFunc(gl.ONE, gl.ONE);
      gl.depthMask(false);
      gl.useProgram(progHalo);
      const Mh = escalar(prep.radioHalo);
      gl.uniformMatrix4fv(gl.getUniformLocation(progHalo, 'uMVP'), false, mult(P, mult(V, Mh)));
      gl.uniformMatrix3fv(gl.getUniformLocation(progHalo, 'uRotVista'), false, rotacion3(R));
      gl.uniform3fv(gl.getUniformLocation(progHalo, 'uColor'), prep.halo);
      gl.uniform1f(gl.getUniformLocation(progHalo, 'uPot'), prep.potHalo);
      gl.uniform1f(gl.getUniformLocation(progHalo, 'uIntensidad'), prep.intensidadHalo);
      gl.bindVertexArray(esfera.vao);
      gl.drawElements(gl.TRIANGLES, esfera.cuenta, gl.UNSIGNED_INT, 0);
      gl.depthMask(true);
      gl.disable(gl.BLEND);
    }
    gl.bindVertexArray(null);
  }

  function bucle(ahora) {
    if (!estado.ultimoTiempo) estado.ultimoTiempo = ahora;
    const dt = Math.min((ahora - estado.ultimoTiempo) / 1000, 0.1);
    estado.ultimoTiempo = ahora;
    if (estado.girando && !estado.arrastrando) estado.giro += dt * estado.velocidad;
    dibujar(ahora);
    requestAnimationFrame(bucle);
  }

  // Arrastre con mouse, dedo o lápiz; la rueda acerca y aleja.
  canvas.addEventListener('pointerdown', function (e) {
    estado.arrastrando = true;
    estado.ultimoX = e.clientX;
    estado.ultimoY = e.clientY;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', function (e) {
    if (!estado.arrastrando) return;
    estado.yaw += (e.clientX - estado.ultimoX) * 0.008;
    estado.pitch = Math.max(-1.45, Math.min(1.45, estado.pitch + (e.clientY - estado.ultimoY) * 0.008));
    estado.ultimoX = e.clientX;
    estado.ultimoY = e.clientY;
  });
  function soltar() {
    estado.arrastrando = false;
  }
  canvas.addEventListener('pointerup', soltar);
  canvas.addEventListener('pointercancel', soltar);
  canvas.addEventListener('wheel', function (e) {
    e.preventDefault();
    estado.distancia = Math.max(1.6, Math.min(12, estado.distancia * Math.exp(e.deltaY * 0.0015)));
  }, { passive: false });

  // ---------- Ficha y lista ----------

  function numeroEs(valor, decimales) {
    const partes = valor.toFixed(decimales).split('.');
    const enteros = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return decimales > 0 ? enteros + ',' + partes[1] : enteros;
  }

  function cientifico(valor) {
    const exp = Math.floor(Math.log10(Math.abs(valor)));
    const sup = { '-': '⁻', '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹' };
    return numeroEs(valor / Math.pow(10, exp), 2) + ' × 10' + String(exp).split('').map(c => sup[c]).join('');
  }

  function horas(valor) {
    const abs = Math.abs(valor);
    const texto = abs >= 48 ? numeroEs(abs / 24, 2) + ' d' : numeroEs(abs, abs < 10 ? 2 : 1) + ' h';
    return (valor < 0 ? '−' : '') + texto + (valor < 0 ? ' (retrógrado)' : '');
  }

  function filasFicha(a) {
    const padres = {};
    DATOS.astros.forEach(x => { padres[x.id] = x.nombre; });
    if (a.categoria === 'sol') {
      return [
        ['Tipo', a.tipo],
        ['Constelación', a.constelacion || '—'],
        ['Signo zodiacal', a.signo || '—'],
        ['Tipo espectral', a.tipo_espectral],
        ['Radio', numeroEs(a.radio_solar, a.radio_solar < 10 ? 2 : a.radio_solar < 100 ? 1 : 0) + ' R☉ (' + numeroEs(a.radio_km, 0) + ' km)'],
        ['Masa', numeroEs(a.masa_solar, a.masa_solar < 10 ? 2 : 1) + ' M☉'],
        ['Temperatura superficial', numeroEs(a.temperatura_superficial_k, 0) + ' K'],
        ['Distancia', a.distancia.unidad === 'UA' ? '1 UA' : numeroEs(a.distancia.valor, Number.isInteger(a.distancia.valor) ? 0 : 1) + ' años luz'],
        ['Edad aproximada', a.edad_aprox_mil_millones_anios == null ? 'Sin dato fiable' : numeroEs(a.edad_aprox_mil_millones_anios, 3) + ' mil millones de años'],
        ['Incertidumbre del radio', a.incertidumbre_radio]
      ];
    }
    const filas = [
      ['Tipo', a.tipo],
      ['Radio medio', numeroEs(a.radio_km, 1) + ' km'],
      ['Masa', cientifico(a.masa_kg) + ' kg'],
      ['Gravedad en la superficie', numeroEs(a.gravedad_ms2, 2) + ' m/s²'],
      ['Densidad media', numeroEs(a.densidad_kg_m3, 0) + ' kg/m³'],
      ['Rotación', horas(a.periodo_rotacion_h)],
      ['Temperatura media', numeroEs(a.temperatura_media_k, 0) + ' K'],
      ['Atmósfera', a.atmosfera]
    ];
    if (a.categoria === 'planeta') {
      filas.splice(6, 0, ['Inclinación del eje', a.inclinacion_eje_grados == null ? 'Desconocida' : numeroEs(a.inclinacion_eje_grados, 2) + '°']);
      filas.push(['Año', numeroEs(a.periodo_orbital_dias, 2) + ' días terrestres']);
      filas.push(['Lunas conocidas', String(a.lunas)]);
      filas.push(['Anillos', a.anillos ? 'Sí' : 'No']);
    } else {
      filas.push(['Cuerpo padre', padres[a.padre]]);
      filas.push(['Distancia al padre', numeroEs(a.distancia_media.valor, 0) + ' km']);
      filas.push(['Órbita', numeroEs(a.periodo_orbital_dias, 3) + ' días']);
    }
    return filas;
  }

  const ETIQUETA_CATEGORIA = { planeta: 'Planeta', luna: 'Luna', sol: 'Estrella' };

  function mostrarFicha(a) {
    const dl = document.createElement('dl');
    for (const [etiqueta, valor] of filasFicha(a)) {
      const dt = document.createElement('dt');
      dt.textContent = etiqueta;
      const dd = document.createElement('dd');
      dd.textContent = valor;
      dl.appendChild(dt);
      dl.appendChild(dd);
    }
    ficha.replaceChildren();
    // El nombre accesible del lienzo dice qué astro se ve, igual que la ficha.
    canvas.setAttribute('aria-label', 'Vista 3D de ' + a.nombre);
    const h = document.createElement('h2');
    h.textContent = a.nombre;
    const p = document.createElement('p');
    p.className = 'categoria';
    p.textContent = ETIQUETA_CATEGORIA[a.categoria];
    ficha.appendChild(h);
    ficha.appendChild(p);
    ficha.appendChild(dl);
  }

  function seleccionar(id) {
    const a = DATOS.astros.find(x => x.id === id);
    if (!a) return;
    estado.actual = a;
    for (const b of lista.querySelectorAll('button[data-id]')) {
      b.setAttribute('aria-pressed', String(b.dataset.id === id));
    }
    // Las texturas se generan la primera vez; mientras tanto se muestra la ficha.
    mostrarFicha(a);
    if (gl) {
      prepararAstro(a);
      dibujar(performance.now());
    }
  }

  function construirLista() {
    const grupos = [
      { titulo: 'Planetas y planetas enanos', astros: DATOS.astros.filter(a => a.categoria === 'planeta') },
      { titulo: 'Lunas', astros: DATOS.astros.filter(a => a.categoria === 'luna') },
      { titulo: 'Soles (estrellas)', astros: DATOS.astros.filter(a => a.categoria === 'sol') }
    ];
    for (const g of grupos) {
      const sec = document.createElement('section');
      const h = document.createElement('h2');
      h.textContent = g.titulo;
      sec.appendChild(h);
      for (const a of g.astros) {
        const b = document.createElement('button');
        b.type = 'button';
        b.dataset.id = a.id;
        if (a.categoria === 'sol') {
          const punto = document.createElement('span');
          punto.className = 'punto';
          punto.setAttribute('aria-hidden', 'true');
          punto.style.background = a.textura.color || '#ffffff';
          b.appendChild(punto);
        }
        b.appendChild(document.createTextNode(a.nombre));
        b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', () => seleccionar(a.id));
        sec.appendChild(b);
      }
      lista.appendChild(sec);
    }
  }

  function actualizarControlesGiro() {
    botonGirar.setAttribute('aria-pressed', String(estado.girando));
    botonGirar.textContent = estado.girando ? 'Pausar giro' : 'Girar';
    deslizadorVelocidad.value = String(estado.velocidad);
    textoVelocidad.textContent = 'una vuelta cada ' + AJUSTES.segundosPorVuelta(estado.velocidad) + ' s';
  }

  botonGirar.addEventListener('click', function () {
    estado.girando = !estado.girando;
    actualizarControlesGiro();
    guardarAjustes();
  });
  deslizadorVelocidad.addEventListener('input', function () {
    estado.velocidad = AJUSTES.limitarVelocidad(deslizadorVelocidad.value);
    actualizarControlesGiro();
    guardarAjustes();
  });
  botonReiniciar.addEventListener('click', function () {
    estado.yaw = 0.5;
    estado.pitch = 0.25;
    estado.distancia = 3.4;
  });

  construirLista();
  deslizadorVelocidad.min = String(AJUSTES.VELOCIDAD_MINIMA);
  deslizadorVelocidad.max = String(AJUSTES.VELOCIDAD_MAXIMA);
  actualizarControlesGiro();
  if (gl) {
    try {
      inicializarGL();
    } catch (e) {
      gl = null;
      aviso.hidden = false;
      aviso.textContent = 'No se pudo preparar WebGL: ' + e.message;
      canvas.hidden = true;
    }
  }
  seleccionar('tierra');
  if (gl) requestAnimationFrame(bucle);
})();
