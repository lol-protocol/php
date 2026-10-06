const { assert, paso, correrPrueba, enviarLogin, iniciarSesion, paginaEnIngles, BASE_URL, API_URL } = require("./ayudante-e2e.cjs");

correrPrueba(async ({ browser, page }) => {
  await paso("credenciales incorrectas muestran un error y no entran al panel", async () => {
    await page.goto(BASE_URL);
    await enviarLogin(page, "clave-incorrecta");
    await page.waitForSelector("#login-error:not([hidden])");
    assert.equal(await page.textContent("#login-error"), "usuario o contraseña incorrectos");
    assert.equal(await page.isHidden("#app"), true);
  });

  await paso("con la interfaz en inglés, el error de credenciales sale en inglés (no el texto en español del backend)", async () => {
    const { page: paginaEn, cerrar } = await paginaEnIngles(browser);
    await paginaEn.goto(BASE_URL);
    await enviarLogin(paginaEn, "clave-incorrecta");
    await paginaEn.waitForSelector("#login-error:not([hidden])");
    assert.equal(await paginaEn.textContent("#login-error"), "wrong username or password");
    await cerrar();
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
    const sinToken = await page.request.post(`${API_URL}/api/notes`, { data: { accion_id: "a00037", texto: "no debería guardarse" } });
    assert.equal(sinToken.status(), 403);

    const tokenInvalido = await page.request.post(`${API_URL}/api/notes`, {
      headers: { "X-CSRF-Token": "token-invento-falso" },
      data: { accion_id: "a00037", texto: "no debería guardarse" },
    });
    assert.equal(tokenInvalido.status(), 403);

    // JSON_UNESCAPED_UNICODE en todos los json_encode(): la tilde viaja como
    // UTF-8 crudo ("inválido"), no escapada ("á") -- mismo formato de
    // cable en toda la API, no solo en los endpoints con payloads grandes.
    const cuerpo = await tokenInvalido.text();
    assert.equal(cuerpo.includes("inválido"), true);
    assert.equal(cuerpo.includes("\\u00e1"), false);

    // Además del texto (para quien lee la respuesta a mano), un código estable que la interfaz traduce.
    assert.equal(JSON.parse(cuerpo).codigo, "csrf_invalido");
  });

  await paso("cerrar sesión vuelve a la pantalla de login", async () => {
    await page.click("#logout-button");
    await page.waitForSelector("#login-screen:not([hidden])");
    assert.equal(await page.isHidden("#login-screen"), false);
  });

}, { entrar: false });
