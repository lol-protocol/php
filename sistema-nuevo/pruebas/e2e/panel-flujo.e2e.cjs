const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion } = require("./ayudante-e2e.cjs");

(async () => {
  const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" });
  const page = await browser.newPage();
  await iniciarSesion(page);

  await paso("elegir un usuario carga su tarjeta y su flujo de acciones", async () => {
    await page.selectOption("#user-select", "u030");
    await page.waitForTimeout(600);
    assert.equal(await page.textContent("#user-card h2"), "敏 张");
    assert.equal((await page.locator(".timeline-item").count()) > 0, true);
  });

  await paso("la paginación avanza de página y cambia los ítems mostrados", async () => {
    const primero1 = await page.locator(".card-title").first().textContent();
    await page.click('#pagination button:has-text("Siguiente")');
    await page.waitForTimeout(500);
    const primero2 = await page.locator(".card-title").first().textContent();
    assert.notEqual(primero1, primero2);
    assert.match(await page.textContent("#pagination"), /Página 2 de/);
  });

  await paso("el filtro de tipo de acción solo deja acciones de ese tipo", async () => {
    await page.selectOption("#type-select", "payment");
    await page.waitForTimeout(500);
    const titulos = await page.locator(".card-title").allTextContents();
    assert.equal(titulos.length > 0, true);
    assert.equal(titulos.every((t) => t === "Pago"), true);
    await page.selectOption("#type-select", "all");
    await page.waitForTimeout(400);
  });

  await paso("el gráfico de evolución se dibuja cuando hay datos", async () => {
    assert.equal(await page.isHidden("#chart-container"), false);
    assert.equal(await page.locator("#chart-container svg").count(), 1);
  });

  await paso("el panel de alertas lista usuarios y saltar a uno cambia la selección", async () => {
    assert.equal(await page.isHidden("#alerts-panel"), false);
    const botonTexto = await page.textContent("#alerts-list .alerts-button >> nth=0");
    const nombreEsperado = botonTexto.split(" (")[0];
    await page.click("#alerts-list .alerts-button >> nth=0");
    await page.waitForTimeout(500);
    assert.equal(await page.textContent("#user-card h2"), nombreEsperado);
  });

  await paso("el selector de idioma traduce la interfaz sin recargar", async () => {
    await page.click('.lang-button[data-lang="en"]');
    await page.waitForTimeout(300);
    assert.equal(await page.textContent("#logout-button"), "Log out");
    await page.click('.lang-button[data-lang="es"]');
    await page.waitForTimeout(300);
    assert.equal(await page.textContent("#logout-button"), "Cerrar sesión");
  });

  await browser.close();
  process.exit(resumenPasos());
})();
