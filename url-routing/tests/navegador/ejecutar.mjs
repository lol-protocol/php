// El formulario de «pedir el retiro» (/r/{id}/) y la cola del propietario (/0/3/) en un navegador real, con una
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
php('tests/navegador/preparar.php');
const entrega = JSON.parse(readFileSync(`${dir}/entrega.json`, 'utf8'));

const puerto = await puertoLibre();
const servidor = spawn('php', ['-S', `127.0.0.1:${puerto}`, '-t', 'public', 'public/index.php'], { cwd: raiz, env: entorno, stdio: 'ignore' });
const base = `http://127.0.0.1:${puerto}`;
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
const navegador = await chromium.launch(ejecutable ? { executablePath: ejecutable } : {});
const errores = [];
const reporte = [];

async function con(opciones, cuerpo) {
  // La CSP del sitio (script-src 'self') impide inyectar axe, que es un script en línea: se la salta solo la prueba,
  // el sitio no cambia. Lo que se audita es el HTML y la hoja de estilos que sirve, no la CSP.
  const contexto = await navegador.newContext({ viewport: { width: 900, height: 900 }, bypassCSP: true, ...opciones });
  const pagina = await contexto.newPage();
  // Chromium pide /favicon.ico por su cuenta y el sitio no tiene uno: se contesta aquí para que su 404 no ensucie la consola.
  await contexto.route('**/favicon.ico', (ruta) => ruta.fulfill({ status: 204 }));
  // Un 422 es la respuesta correcta a un formulario con errores (la prueba lo envía vacío a propósito).
  pagina.on('response', (r) => { if (r.status() >= 400 && r.status() !== 422) { errores.push(`HTTP ${r.status()} ${r.url()}`); } });
  pagina.on('pageerror', (e) => errores.push(String(e)));
  // El «Failed to load resource … 422» de Chromium es la respuesta esperada del formulario con errores (arriba).
  pagina.on('console', (m) => { if (m.type() === 'error' && !/status of 422/.test(m.text())) { errores.push(m.text()); } });
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
