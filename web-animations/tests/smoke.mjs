// Abre cada animación en Chromium y falla si hay errores de JS, recursos que
// no cargan, helpers compartidos sin aplicar, o un botón de pausa que no responde.
import { chromium } from 'playwright';
import { readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const animationsDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'animations');
const pages = readdirSync(animationsDir).filter(f => f.endsWith('.html')).sort();

const browser = await chromium.launch();
const failures = [];

for (const file of pages) {
    const page = await browser.newPage();
    const problems = [];

    page.on('pageerror', err => problems.push(`JS: ${err.message}`));
    page.on('console', msg => { if (msg.type() === 'error') problems.push(`console: ${msg.text()}`); });
    page.on('requestfailed', req => problems.push(`no carga: ${req.url().split('/web-animations/')[1] ?? req.url()}`));

    await page.goto(pathToFileURL(join(animationsDir, file)).href, { waitUntil: 'load' });

    if (file.startsWith('animacion-')) {
        const state = await page.evaluate(() => ({
            css: getComputedStyle(document.documentElement).getPropertyValue('--primary-gradient').trim() !== '',
            js: typeof AnimationToggle === 'function',
        }));
        if (!state.css) problems.push('common.css no aplicado');
        if (!state.js) problems.push('common.js no cargado');

        const button = page.locator('button').first();
        if (await button.count()) {
            const before = await button.textContent();
            await button.click();
            const after = await button.textContent();
            if (before === after) problems.push(`el botón no cambió de estado ("${before}")`);
        }
    }

    await page.waitForTimeout(300);
    await page.close();

    console.log(`${problems.length ? '✗' : '✓'} ${file}`);
    problems.forEach(p => console.log(`    ${p}`));
    if (problems.length) failures.push(file);
}

await browser.close();

console.log(`\n${pages.length - failures.length}/${pages.length} páginas OK`);
process.exit(failures.length ? 1 : 0);
