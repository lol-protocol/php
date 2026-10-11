// Pruebas de extremo a extremo con un navegador real (Playwright + Chromium).
// Uso: con la aplicación corriendo, `NODE_PATH="$(npm root -g)" node e2e/navegador.mjs [http://127.0.0.1:8080]`
// (Playwright instalado de forma global, como en los otros proyectos del repositorio).
// Sin `waitForFunction` con texto: la CSP bloquea `eval`.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';

// require (y no import) para que respete NODE_PATH.
const { chromium } = createRequire(import.meta.url)('playwright');

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

await navegador.close();

const fallidas = resultados.filter(([ok]) => !ok).length;
console.log(`\n${resultados.length - fallidas} de ${resultados.length} pruebas pasaron.`);
process.exit(fallidas === 0 ? 0 : 1);
