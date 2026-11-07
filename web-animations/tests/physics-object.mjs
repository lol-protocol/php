// PhysicsObject (src/helpers/common.js) es la base de la física de B (círculos rebotadores) y de G (lluvia de
// partículas). Esta prueba fija lo que no ve el smoke test:
//  - un 0 que se le pasa es un valor, no «no me pasaron nada» (bounceCoeff: 0 es «sin rebote»);
//  - 'reflect' (la bola de B) sigue el camino de un espejo y no pierde velocidad, 'clamp' (el default) sí rebota con
//    bounceCoeff y recorta contra el borde;
//  - sin bordes propios, el objeto usa el tamaño de la ventana y lo sigue cuando cambia.
import { chromium } from 'playwright';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const common = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'helpers', 'common.js');

// CHROMIUM = un Chromium ya instalado (los contenedores de la nube traen otra version que la que baja Playwright).
const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
const page = await browser.newPage({ viewport: { width: 800, height: 600 } });
const errores = [];
page.on('pageerror', err => errores.push(`JS: ${err.message}`));
await page.setContent('<div id="e"></div>');
await page.addScriptTag({ path: common });

const medido = await page.evaluate(() => {
    const el = () => document.createElement('div');
    const bordes = { width: 100, height: 100 };
    const r = {};

    const porDefecto = new PhysicsObject(el());
    r.porDefecto = { x: porDefecto.x, radius: porDefecto.radius, bounceCoeff: porDefecto.bounceCoeff, bounceMode: porDefecto.bounceMode };

    const ceros = new PhysicsObject(el(), { x: 0, vx: 0, bounceCoeff: 0 });
    r.ceros = { x: ceros.x, vx: ceros.vx, bounceCoeff: ceros.bounceCoeff };

    // Cae sobre el piso con bounceCoeff 0: se queda quieta, no rebota al 80 %.
    const sinRebote = new PhysicsObject(el(), { x: 50, y: 95, vy: 5, radius: 10, bounceCoeff: 0, bounds: bordes });
    sinRebote.checkBounds();
    r.sinRebote = { y: sinRebote.y, vy: sinRebote.vy };

    const conRebote = new PhysicsObject(el(), { x: 50, y: 95, vy: 5, radius: 10, bounceCoeff: 0.5, bounds: bordes });
    conRebote.checkBounds();
    r.conRebote = { y: conRebote.y, vy: conRebote.vy };

    // Pasa 15 px de la pared derecha (el límite del centro es 90): 'reflect' vuelve 15 px y conserva la velocidad; 'clamp' se queda en el borde.
    const espejo = new PhysicsObject(el(), { x: 105, y: 50, vx: 3, vy: 0, radius: 10, bounceMode: 'reflect', bounds: bordes });
    espejo.checkBounds();
    r.espejo = { x: espejo.x, vx: espejo.vx };
    const recorte = new PhysicsObject(el(), { x: 105, y: 50, vx: 3, vy: 0, radius: 10, bounds: bordes });
    recorte.checkBounds();
    r.recorte = { x: recorte.x, vx: recorte.vx };

    // Los bordes que se le pasan son los suyos: no los reemplaza.
    r.bordesPropios = new PhysicsObject(el(), { bounds: bordes }).bounds === bordes;

    // Sin bordes propios usa la ventana, y es el mismo objeto para todos.
    const a = new PhysicsObject(el());
    const b = new PhysicsObject(el());
    r.ventana = { igual: a.bounds === b.bounds, width: a.bounds.width, height: a.bounds.height };
    window.__a = a;
    return r;
});

await page.setViewportSize({ width: 400, height: 300 });
// 'resize' se despacha al preparar el siguiente cuadro: se espera uno (dos, por si el primero ya estaba en curso).
await page.evaluate(() => new Promise((listo) => requestAnimationFrame(() => requestAnimationFrame(() => listo()))));
const achicada = await page.evaluate(() => ({ width: window.__a.bounds.width, height: window.__a.bounds.height }));

await browser.close();

const problemas = [...errores];
const igual = (cosa, obtenido, esperado) => {
    if (JSON.stringify(obtenido) !== JSON.stringify(esperado)) problemas.push(`${cosa}: ${JSON.stringify(obtenido)} en vez de ${JSON.stringify(esperado)}`);
};
igual('valores por defecto', medido.porDefecto, { x: 0, radius: 10, bounceCoeff: 0.8, bounceMode: 'clamp' });
igual('los ceros pasados', medido.ceros, { x: 0, vx: 0, bounceCoeff: 0 });
igual('bounceCoeff 0 en el piso', medido.sinRebote, { y: 90, vy: 0 });
igual('bounceCoeff 0.5 en el piso', medido.conRebote, { y: 90, vy: -2.5 });
igual('reflect contra la pared', medido.espejo, { x: 75, vx: -3 });
igual('clamp contra la pared', medido.recorte, { x: 90, vx: -3 });
igual('bordes propios', medido.bordesPropios, true);
igual('bordes de la ventana', medido.ventana, { igual: true, width: 800, height: 600 });
igual('bordes de la ventana tras achicarla', achicada, { width: 400, height: 300 });

console.log(problemas.length ? '✗ PhysicsObject' : '✓ PhysicsObject: ceros, reflect, clamp y bordes de la ventana');
problemas.forEach(p => console.log(`    ${p}`));
process.exit(problemas.length ? 1 : 0);
