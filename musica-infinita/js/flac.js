/*
 * Codificador FLAC (sin pérdida) y escritor WAV, en JavaScript puro (navegador y Node).
 *
 * Sirve para guardar lo que suena: la grabación en vivo y la exportación de N minutos de
 * música. FLAC guarda exactamente las mismas muestras que un WAV, en cerca de la mitad
 * del espacio.
 *
 * Usa predictores fijos (órdenes 0 a 4) con residuos en código de Rice y particiones, y
 * decorrelación estéreo (izquierda/lateral, derecha/lateral o medio/lateral, la que
 * ocupe menos), como el modo rápido de la herramienta flac. No usa LPC: comprime un poco
 * menos (en torno a un 5-10 %), pero es simple y rápido. El MD5 de STREAMINFO va
 * calculado, así que `flac -t` y ffmpeg pueden verificar el archivo.
 */
(function (raiz) {
  'use strict';

  const TAM_BLOQUE = 4096;

  // ------------------------------------------------------------ bits

  function Bits(capacidad) {
    this.buf = new Uint8Array(capacidad || 65536);
    this.pos = 0; // bytes completos
    this.acc = 0; // bits pendientes
    this.nacc = 0;
  }
  Bits.prototype.asegurar = function (n) {
    if (this.pos + n <= this.buf.length) return;
    let cap = this.buf.length * 2;
    while (cap < this.pos + n) cap *= 2;
    const nb = new Uint8Array(cap);
    nb.set(this.buf.subarray(0, this.pos));
    this.buf = nb;
  };
  /** Escribe los n bits bajos de v (n <= 24). */
  Bits.prototype.escribir = function (v, n) {
    if (n > 24) {
      this.escribir(Math.floor(v / 16777216) & ((1 << (n - 24)) - 1), n - 24);
      this.escribir(v & 0xffffff, 24);
      return;
    }
    this.acc = (this.acc << n) | (v & ((1 << n) - 1));
    this.nacc += n;
    if (this.nacc >= 8) {
      this.asegurar(4);
      while (this.nacc >= 8) {
        this.nacc -= 8;
        this.buf[this.pos++] = (this.acc >>> this.nacc) & 0xff;
      }
      this.acc &= (1 << this.nacc) - 1;
    }
  };
  Bits.prototype.ceros = function (n) {
    while (n >= 24) {
      this.escribir(0, 24);
      n -= 24;
    }
    if (n > 0) this.escribir(0, n);
  };
  Bits.prototype.alinear = function () {
    if (this.nacc > 0) this.escribir(0, 8 - this.nacc);
  };
  Bits.prototype.bytes = function () {
    return this.buf.subarray(0, this.pos);
  };

  // ------------------------------------------------------------ CRC y MD5

  const TABLA_CRC8 = new Uint8Array(256);
  const TABLA_CRC16 = new Uint16Array(256);
  for (let i = 0; i < 256; i++) {
    let c = i;
    for (let k = 0; k < 8; k++) c = c & 0x80 ? ((c << 1) ^ 0x07) & 0xff : (c << 1) & 0xff;
    TABLA_CRC8[i] = c;
    let d = i << 8;
    for (let k = 0; k < 8; k++) d = d & 0x8000 ? ((d << 1) ^ 0x8005) & 0xffff : (d << 1) & 0xffff;
    TABLA_CRC16[i] = d;
  }
  function crc8(b, desde, hasta) {
    let c = 0;
    for (let i = desde; i < hasta; i++) c = TABLA_CRC8[c ^ b[i]];
    return c;
  }
  function crc16(b, desde, hasta) {
    let c = 0;
    for (let i = desde; i < hasta; i++) c = ((c << 8) ^ TABLA_CRC16[(c >> 8) ^ b[i]]) & 0xffff;
    return c;
  }

  // MD5 incremental (RFC 1321).
  const MD5_S = [7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21];
  const MD5_K = new Int32Array(64);
  for (let i = 0; i < 64; i++) MD5_K[i] = Math.floor(Math.abs(Math.sin(i + 1)) * 4294967296) | 0;

  function Md5() {
    this.h = new Int32Array([0x67452301, 0xefcdab89 | 0, 0x98badcfe | 0, 0x10325476]);
    this.bloque = new Uint8Array(64);
    this.n = 0; // bytes en el bloque
    this.total = 0;
    this.w = new Int32Array(16);
  }
  Md5.prototype.procesar = function (b) {
    const w = this.w;
    for (let i = 0; i < 16; i++) w[i] = b[i * 4] | (b[i * 4 + 1] << 8) | (b[i * 4 + 2] << 16) | (b[i * 4 + 3] << 24);
    let a = this.h[0];
    let bb = this.h[1];
    let c = this.h[2];
    let d = this.h[3];
    for (let i = 0; i < 64; i++) {
      let f;
      let g;
      if (i < 16) {
        f = (bb & c) | (~bb & d);
        g = i;
      } else if (i < 32) {
        f = (d & bb) | (~d & c);
        g = (5 * i + 1) & 15;
      } else if (i < 48) {
        f = bb ^ c ^ d;
        g = (3 * i + 5) & 15;
      } else {
        f = c ^ (bb | ~d);
        g = (7 * i) & 15;
      }
      const tmp = d;
      d = c;
      c = bb;
      const x = (a + f + MD5_K[i] + w[g]) | 0;
      bb = (bb + ((x << MD5_S[i]) | (x >>> (32 - MD5_S[i])))) | 0;
      a = tmp;
    }
    this.h[0] = (this.h[0] + a) | 0;
    this.h[1] = (this.h[1] + bb) | 0;
    this.h[2] = (this.h[2] + c) | 0;
    this.h[3] = (this.h[3] + d) | 0;
  };
  Md5.prototype.byte = function (v) {
    this.bloque[this.n++] = v;
    this.total++;
    if (this.n === 64) {
      this.procesar(this.bloque);
      this.n = 0;
    }
  };
  Md5.prototype.actualizar = function (bytes) {
    let i = 0;
    while (i < bytes.length && this.n !== 0) this.byte(bytes[i++]);
    // bloques enteros sin copiar byte a byte
    for (; i + 64 <= bytes.length; i += 64) {
      this.procesar(bytes.subarray(i, i + 64));
      this.total += 64;
    }
    while (i < bytes.length) this.byte(bytes[i++]);
  };
  Md5.prototype.final = function () {
    const bits = this.total * 8;
    this.byte(0x80);
    while (this.n !== 56) this.byte(0);
    const lo = bits >>> 0;
    const hi = Math.floor(bits / 4294967296) >>> 0;
    for (let i = 0; i < 4; i++) this.byte((lo >>> (8 * i)) & 0xff);
    for (let i = 0; i < 4; i++) this.byte((hi >>> (8 * i)) & 0xff);
    const out = new Uint8Array(16);
    for (let i = 0; i < 4; i++) for (let k = 0; k < 4; k++) out[i * 4 + k] = (this.h[i] >>> (8 * k)) & 0xff;
    return out;
  };

  function md5(bytes) {
    const m = new Md5();
    m.actualizar(bytes);
    return m.final();
  }

  // ------------------------------------------------------------ subtramas

  // Residuo del predictor fijo de orden o.
  function residuo(x, n, o, out) {
    switch (o) {
      case 0:
        for (let i = 0; i < n; i++) out[i] = x[i];
        break;
      case 1:
        for (let i = 1; i < n; i++) out[i] = x[i] - x[i - 1];
        break;
      case 2:
        for (let i = 2; i < n; i++) out[i] = x[i] - 2 * x[i - 1] + x[i - 2];
        break;
      case 3:
        for (let i = 3; i < n; i++) out[i] = x[i] - 3 * x[i - 1] + 3 * x[i - 2] - x[i - 3];
        break;
      default:
        for (let i = 4; i < n; i++) out[i] = x[i] - 4 * x[i - 1] + 6 * x[i - 2] - 4 * x[i - 3] + x[i - 4];
    }
  }

  function sumaAbs(r, desde, n) {
    let s = 0;
    for (let i = desde; i < n; i++) s += r[i] < 0 ? -r[i] : r[i];
    return s;
  }

  /** Mejor orden fijo para x: {orden, estimacion} (estimación en "suma de residuos"). */
  function mejorOrden(x, n, tmp) {
    let mejor = 0;
    let est = Infinity;
    const maxO = Math.min(4, n - 1);
    for (let o = 0; o <= maxO; o++) {
      residuo(x, n, o, tmp);
      const s = sumaAbs(tmp, o, n) / Math.max(1, n - o);
      if (s < est) {
        est = s;
        mejor = o;
      }
    }
    return { orden: mejor, estimacion: est };
  }

  function bitsRice(u, desde, hasta, k) {
    let s = (hasta - desde) * (k + 1);
    for (let i = desde; i < hasta; i++) s += u[i] >>> k;
    return s;
  }

  /** Plan de particiones de Rice: {ordenParticion, parametros[], bits}. */
  function planRice(u, n, orden, maxParam) {
    let mejor = null;
    let maxPO = 0;
    while (maxPO < 8 && n % (1 << (maxPO + 1)) === 0 && (n >> (maxPO + 1)) > orden) maxPO++;
    for (let po = 0; po <= maxPO; po++) {
      const partes = 1 << po;
      const tam = n >> po;
      const params = [];
      let total = 0;
      for (let p = 0; p < partes; p++) {
        const desde = p === 0 ? orden : p * tam;
        const hasta = (p + 1) * tam;
        let suma = 0;
        for (let i = desde; i < hasta; i++) suma += u[i];
        const media = suma / Math.max(1, hasta - desde);
        let k0 = media > 1 ? Math.floor(Math.log2(media)) : 0;
        let mk = k0;
        let mb = Infinity;
        for (let k = Math.max(0, k0 - 1); k <= Math.min(maxParam, k0 + 1); k++) {
          const b = bitsRice(u, desde, hasta, k);
          if (b < mb) {
            mb = b;
            mk = k;
          }
        }
        params.push(mk);
        total += mb + (maxParam > 14 ? 5 : 4);
      }
      if (!mejor || total < mejor.bits) mejor = { ordenParticion: po, parametros: params, bits: total };
    }
    return mejor;
  }

  /** Escribe una subtrama de un canal (bps bits por muestra). */
  function escribirSubtrama(w, x, n, bps, tmp, u) {
    // constante (silencio, por ejemplo)
    let constante = true;
    for (let i = 1; i < n && constante; i++) if (x[i] !== x[0]) constante = false;
    if (constante) {
      w.escribir(0, 1);
      w.escribir(0, 6);
      w.escribir(0, 1);
      w.escribir(x[0] & ((1 << bps) - 1), bps);
      return;
    }
    const o = mejorOrden(x, n, tmp).orden;
    residuo(x, n, o, tmp);
    for (let i = o; i < n; i++) u[i] = tmp[i] >= 0 ? tmp[i] * 2 : -tmp[i] * 2 - 1;
    const maxParam = bps > 16 ? 30 : 14;
    const plan = planRice(u, n, o, maxParam);
    const bitsFijo = 8 + o * bps + 2 + 4 + plan.bits;
    if (bitsFijo >= 8 + n * bps) {
      // verbatim: no comprime
      w.escribir(0, 1);
      w.escribir(1, 6);
      w.escribir(0, 1);
      for (let i = 0; i < n; i++) w.escribir(x[i] & ((1 << bps) - 1), bps);
      return;
    }
    w.escribir(0, 1);
    w.escribir(8 | o, 6); // 001xxx: fijo de orden xxx
    w.escribir(0, 1);
    for (let i = 0; i < o; i++) w.escribir(x[i] & ((1 << bps) - 1), bps);
    const ancho = maxParam > 14 ? 5 : 4;
    w.escribir(maxParam > 14 ? 1 : 0, 2);
    w.escribir(plan.ordenParticion, 4);
    const tam = n >> plan.ordenParticion;
    for (let p = 0; p < plan.parametros.length; p++) {
      const k = plan.parametros[p];
      w.escribir(k, ancho);
      const desde = p === 0 ? o : p * tam;
      const hasta = (p + 1) * tam;
      for (let i = desde; i < hasta; i++) {
        const v = u[i];
        const q = v >>> k;
        w.ceros(q);
        w.escribir(1, 1);
        if (k) w.escribir(v & ((1 << k) - 1), k);
      }
    }
  }

  function utf8Numero(w, v) {
    if (v < 0x80) {
      w.escribir(v, 8);
      return;
    }
    let n = 2;
    while (v >= Math.pow(2, 5 * n + 1)) n++;
    const primero = (0xff00 >> n) & 0xff;
    w.escribir(primero | (Math.floor(v / Math.pow(2, 6 * (n - 1))) & (0xff >> (n + 1))), 8);
    for (let i = n - 2; i >= 0; i--) w.escribir(0x80 | (Math.floor(v / Math.pow(2, 6 * i)) & 0x3f), 8);
  }

  const CODIGO_FRECUENCIA = { 88200: 1, 176400: 2, 192000: 3, 8000: 4, 16000: 5, 22050: 6, 24000: 7, 32000: 8, 44100: 9, 48000: 10, 96000: 11 };
  const CODIGO_BITS = { 8: 1, 12: 2, 16: 4, 20: 5, 24: 6 };

  // ------------------------------------------------------------ codificador

  /**
   * opciones: {frecuencia: 48000, canales: 2, bits: 16 | 24, etiquetas: {TITLE: '...'}}
   * agregar([Float32Array, Float32Array]) tantas veces como haga falta; terminar() da
   * un arreglo de Uint8Array (las partes del archivo, para new Blob(partes)).
   */
  function CodificadorFlac(opciones) {
    opciones = opciones || {};
    this.frecuencia = opciones.frecuencia || 48000;
    this.canales = opciones.canales || 2;
    this.bits = opciones.bits === 24 ? 24 : 16;
    this.etiquetas = opciones.etiquetas || {};
    this.tramperia = opciones.dither == null ? this.bits === 16 : !!opciones.dither;
    this.bloque = [];
    for (let c = 0; c < this.canales; c++) this.bloque.push(new Int32Array(TAM_BLOQUE));
    this.lleno = 0;
    this.numTrama = 0;
    this.totalMuestras = 0;
    this.tramas = [];
    this.minTrama = Infinity;
    this.maxTrama = 0;
    this.md5 = new Md5();
    this.bytesMuestra = this.bits / 8;
    this.tmp = new Int32Array(TAM_BLOQUE);
    this.u = new Uint32Array(TAM_BLOQUE);
    this.derivados = [new Int32Array(TAM_BLOQUE), new Int32Array(TAM_BLOQUE)];
    this.semillaDither = 22222;
  }

  CodificadorFlac.prototype.azar = function () {
    this.semillaDither = (Math.imul(this.semillaDither, 1103515245) + 12345) >>> 0;
    return this.semillaDither / 4294967296;
  };

  /** Agrega muestras en coma flotante (-1..1), una Float32Array por canal. */
  CodificadorFlac.prototype.agregar = function (canales) {
    const n = canales[0].length;
    const escala = this.bits === 24 ? 8388607 : 32767;
    const min = -escala - 1;
    for (let i = 0; i < n; i++) {
      for (let c = 0; c < this.canales; c++) {
        let v = (canales[c] || canales[0])[i] * escala;
        if (this.tramperia) v += this.azar() - this.azar(); // dither triangular de 1 LSB
        v = Math.round(v);
        this.bloque[c][this.lleno] = v > escala ? escala : v < min ? min : v;
      }
      this.lleno++;
      if (this.lleno === TAM_BLOQUE) this.vaciar();
    }
  };

  /** Agrega muestras enteras ya cuantizadas (para pruebas). */
  CodificadorFlac.prototype.agregarEnteros = function (canales) {
    const n = canales[0].length;
    for (let i = 0; i < n; i++) {
      for (let c = 0; c < this.canales; c++) this.bloque[c][this.lleno] = canales[c][i];
      this.lleno++;
      if (this.lleno === TAM_BLOQUE) this.vaciar();
    }
  };

  CodificadorFlac.prototype.vaciar = function () {
    const n = this.lleno;
    if (!n) return;
    const bps = this.bits;
    // MD5 de las muestras intercaladas, en little-endian con signo
    const bm = this.bytesMuestra;
    const crudo = new Uint8Array(n * this.canales * bm);
    let k = 0;
    for (let i = 0; i < n; i++) {
      for (let c = 0; c < this.canales; c++) {
        const v = this.bloque[c][i];
        crudo[k++] = v & 0xff;
        crudo[k++] = (v >> 8) & 0xff;
        if (bm === 3) crudo[k++] = (v >> 16) & 0xff;
      }
    }
    this.md5.actualizar(crudo);

    const w = new Bits(n * this.canales * bm + 64);
    w.escribir(0x3ffe, 14); // sincronía
    w.escribir(0, 1);
    w.escribir(0, 1); // bloques de tamaño fijo
    const codBloque = n === TAM_BLOQUE ? 12 : n <= 256 ? 6 : 7;
    w.escribir(codBloque, 4);
    w.escribir(CODIGO_FRECUENCIA[this.frecuencia] || 0, 4);

    // elección de la decorrelación estéreo
    let asignacion = this.canales - 1;
    let subtramas;
    if (this.canales === 2) {
      const L = this.bloque[0];
      const R = this.bloque[1];
      const M = this.derivados[0];
      const S = this.derivados[1];
      for (let i = 0; i < n; i++) {
        M[i] = (L[i] + R[i]) >> 1;
        S[i] = L[i] - R[i];
      }
      const eL = mejorOrden(L, n, this.tmp).estimacion;
      const eR = mejorOrden(R, n, this.tmp).estimacion;
      const eM = mejorOrden(M, n, this.tmp).estimacion;
      const eS = mejorOrden(S, n, this.tmp).estimacion;
      const opciones = [
        { a: 1, costo: eL + eR, s: [[L, bps], [R, bps]] },
        { a: 8, costo: eL + eS, s: [[L, bps], [S, bps + 1]] },
        { a: 9, costo: eS + eR, s: [[S, bps + 1], [R, bps]] },
        { a: 10, costo: eM + eS, s: [[M, bps], [S, bps + 1]] },
      ];
      opciones.sort(function (x, y) { return x.costo - y.costo; });
      asignacion = opciones[0].a;
      subtramas = opciones[0].s;
    } else {
      subtramas = this.bloque.map(function (x) { return [x, bps]; });
    }
    w.escribir(asignacion, 4);
    w.escribir(CODIGO_BITS[bps] || 0, 3);
    w.escribir(0, 1);
    utf8Numero(w, this.numTrama);
    if (codBloque === 6) w.escribir(n - 1, 8);
    else if (codBloque === 7) w.escribir(n - 1, 16);
    // CRC-8 de la cabecera
    w.escribir(crc8(w.buf, 0, w.pos), 8);
    for (const st of subtramas) escribirSubtrama(w, st[0], n, st[1], this.tmp, this.u);
    w.alinear();
    const c16 = crc16(w.buf, 0, w.pos);
    w.escribir(c16 >> 8, 8);
    w.escribir(c16 & 0xff, 8);
    const trama = w.bytes().slice();
    this.tramas.push(trama);
    if (trama.length < this.minTrama) this.minTrama = trama.length;
    if (trama.length > this.maxTrama) this.maxTrama = trama.length;
    this.numTrama++;
    this.totalMuestras += n;
    this.lleno = 0;
  };

  CodificadorFlac.prototype.terminar = function () {
    this.vaciar();
    const md5f = this.md5.final();
    const cab = new Bits(256);
    cab.escribir(0x664c, 16); // "fL"
    cab.escribir(0x6143, 16); // "aC"
    const claves = Object.keys(this.etiquetas);
    const hayComentarios = claves.length > 0;
    // STREAMINFO
    cab.escribir(hayComentarios ? 0 : 1, 1);
    cab.escribir(0, 7);
    cab.escribir(34, 24);
    const varias = this.numTrama > 1;
    cab.escribir(varias ? TAM_BLOQUE : this.totalMuestras || TAM_BLOQUE, 16);
    cab.escribir(varias ? TAM_BLOQUE : this.totalMuestras || TAM_BLOQUE, 16);
    cab.escribir(this.numTrama ? this.minTrama : 0, 24);
    cab.escribir(this.maxTrama, 24);
    cab.escribir(this.frecuencia, 20);
    cab.escribir(this.canales - 1, 3);
    cab.escribir(this.bits - 1, 5);
    cab.escribir(Math.floor(this.totalMuestras / 4294967296) & 0xf, 4);
    cab.escribir(this.totalMuestras >>> 16, 16);
    cab.escribir(this.totalMuestras & 0xffff, 16);
    for (let i = 0; i < 16; i++) cab.escribir(md5f[i], 8);
    if (hayComentarios) {
      const enc = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
      const utf = function (s) {
        if (enc) return enc.encode(s);
        return Uint8Array.from(Buffer.from(s, 'utf8'));
      };
      const vendedor = utf('musica-infinita');
      const comentarios = claves.map(function (k) { return utf(k + '=' + String(this.etiquetas[k])); }, this);
      let largo = 4 + vendedor.length + 4;
      for (const c of comentarios) largo += 4 + c.length;
      cab.escribir(1, 1);
      cab.escribir(4, 7);
      cab.escribir(largo, 24);
      const le32 = function (v) {
        for (let i = 0; i < 4; i++) cab.escribir((v >>> (8 * i)) & 0xff, 8);
      };
      le32(vendedor.length);
      for (const x of vendedor) cab.escribir(x, 8);
      le32(comentarios.length);
      for (const c of comentarios) {
        le32(c.length);
        for (const x of c) cab.escribir(x, 8);
      }
    }
    const partes = [cab.bytes().slice()].concat(this.tramas);
    this.tramas = [];
    return partes;
  };

  CodificadorFlac.prototype.duracion = function () {
    return (this.totalMuestras + this.lleno) / this.frecuencia;
  };

  function unir(partes) {
    let n = 0;
    for (const p of partes) n += p.length;
    const out = new Uint8Array(n);
    let k = 0;
    for (const p of partes) {
      out.set(p, k);
      k += p.length;
    }
    return out;
  }

  /** Codifica de una vez: canales = [Float32Array, ...]. Devuelve un Uint8Array. */
  function codificar(canales, opciones) {
    const c = new CodificadorFlac(Object.assign({ canales: canales.length }, opciones));
    c.agregar(canales);
    return unir(c.terminar());
  }

  /** Cabecera WAV PCM para nMuestras por canal (se puede escribir antes que los datos). */
  function cabeceraWav(nc, frecuencia, bits, nMuestras) {
    const bm = bits / 8;
    const datos = nMuestras * nc * bm;
    const out = new Uint8Array(44);
    const dv = new DataView(out.buffer);
    const s = function (p, t) { for (let i = 0; i < 4; i++) out[p + i] = t.charCodeAt(i); };
    s(0, 'RIFF');
    dv.setUint32(4, 36 + datos, true);
    s(8, 'WAVE');
    s(12, 'fmt ');
    dv.setUint32(16, 16, true);
    dv.setUint16(20, 1, true);
    dv.setUint16(22, nc, true);
    dv.setUint32(24, frecuencia, true);
    dv.setUint32(28, frecuencia * nc * bm, true);
    dv.setUint16(32, nc * bm, true);
    dv.setUint16(34, bits, true);
    s(36, 'data');
    dv.setUint32(40, datos, true);
    return out;
  }

  /** Muestras PCM intercaladas en little-endian (16 o 24 bits) de canales en coma flotante. */
  function pcm(canales, bits) {
    const nc = canales.length;
    const n = canales[0].length;
    const bm = bits / 8;
    const out = new Uint8Array(n * nc * bm);
    const escala = bits === 24 ? 8388607 : 32767;
    let p = 0;
    for (let i = 0; i < n; i++) {
      for (let c = 0; c < nc; c++) {
        let v = Math.round(canales[c][i] * escala);
        v = v > escala ? escala : v < -escala - 1 ? -escala - 1 : v;
        out[p++] = v & 0xff;
        out[p++] = (v >> 8) & 0xff;
        if (bm === 3) out[p++] = (v >> 16) & 0xff;
      }
    }
    return out;
  }

  /** WAV PCM de 16 o 24 bits, de una vez. */
  function wav(canales, frecuencia, bits) {
    bits = bits === 24 ? 24 : 16;
    return unir([cabeceraWav(canales.length, frecuencia, bits, canales[0].length), pcm(canales, bits)]);
  }

  const API = { CodificadorFlac: CodificadorFlac, codificar: codificar, unir: unir, wav: wav, cabeceraWav: cabeceraWav, pcm: pcm, md5: md5, crc8: crc8, crc16: crc16, TAM_BLOQUE: TAM_BLOQUE };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.Flac = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
