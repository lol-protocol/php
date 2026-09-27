const { chromium } = require("playwright");
const { execSync } = require("node:child_process");
const { assert, paso, resumenPasos, iniciarSesion, BASE_URL } = require("./ayudante-e2e.cjs");

function ejecutarSql(sql) {
  const env = {
    ...process.env,
    PGPASSWORD: process.env.BACKOFFICE_BD_CLAVE || "backoffice_dev_2026",
  };
  const host = process.env.BACKOFFICE_BD_HOST || "localhost";
  const usuario = process.env.BACKOFFICE_BD_USUARIO || "backoffice_app";
  const nombre = process.env.BACKOFFICE_BD_NOMBRE || "backoffice";
  execSync(`psql -h ${host} -U ${usuario} -d ${nombre} -c "${sql}"`, { env, stdio: "pipe" });
}

function limpiarIntentosLogin() {
  ejecutarSql("DELETE FROM intentos_login;");
}

async function intentarLogin(page, password) {
  const respuesta = page.waitForResponse((r) => r.url().includes("/api/login"));
  await page.fill("#login-username", "admin");
  await page.fill("#login-password", password);
  await page.click("#login-form button[type=submit]");
  return respuesta;
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await iniciarSesion(page);

  await paso("dashboard de KPIs incluye el tile de usuarios con alertas", async () => {
    await page.waitForSelector("#kpis-dashboard .kpi-tile");
    const labels = await page.locator(".kpi-label").allTextContents();
    assert.equal(labels.some((l) => /alert/i.test(l)), true);
  });

  await paso("escribir una nota muestra el indicador de guardado", async () => {
    await page.selectOption("#user-select", "u030");
    await page.waitForTimeout(600);
    const textarea = page.locator(".note-textarea").first();
    const respuesta = page.waitForResponse((r) => r.url().includes("/api/notes"));
    await textarea.fill("nota con feedback visual");
    await respuesta; // el POST solo sale tras los 600ms de debounce: esto ya los cubre
    await page.waitForSelector(".note-status--ok", { timeout: 1000 });
    await page.waitForTimeout(2200); // el "✓" se autooculta a los 2s
    assert.equal(await page.locator(".note-status").first().textContent(), "");
    await textarea.fill("");
    await page.waitForResponse((r) => r.url().includes("/api/notes"));
  });

  await paso("dos guardados de nota superpuestos: gana el último texto escrito, no el que llega primero", async () => {
    const textarea = page.locator(".note-textarea").first();
    let numeroRequest = 0;
    await page.route("**/api/notes", async (route) => {
      numeroRequest++;
      if (numeroRequest === 1) await new Promise((r) => setTimeout(r, 1000)); // el 1er guardado (texto viejo) se demora a propósito
      await route.continue();
    });

    await textarea.fill("primero");
    await page.waitForTimeout(700); // deja que el debounce dispare el 1er guardado, todavía en vuelo
    await textarea.fill("segundo"); // sin el fix, este 2do guardado le podía ganar la carrera al primero
    await page.waitForTimeout(2200); // esperar a que ambas respuestas vuelvan, incluida la demorada
    await page.unroute("**/api/notes");

    // Releer desde el backend (no el DOM, que nunca se tocó): si el guardado
    // viejo pisó al nuevo, esto lo muestra.
    await page.selectOption("#user-select", "u030");
    await page.waitForTimeout(600);
    assert.equal(await page.locator(".note-textarea").first().inputValue(), "segundo");

    await textarea.fill("");
    await page.waitForResponse((r) => r.url().includes("/api/notes"));
  });

  await paso("guardar un filtro con el backend caído muestra un toast de error", async () => {
    await page.route("**/api/filtros", (route) => route.abort("failed"));
    await page.click("#btn-guardar-filtro");
    await page.waitForSelector(".modal-fondo");
    await page.fill(".modal-caja input", "filtro que va a fallar");
    await page.click(".modal-btn-confirmar");
    await page.waitForSelector("#toast-error:not([hidden])", { timeout: 2000 });
    assert.equal((await page.textContent("#toast-error")).length > 0, true);
    await page.unroute("**/api/filtros");
  });

  await paso("guardar/eliminar un filtro por la UI usa el modal propio, no los diálogos nativos del navegador", async () => {
    ejecutarSql("DELETE FROM filtros_guardados WHERE nombre = 'e2e-test-filtro-modal';"); // por si quedó sucio de una corrida anterior interrumpida

    let aparecioDialogoNativo = false;
    page.on("dialog", () => { aparecioDialogoNativo = true; });

    await page.click("#btn-guardar-filtro");
    await page.waitForSelector(".modal-fondo");
    await page.fill(".modal-caja input", "e2e-test-filtro-modal");
    await page.click(".modal-btn-confirmar");
    await page.waitForSelector(".modal-fondo", { state: "detached" });
    // state: "attached" -- un <option> no es "visible" para Playwright salvo que su <select> esté abierto.
    await page.waitForSelector("#saved-filters-select option:has-text('e2e-test-filtro-modal')", { state: "attached" });

    // Cancelar el modal de confirmación de borrado no borra nada.
    await page.selectOption("#saved-filters-select", { label: "e2e-test-filtro-modal" });
    await page.click("#btn-eliminar-filtro");
    await page.waitForSelector(".modal-fondo");
    await page.click(".modal-btn-cancelar");
    await page.waitForSelector(".modal-fondo", { state: "detached" });
    assert.equal(await page.locator("#saved-filters-select option:has-text('e2e-test-filtro-modal')").count(), 1);

    // Confirmarlo sí borra (peligroso: true -> botón .modal-btn-peligro, no .modal-btn-confirmar).
    await page.click("#btn-eliminar-filtro");
    await page.waitForSelector(".modal-fondo");
    await page.click(".modal-btn-peligro");
    await page.waitForSelector("#saved-filters-select option:has-text('e2e-test-filtro-modal')", { state: "detached" });

    assert.equal(aparecioDialogoNativo, false);
    page.removeAllListeners("dialog");
  });

  await paso("cambiar rápido entre 2 filtros guardados aplica el más nuevo, no el que responde último", async () => {
    ejecutarSql(`INSERT INTO filtros_guardados (nombre, scope, age_min, age_max, gender, tipo_accion) VALUES
      ('e2e-test-filtro-A', 'all_countries', 18, 30, 'M', 'login'),
      ('e2e-test-filtro-B', 'all_countries', 40, 65, 'F', 'payment');`);
    await page.reload();
    await page.waitForSelector("#app:not([hidden])", { timeout: 30000 });
    await page.waitForTimeout(500);

    let numeroRequest = 0;
    await page.route("**/api/filtros", async (route) => {
      numeroRequest++;
      if (numeroRequest === 1) await new Promise((r) => setTimeout(r, 1000)); // filtro A (viejo) se demora a propósito
      await route.continue();
    });

    await page.selectOption("#saved-filters-select", { label: "e2e-test-filtro-A" });
    await page.waitForTimeout(200);
    await page.selectOption("#saved-filters-select", { label: "e2e-test-filtro-B" }); // sin el fix, A (más lento) le podía ganar la carrera a B
    await page.waitForTimeout(1500);
    await page.unroute("**/api/filtros");

    assert.equal(await page.locator("#age-min").inputValue(), "40");
    assert.equal(await page.locator("#age-max").inputValue(), "65");
    assert.equal(await page.locator("#gender-select").inputValue(), "F");

    ejecutarSql("DELETE FROM filtros_guardados WHERE nombre LIKE 'e2e-test-filtro-%';");
  });

  await browser.close();

  // Rate limiting: aparte, en su propia sesión de navegador y SIEMPRE con
  // limpieza al final (try/finally) -- si esto queda bloqueado, toda corrida
  // posterior de la suite (o el uso real del panel) se rompe por 15 minutos.
  try {
    const browser2 = await chromium.launch();
    const page2 = await browser2.newPage();

    await paso("5 intentos fallidos de login bloquean con 429 y mensaje claro", async () => {
      await page2.goto(BASE_URL);
      for (let i = 0; i < 5; i++) {
        const respuesta = await intentarLogin(page2, "clave-mala");
        assert.equal(respuesta.status(), 401);
      }
      // Login #6, ahora con la clave CORRECTA: igual debe rechazarse por el bloqueo.
      const respuestaBloqueada = await intentarLogin(page2, "admin123");
      assert.equal(respuestaBloqueada.status(), 429);
      await page2.waitForSelector("#login-error:not([hidden])");
      assert.match(await page2.textContent("#login-error"), /intentos/i);
      assert.equal(await page2.isHidden("#app"), true);
    });

    await browser2.close();
  } finally {
    limpiarIntentosLogin();
  }

  process.exit(resumenPasos());
})();
