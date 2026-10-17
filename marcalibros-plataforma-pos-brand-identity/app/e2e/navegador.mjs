// Pruebas de extremo a extremo con un navegador real (Playwright + Chromium).
// Uso: con la aplicación corriendo, `NODE_PATH="$(npm root -g)" node e2e/navegador.mjs [http://127.0.0.1:8080]`
// (Playwright instalado de forma global, como en los otros proyectos del repositorio).
// Sin `waitForFunction` con texto: la CSP bloquea `eval`.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawn } from 'node:child_process';

// require (y no import) para que respete NODE_PATH.
const requerir = createRequire(import.meta.url);
const { chromium } = requerir('playwright');
// axe-core revisa la accesibilidad (WCAG 2.1 A y AA). Se instala igual que Playwright, de forma global.
const axe = requerir.resolve('axe-core/axe.min.js');

const base = process.argv[2] ?? 'http://127.0.0.1:8080';
const raiz = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const foto = join(raiz, 'images', 'paisaje-bosque-1920x1080.jpg');

const resultados = [];
async function prueba(nombre, fn) {
  try {
    await fn();
    resultados.push([true, nombre]);
    console.log(`ok    ${nombre}`);
  } catch (error) {
    resultados.push([false, nombre]);
    console.log(`FALLA ${nombre}\n      ${String(error?.message ?? error).split('\n')[0]}`);
  }
}
function exigir(condicion, mensaje) {
  if (!condicion) throw new Error(mensaje);
}

/** Página nueva que anota errores de consola (incluidas violaciones de CSP) y respuestas 4xx/5xx de recursos. */
async function abrir(navegador, opciones = {}) {
  const contexto = await navegador.newContext(opciones);
  const pagina = await contexto.newPage();
  pagina.errores = [];
  pagina.on('console', (m) => { if (m.type() === 'error') pagina.errores.push(m.text()); });
  pagina.on('pageerror', (e) => pagina.errores.push(e.message));
  return pagina;
}

/** Ancho y alto reales de una imagen JPEG servida por la aplicación. */
async function medidas(pagina, ruta) {
  const respuesta = await pagina.request.get(base + ruta);
  exigir(respuesta.status() === 200, `${ruta} respondió ${respuesta.status()}`);
  exigir(respuesta.headers()['content-type'] === 'image/jpeg', `${ruta} no es image/jpeg`);
  const datos = await respuesta.body();
  for (let i = 2; i < datos.length - 8;) {
    const marcador = datos[i + 1];
    if (marcador >= 0xc0 && marcador <= 0xc3) {
      return [datos.readUInt16BE(i + 7), datos.readUInt16BE(i + 5)];
    }
    i += 2 + datos.readUInt16BE(i + 2);
  }
  throw new Error(`${ruta}: no se encontró el tamaño en el JPEG`);
}

const navegador = await chromium.launch();

await prueba('el catálogo carga las fuentes de la marca sin errores de CSP', async () => {
  const p = await abrir(navegador);
  await p.goto(base + '/', { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  const cargadas = await p.evaluate(() => [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family));
  exigir(cargadas.includes('Source Serif 4'), `Source Serif 4 no cargó (${cargadas})`);
  exigir(cargadas.includes('Inter'), `Inter no cargó (${cargadas})`);
  exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
  await p.context().close();
});

await prueba('categoría, detalle y descarga del SVG', async () => {
  const p = await abrir(navegador);
  await p.goto(base + '/?categoria=leyes');
  exigir((await p.locator('h1').innerText()).length > 0, 'la categoría no tiene título');
  await p.locator('a[href^="/marcalibro/mb-leyes-"]').first().click();
  await p.waitForURL(/\/marcalibro\/mb-leyes-/);
  const enlace = p.locator('a[href$=".svg"], a[href*="svg"]').first();
  const href = await enlace.getAttribute('href');
  const respuesta = await p.request.get(new URL(href, base).href);
  exigir(respuesta.status() === 200, `el SVG respondió ${respuesta.status()}`);
  exigir((respuesta.headers()['content-security-policy'] ?? '').includes('sandbox'), 'el SVG se sirve sin CSP sandbox');
  exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
  await p.context().close();
});

let creado = null;
await prueba('subir una foto, moverla con el teclado y obtener 1400×200 y 2800×400', async () => {
  const p = await abrir(navegador);
  await p.goto(base + '/crear');
  await p.locator('#imagen').setInputFiles(foto);
  await p.locator('#encuadre').waitFor({ state: 'visible' });
  const zoom = p.locator('#zoom');
  await zoom.fill('2');
  await zoom.dispatchEvent('input');
  const antes = await p.locator('#x').inputValue();
  await p.locator('#marco').focus();
  await p.keyboard.press('ArrowRight');
  await p.keyboard.press('ArrowRight');
  const despues = await p.locator('#x').inputValue();
  exigir(Number(despues) > Number(antes), `las flechas no movieron el marco (${antes} → ${despues})`);
  await p.locator('#nombre').fill('Prueba de navegador');
  await Promise.all([p.waitForURL(/\/mio\/mp-[0-9a-f]+$/), p.locator('#crear').click()]);
  creado = new URL(p.url()).pathname;
  exigir((await medidas(p, creado + '/1x.jpg')).join('×') === '1400×200', 'el 1x no mide 1400×200');
  exigir((await medidas(p, creado + '/2x.jpg')).join('×') === '2800×400', 'el 2x no mide 2800×400');
  exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
  await p.context().close();
});

await prueba('borrar el marcalibro creado deja el enlace sin funcionar', async () => {
  exigir(creado !== null, 'no hay marcalibro creado en la prueba anterior');
  const p = await abrir(navegador);
  await p.goto(base + creado);
  await p.locator('details.borrar summary').click();
  await Promise.all([p.waitForURL(/aviso=borrado/), p.locator('details.borrar button[type=submit]').click()]);
  const respuesta = await p.request.get(base + creado);
  exigir(respuesta.status() === 404, `tras borrar, ${creado} respondió ${respuesta.status()}`);
  await p.context().close();
});

await prueba('un archivo que no es imagen se rechaza con un mensaje', async () => {
  const p = await abrir(navegador, { javaScriptEnabled: false });
  const falso = join(mkdtempSync(join(tmpdir(), 'marcalibros-')), 'no-es-imagen.jpg');
  writeFileSync(falso, 'esto no es un JPEG');
  await p.goto(base + '/crear');
  await p.locator('#imagen').setInputFiles(falso);
  await p.locator('#crear').click();
  await p.locator('#error-servidor').waitFor();
  exigir((await p.locator('#error-servidor').innerText()).trim().length > 0, 'el mensaje de error está vacío');
  await p.context().close();
});

await prueba('sin JavaScript se crea un marcalibro con un paisaje', async () => {
  const p = await abrir(navegador, { javaScriptEnabled: false });
  await p.goto(base + '/crear');
  await p.locator('input[name=fuente][value=paisaje]').check();
  await p.locator('input[name=paisaje]').first().check();
  await Promise.all([p.waitForURL(/\/mio\/mp-[0-9a-f]+$/), p.locator('#crear').click()]);
  const ruta = new URL(p.url()).pathname;
  exigir((await medidas(p, ruta + '/1x.jpg')).join('×') === '1400×200', 'el 1x no mide 1400×200');
  await p.context().close();
});

await prueba('el filtro por motivo y color funciona desde el formulario', async () => {
  const p = await abrir(navegador, { javaScriptEnabled: false });
  await p.goto(base + '/');
  await p.locator('#filtro-motivo').selectOption('faro');
  await p.locator('#filtro-color').selectOption('ocre');
  await Promise.all([p.waitForURL(/motivo=faro/), p.locator('.filtro button[type=submit]').click()]);
  exigir(new URL(p.url()).searchParams.get('color') === 'ocre', `la dirección no lleva el color: ${p.url()}`);
  const enlaces = await p.locator('.rejilla a[href^="/marcalibro/"]').evaluateAll((as) => as.map((a) => a.getAttribute('href')));
  exigir(enlaces.length > 0, 'el filtro no devolvió marcalibros');
  exigir(enlaces.every((h) => h.startsWith('/marcalibro/mb-modernismo-anglosajon-')), `hay resultados de otro motivo: ${enlaces}`);
  await p.locator('text=Quitar filtros').click();
  await p.locator('h1', { hasText: 'Elige un marcalibro' }).waitFor();
  await p.context().close();
});

// Revisión automática de accesibilidad. La CSP no deja inyectar scripts: este contexto la ignora solo para axe.
for (const esquema of ['light', 'dark']) {
  await prueba(`sin problemas de accesibilidad WCAG 2.1 AA (${esquema === 'light' ? 'modo claro' : 'modo oscuro'})`, async () => {
    const p = await abrir(navegador, { bypassCSP: true, colorScheme: esquema });
    const problemas = [];
    for (const ruta of ['/', '/?categoria=poesia', '/?motivo=faro&color=ocre', '/?categoria=teatro&motivo=torres', '/marcalibro/mb-historia-02', '/crear']) {
      await p.goto(base + ruta, { waitUntil: 'networkidle' });
      await p.addScriptTag({ path: axe });
      const resultado = await p.evaluate(async () => {
        const r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } });
        return r.violations.map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
      });
      problemas.push(...resultado.map((v) => `${ruta} → ${v}`));
    }
    exigir(problemas.length === 0, problemas.join('\n      '));
    await p.context().close();
  });
}

await prueba('en modo oscuro el fondo es tinta, el texto papel y el logo va sobre papel', async () => {
  const p = await abrir(navegador, { colorScheme: 'dark' });
  for (const ruta of ['/', '/crear', '/marcalibro/mb-leyes-07']) {
    await p.goto(base + ruta, { waitUntil: 'networkidle' });
    const [fondo, texto, logo] = await p.evaluate(() => [
      getComputedStyle(document.body).backgroundColor,
      getComputedStyle(document.body).color,
      getComputedStyle(document.querySelector('.marca img')).backgroundColor,
    ]);
    exigir(fondo === 'rgb(30, 42, 56)', `${ruta}: el fondo es ${fondo}`);
    exigir(texto === 'rgb(244, 238, 225)', `${ruta}: el texto es ${texto}`);
    exigir(logo === 'rgb(244, 238, 225)', `${ruta}: el logo va sobre ${logo}`);
  }
  // Las muestras de color siguen mostrando la paleta real, no los colores del tema.
  const tinta = await p.evaluate(() => getComputedStyle(document.querySelector('.muestra--tinta')).backgroundColor);
  exigir(tinta === 'rgb(30, 42, 56)', `la muestra de tinta es ${tinta}`);
  exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
  await p.context().close();
});

await prueba('en el celular no hay desplazamiento horizontal', async () => {
  const p = await abrir(navegador, { viewport: { width: 360, height: 740 }, isMobile: true, hasTouch: true });
  for (const ruta of ['/', '/crear', '/?categoria=teatro']) {
    await p.goto(base + ruta, { waitUntil: 'networkidle' });
    const [ancho, visible] = await p.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth]);
    exigir(ancho <= visible, `${ruta}: la página mide ${ancho} px en una pantalla de ${visible} px`);
  }
  exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
  await p.context().close();
});

/**
 * JPEG de prueba con un degradado: el rojo crece con la x y el verde con la y de la imagen tal como se ve.
 * Con orientación 6 los píxeles se guardan girados y una cabecera EXIF pide girarlos 90° a la derecha.
 */
async function fotoDePrueba(navegador, ancho, alto, orientacion) {
  const p = await navegador.newPage();
  const b64 = await p.evaluate(async ([W, H, o]) => {
    const [sw, sh] = o === 6 ? [H, W] : [W, H];
    const lienzo = new OffscreenCanvas(sw, sh);
    const ctx = lienzo.getContext('2d');
    const datos = ctx.createImageData(sw, sh);
    for (let sy = 0; sy < sh; sy++) {
      for (let sx = 0; sx < sw; sx++) {
        const [x, y] = o === 6 ? [sh - 1 - sy, sx] : [sx, sy];
        const i = 4 * (sy * sw + sx);
        datos.data[i] = (255 * x) / (W - 1);
        datos.data[i + 1] = (255 * y) / (H - 1);
        datos.data[i + 2] = 128;
        datos.data[i + 3] = 255;
      }
    }
    ctx.putImageData(datos, 0, 0);
    const bytes = new Uint8Array(await (await lienzo.convertToBlob({ type: 'image/jpeg', quality: 0.95 })).arrayBuffer());
    let texto = '';
    for (const b of bytes) texto += String.fromCharCode(b);
    return btoa(texto);
  }, [ancho, alto, orientacion]);
  await p.close();

  let jpeg = Buffer.from(b64, 'base64');
  if (orientacion !== 1) {
    // TIFF big-endian con una sola etiqueta: 0x0112 (Orientation), tipo SHORT.
    const tiff = Buffer.from([0x4d, 0x4d, 0, 0x2a, 0, 0, 0, 8, 0, 1, 0x01, 0x12, 0, 3, 0, 0, 0, 1, 0, orientacion, 0, 0, 0, 0, 0, 0]);
    const cuerpo = Buffer.concat([Buffer.from('Exif\0\0', 'binary'), tiff]);
    const app1 = Buffer.concat([Buffer.from([0xff, 0xe1, (cuerpo.length + 2) >> 8, (cuerpo.length + 2) & 0xff]), cuerpo]);
    // Después de SOI y, si lo hay, del segmento JFIF.
    const corte = jpeg[3] === 0xe0 ? 4 + jpeg.readUInt16BE(4) : 2;
    jpeg = Buffer.concat([jpeg.subarray(0, corte), app1, jpeg.subarray(corte)]);
  }
  const ruta = join(mkdtempSync(join(tmpdir(), 'marcalibros-')), `prueba-${ancho}x${alto}-o${orientacion}.jpg`);
  writeFileSync(ruta, jpeg);
  return ruta;
}

/** Diferencia media (0 a 255) en rojo y verde entre dos imágenes reducidas a 70×10. */
async function diferencia(navegador, a, b) {
  const p = await navegador.newPage();
  const resultado = await p.evaluate(async ([ua, ub]) => {
    const pixeles = async (url) => {
      const bitmap = await createImageBitmap(await (await fetch(url)).blob());
      const lienzo = new OffscreenCanvas(70, 10);
      const ctx = lienzo.getContext('2d');
      ctx.drawImage(bitmap, 0, 0, 70, 10);
      return ctx.getImageData(0, 0, 70, 10).data;
    };
    const [pa, pb] = [await pixeles(ua), await pixeles(ub)];
    let suma = 0;
    for (let i = 0; i < pa.length; i += 4) suma += Math.abs(pa[i] - pb[i]) + Math.abs(pa[i + 1] - pb[i + 1]);
    return suma / (2 * 700);
  }, [a, b]);
  await p.close();
  return resultado;
}

const dataUrl = (buffer, tipo) => `data:${tipo};base64,${buffer.toString('base64')}`;

// La cuenta del recorte está en crear.js y en ImageNormalizer.php: la franja que se ve debe ser la que se guarda.
for (const [nombre, ancho, alto, orientacion, zoom, x, y] of [
  ['horizontal', 3000, 1000, 1, '1.3', '0.2', '0.8'],
  ['vertical', 1200, 2400, 1, '1.5', '0.3', '0.7'],
  ['vertical con orientación EXIF', 1200, 2400, 6, '1.5', '0.3', '0.7'],
]) {
  await prueba(`lo que se ve al encuadrar es lo que se guarda (${nombre})`, async () => {
    const archivo = await fotoDePrueba(navegador, ancho, alto, orientacion);
    const p = await abrir(navegador, { viewport: { width: 1280, height: 900 } });
    await p.goto(base + '/crear');
    await p.locator('#imagen').setInputFiles(archivo);
    await p.locator('#encuadre').waitFor({ state: 'visible' });
    for (const [id, valor] of [['zoom', zoom], ['x', x], ['y', y]]) {
      await p.locator('#' + id).fill(valor);
      await p.locator('#' + id).dispatchEvent('input');
    }
    const vista = await p.locator('#resultado').screenshot();
    await Promise.all([p.waitForURL(/\/mio\/mp-[0-9a-f]+$/), p.locator('#crear').click()]);
    const guardada = await p.request.get(base + new URL(p.url()).pathname + '/1x.jpg');
    const d = await diferencia(navegador, dataUrl(vista, 'image/png'), dataUrl(await guardada.body(), 'image/jpeg'));
    exigir(d < 6, `la vista previa y el marcalibro guardado difieren en ${d.toFixed(1)} de 255`);
    exigir(p.errores.length === 0, `errores en consola: ${p.errores}`);
    await p.context().close();
  });
}

await prueba('el límite de intentos cuenta también los fallidos', async () => {
  // Un servidor aparte, con un intento por hora y su propio almacenamiento.
  const puerto = 8000 + Math.floor(Math.random() * 900) + 100;
  const otraBase = `http://127.0.0.1:${puerto}`;
  const app = join(dirname(fileURLToPath(import.meta.url)), '..');
  const servidor = spawn('php', ['-S', `127.0.0.1:${puerto}`, '-t', 'public', 'public/index.php'], {
    cwd: app,
    env: { ...process.env, MARCALIBROS_STORAGE: mkdtempSync(join(tmpdir(), 'marcalibros-limite-')), MARCALIBROS_CREATES_PER_HOUR: '1' },
    stdio: 'ignore',
  });
  try {
    for (let i = 0; ; i++) {
      try { if ((await fetch(otraBase + '/')).ok) break; } catch { /* todavía no levanta */ }
      exigir(i < 50, 'el servidor del límite no levantó');
      await new Promise((r) => setTimeout(r, 100));
    }
    const p = await abrir(navegador, { javaScriptEnabled: false });
    const falso = join(mkdtempSync(join(tmpdir(), 'marcalibros-')), 'no-es-imagen.jpg');
    writeFileSync(falso, 'esto no es un JPEG');
    await p.goto(otraBase + '/crear');
    await p.locator('#imagen').setInputFiles(falso);
    await p.locator('#crear').click();
    await p.locator('#error-servidor').waitFor();
    const primero = await p.locator('#error-servidor').innerText();
    exigir(!primero.includes('intentos'), `el primer intento ya chocó con el límite: ${primero}`);

    await p.goto(otraBase + '/crear');
    await p.locator('input[name=fuente][value=paisaje]').check();
    await p.locator('input[name=paisaje]').first().check();
    const [respuesta] = await Promise.all([p.waitForResponse((r) => r.request().method() === 'POST'), p.locator('#crear').click()]);
    exigir(respuesta.status() === 429, `el segundo intento respondió ${respuesta.status()}`);
    exigir((await p.locator('#error-servidor').innerText()).includes('intentos'), 'falta el mensaje del límite');
    await p.context().close();
  } finally {
    servidor.kill();
  }
});

await navegador.close();

const fallidas = resultados.filter(([ok]) => !ok).length;
console.log(`\n${resultados.length - fallidas} de ${resultados.length} pruebas pasaron.`);
process.exit(fallidas === 0 ? 0 : 1);
