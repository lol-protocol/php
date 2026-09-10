const { chromium } = require("playwright");
const { execSync } = require("node:child_process");
const { assert, paso, resumenPasos, iniciarSesion, BASE_URL } = require("./ayudante-e2e.cjs");

function limpiarIntentosLogin() {
  const env = {
    ...process.env,
    PGPASSWORD: process.env.BACKOFFICE_BD_CLAVE || "backoffice_dev_2026",
  };
  const host = process.env.BACKOFFICE_BD_HOST || "localhost";
  const usuario = process.env.BACKOFFICE_BD_USUARIO || "backoffice_app";
  const nombre = process.env.BACKOFFICE_BD_NOMBRE || "backoffice";
  execSync(`psql -h ${host} -U ${usuario} -d ${nombre} -c "DELETE FROM intentos_login;"`, { env, stdio: "pipe" });
}

async function intentarLogin(page, password) {
  const respuesta = page.waitForResponse((r) => r.url().includes("/api/login"));
  await page.fill("#login-username", "admin");
  await page.fill("#login-password", password);
  await page.click("#login-form button[type=submit]");
  return respuesta;
}

(async () => {
  const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" });
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

  await browser.close();

  // Rate limiting: aparte, en su propia sesión de navegador y SIEMPRE con
  // limpieza al final (try/finally) -- si esto queda bloqueado, toda corrida
  // posterior de la suite (o el uso real del panel) se rompe por 15 minutos.
  try {
    const browser2 = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" });
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
