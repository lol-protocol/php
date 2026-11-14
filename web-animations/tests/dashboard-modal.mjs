// El modal de detalles de la galería (src/animations/index.html) se cierra con la ×, con un clic en el fondo y con Escape,
// y un clic dentro del contenido no lo cierra. Antes solo cerraba con Escape: el modal cuelga de <body>, fuera de las
// tarjetas, y el manejador de clics salía si el clic no caía en una tarjeta.
import { chromium } from 'playwright';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const galeria = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'animations', 'index.html');

// CHROMIUM = un Chromium ya instalado (los contenedores de la nube traen otra version que la que baja Playwright).
const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
const errores = [];
page.on('pageerror', err => errores.push(`JS: ${err.message}`));
await page.goto(pathToFileURL(galeria).href, { waitUntil: 'load' });
await page.waitForSelector('[data-anim-id] .view-details-btn');

const abiertos = () => page.$$eval('.modal-overlay', modales => modales.length);
// Cada caso parte sin modal: si uno falla, el siguiente no se queda esperando detrás del anterior.
const abrir = async () => { await page.evaluate(() => document.querySelectorAll('.modal-overlay').forEach(m => m.remove())); await page.click('[data-anim-id] .view-details-btn'); await page.waitForSelector('.modal-overlay.active'); };
const esperarCierre = () => page.waitForFunction(() => document.querySelectorAll('.modal-overlay').length === 0, null, { timeout: 2000 }).then(() => true, () => false);

const medido = {};
await abrir();
medido.abre = await abiertos();

await page.click('.modal-content .modal-body');           // dentro del contenido: no cierra
await page.waitForTimeout(450);
medido.contenidoNoCierra = await abiertos();

await page.click('.modal-close');
medido.cierraConLaX = await esperarCierre();

await abrir();
await page.mouse.click(5, 5);                              // el fondo, fuera del contenido
medido.cierraConElFondo = await esperarCierre();

await abrir();
await page.keyboard.press('Escape');
medido.cierraConEscape = await esperarCierre();

await browser.close();

const problemas = [...errores];
const igual = (cosa, obtenido, esperado) => {
    if (JSON.stringify(obtenido) !== JSON.stringify(esperado)) problemas.push(`${cosa}: ${JSON.stringify(obtenido)} en vez de ${JSON.stringify(esperado)}`);
};
igual('el modal se abre', medido.abre, 1);
igual('un clic dentro del contenido no lo cierra', medido.contenidoNoCierra, 1);
igual('la × lo cierra', medido.cierraConLaX, true);
igual('un clic en el fondo lo cierra', medido.cierraConElFondo, true);
igual('Escape lo cierra', medido.cierraConEscape, true);

console.log(problemas.length ? '✗ modal de la galería' : '✓ modal de la galería: se cierra con ×, fondo y Escape');
problemas.forEach(p => console.log(`    ${p}`));
process.exit(problemas.length ? 1 : 0);
