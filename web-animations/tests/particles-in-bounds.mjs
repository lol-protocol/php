// La lluvia de partículas (animación G) tiene que rebotar en los bordes de lo que se ve: ninguna partícula se hunde en
// el piso ni en una pared. `Particle` heredaba de PhysicsObject (que cuenta x, y desde el centro del círculo) pero
// sobrescribía render() para dibujar desde la esquina, así que la mitad de cada partícula quedaba bajo el piso y fuera
// de la pared derecha; y su checkBounds() copiado no recortaba la posición. El smoke test no lo ve: la página carga
// bien y el botón anda.
//
// Es determinista: los números al azar salen de una semilla y los cuadros los avanza la prueba, no el reloj.
import { chromium } from 'playwright';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const pagina = pathToFileURL(join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'animations', 'animacion-g.html')).href;
const CUADROS = 1500; // unos 25 s a 60 cuadros por segundo: de sobra para que caigan, reboten y se asienten
const TOLERANCIA = 0.01; // px

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 900, height: 600 } });
const errores = [];
page.on('pageerror', err => errores.push(`JS: ${err.message}`));
page.on('console', msg => { if (msg.type() === 'error') errores.push(`console: ${msg.text()}`); });

await page.addInitScript(() => {
    let semilla = 20261020;
    Math.random = () => {
        semilla = (semilla * 1664525 + 1013904223) % 4294967296;
        return semilla / 4294967296;
    };
    let pendiente = null;
    window.requestAnimationFrame = (cb) => { pendiente = cb; return 1; };
    window.cancelAnimationFrame = () => { pendiente = null; };
    window.__cuadro = () => {
        const cb = pendiente;
        pendiente = null;
        if (cb) cb(performance.now());
    };
});
await page.goto(pagina, { waitUntil: 'load' });

const peor = await page.evaluate((cuadros) => {
    const ancho = window.innerWidth;
    const alto = window.innerHeight;
    const peor = { particulas: 0, piso: 0, izquierda: 0, derecha: 0 };
    for (let i = 0; i < cuadros; i++) {
        window.__cuadro();
        const particulas = document.querySelectorAll('.particle');
        peor.particulas = particulas.length;
        for (const el of particulas) {
            const r = el.getBoundingClientRect();
            peor.piso = Math.max(peor.piso, r.bottom - alto);
            peor.izquierda = Math.max(peor.izquierda, -r.left);
            peor.derecha = Math.max(peor.derecha, r.right - ancho);
        }
    }
    return peor;
}, CUADROS);

await browser.close();

const problemas = [...errores];
if (peor.particulas === 0) problemas.push('no hay partículas en la página');
for (const [borde, texto] of [['piso', 'bajo el piso'], ['izquierda', 'fuera de la pared izquierda'], ['derecha', 'fuera de la pared derecha']]) {
    if (peor[borde] > TOLERANCIA) problemas.push(`una partícula llegó a quedar ${peor[borde].toFixed(2)} px ${texto}`);
}

console.log(problemas.length
    ? '✗ animacion-g.html'
    : `✓ animacion-g.html: ${peor.particulas} partículas, ${CUADROS} cuadros, siempre dentro de la ventana`);
problemas.forEach(p => console.log(`    ${p}`));
process.exit(problemas.length ? 1 : 0);
