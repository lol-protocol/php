// Las piezas visuales que comparten el login, los modales y la barra de filtros (.campo y .caja-neon, en base.css): que se
// vean igual en los tres lugares, sin comparar píxeles. Antes cada lugar tenía su copia y podían irse separando sin aviso.
const { chromium } = require("playwright");
const { assert, paso, resumenPasos, BASE_URL } = require("./ayudante-e2e.cjs");

const estilo = (page, selector, propiedades) =>
  page.$eval(
    selector,
    (el, props) => {
      const calculado = getComputedStyle(el);
      return Object.fromEntries(props.map((p) => [p, calculado[p]]));
    },
    propiedades
  );

const ASPECTO_DEL_CAMPO = ["fontFamily", "borderTopWidth", "borderTopStyle", "borderTopColor", "borderTopLeftRadius", "backgroundColor", "color"];
const FOCO_DEL_CAMPO = ["borderTopColor", "boxShadow", "outlineStyle"];
const CAJA = ["backgroundColor", "borderTopWidth", "borderTopColor", "borderTopLeftRadius", "boxShadow", "paddingTop", "paddingLeft"];

/** El campo sin foco (su aspecto) y con foco (su brillo). */
async function aspectoYFoco(page, selector) {
  await page.evaluate(() => document.activeElement?.blur());
  const campo = await estilo(page, selector, [...ASPECTO_DEL_CAMPO, "paddingTop", "paddingLeft", "fontSize"]);
  await page.focus(selector);
  const foco = await estilo(page, selector, FOCO_DEL_CAMPO);
  await page.evaluate(() => document.activeElement?.blur());
  return { campo, foco };
}

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(BASE_URL);
  await page.waitForSelector("#login-screen:not([hidden])");

  const login = { ...(await aspectoYFoco(page, "#login-username")), caja: await estilo(page, ".login-card", CAJA) };

  await page.fill("#login-username", "admin");
  await page.fill("#login-password", "admin123");
  await page.click("#login-form button[type=submit]");
  await page.waitForSelector("#app:not([hidden])");
  await page.waitForTimeout(500);

  const brillo = (await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue("--accent-glow"))).trim();
  const acento = (await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue("--accent"))).trim();

  const barra = {};
  for (const id of ["#user-search", "#user-select", "#scope-select", "#type-select", "#age-min", "#age-max", "#gender-select", "#saved-filters-select"]) {
    barra[id] = await aspectoYFoco(page, id);
  }

  await page.click("#btn-guardar-filtro");
  await page.waitForSelector(".modal-caja input");
  const modal = { ...(await aspectoYFoco(page, ".modal-caja input")), caja: await estilo(page, ".modal-caja", CAJA) };

  await paso("al enfocar un campo (login, barra de filtros o modal) se ve el mismo brillo, con el borde del acento y sin el aro del navegador", async () => {
    assert.ok(brillo.startsWith("rgba("), `la variable --accent-glow no está definida: "${brillo}"`);
    const esperado = { borderTopColor: login.foco.borderTopColor, boxShadow: login.foco.boxShadow, outlineStyle: "none" };
    assert.ok(esperado.boxShadow.includes(brillo) && esperado.boxShadow !== "none", `el foco del login no brilla con ${brillo}: ${esperado.boxShadow}`);
    assert.equal(esperado.borderTopColor, "rgb(0, 240, 255)", `el borde enfocado no es el del acento (${acento})`);
    assert.deepEqual(modal.foco, esperado, "campo del modal");
    for (const [id, { foco }] of Object.entries(barra)) assert.deepEqual(foco, esperado, `campo ${id} de la barra`);
  });

  await paso("el aspecto de un campo es el mismo en el login, la barra y el modal; la barra solo lo compacta (relleno y letra)", async () => {
    const comun = (e) => Object.fromEntries(ASPECTO_DEL_CAMPO.map((p) => [p, e[p]]));
    assert.deepEqual(comun(modal.campo), comun(login.campo), "modal contra login");
    assert.equal(modal.campo.paddingTop, login.campo.paddingTop);
    assert.equal(modal.campo.fontSize, login.campo.fontSize);
    for (const [id, { campo }] of Object.entries(barra)) {
      assert.deepEqual(comun(campo), comun(login.campo), `campo ${id} de la barra contra login`);
      assert.notEqual(campo.paddingTop, login.campo.paddingTop, `${id}: la barra usa un relleno más compacto`);
      assert.notEqual(campo.fontSize, login.campo.fontSize, `${id}: la barra usa una letra más chica`);
    }
  });

  await paso("la tarjeta del login y el cuadro de un modal comparten borde, fondo, resplandor y relleno", async () => {
    assert.notEqual(login.caja.boxShadow, "none");
    assert.deepEqual(modal.caja, login.caja);
  });

  await browser.close();
  process.exit(resumenPasos());
})();
