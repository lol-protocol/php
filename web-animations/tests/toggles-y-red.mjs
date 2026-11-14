// Los alternadores de common.js, la red radial y la plantilla de secuencias. Esta prueba fija lo que no ve el smoke test:
//  - toggle() corre el callback de SequenceToggle una vez al reanudar, no dos (pause() y resume() ya hacen su parte), y
//    ParticleEmitterToggle arranca un solo emisor;
//  - createRadialWeb cierra cada anillo: tantas líneas como puntos, más las radiales entre capas;
//  - el código que genera jsSequenceAnimation se carga junto a common.js (no vuelve a declarar SequenceToggle).
import { chromium } from 'playwright';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const helpers = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'helpers');

// CHROMIUM = un Chromium ya instalado (los contenedores de la nube traen otra version que la que baja Playwright).
const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
const page = await browser.newPage();
const errores = [];
page.on('pageerror', err => errores.push(`JS: ${err.message}`));
await page.setContent('<button>Pausar/Reanudar</button><svg id="web"></svg>');
await page.addScriptTag({ path: join(helpers, 'common.js') });
await page.addScriptTag({ path: join(helpers, 'animation-templates.js') });

const medido = await page.evaluate(async () => {
    const r = {};

    let llamadas = 0;
    const secuencia = new SequenceToggle(() => llamadas++, 1e6);
    secuencia.toggle();                       // pausa
    r.alPausar = llamadas;
    secuencia.toggle();                       // reanuda
    r.alReanudar = llamadas;
    secuencia.pause();
    llamadas = 0;
    secuencia.resume();
    r.conResume = llamadas;
    secuencia.pause();
    r.temporizadorTrasPausa = secuencia.timeoutId;

    // Un emisor: se cuentan los setInterval que abre toggle().
    const abiertos = [];
    const original = window.setInterval;
    window.setInterval = (...args) => { const id = original(...args); abiertos.push(id); return id; };
    const emisor = new ParticleEmitterToggle(() => {}, 1e6);
    emisor.toggle();                          // pausa
    emisor.toggle();                          // reanuda
    r.emisoresAlReanudar = abiertos.length;
    emisor.pause();
    window.setInterval = original;

    const svg = document.getElementById('web');
    SVGPatterns.createRadialWeb(svg, 0, 0, 3, 6);
    const lineas = [...svg.querySelectorAll('line')];
    // 3 capas de 6 puntos: 18 lados de anillo y 2 capas con 6 radiales hacia dentro.
    r.lineas = lineas.length;
    const primera = lineas.filter(l => l.getAttribute('x2') === lineas[0].getAttribute('x1') && l.getAttribute('y2') === lineas[0].getAttribute('y1'));
    r.cierraElAnillo = primera.length > 0;

    // Código generado por la plantilla, cargado después de common.js como en una página real.
    window.build = () => { window.construido = (window.construido ?? 0) + 1; };
    const codigo = AnimationTemplate.jsSequenceAnimation('build', 1e6);
    r.plantilla = { declaraLaClase: /class\s+SequenceToggle/.test(codigo) };
    const script = document.createElement('script');
    script.textContent = codigo;
    document.body.appendChild(script);
    r.plantilla.construyo = window.construido;
    if (typeof toggle !== 'undefined') toggle.pause();  // `const toggle` del script generado no es propiedad de window
    return r;
});

await browser.close();

const problemas = [...errores];
const igual = (cosa, obtenido, esperado) => {
    if (JSON.stringify(obtenido) !== JSON.stringify(esperado)) problemas.push(`${cosa}: ${JSON.stringify(obtenido)} en vez de ${JSON.stringify(esperado)}`);
};
igual('callback al pausar', medido.alPausar, 0);
igual('callback al reanudar con toggle()', medido.alReanudar, 1);
igual('callback al reanudar con resume()', medido.conResume, 1);
igual('temporizador tras pausar', medido.temporizadorTrasPausa, null);
igual('emisores al reanudar con toggle()', medido.emisoresAlReanudar, 1);
igual('líneas de 3 capas de 6 puntos', medido.lineas, 18 + 2 * 6);
igual('el anillo se cierra', medido.cierraElAnillo, true);
igual('la plantilla no declara SequenceToggle', medido.plantilla.declaraLaClase, false);
igual('la plantilla corre su función una vez al cargarse', medido.plantilla.construyo, 1);

console.log(problemas.length ? '✗ alternadores y red radial' : '✓ alternadores, red radial y plantilla de secuencias');
problemas.forEach(p => console.log(`    ${p}`));
process.exit(problemas.length ? 1 : 0);
