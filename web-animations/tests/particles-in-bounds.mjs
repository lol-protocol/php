// La lluvia de partículas (animación G) tiene que rebotar en los bordes de lo que se ve: ninguna partícula se hunde en
// el piso ni en una pared. `Particle` heredaba de PhysicsObject (que cuenta x, y desde el centro del círculo) pero
// sobrescribía render() para dibujar desde la esquina, así que la mitad de cada partícula quedaba bajo el piso y fuera
// de la pared derecha; y su checkBounds() copiado no recortaba la posición. El smoke test no lo ve: la página carga
// bien y el botón anda.
//
// También tiene que seguir dentro cuando cambia el tamaño de la ventana: las partículas guardaban el tamaño que tenía
// al crearse, y al achicarla quedaban hasta 300 px bajo el piso y 460 px fuera de la pared derecha.
//
// Es determinista: los números al azar salen de una semilla y los cuadros los avanza la prueba, no el reloj.
import { chromium } from 'playwright';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const pagina = pathToFileURL(join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'animations', 'animacion-g.html')).href;
const CUADROS = 1500; // unos 25 s a 60 cuadros por segundo: de sobra para que caigan, reboten y se asienten
const CUADROS_DE_MARGEN = 5;
const TOLERANCIA = 0.01; // px

// CHROMIUM = un Chromium ya instalado (los contenedores de la nube traen otra version que la que baja Playwright).
const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
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

// Avanza los cuadros contra el tamaño que tenga la ventana en ese momento y devuelve cuánto se salió, como máximo.
const avanzar = (cuadros) => page.evaluate((cuadros) => {
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
}, cuadros);

// Cambia el tamaño y espera a que la página haya recibido 'resize' (se despacha al preparar el siguiente cuadro, no
// al instante). Su escucha se registra después de la de la física, así que corre cuando esta ya actualizó los bordes.
const cambiarTamano = async (width, height) => {
    const recibido = page.evaluate(() => new Promise((listo) => window.addEventListener('resize', () => listo(), { once: true })));
    await page.setViewportSize({ width, height });
    await recibido;
};

const peor = await avanzar(CUADROS);

// La ventana se achica: las que quedaron fuera tienen que volver a entrar en uno o dos cuadros. Se mide después de
// unos cuadros de margen, para no contar el cuadro en que se ven fuera antes de que el siguiente las acomode.
await cambiarTamano(400, 300);
await avanzar(CUADROS_DE_MARGEN);
const peorAchicada = await avanzar(CUADROS);

// Y se agranda: no se quedan pegadas al piso y a la pared de antes, tienen que llegar al borde nuevo.
await cambiarTamano(1200, 800);
await avanzar(CUADROS_DE_MARGEN);
const peorAgrandada = await avanzar(CUADROS);
const hastaDondeLlegan = await page.evaluate(() => Math.max(...[...document.querySelectorAll('.particle')].map((el) => el.getBoundingClientRect().bottom)));

await browser.close();

const problemas = [...errores];
if (peor.particulas === 0) problemas.push('no hay partículas en la página');
const revisar = (medido, cuando) => {
    for (const [borde, texto] of [['piso', 'bajo el piso'], ['izquierda', 'fuera de la pared izquierda'], ['derecha', 'fuera de la pared derecha']]) {
        if (medido[borde] > TOLERANCIA) problemas.push(`${cuando}: una partícula llegó a quedar ${medido[borde].toFixed(2)} px ${texto}`);
    }
};
revisar(peor, 'ventana de 900×600');
revisar(peorAchicada, 'ventana achicada a 400×300');
revisar(peorAgrandada, 'ventana agrandada a 1200×800');
if (hastaDondeLlegan < 700) problemas.push(`ventana agrandada a 1200×800: la partícula más baja quedó en ${hastaDondeLlegan.toFixed(0)} px, no llegó al piso nuevo`);

console.log(problemas.length
    ? '✗ animacion-g.html'
    : `✓ animacion-g.html: ${peor.particulas} partículas, ${CUADROS} cuadros, siempre dentro de la ventana, también al achicarla y al agrandarla`);
problemas.forEach(p => console.log(`    ${p}`));
process.exit(problemas.length ? 1 : 0);
