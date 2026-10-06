// El cierre de sesión por inactividad: a los 29 minutos sin actividad aparece un aviso ("Sesión por expirar") con dos
// botones, y a los 30 la sesión se cierra sola. Se prueba con el reloj falso de Playwright (page.clock), que adelanta
// el tiempo del navegador sin esperar de verdad. Antes no tenía ninguna prueba. Cada paso arranca con un navegador
// nuevo y una sesión nueva, para que un paso que falla no arrastre a los demás.
const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion } = require("./ayudante-e2e.cjs");

const API = "http://localhost:8000";

/** Abre el panel con el reloj falso instalado y la sesión iniciada, corre fn(page) y cierra el navegador. */
async function conPanel(browser, fn, opciones = {}) {
  const contexto = await browser.newContext(opciones);
  const page = await contexto.newPage();
  try {
    await page.clock.install();
    await iniciarSesion(page);
    await fn(page);
  } finally {
    await contexto.close();
  }
}

const aviso = (page) => page.getByText("Sesión por expirar", { exact: true });
const botonContinuar = (page) => page.getByRole("button", { name: "Continuar activo" });
const botonSalir = (page) => page.getByRole("button", { name: "Cerrar sesión ahora" });
const sesionDelServidor = async (page) => (await (await page.request.get(`${API}/api/session`)).json()).authenticated;

(async () => {
  const browser = await chromium.launch();

  await paso("a los 28:30 sin actividad todavía no hay aviso; a los 29:30 aparece, con el mensaje y los dos botones", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("28:30");
      assert.equal(await aviso(page).isVisible(), false);
      await page.clock.runFor("01:00");
      assert.equal(await aviso(page).isVisible(), true);
      assert.equal(await page.getByText("Por inactividad, tu sesión se cerrará en 1 minuto.").isVisible(), true);
      assert.equal(await botonContinuar(page).isVisible(), true);
      assert.equal(await botonSalir(page).isVisible(), true);
    })
  );

  await paso("mover el mouse o scrollear no cierra el aviso: hay que poder llegar a los botones", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      await page.mouse.move(200, 200);
      await page.mouse.move(420, 320);
      await page.mouse.wheel(0, 120);
      await page.waitForTimeout(100);
      assert.equal(await aviso(page).isVisible(), true);
    })
  );

  await paso("'Continuar activo' cierra el aviso, la sesión sigue y el próximo aviso llega 29 minutos después del clic", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      await botonContinuar(page).click({ timeout: 3000 });
      assert.equal(await aviso(page).isVisible(), false);
      assert.equal(await page.locator("#app").isVisible(), true);
      await page.clock.runFor("28:30");
      assert.equal(await aviso(page).isVisible(), false, "el reloj de inactividad arranca de nuevo con el clic");
      await page.clock.runFor("01:00");
      assert.equal(await aviso(page).isVisible(), true, "el segundo aviso también aparece");
      assert.equal(await sesionDelServidor(page), true);
    })
  );

  await paso("'Cerrar sesión ahora' cierra la sesión al instante, con el mouse", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      await botonSalir(page).click({ timeout: 3000 });
      await page.waitForSelector("#login-screen:not([hidden])", { timeout: 3000 });
      assert.equal(await page.textContent("#login-error"), "Tu sesión fue cerrada por inactividad.");
      assert.equal(await sesionDelServidor(page), false, "el servidor también cerró la sesión");
    })
  );

  await paso("si no se contesta, un minuto después del aviso la sesión se cierra sola", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      assert.equal(await aviso(page).isVisible(), true);
      await page.clock.runFor("00:20"); // 29:50: al aviso le queda medio minuto
      assert.equal(await page.locator("#app").isVisible(), true, "todavía queda tiempo");
      await page.clock.runFor("00:20"); // 30:10: se cumplieron los 30 minutos
      await page.waitForSelector("#login-screen:not([hidden])", { timeout: 3000 });
      assert.equal(await page.textContent("#login-error"), "Tu sesión fue cerrada por inactividad.");
      assert.equal(await aviso(page).isVisible(), false, "el aviso no queda flotando sobre el login");
      assert.equal(await sesionDelServidor(page), false);
    })
  );

  await paso("el aviso sale en el idioma elegido", () =>
    conPanel(browser, async (page) => {
      await page.click('.lang-button[data-lang="en"]');
      await page.clock.runFor("29:30");
      assert.equal(await page.getByText("Session expiring", { exact: true }).isVisible(), true);
      assert.equal(await page.getByRole("button", { name: "Log out now" }).isVisible(), true);
      await page.getByRole("button", { name: "Stay logged in" }).click({ timeout: 3000 });
      assert.equal(await page.getByText("Session expiring", { exact: true }).isVisible(), false);
    })
  );

  await paso("con el aviso abierto, mover el mouse no posterga el cierre de la sesión", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      await page.mouse.move(300, 300);
      await page.clock.runFor("00:20");
      await page.mouse.move(500, 400);
      await page.clock.runFor("00:20"); // 30:10 desde la última actividad real
      await page.waitForSelector("#login-screen:not([hidden])", { timeout: 3000 });
      assert.equal(await sesionDelServidor(page), false);
    })
  );

  await paso("con una pantalla táctil, tocar 'Cerrar sesión ahora' también cierra la sesión", () =>
    conPanel(
      browser,
      async (page) => {
        await page.clock.runFor("29:30");
        const caja = await botonSalir(page).boundingBox();
        await page.touchscreen.tap(caja.x + caja.width / 2, caja.y + caja.height / 2);
        await page.waitForSelector("#login-screen:not([hidden])", { timeout: 3000 });
        assert.equal(await sesionDelServidor(page), false);
      },
      { hasTouch: true }
    )
  );

  await paso("Escape y un clic afuera del cuadro cierran el aviso y la sesión sigue, como en los demás modales", () =>
    conPanel(browser, async (page) => {
      await page.clock.runFor("29:30");
      assert.equal(await aviso(page).isVisible(), true);
      await page.keyboard.press("Escape");
      assert.equal(await aviso(page).isVisible(), false, "Escape");
      await page.clock.runFor("29:30");
      assert.equal(await aviso(page).isVisible(), true, "vuelve a avisar 29 minutos después");
      await page.mouse.click(5, 5);
      assert.equal(await aviso(page).isVisible(), false, "clic afuera");
      assert.equal(await page.locator("#app").isVisible(), true);
      assert.equal(await sesionDelServidor(page), true);
    })
  );

  await paso("si hay otro modal abierto cuando llega el aviso, queda uno solo: el del aviso", () =>
    conPanel(browser, async (page) => {
      await page.click("#btn-guardar-filtro");
      await page.waitForSelector(".modal-fondo");
      await page.clock.runFor("29:30");
      assert.equal(await aviso(page).isVisible(), true);
      assert.equal(await page.locator(".modal-fondo").count(), 1, "el modal del filtro se cierra: no quedan dos encimados");
      await botonContinuar(page).click({ timeout: 3000 });
      assert.equal(await page.locator(".modal-fondo").count(), 0, "al contestar el aviso no queda ningún modal");
    })
  );

  await browser.close();
  process.exit(resumenPasos());
})();
