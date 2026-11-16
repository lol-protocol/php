/*
 * Visualizaciones al estilo de Winamp: el analizador de espectro clásico con sus picos que
 * caen, un osciloscopio, una «MilkDrop» (cada cuadro se dibuja sobre el anterior, acercado,
 * girado y deformado, con la onda encima) y una lluvia de notas que muestra qué toca cada
 * voz. Todas reaccionan al sonido (FFT y onda) y la MilkDrop además al pulso y a los
 * cambios de sección del generador.
 *
 * Solo navegador. La MilkDrop usa WebGL2; sin WebGL2 cae a una versión en canvas 2D.
 */
(function (raiz) {
  'use strict';

  const MODOS = ['milkdrop', 'espectro', 'notas', 'osciloscopio'];
  const NOMBRES_MODOS = { milkdrop: 'MilkDrop', espectro: 'Espectro', notas: 'Lluvia de notas', osciloscopio: 'Osciloscopio' };

  const COLOR_VOZ = { melodia: [255, 214, 92], acordes: [120, 170, 255], bajo: [255, 96, 140], arpegio: [120, 255, 200], bateria: [200, 200, 200], midi: [255, 180, 120] };

  // Ajustes de la MilkDrop. Se pasa de uno a otro de a poco.
  const PRESETS = [
    { nombre: 'Túnel', zoom: 1.035, rot: 0.004, warp: 0.004, warpFrec: 6, decay: 0.965, tono: 0.002, onda: 0, caleido: 0, brillo: 1 },
    { nombre: 'Remolino', zoom: 1.012, rot: 0.02, warp: 0.009, warpFrec: 3, decay: 0.975, tono: 0.004, onda: 1, caleido: 0, brillo: 0.9 },
    { nombre: 'Caleidoscopio', zoom: 1.02, rot: -0.008, warp: 0.006, warpFrec: 9, decay: 0.955, tono: 0.003, onda: 2, caleido: 1, brillo: 1 },
    { nombre: 'Nebulosa', zoom: 0.992, rot: 0.003, warp: 0.012, warpFrec: 2, decay: 0.982, tono: 0.001, onda: 0, caleido: 0, brillo: 0.8 },
    { nombre: 'Espejo', zoom: 1.04, rot: 0, warp: 0.003, warpFrec: 12, decay: 0.94, tono: 0.006, onda: 3, caleido: 1, brillo: 1.1 },
    { nombre: 'Lissajous', zoom: 1.006, rot: -0.015, warp: 0.002, warpFrec: 5, decay: 0.97, tono: 0.003, onda: 2, caleido: 0, brillo: 1 },
  ];
  const CLAVES_PRESET = ['zoom', 'rot', 'warp', 'warpFrec', 'decay', 'tono', 'onda', 'caleido', 'brillo'];

  function Visualizador(canvas, motor) {
    this.canvas = canvas;
    this.motor = motor;
    this.modo = null;
    this.notas = [];
    this.info = null;
    this.activo = false;
    this.t0 = performance.now();
    this.picos = null;
    this.alturas = null;
    this.energiaMedia = 0;
    this.pulso = 0;
    this.presetActual = Object.assign({}, PRESETS[0]);
    this.presetObjetivo = PRESETS[0];
    this.cambioPreset = performance.now();
    this.indicePreset = 0;
    this.tono = 0;

    const ctx = motor.ctx;
    this.analizador = ctx.createAnalyser();
    this.analizador.fftSize = 4096;
    this.analizador.smoothingTimeConstant = 0.72;
    this.analizador.minDecibels = -92;
    this.analizador.maxDecibels = -18;
    motor.salida.connect(this.analizador);
    const split = ctx.createChannelSplitter(2);
    motor.salida.connect(split);
    this.anI = ctx.createAnalyser();
    this.anD = ctx.createAnalyser();
    this.anI.fftSize = this.anD.fftSize = 1024;
    split.connect(this.anI, 0);
    split.connect(this.anD, 1);
    this.frec = new Uint8Array(this.analizador.frequencyBinCount);
    this.ondaI = new Float32Array(1024);
    this.ondaD = new Float32Array(1024);

    this.redimensionar();
    const self = this;
    window.addEventListener('resize', function () {
      self.redimensionar();
    });
  }

  Visualizador.MODOS = MODOS;
  Visualizador.NOMBRES_MODOS = NOMBRES_MODOS;

  Visualizador.prototype.redimensionar = function () {
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    this.canvas.width = Math.floor(this.canvas.clientWidth * dpr) || 800;
    this.canvas.height = Math.floor(this.canvas.clientHeight * dpr) || 450;
    this.picos = null;
    if (this.gl) this.crearTexturas();
  };

  Visualizador.prototype.ponerModo = function (modo) {
    if (MODOS.indexOf(modo) < 0) return;
    this.modo = modo;
    // un canvas no puede tener contexto 2D y WebGL a la vez: si hay que cambiar, se reemplaza
    if (modo === 'milkdrop' && !this.sinGl) {
      if (!this.gl) {
        if (this.c2d) this.reemplazarCanvas();
        this.iniciarGl();
      }
    } else if (this.gl) {
      this.reemplazarCanvas();
    }
  };

  Visualizador.prototype.reemplazarCanvas = function () {
    const nuevo = this.canvas.cloneNode(false);
    this.canvas.parentNode.replaceChild(nuevo, this.canvas);
    this.canvas = nuevo;
    this.gl = null;
    this.texs = null; // eran del contexto anterior
    this.c2d = null;
    this.redimensionar();
    if (this.alReemplazar) this.alReemplazar(nuevo);
  };

  Visualizador.prototype.siguienteModo = function () {
    this.ponerModo(MODOS[(MODOS.indexOf(this.modo) + 1) % MODOS.length]);
    return this.modo;
  };

  Visualizador.prototype.siguientePreset = function () {
    this.indicePreset = (this.indicePreset + 1 + Math.floor(Math.random() * (PRESETS.length - 1))) % PRESETS.length;
    this.presetObjetivo = PRESETS[this.indicePreset];
    this.cambioPreset = performance.now();
    return this.presetObjetivo.nombre;
  };

  /** Cada nota tocada (con su tiempo de audio) alimenta la lluvia de notas. */
  Visualizador.prototype.nota = function (ev, t) {
    this.notas.push({ nota: ev.nota, voz: ev.voz, t: t, dur: ev.dur, vel: ev.vel, canal: ev.canal });
    if (this.notas.length > 1500) this.notas.splice(0, 500);
  };

  Visualizador.prototype.alInfo = function (info) {
    const cambioSeccion = !this.info || info.seccion !== this.info.seccion;
    this.info = info;
    if (cambioSeccion) this.siguientePreset();
  };

  Visualizador.prototype.iniciar = function () {
    if (this.activo) return;
    this.activo = true;
    const self = this;
    function cuadro() {
      if (!self.activo) return;
      self.dibujar();
      requestAnimationFrame(cuadro);
    }
    requestAnimationFrame(cuadro);
  };

  Visualizador.prototype.detener = function () {
    this.activo = false;
  };

  Visualizador.prototype.leerAudio = function () {
    this.analizador.getByteFrequencyData(this.frec);
    this.anI.getFloatTimeDomainData(this.ondaI);
    this.anD.getFloatTimeDomainData(this.ondaD);
    // energía de graves (hasta ~150 Hz) para el pulso
    const binHz = this.motor.ctx.sampleRate / this.analizador.fftSize;
    const hasta = Math.max(2, Math.floor(150 / binHz));
    let e = 0;
    for (let i = 1; i < hasta; i++) e += this.frec[i];
    e /= hasta * 255;
    this.energia = e;
    if (e > this.energiaMedia * 1.25 && e > 0.25 && this.pulso < 0.3) this.pulso = 1;
    this.energiaMedia = this.energiaMedia * 0.96 + e * 0.04;
    this.pulso *= 0.9;
    let total = 0;
    for (let i = 0; i < this.frec.length; i += 4) total += this.frec[i];
    this.volumen = total / (this.frec.length / 4) / 255;
  };

  Visualizador.prototype.dibujar = function () {
    this.leerAudio();
    if (this.modo === 'milkdrop') {
      if (this.gl) this.dibujarGl();
      else this.dibujarMilkdrop2d();
      return;
    }
    if (!this.c2d) this.c2d = this.canvas.getContext('2d');
    if (!this.c2d) return;
    if (this.modo === 'espectro') this.dibujarEspectro();
    else if (this.modo === 'notas') this.dibujarNotas();
    else this.dibujarOsciloscopio();
  };

  // ------------------------------------------------------------ espectro clásico

  Visualizador.prototype.dibujarEspectro = function () {
    const c = this.c2d;
    const W = this.canvas.width;
    const H = this.canvas.height;
    c.fillStyle = '#05070a';
    c.fillRect(0, 0, W, H);
    const ancho = Math.max(8, Math.round(W / 64));
    const n = Math.floor(W / ancho);
    if (!this.picos || this.picos.length !== n) {
      this.picos = new Float32Array(n);
      this.velPicos = new Float32Array(n);
      this.alturas = new Float32Array(n);
    }
    const sr = this.motor.ctx.sampleRate;
    const binHz = sr / this.analizador.fftSize;
    const fMin = 30;
    const fMax = Math.min(18000, sr / 2);
    const grad = c.createLinearGradient(0, H, 0, 0);
    grad.addColorStop(0, '#0f9b3a');
    grad.addColorStop(0.55, '#c8e02a');
    grad.addColorStop(0.8, '#ffb300');
    grad.addColorStop(1, '#ff3020');
    const segmento = Math.max(3, Math.round(H / 60));
    for (let i = 0; i < n; i++) {
      const f0 = fMin * Math.pow(fMax / fMin, i / n);
      const f1 = fMin * Math.pow(fMax / fMin, (i + 1) / n);
      const b0 = Math.max(1, Math.floor(f0 / binHz));
      const b1 = Math.max(b0 + 1, Math.ceil(f1 / binHz));
      let v = 0;
      for (let b = b0; b < b1 && b < this.frec.length; b++) v = Math.max(v, this.frec[b]);
      v = v / 255;
      v = Math.pow(v, 1.4);
      // las barras suben rápido y bajan con gravedad, como en Winamp
      this.alturas[i] = Math.max(v, this.alturas[i] - 0.025);
      const h = this.alturas[i] * H * 0.92;
      if (h > this.picos[i]) {
        this.picos[i] = h;
        this.velPicos[i] = 0;
      } else {
        this.velPicos[i] += 0.35;
        this.picos[i] = Math.max(0, this.picos[i] - this.velPicos[i]);
      }
      const x = i * ancho;
      c.fillStyle = grad;
      // barras en segmentos, como un vúmetro de LED
      for (let y = 0; y < h; y += segmento) {
        c.fillRect(x + 1, H - y - segmento + 1, ancho - 2, segmento - 1);
      }
      c.fillStyle = '#e8f0ff';
      c.fillRect(x + 1, H - this.picos[i] - 3, ancho - 2, 2);
    }
    this.dibujarOndaSobre(c, W, H * 0.12, H * 0.08, 'rgba(160,255,190,0.55)');
  };

  Visualizador.prototype.dibujarOndaSobre = function (c, W, y0, alto, color) {
    c.strokeStyle = color;
    c.lineWidth = Math.max(1, W / 900);
    c.beginPath();
    const d = this.ondaI;
    for (let i = 0; i < d.length; i++) {
      const x = (i / (d.length - 1)) * W;
      const y = y0 + d[i] * alto;
      if (i) c.lineTo(x, y);
      else c.moveTo(x, y);
    }
    c.stroke();
  };

  // ------------------------------------------------------------ osciloscopio

  Visualizador.prototype.dibujarOsciloscopio = function () {
    const c = this.c2d;
    const W = this.canvas.width;
    const H = this.canvas.height;
    c.fillStyle = 'rgba(2,6,4,0.35)';
    c.fillRect(0, 0, W, H);
    c.lineWidth = Math.max(1.5, W / 500);
    c.shadowBlur = 12;
    const colores = ['#4dff9a', '#5ac8ff'];
    [this.ondaI, this.ondaD].forEach(function (d, k) {
      c.strokeStyle = colores[k];
      c.shadowColor = colores[k];
      c.beginPath();
      // se busca un cruce por cero para que la onda no tiemble
      let ini = 0;
      for (let i = 1; i < d.length / 2; i++) {
        if (d[i - 1] < 0 && d[i] >= 0) {
          ini = i;
          break;
        }
      }
      const n = d.length / 2;
      for (let i = 0; i < n; i++) {
        const x = (i / (n - 1)) * W;
        const y = H * (k ? 0.68 : 0.32) + d[ini + i] * H * 0.3;
        if (i) c.lineTo(x, y);
        else c.moveTo(x, y);
      }
      c.stroke();
    });
    c.shadowBlur = 0;
    // Lissajous pequeño en la esquina
    const r = Math.min(W, H) * 0.12;
    const cx = W - r * 1.3;
    const cy = r * 1.3;
    c.strokeStyle = 'rgba(255,220,120,0.7)';
    c.lineWidth = 1;
    c.beginPath();
    for (let i = 0; i < this.ondaI.length; i += 2) {
      const x = cx + (this.ondaI[i] - this.ondaD[i]) * r * 1.4;
      const y = cy - (this.ondaI[i] + this.ondaD[i]) * r * 1.4;
      if (i) c.lineTo(x, y);
      else c.moveTo(x, y);
    }
    c.stroke();
  };

  // ------------------------------------------------------------ lluvia de notas

  Visualizador.prototype.dibujarNotas = function () {
    const c = this.c2d;
    const W = this.canvas.width;
    const H = this.canvas.height;
    const ahora = this.motor.ctx.currentTime;
    c.fillStyle = 'rgba(4,4,12,0.28)';
    c.fillRect(0, 0, W, H);
    // línea de "ahora" a un 78 % del ancho: a la derecha viene lo que va a sonar
    const xAhora = W * 0.78;
    const segPorAncho = 9;
    const px = W / segPorAncho;
    const notaMin = 28;
    const notaMax = 96;
    const alto = H / (notaMax - notaMin);
    c.strokeStyle = 'rgba(255,255,255,0.25)';
    c.beginPath();
    c.moveTo(xAhora, 0);
    c.lineTo(xAhora, H);
    c.stroke();
    let vivas = 0;
    for (const n of this.notas) {
      const x0 = xAhora + (n.t - ahora) * px;
      const x1 = x0 + Math.max(2, n.dur * px);
      if (x1 < 0 || x0 > W) continue;
      vivas++;
      let y;
      if (n.canal === 9) y = H - alto * 3 - ((n.nota * 37) % 11) * alto * 0.6;
      else y = H - (Math.max(notaMin, Math.min(notaMax, n.nota)) - notaMin) * alto;
      const col = COLOR_VOZ[n.voz] || COLOR_VOZ.midi;
      const sonando = n.t <= ahora && n.t + n.dur >= ahora;
      const a = sonando ? 1 : n.t > ahora ? 0.35 : 0.55;
      c.fillStyle = 'rgba(' + col[0] + ',' + col[1] + ',' + col[2] + ',' + a + ')';
      if (sonando) {
        c.shadowColor = 'rgb(' + col.join(',') + ')';
        c.shadowBlur = 18;
      }
      const h = Math.max(3, alto * (n.canal === 9 ? 0.5 : 0.85));
      c.fillRect(x0, y - h / 2, x1 - x0, h);
      c.shadowBlur = 0;
      if (sonando && n.voz === 'melodia') {
        // chispa en la línea de ahora
        const rr = 4 + (n.vel / 127) * 10 * (1 + this.pulso);
        c.beginPath();
        c.arc(xAhora, y, rr, 0, Math.PI * 2);
        c.fill();
      }
    }
    if (!vivas && this.notas.length > 1000) this.notas = [];
  };

  // ------------------------------------------------------------ MilkDrop en 2D (respaldo)

  Visualizador.prototype.dibujarMilkdrop2d = function () {
    if (!this.c2d) this.c2d = this.canvas.getContext('2d');
    const c = this.c2d;
    const W = this.canvas.width;
    const H = this.canvas.height;
    const p = this.interpolarPreset();
    c.save();
    c.globalAlpha = p.decay;
    c.translate(W / 2, H / 2);
    c.rotate(p.rot * (1 + this.pulso));
    const z = p.zoom + this.pulso * 0.02;
    c.scale(z, z);
    c.translate(-W / 2, -H / 2);
    c.drawImage(this.canvas, 0, 0);
    c.restore();
    c.fillStyle = 'rgba(0,0,0,0.04)';
    c.fillRect(0, 0, W, H);
    this.tono = (this.tono + p.tono * 360) % 360;
    c.strokeStyle = 'hsl(' + this.tono + ',90%,' + (55 + this.pulso * 20) + '%)';
    c.lineWidth = 2;
    c.beginPath();
    const r = Math.min(W, H) * 0.22;
    for (let i = 0; i < this.ondaI.length; i += 2) {
      const ang = (i / this.ondaI.length) * Math.PI * 2;
      const rr = r * (1 + this.ondaI[i] * 0.8);
      const x = W / 2 + Math.cos(ang) * rr;
      const y = H / 2 + Math.sin(ang) * rr;
      if (i) c.lineTo(x, y);
      else c.moveTo(x, y);
    }
    c.closePath();
    c.stroke();
  };

  Visualizador.prototype.interpolarPreset = function () {
    const p = this.presetActual;
    const o = this.presetObjetivo;
    for (const k of CLAVES_PRESET) p[k] += (o[k] - p[k]) * 0.02;
    return p;
  };

  // ------------------------------------------------------------ MilkDrop en WebGL2

  const VS = '#version 300 es\nin vec2 p; out vec2 uv; void main(){ uv = p*0.5+0.5; gl_Position = vec4(p,0.,1.); }';

  const FS_WARP = [
    '#version 300 es',
    'precision highp float;',
    'in vec2 uv; out vec4 color;',
    'uniform sampler2D previo; uniform float zoom, rot, warp, warpFrec, decay, tono, tiempo, pulso, caleido, aspecto;',
    'vec3 girarTono(vec3 c, float a){',
    '  const vec3 k = vec3(0.57735);',
    '  float ca = cos(a);',
    '  return c*ca + cross(k,c)*sin(a) + k*dot(k,c)*(1.0-ca);',
    '}',
    'void main(){',
    '  vec2 c = uv - 0.5; c.x *= aspecto;',
    '  if (caleido > 0.5) { c = abs(c); }',
    '  float r = length(c);',
    '  float a = rot*(1.0+pulso*2.0) + warp*4.0*sin(r*8.0 - tiempo*0.7);',
    '  c = mat2(cos(a), -sin(a), sin(a), cos(a)) * c;',
    '  c /= zoom + pulso*0.025;',
    '  c += warp * vec2(sin(c.y*warpFrec + tiempo*1.1), cos(c.x*warpFrec*1.3 + tiempo*0.9));',
    '  c.x /= aspecto;',
    '  vec3 s = texture(previo, c + 0.5).rgb;',
    // un leve desenfoque evita el "aliasing" del zoom repetido
    '  vec2 px = vec2(1.0)/vec2(textureSize(previo,0));',
    '  s = s*0.6 + 0.1*(texture(previo, c+0.5+vec2(px.x,0.)).rgb + texture(previo, c+0.5-vec2(px.x,0.)).rgb + texture(previo, c+0.5+vec2(0.,px.y)).rgb + texture(previo, c+0.5-vec2(0.,px.y)).rgb);',
    '  s = girarTono(s, tono*6.2831);',
    '  color = vec4(s*decay - 0.002, 1.0);',
    '}',
  ].join('\n');

  const VS_LINEA = '#version 300 es\nin vec2 p; in float b; out float brillo; uniform float aspecto; void main(){ brillo=b; gl_Position = vec4(p.x/aspecto, p.y, 0., 1.); gl_PointSize = 3.0; }';
  const FS_LINEA = '#version 300 es\nprecision mediump float; in float brillo; out vec4 color; uniform vec3 col; void main(){ color = vec4(col*brillo, 1.0); }';
  const FS_PANTALLA = [
    '#version 300 es',
    'precision mediump float;',
    'in vec2 uv; out vec4 color; uniform sampler2D tex; uniform float brillo;',
    'void main(){ vec3 c = texture(tex, uv).rgb * brillo; c = c/(1.0+c*0.25); color = vec4(pow(c, vec3(0.9)), 1.0); }',
  ].join('\n');

  function compilar(gl, vs, fs) {
    function sh(tipo, src) {
      const s = gl.createShader(tipo);
      gl.shaderSource(s, src);
      gl.compileShader(s);
      if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s));
      return s;
    }
    const p = gl.createProgram();
    gl.attachShader(p, sh(gl.VERTEX_SHADER, vs));
    gl.attachShader(p, sh(gl.FRAGMENT_SHADER, fs));
    gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p));
    const u = {};
    const n = gl.getProgramParameter(p, gl.ACTIVE_UNIFORMS);
    for (let i = 0; i < n; i++) {
      const info = gl.getActiveUniform(p, i);
      u[info.name] = gl.getUniformLocation(p, info.name);
    }
    return { p: p, u: u };
  }

  Visualizador.prototype.iniciarGl = function () {
    let gl = null;
    try {
      gl = this.canvas.getContext('webgl2', { antialias: false, preserveDrawingBuffer: false });
    } catch (e) {
      gl = null;
    }
    if (!gl) {
      this.sinGl = true;
      return;
    }
    try {
      this.gl = gl;
      this.pWarp = compilar(gl, VS, FS_WARP);
      this.pLinea = compilar(gl, VS_LINEA, FS_LINEA);
      this.pPantalla = compilar(gl, VS, FS_PANTALLA);
      this.quad = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, this.quad);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      this.bufLinea = gl.createBuffer();
      this.datosLinea = new Float32Array(2048 * 3);
      this.crearTexturas();
    } catch (e) {
      console.warn('WebGL2 no disponible para la MilkDrop:', e);
      this.gl = null;
      this.sinGl = true;
    }
  };

  Visualizador.prototype.crearTexturas = function () {
    const gl = this.gl;
    // a media resolución: se ve igual (es borroso por naturaleza) y cuesta un cuarto
    const w = Math.max(64, this.canvas.width >> 1);
    const h = Math.max(64, this.canvas.height >> 1);
    if (this.texs) for (const t of this.texs) { gl.deleteTexture(t.tex); gl.deleteFramebuffer(t.fb); }
    this.texs = [];
    for (let i = 0; i < 2; i++) {
      const tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.MIRRORED_REPEAT);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.MIRRORED_REPEAT);
      const fb = gl.createFramebuffer();
      gl.bindFramebuffer(gl.FRAMEBUFFER, fb);
      gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, tex, 0);
      gl.clearColor(0, 0, 0, 1);
      gl.clear(gl.COLOR_BUFFER_BIT);
      this.texs.push({ tex: tex, fb: fb, w: w, h: h });
    }
    gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    this.actual = 0;
  };

  Visualizador.prototype.cuadrado = function (prog) {
    const gl = this.gl;
    gl.bindBuffer(gl.ARRAY_BUFFER, this.quad);
    const loc = gl.getAttribLocation(prog.p, 'p');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    gl.disableVertexAttribArray(loc);
  };

  Visualizador.prototype.dibujarGl = function () {
    const gl = this.gl;
    if (gl.isContextLost()) return;
    const p = this.interpolarPreset();
    const ahora = (performance.now() - this.t0) / 1000;
    // cambio de ajuste cada 30 s aunque no cambie la sección
    if (performance.now() - this.cambioPreset > 30000) this.siguientePreset();
    const origen = this.texs[this.actual];
    const destino = this.texs[1 - this.actual];
    const aspecto = this.canvas.width / this.canvas.height;

    // 1) deformar el cuadro anterior
    gl.bindFramebuffer(gl.FRAMEBUFFER, destino.fb);
    gl.viewport(0, 0, destino.w, destino.h);
    gl.useProgram(this.pWarp.p);
    gl.activeTexture(gl.TEXTURE0);
    gl.bindTexture(gl.TEXTURE_2D, origen.tex);
    const u = this.pWarp.u;
    gl.uniform1i(u.previo, 0);
    gl.uniform1f(u.zoom, p.zoom);
    gl.uniform1f(u.rot, p.rot);
    gl.uniform1f(u.warp, p.warp * (1 + (this.energia || 0)));
    gl.uniform1f(u.warpFrec, p.warpFrec);
    gl.uniform1f(u.decay, p.decay);
    gl.uniform1f(u.tono, p.tono);
    gl.uniform1f(u.tiempo, ahora);
    gl.uniform1f(u.pulso, this.pulso);
    gl.uniform1f(u.caleido, p.caleido);
    gl.uniform1f(u.aspecto, aspecto);
    this.cuadrado(this.pWarp);

    // 2) la onda encima
    this.tono = (this.tono + 0.6 + this.pulso * 3) % 360;
    const h = this.tono / 60;
    const x = 1 - Math.abs((h % 2) - 1);
    const rgb = h < 1 ? [1, x, 0] : h < 2 ? [x, 1, 0] : h < 3 ? [0, 1, x] : h < 4 ? [0, x, 1] : h < 5 ? [x, 0, 1] : [1, 0, x];
    const n = this.armarOnda(Math.round(p.onda) % 4, ahora);
    gl.useProgram(this.pLinea.p);
    gl.uniform3f(this.pLinea.u.col, rgb[0], rgb[1], rgb[2]);
    gl.uniform1f(this.pLinea.u.aspecto, 1);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.bufLinea);
    gl.bufferData(gl.ARRAY_BUFFER, this.datosLinea.subarray(0, n * 3), gl.STREAM_DRAW);
    const lp = gl.getAttribLocation(this.pLinea.p, 'p');
    const lb = gl.getAttribLocation(this.pLinea.p, 'b');
    gl.enableVertexAttribArray(lp);
    gl.vertexAttribPointer(lp, 2, gl.FLOAT, false, 12, 0);
    gl.enableVertexAttribArray(lb);
    gl.vertexAttribPointer(lb, 1, gl.FLOAT, false, 12, 8);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE);
    gl.drawArrays(gl.LINE_STRIP, 0, n);
    gl.drawArrays(gl.POINTS, 0, n);
    gl.disable(gl.BLEND);
    gl.disableVertexAttribArray(lp);
    gl.disableVertexAttribArray(lb);

    // 3) a la pantalla
    gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    gl.viewport(0, 0, this.canvas.width, this.canvas.height);
    gl.useProgram(this.pPantalla.p);
    gl.bindTexture(gl.TEXTURE_2D, destino.tex);
    gl.uniform1i(this.pPantalla.u.tex, 0);
    gl.uniform1f(this.pPantalla.u.brillo, p.brillo * (1 + this.pulso * 0.3));
    this.cuadrado(this.pPantalla);
    this.actual = 1 - this.actual;
  };

  /** Llena datosLinea con la onda en la forma del ajuste; devuelve cuántos puntos. */
  Visualizador.prototype.armarOnda = function (forma, t) {
    const d = this.datosLinea;
    const L = this.ondaI;
    const R = this.ondaD;
    const aspecto = this.canvas.width / this.canvas.height;
    const amp = 0.5 + (this.energia || 0) * 0.8;
    let n = 0;
    const brillo = 0.55 + this.pulso * 0.6;
    if (forma === 0) {
      // círculo que respira
      const N = 512;
      const r = 0.32 + this.pulso * 0.08;
      for (let i = 0; i <= N; i++) {
        const k = i % N;
        const a = (k / N) * Math.PI * 2 + t * 0.2;
        const rr = r + L[k * 2] * 0.35 * amp;
        d[n * 3] = (Math.cos(a) * rr) / aspecto;
        d[n * 3 + 1] = Math.sin(a) * rr;
        d[n * 3 + 2] = brillo;
        n++;
      }
    } else if (forma === 1) {
      // línea horizontal ondulante
      for (let i = 0; i < 1024; i += 2) {
        d[n * 3] = (i / 1023) * 2 - 1;
        d[n * 3 + 1] = L[i] * 0.8 * amp + Math.sin(t * 0.5) * 0.2;
        d[n * 3 + 2] = brillo;
        n++;
      }
    } else if (forma === 2) {
      // Lissajous estéreo
      for (let i = 0; i < 1024; i++) {
        d[n * 3] = ((L[i] - R[i]) * 1.2 * amp) / aspecto;
        d[n * 3 + 1] = (L[i] + R[i]) * 1.2 * amp;
        d[n * 3 + 2] = brillo;
        n++;
      }
    } else {
      // espiral con el espectro
      const N = 600;
      const binMax = Math.min(this.frec.length, 700);
      for (let i = 0; i < N; i++) {
        const a = (i / N) * Math.PI * 6 + t * 0.4;
        const v = this.frec[Math.floor((i / N) * binMax)] / 255;
        const rr = 0.05 + (i / N) * 0.6 + v * 0.12;
        d[n * 3] = (Math.cos(a) * rr) / aspecto;
        d[n * 3 + 1] = Math.sin(a) * rr;
        d[n * 3 + 2] = brillo * (0.4 + v);
        n++;
      }
    }
    return n;
  };

  raiz.Visualizador = Visualizador;
})(typeof globalThis !== 'undefined' ? globalThis : this);
