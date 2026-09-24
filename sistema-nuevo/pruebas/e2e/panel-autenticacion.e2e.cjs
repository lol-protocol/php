const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion, BASE_URL } = require("./ayudante-e2e.cjs");

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  await paso("credenciales incorrectas muestran un error y no entran al panel", async () => {
    await page.goto(BASE_URL);
    await page.fill("#login-username", "admin");
    await page.fill("#login-password", "clave-incorrecta");
    await page.click("#login-form button[type=submit]");
    await page.waitForSelector("#login-error:not([hidden])");
    assert.equal(await page.textContent("#login-error"), "usuario o contraseña incorrectos");
    assert.equal(await page.isHidden("#app"), true);
  });

  await paso("credenciales correctas entran al panel y muestran el usuario conectado", async () => {
    await iniciarSesion(page);
    assert.equal(await page.isHidden("#app"), false);
    assert.match(await page.textContent("#session-username"), /admin/);
  });

  await paso("la cookie de sesión es HttpOnly: el JS de la página no puede leerla", async () => {
    const sesion = (await page.context().cookies()).find((c) => c.name === "PHPSESSID");
    assert.ok(sesion, "no hay cookie de sesión después del login");
    assert.equal(sesion.httpOnly, true);
    assert.doesNotMatch(await page.evaluate(() => document.cookie), /PHPSESSID/);
  });

  await paso("un POST sin token CSRF (o con uno inválido) se rechaza pese a tener sesión válida", async () => {
    const API_BASE = "http://localhost:8000";
    const sinToken = await page.request.post(`${API_BASE}/api/notes`, { data: { accion_id: "a00037", texto: "no debería guardarse" } });
    assert.equal(sinToken.status(), 403);

    const tokenInvalido = await page.request.post(`${API_BASE}/api/notes`, {
      headers: { "X-CSRF-Token": "token-invento-falso" },
      data: { accion_id: "a00037", texto: "no debería guardarse" },
    });
    assert.equal(tokenInvalido.status(), 403);
  });

  await paso("cerrar sesión vuelve a la pantalla de login", async () => {
    await page.click("#logout-button");
    await page.waitForSelector("#login-screen:not([hidden])");
    assert.equal(await page.isHidden("#login-screen"), false);
  });

  await browser.close();
  process.exit(resumenPasos());
})();
