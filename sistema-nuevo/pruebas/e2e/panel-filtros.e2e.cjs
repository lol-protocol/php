// Los cinco filtros de la barra superior (país, edad mínima y máxima, género, tipo de acción) y el aviso de error que
// se muestra arriba del timeline, tal como los usa la persona: qué se manda al pedir el timeline, cuándo se pide, qué
// pasa al cambiar de idioma, qué se guarda y qué se aplica de un filtro guardado, y cómo se avisa de un fallo.
// Son pruebas de comportamiento: no saben en qué módulo vive cada cosa, así que valen igual antes y después de
// reorganizar el código de la interfaz.
const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion, ejecutarSql, consultarSql } = require("./ayudante-e2e.cjs");

const LIMPIAR = "DELETE FROM filtros_guardados WHERE nombre LIKE 'e2e-controles-%';";
const CORS = { "Access-Control-Allow-Origin": "http://localhost:8082", "Access-Control-Allow-Credentials": "true" };

(async () => {
  ejecutarSql(LIMPIAR); // por si quedó sucio de una corrida anterior interrumpida
  const browser = await chromium.launch();
  const page = await browser.newPage();
  try {
    await iniciarSesion(page);

    // Los parámetros de cada pedido de /api/timeline que hace la página, en orden.
    const pedidos = [];
    page.on("request", (req) => {
      const url = new URL(req.url());
      if (url.pathname === "/api/timeline") pedidos.push(Object.fromEntries(url.searchParams));
    });
    const ultimo = () => pedidos[pedidos.length - 1];

    const usuario = await page.inputValue("#user-select");
    const pais = await page.locator('#scope-select option[value^="country:"]').first().getAttribute("value");
    // Lo que tiene que viajar en el pedido según lo elegido en los controles (cada paso va cambiando una parte).
    const elegido = { scope: "all", age_min: "18", age_max: "65", gender: "all", type: "all" };
    const pedidoEsperado = () => ({ user_id: usuario, ...elegido, page: "1", per_page: "20" });

    await paso("cada desplegable (país, género, tipo) pide el timeline al instante con lo elegido", async () => {
      for (const [control, parametro, valor] of [
        ["#scope-select", "scope", pais],
        ["#gender-select", "gender", "F"],
        ["#type-select", "type", "payment"],
      ]) {
        const antes = pedidos.length;
        await page.selectOption(control, valor);
        await page.waitForTimeout(250);
        assert.equal(pedidos.length, antes + 1, `${control}: tiene que pedir el timeline una sola vez`);
        elegido[parametro] = valor;
        assert.deepEqual(ultimo(), pedidoEsperado(), `${control}: lo que viaja en el pedido`);
      }
    });

    await paso("las edades esperan a que se deje de tipear: varias teclas seguidas son un solo pedido", async () => {
      for (const [control, parametro, valor] of [
        ["#age-min", "age_min", "33"],
        ["#age-max", "age_max", "47"],
      ]) {
        const antes = pedidos.length;
        await page.locator(control).fill("");
        await page.locator(control).pressSequentially(valor, { delay: 30 });
        await page.waitForTimeout(150);
        assert.equal(pedidos.length, antes, `${control}: todavía tiene que estar esperando`);
        await page.waitForTimeout(700);
        assert.equal(pedidos.length, antes + 1, `${control}: un solo pedido cuando se deja de tipear`);
        elegido[parametro] = valor;
        assert.deepEqual(ultimo(), pedidoEsperado(), `${control}: lo que viaja en el pedido (con los cinco filtros)`);
      }
    });

    await paso("cambiar de idioma conserva lo elegido en los cinco controles y no vuelve a pedir el timeline", async () => {
      const antes = pedidos.length;
      for (const idioma of ["en", "es"]) {
        await page.click(`.lang-button[data-lang="${idioma}"]`);
        await page.waitForTimeout(400);
        assert.equal(await page.inputValue("#scope-select"), pais, `[${idioma}] país`);
        assert.equal(await page.inputValue("#type-select"), "payment", `[${idioma}] tipo de acción`);
        assert.equal(await page.inputValue("#gender-select"), "F", `[${idioma}] género`);
        assert.equal(await page.inputValue("#age-min"), "33", `[${idioma}] edad mínima`);
        assert.equal(await page.inputValue("#age-max"), "47", `[${idioma}] edad máxima`);
      }
      assert.equal(pedidos.length, antes, "cambiar de idioma vuelve a pintar lo último recibido, sin pedir nada");
    });

    const guardarPorUI = async (nombre) => {
      await page.click("#btn-guardar-filtro");
      await page.waitForSelector(".modal-fondo");
      await page.fill(".modal-caja input", nombre);
      await page.click(".modal-btn-confirmar");
      await page.waitForSelector(".modal-fondo", { state: "detached" });
      await page.waitForSelector(`#saved-filters-select option:has-text('${nombre}')`, { state: "attached" });
    };
    const filaGuardada = (nombre) =>
      consultarSql(`SELECT scope, age_min, age_max, gender, tipo_accion FROM filtros_guardados WHERE nombre = '${nombre}'`)[0];

    await paso("guardar un filtro manda a la base lo que hay elegido en los cinco controles", async () => {
      await guardarPorUI("e2e-controles-1");
      assert.deepEqual(filaGuardada("e2e-controles-1"), [pais, "33", "47", "F", "payment"]);

      // "todos" se guarda como sin filtro (NULL / all_countries) y una edad vacía como NULL.
      await page.selectOption("#scope-select", "all");
      await page.selectOption("#gender-select", "all");
      await page.selectOption("#type-select", "all");
      await page.locator("#age-min").fill("");
      await page.waitForTimeout(100);
      await guardarPorUI("e2e-controles-2");
      assert.deepEqual(filaGuardada("e2e-controles-2"), ["all_countries", null, "47", null, null]);
    });

    await paso("aplicar un filtro guardado pone los cinco controles y recarga el timeline con ellos", async () => {
      ejecutarSql(`INSERT INTO filtros_guardados (nombre, scope, age_min, age_max, gender, tipo_accion) VALUES
        ('e2e-controles-3', '${pais}', 21, 29, 'O', 'refund'),
        ('e2e-controles-4', 'all_countries', NULL, NULL, NULL, NULL);`);
      await page.reload();
      await page.waitForSelector("#app:not([hidden])", { timeout: 30000 });
      await page.waitForTimeout(500);
      const antes = pedidos.length;

      await Promise.all([
        page.waitForRequest((req) => new URL(req.url()).pathname === "/api/timeline"),
        page.selectOption("#saved-filters-select", { label: "e2e-controles-3" }),
      ]);
      await page.waitForTimeout(300);
      assert.equal(pedidos.length, antes + 1, "aplicar un filtro pide el timeline una sola vez");
      assert.deepEqual(
        [
          await page.inputValue("#scope-select"), await page.inputValue("#age-min"), await page.inputValue("#age-max"),
          await page.inputValue("#gender-select"), await page.inputValue("#type-select"),
        ],
        [pais, "21", "29", "O", "refund"]
      );
      assert.deepEqual(
        [ultimo().scope, ultimo().age_min, ultimo().age_max, ultimo().gender, ultimo().type],
        [pais, "21", "29", "O", "refund"]
      );

      // Un filtro con todo en NULL vuelve a los valores de fábrica: todos los países, 18-65, todos los géneros y tipos.
      await page.selectOption("#saved-filters-select", { label: "e2e-controles-4" });
      await page.waitForTimeout(500);
      assert.deepEqual(
        [
          await page.inputValue("#scope-select"), await page.inputValue("#age-min"), await page.inputValue("#age-max"),
          await page.inputValue("#gender-select"), await page.inputValue("#type-select"),
        ],
        ["all", "18", "65", "all", "all"]
      );
    });

    await paso("si /api/timeline falla, un aviso con ⚠ y el mensaje lo dice, y se va con el próximo pedido que sale bien", async () => {
      assert.equal(await page.isHidden("#status-message"), true, "antes del fallo no hay aviso");
      await page.route("**/api/timeline*", (route) =>
        route.fulfill({ status: 500, contentType: "application/json", headers: CORS, body: JSON.stringify({ error: "x", codigo: "error_interno" }) })
      );
      await page.selectOption("#type-select", "login");
      await page.waitForSelector("#status-message:not([hidden])", { timeout: 3000 });
      assert.equal(await page.textContent("#status-message"), "⚠ error interno del servidor");
      assert.match(await page.getAttribute("#status-message", "class"), /status-message--warning/);

      await page.unroute("**/api/timeline*");
      await page.selectOption("#type-select", "all");
      await page.waitForSelector("#status-message", { state: "hidden", timeout: 3000 });
    });

    await paso("si falla la carga inicial (sin red), el aviso con ⚠ también aparece", async () => {
      await page.route("**/api/kpis", (route) => route.abort("failed"));
      await page.reload();
      await page.waitForSelector("#app:not([hidden])", { timeout: 30000 });
      await page.waitForSelector("#status-message:not([hidden])", { timeout: 5000 });
      assert.match(await page.textContent("#status-message"), /^⚠ \S/);
      assert.match(await page.getAttribute("#status-message", "class"), /status-message--warning/);
      await page.unroute("**/api/kpis");
      await page.reload();
      await page.waitForSelector("#app:not([hidden])", { timeout: 30000 });
    });
  } finally {
    await browser.close();
    ejecutarSql(LIMPIAR);
  }
  process.exit(resumenPasos());
})();
