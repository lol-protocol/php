const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion, BASE_URL } = require("./ayudante-e2e.cjs");

(async () => {
  const browser = await chromium.launch({ executablePath: "/opt/pw-browsers/chromium" });
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

  await paso("cerrar sesión vuelve a la pantalla de login", async () => {
    await page.click("#logout-button");
    await page.waitForSelector("#login-screen:not([hidden])");
    assert.equal(await page.isHidden("#login-screen"), false);
  });

  await browser.close();
  process.exit(resumenPasos());
})();
