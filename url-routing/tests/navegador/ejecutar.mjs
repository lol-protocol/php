// Los formularios de «pedir el retiro» (/r/{id}/) y de pedir los datos de una persona (/s/{id}/), y la cola del propietario (/0/3/) en un navegador real, con una
// auditoría de accesibilidad de axe-core. Las pruebas de PHP (../App) no abren un navegador y no pueden probar esto.
//
//   cd url-routing/tests/navegador && npm ci && npx playwright install chromium && npm test
//
// Levanta el servidor de PHP de verdad sobre una base SQLite temporal, con tres pedidos de ejemplo (preparar.php).
// En la nube de las sesiones, en lugar de `npx playwright install`, se usa el Chromium que ya hay: la variable
// CHROMIUM, o /opt/pw-browsers/chromium si existe. Necesita `php` en el PATH y `composer install` hecho en url-routing/.
//
// Lo que NO prueba: un lector de pantalla real, ni Firefox ni Safari, ni el contraste donde axe no puede calcularlo.

import { chromium } from 'playwright';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createRequire } from 'node:module';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const raiz = path.resolve(aqui, '../..');
const axeRuta = createRequire(import.meta.url).resolve('axe-core/axe.min.js');
const TOKEN = 'token-de-prueba-del-propietario-0123456789';
const ETIQUETAS_AXE = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

function igual(esperado, obtenido, mensaje = '') {
  if (esperado !== obtenido) {
    throw new Error(`${mensaje} esperado ${JSON.stringify(esperado)}, obtenido ${JSON.stringify(obtenido)}`.trim());
  }
}

function verdadero(condicion, mensaje) {
  if (!condicion) {
    throw new Error(mensaje);
  }
}

function puertoLibre() {
  return new Promise((resolver, rechazar) => {
    const servidor = net.createServer();
    servidor.listen(0, '127.0.0.1', () => {
      const { port } = servidor.address();
      servidor.close(() => resolver(port));
    });
    servidor.on('error', rechazar);
  });
}

const dir = mkdtempSync(path.join(os.tmpdir(), 'url-routing-navegador-'));
const entorno = {
  ...process.env,
  DB_DSN_GENEALOGY: `sqlite:${dir}/genealogy.sqlite`,
  DB_DSN_POS: `sqlite:${dir}/pos.sqlite`,
  LOG_FILE: `${dir}/app.log`,
  URL_LOG_DIR: 'off',
  OWNER_TOKEN: TOKEN,
  ENTREGA_SALIDA: `${dir}/entrega.json`,
};

function php(...argumentos) {
  const r = spawnSync('php', argumentos, { cwd: raiz, env: entorno, encoding: 'utf8' });
  if (r.status !== 0) {
    throw new Error(`php ${argumentos.join(' ')} falló:\n${r.stdout}${r.stderr}`);
  }
}

php('bin/migrate.php', 'genealogy', '--seed');
php('bin/migrate.php', 'pos', '--seed');
php('tests/navegador/preparar.php');
const entrega = JSON.parse(readFileSync(`${dir}/entrega.json`, 'utf8'));

const puerto = await puertoLibre();
const servidor = spawn('php', ['-S', `127.0.0.1:${puerto}`, '-t', 'public', 'public/index.php'], { cwd: raiz, env: entorno, stdio: 'ignore' });
const base = `http://127.0.0.1:${puerto}`;
// El POS es el mismo servidor con otro nombre de host (POS_DOMAINS trae contrastocolor.test): Chromium lo resuelve a 127.0.0.1.
const basePos = `http://contrastocolor.test:${puerto}`;
for (let intento = 0; ; intento++) {
  try {
    await fetch(`${base}/`);
    break;
  } catch {
    verdadero(intento < 50, 'El servidor de PHP no arrancó');
    await new Promise((r) => setTimeout(r, 100));
  }
}

const ejecutable = process.env.CHROMIUM || (existsSync('/opt/pw-browsers/chromium') ? '/opt/pw-browsers/chromium' : undefined);
const navegador = await chromium.launch({ ...(ejecutable ? { executablePath: ejecutable } : {}), args: ['--host-resolver-rules=MAP contrastocolor.test 127.0.0.1'] });
const errores = [];
const reporte = [];

/**
 * Abre una página con su propio contexto. En `opciones`, además de las de Playwright, `esperados` son los estados
 * HTTP de error que ESA prueba provoca a propósito (un 404 que audita, el 422 de un formulario con errores): no cuentan
 * como fallo. Cualquier otro error de consola o respuesta >= 400 sí.
 */
async function con({ esperados = [422], ...opciones }, cuerpo) {
  // La CSP del sitio (script-src 'self') impide inyectar axe, que es un script en línea: se la salta solo la prueba,
  // el sitio no cambia. Lo que se audita es el HTML y la hoja de estilos que sirve, no la CSP.
  const contexto = await navegador.newContext({ viewport: { width: 900, height: 900 }, bypassCSP: true, ...opciones });
  const pagina = await contexto.newPage();
  // Chromium pide /favicon.ico por su cuenta y el sitio no tiene uno: se contesta aquí para que su 404 no ensucie la consola.
  await contexto.route('**/favicon.ico', (ruta) => ruta.fulfill({ status: 204 }));
  pagina.on('response', (r) => { if (r.status() >= 400 && !esperados.includes(r.status())) { errores.push(`HTTP ${r.status()} ${r.url()}`); } });
  pagina.on('pageerror', (e) => errores.push(String(e)));
  // Chromium además dice en la consola «Failed to load resource … status of N» por cada respuesta de error.
  pagina.on('console', (m) => { if (m.type() === 'error' && !esperados.some((e) => m.text().includes(`status of ${e}`))) { errores.push(m.text()); } });
  try {
    return await cuerpo(pagina);
  } finally {
    await contexto.close();
  }
}

const comoPropietario = { httpCredentials: { username: 'propietario', password: TOKEN, send: 'always' } };

/** Audita la página abierta con axe: cualquier violación, o cualquier punto «por revisar», falla. */
async function auditar(pagina, nombre) {
  await pagina.addScriptTag({ path: axeRuta });
  const r = await pagina.evaluate((etiquetas) => axe.run(document, { runOnly: { type: 'tag', values: etiquetas } }), ETIQUETAS_AXE);
  const lista = (xs) => xs.map((v) => `  - ${v.id} (${v.impact ?? 'sin impacto'}): ${v.help}\n${v.nodes.map((n) => `      ${n.target.join(' ')}`).join('\n')}`).join('\n');
  reporte.push(`${nombre}: ${r.violations.length} violaciones, ${r.incomplete.length} por revisar, ${r.passes.length} reglas cumplidas`);
  verdadero(r.violations.length === 0, `${nombre}: axe encontró violaciones:\n${lista(r.violations)}`);
  verdadero(r.incomplete.length === 0, `${nombre}: axe no pudo decidir:\n${lista(r.incomplete)}`);
}

const pruebas = {
  async 'el formulario de pedir el retiro, para un visitante'() {
    return con({}, async (p) => {
      const respuesta = await p.goto(`${base}/r/6128473105/`);
      igual(200, respuesta.status(), 'el formulario responde');
      await auditar(p, '/r/{id}/');
      for (const campo of ['motivo', 'detalle', 'contacto']) {
        for (const control of await p.locator(`[name="${campo}"]`).all()) {
          const nombre = await control.evaluate((e) => e.labels?.[0]?.textContent?.trim() ?? '');
          verdadero(nombre !== '', `cada control de «${campo}» tiene una etiqueta asociada`);
        }
      }
      // El campo trampa existe para los robots: ni una persona con lector de pantalla ni con el teclado debe llegar a él.
      const trampa = p.locator('[name="sitio_web"]');
      igual(0, await trampa.evaluate((e) => e.tabIndex + 1), 'el campo trampa no recibe el foco del teclado');
      verdadero((await trampa.boundingBox()).x < 0, 'está fuera de la pantalla: nadie la ve ni la alcanza con el ratón');
      igual('true', await trampa.evaluate((e) => (e.closest('[aria-hidden]')?.getAttribute('aria-hidden') ?? e.getAttribute('aria-hidden'))), 'ni se anuncia');
    });
  },

  async 'el formulario con sus errores'() {
    return con({}, async (p) => {
      await p.goto(`${base}/r/6128473105/`);
      const formulario = p.locator('form:has([name="motivo"])');
      await formulario.evaluate((f) => { f.noValidate = true; });
      await formulario.locator('button[type="submit"], input[type="submit"]').first().click();
      await p.waitForLoadState();
      verdadero((await p.locator('body').innerText()).includes('Elige un motivo'), 'muestra el error del motivo');
      await auditar(p, '/r/{id}/ con errores');
    });
  },

  async 'el formulario de pedir los datos de una persona'() {
    return con({}, async (p) => {
      const respuesta = await p.goto(`${base}/s/6128473105/`);
      igual(200, respuesta.status(), 'el formulario responde');
      await auditar(p, '/s/{id}/');
      for (const campo of ['derecho', 'detalle', 'contacto']) {
        for (const control of await p.locator(`[name="${campo}"]`).all()) {
          const nombre = await control.evaluate((e) => e.labels?.[0]?.textContent?.trim() ?? '');
          verdadero(nombre !== '', `cada control de «${campo}» tiene una etiqueta asociada`);
        }
      }
      // El mismo campo trampa que el del retiro: ni el teclado ni un lector de pantalla deben llegar a él.
      const trampa = p.locator('[name="sitio_web"]');
      igual(0, await trampa.evaluate((e) => e.tabIndex + 1), 'el campo trampa no recibe el foco del teclado');
      verdadero((await trampa.boundingBox()).x < 0, 'está fuera de la pantalla');
      igual('true', await trampa.evaluate((e) => (e.closest('[aria-hidden]')?.getAttribute('aria-hidden') ?? e.getAttribute('aria-hidden'))), 'ni se anuncia');
    });
  },

  async 'el formulario de datos con sus errores'() {
    return con({}, async (p) => {
      await p.goto(`${base}/s/6128473105/`);
      const formulario = p.locator('form:has([name="derecho"])');
      await formulario.evaluate((f) => { f.noValidate = true; });
      await formulario.locator('button[type="submit"], input[type="submit"]').first().click();
      await p.waitForLoadState();
      verdadero((await p.locator('body').innerText()).includes('Elige qué quieres pedir'), 'muestra el error del derecho');
      await auditar(p, '/s/{id}/ con errores');
    });
  },

  async 'la entrega con clave: pedir la clave'() {
    return con({}, async (p) => {
      igual(200, (await p.goto(`${base}/e/${entrega.token}/`)).status(), 'el enlace responde');
      await auditar(p, '/e/{token}/');
      for (const control of await p.locator('[name="clave"]').all()) {
        const nombre = await control.evaluate((e) => e.labels?.[0]?.textContent?.trim() ?? '');
        verdadero(nombre !== '', 'el campo de la clave tiene su etiqueta');
      }
    });
  },

  async 'la entrega con clave: una clave mala'() {
    return con({}, async (p) => {
      await p.goto(`${base}/e/${entrega.token}/`);
      await p.fill('[name="clave"]', 'AAAA-AAAA-AAAA');
      await p.getByRole('button', { name: 'Ver mis datos' }).click();
      await p.waitForLoadState();
      verdadero((await p.locator('body').innerText()).includes('La clave no es correcta'), 'dice que la clave no es correcta');
      await auditar(p, '/e/{token}/ con una clave mala');
    });
  },

  async 'la entrega con clave: lo que se muestra con la clave buena'() {
    return con({}, async (p) => {
      await p.goto(`${base}/e/${entrega.token}/`);
      await p.fill('[name="clave"]', entrega.clave);
      await p.getByRole('button', { name: 'Ver mis datos' }).click();
      await p.waitForLoadState();
      const texto = await p.locator('body').innerText();
      verdadero(texto.includes('Esto es lo que el sitio guarda de ti hoy'), 'muestra sus datos');
      verdadero(!texto.includes(entrega.clave), 'no repite la clave');
      await auditar(p, '/e/{token}/ con la clave buena');
    });
  },

  async 'las solicitudes de datos del propietario'() {
    return con(comoPropietario, async (p) => {
      const respuesta = await p.goto(`${base}/0/4/`);
      igual(200, respuesta.status(), 'el panel responde al propietario');
      const texto = await p.locator('body').innerText();
      verdadero(texto.includes('Solicitud n.º'), 'lista las solicitudes');
      verdadero(texto.includes('Marcar como atendida'), 'la pendiente tiene sus botones');
      await auditar(p, '/0/4/');
    });
  },

  // Los errores son páginas del sitio (ErrorPage::page): cada sitio tiene su vista, y una sin etiqueta o sin contraste
  // no la vería nadie hasta que alguien tropezara con ella.
  async 'un 404 de genealogía'() {
    return con({ esperados: [404] }, async (p) => {
      const respuesta = await p.goto(`${base}/999999999/`);
      igual(404, respuesta.status(), 'un id que no existe');
      verdadero((await p.locator('body').innerText()).includes('Volver al inicio'), 'ofrece volver');
      await auditar(p, '404 (genealogía)');
    });
  },

  async 'un 401: la cuenta sin la clave'() {
    return con({ esperados: [401] }, async (p) => {
      // Chromium no abre un 401 con desafío de clave sin credenciales (net::ERR_INVALID_AUTH_CREDENTIALS): se pide
      // aparte y se dibuja su respuesta, como la del 403.
      const respuesta = await p.context().request.get(`${base}/0/`);
      igual(401, respuesta.status(), 'pide la clave');
      await p.setContent(await respuesta.text());
      verdadero((await p.locator('h1').innerText()).startsWith('401 - '), 'dice qué es');
      await auditar(p, '401 (la cuenta)');
    });
  },

  async 'un 403: un formulario sin su token'() {
    return con({ esperados: [403] }, async (p) => {
      // Un POST no se puede abrir con goto: se pide aparte y se dibuja su respuesta (el layout no tiene scripts ni hojas externas).
      const respuesta = await p.context().request.post(`${base}/r/6128473105/`, { form: { motivo: 'otro' } });
      igual(403, respuesta.status(), 'sin token se rechaza');
      await p.setContent(await respuesta.text());
      verdadero((await p.locator('h1').innerText()).startsWith('403 - '), 'dice qué es');
      await auditar(p, '403 (sin token)');
    });
  },

  async 'un 404 del POS'() {
    return con({ esperados: [404] }, async (p) => {
      const respuesta = await p.goto(`${basePos}/9999999999/`);
      igual(404, respuesta.status(), 'un id que no existe en el POS');
      verdadero((await p.locator('body').innerText()).includes('Contrastocolor'), 'es el layout del POS, no el de genealogía');
      await auditar(p, '404 (POS)');
    });
  },

  async 'la cola de pedidos del propietario'() {
    return con(comoPropietario, async (p) => {
      const respuesta = await p.goto(`${base}/0/3/`);
      igual(200, respuesta.status(), 'la cola responde al propietario');
      const texto = await p.locator('body').innerText();
      verdadero(texto.includes('Borrados el'), 'el pedido depurado lo dice (y ya no ofrece su correo)');
      await auditar(p, '/0/3/');
    });
  },
};

let fallos = 0;
try {
  for (const [nombre, prueba] of Object.entries(pruebas)) {
    try {
      await prueba();
      console.log(`ok   ${nombre}`);
    } catch (e) {
      fallos++;
      console.log(`FALLA ${nombre}\n${e.message}`);
    }
  }
  if (errores.length > 0) {
    fallos++;
    console.log(`FALLA errores de consola o de la página:\n${errores.join('\n')}`);
  }
} finally {
  await navegador.close();
  servidor.kill();
  rmSync(dir, { recursive: true, force: true });
}

console.log(reporte.join('\n'));
console.log(fallos === 0 ? `\n${Object.keys(pruebas).length} pruebas en el navegador OK` : `\n${fallos} fallos`);
process.exit(fallos === 0 ? 0 : 1);
