// Los porcentajes y los colores de los badges de cada tarjeta ("+25% más lento", "≈ promedio (+3%)")
// salen de una cadena de tres eslabones: el servicio Java calcula promedio/mediana/p90 de un universo
// de comparación, PHP calcula el delta % de cada acción contra ese promedio y el JS lo pinta verde,
// rojo o gris. Los demás e2e solo ven que los badges existan; ninguno miraba un valor. Acá cada
// eslabón se contrasta contra PostgreSQL, que sirve de oráculo independiente: percentile_cont()
// usa la misma definición de percentil (interpolación lineal, rank = p * (n - 1)) pero es otra
// implementación, y lee las tablas en vez del CSV que lee Java.
const { chromium } = require("playwright");
const { assert, paso, resumenPasos, iniciarSesion, consultarSql } = require("./ayudante-e2e.cjs");

const JAVA = "http://localhost:8081";
const API = "http://localhost:8000";
// El servicio redondea a 1 decimal las duraciones y a 2 los montos en USD.
const REDONDEO_MS = 0.05;
const REDONDEO_USD = 0.005;

/** Los valores van a un SQL armado a mano: solo pasan los que tienen la forma esperada. */
function validar(valor, patron, que) {
  if (!patron.test(valor)) throw new Error(`${que} inesperado: ${valor}`);
  return valor;
}

/**
 * El universo que arma ManejadorEstadisticas (tipo + países + edad + género, sin el usuario
 * excluido), pero calculado con SQL sobre las tablas.
 */
function oraculo({ tipo, paises = null, edadMin = 0, edadMax = 150, genero = "all", excluir = null }) {
  const donde = [
    `a.tipo_clave = '${validar(tipo, /^[a-z_]+$/, "tipo")}'`,
    `u.edad BETWEEN ${Number(edadMin)} AND ${Number(edadMax)}`,
  ];
  if (paises !== null) {
    donde.push(`u.pais_codigo IN (${paises.map((p) => `'${validar(p, /^[A-Z]{2}$/, "país")}'`).join(", ")})`);
  }
  if (genero !== "all") donde.push(`u.genero = '${validar(genero, /^[MFO]$/, "género")}'`);
  if (excluir !== null) donde.push(`a.usuario_id <> '${validar(excluir, /^u\d+$/, "usuario")}'`);

  const [fila] = consultarSql(`
    SELECT count(*),
           avg(a.duracion_ms),
           percentile_cont(0.5) WITHIN GROUP (ORDER BY a.duracion_ms),
           percentile_cont(0.9) WITHIN GROUP (ORDER BY a.duracion_ms),
           avg(a.monto_usd),
           percentile_cont(0.5) WITHIN GROUP (ORDER BY a.monto_usd),
           percentile_cont(0.9) WITHIN GROUP (ORDER BY a.monto_usd)
    FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
    WHERE ${donde.join(" AND ")}`);
  const [count, avgMs, medMs, p90Ms, avgUsd, medUsd, p90Usd] = fila.map((c) => (c === null ? null : Number(c)));
  return { count, avgMs, medMs, p90Ms, avgUsd, medUsd, p90Usd };
}

async function javaStats({ tipo, paises = null, edadMin, edadMax, genero, excluir }) {
  const q = new URLSearchParams({ type: tipo });
  if (paises !== null) q.set("countries", paises.join(","));
  if (edadMin !== undefined) q.set("age_min", String(edadMin));
  if (edadMax !== undefined) q.set("age_max", String(edadMax));
  if (genero !== undefined) q.set("gender", genero);
  if (excluir !== undefined && excluir !== null) q.set("exclude", excluir);
  const r = await fetch(`${JAVA}/stats?${q}`);
  assert.equal(r.status, 200, `GET /stats?${q}`);
  return r.json();
}

function cercano(real, esperado, tolerancia, etiqueta) {
  if (esperado === null) {
    assert.equal(real, null, `${etiqueta}: esperaba null y vino ${real}`);
    return;
  }
  assert.notEqual(real, null, `${etiqueta}: esperaba ${esperado} y vino null`);
  assert.ok(Math.abs(real - esperado) <= tolerancia + 1e-9, `${etiqueta}: servicio ${real} vs PostgreSQL ${esperado}`);
}

/** El servicio devuelve avg_duration_ms 0.0 (y el resto null) cuando el universo no tiene acciones. */
function compararUniverso(real, sql, etiqueta) {
  assert.equal(real.count, sql.count, `${etiqueta}: count`);
  cercano(real.avg_duration_ms, sql.count === 0 ? 0 : sql.avgMs, REDONDEO_MS, `${etiqueta}: avg_duration_ms`);
  cercano(real.median_duration_ms, sql.medMs, REDONDEO_MS, `${etiqueta}: median_duration_ms`);
  cercano(real.p90_duration_ms, sql.p90Ms, REDONDEO_MS, `${etiqueta}: p90_duration_ms`);
  cercano(real.avg_amount_usd, sql.avgUsd, REDONDEO_USD, `${etiqueta}: avg_amount_usd`);
  cercano(real.median_amount_usd, sql.medUsd, REDONDEO_USD, `${etiqueta}: median_amount_usd`);
  cercano(real.p90_amount_usd, sql.p90Usd, REDONDEO_USD, `${etiqueta}: p90_amount_usd`);
}

/** Sesión contra la API real (cookie de sesión a mano): devuelve un GET que parsea el JSON. */
async function sesionApi() {
  const r = await fetch(`${API}/api/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ username: "admin", password: "admin123" }),
  });
  assert.equal(r.status, 200, "login contra la API");
  const cookie = r.headers.getSetCookie().map((c) => c.split(";")[0]).join("; ");
  return async (ruta) => {
    const res = await fetch(`${API}${ruta}`, { headers: { Cookie: cookie } });
    assert.equal(res.status, 200, `GET ${ruta}`);
    return res.json();
  };
}

function mismoDelta(real, esperado, etiqueta) {
  if (esperado === null) {
    assert.equal(real, null, `${etiqueta}: esperaba null y vino ${real}`);
    return;
  }
  assert.notEqual(real, null, `${etiqueta}: esperaba ${esperado} y vino null`);
  assert.ok(Math.abs(real - esperado) < 1e-6, `${etiqueta}: delta ${real} vs ${esperado}`);
}

/** El delta % que tiene que mostrar una acción, calculado del promedio que viaja con ella. */
function deltaEsperado(valor, promedio) {
  return valor === null || !(promedio > 0) ? null : ((valor - promedio) / promedio) * 100;
}

/** Texto y color de un badge de comparación, según lo que dice el manual: ±10% es "en el promedio". */
async function verificarBadge(badge, delta, etiquetaBuena, etiquetaMala, etiqueta) {
  const clase = await badge.getAttribute("class");
  const texto = (await badge.textContent()).trim();
  let esperadaClase;
  let esperadoTexto;
  if (delta === null) {
    esperadaClase = "badge--neutral";
    esperadoTexto = "Sin datos de comparación";
  } else {
    // Lo que el manual llama "±10%" se mide sobre el porcentaje entero que se ve (2.5 -> 3, -0.3 -> 0).
    const entero = Number(delta.toFixed(0));
    const pct = `${entero > 0 ? "+" : ""}${entero}%`;
    if (Math.abs(entero) <= 10) {
      esperadaClase = "badge--neutral";
      esperadoTexto = `≈ promedio (${pct})`;
    } else if (entero < 0) {
      esperadaClase = "badge--good";
      esperadoTexto = `${pct} ${etiquetaBuena}`;
    } else {
      esperadaClase = "badge--bad";
      esperadoTexto = `${pct} ${etiquetaMala}`;
    }
  }
  assert.ok(clase.split(" ").includes(esperadaClase), `${etiqueta}: delta ${delta}, clase "${clase}" (esperaba ${esperadaClase})`);
  assert.equal(
    clase.split(" ").filter((c) => c.startsWith("badge--")).length,
    1,
    `${etiqueta}: un badge no puede tener dos colores ("${clase}")`
  );
  assert.equal(texto, esperadoTexto, `${etiqueta}: delta ${delta}`);
}

/** La respuesta de /api/timeline que el panel pide para ese usuario (y ese filtro de tipo). */
function esperarTimeline(page, usuario, fragmento) {
  return page.waitForResponse(
    (r) => r.url().includes("/api/timeline") && r.url().includes(`user_id=${usuario}`) && r.url().includes(fragmento) && r.status() === 200
  );
}

/** Compara cada tarjeta del panel con la respuesta de /api/timeline que la dibujó. */
async function verificarTarjetas(page, datos) {
  await page.waitForFunction((n) => document.querySelectorAll(".timeline-item").length === n, datos.timeline.length);
  const tarjetas = page.locator(".timeline-item");
  const vistos = { tarjetas: datos.timeline.length, fueraDelPromedio: 0, conMonto: 0 };
  for (let i = 0; i < datos.timeline.length; i++) {
    const item = datos.timeline[i];
    const badges = tarjetas.nth(i).locator(".badges .badge");
    assert.equal(await badges.count(), item.amount_usd === null ? 1 : 2, `${item.id}: cantidad de badges`);

    await verificarBadge(badges.nth(0), item.duration_delta_pct, "más rápido", "más lento", `${item.id} duración`);
    await verificarTooltip(badges.nth(0), item.cohort.median_duration_ms, `${item.id} duración`);
    if (item.duration_delta_pct !== null && Math.abs(item.duration_delta_pct) > 10) vistos.fueraDelPromedio++;

    if (item.amount_usd !== null) {
      vistos.conMonto++;
      await verificarBadge(badges.nth(1), item.amount_delta_pct, "más barato", "más caro", `${item.id} monto`);
      await verificarTooltip(badges.nth(1), item.cohort.median_amount_usd, `${item.id} monto`);
    }
  }
  return vistos;
}

/** Al pasar el mouse, el badge muestra mediana y p90 del universo (si el universo las tiene). */
async function verificarTooltip(badge, mediana, etiqueta) {
  const titulo = await badge.getAttribute("title");
  if (mediana === null) {
    assert.equal(titulo, null, `${etiqueta}: sin mediana no hay tooltip`);
    return;
  }
  assert.match(titulo, /^mediana: .+ · p90: .+$/, `${etiqueta}: tooltip`);
  assert.equal(titulo.includes("NaN"), false, `${etiqueta}: el tooltip no puede traer NaN ("${titulo}")`);
}

(async () => {
  const tipos = consultarSql("SELECT DISTINCT tipo_clave FROM acciones ORDER BY 1").map(([t]) => t);
  const paisesGrandes = consultarSql("SELECT pais_codigo FROM usuarios GROUP BY 1 ORDER BY count(*) DESC, 1 LIMIT 10").map(([p]) => p);
  const masActivos = consultarSql("SELECT usuario_id FROM acciones GROUP BY 1 ORDER BY count(*) DESC, 1 LIMIT 2").map(([u]) => u);

  await paso("servicio Java == PostgreSQL: promedio, mediana y p90 de cada tipo de acción, sin filtros", async () => {
    assert.ok(tipos.length >= 10, `tipos de acción en la base: ${tipos.length}`);
    for (const tipo of tipos) {
      compararUniverso(await javaStats({ tipo }), oraculo({ tipo }), `tipo ${tipo}`);
    }
  });

  await paso("servicio Java == PostgreSQL con filtros de país, edad y género, y sin el propio usuario", async () => {
    const variantes = [
      ["países, sin el usuario más activo", { paises: paisesGrandes, excluir: masActivos[0] }],
      ["edad y género", { edadMin: 25, edadMax: 45, genero: "M" }],
      ["todo junto", { paises: paisesGrandes, edadMin: 20, edadMax: 60, genero: "F", excluir: masActivos[0] }],
    ];
    let conAcciones = 0;
    for (const [etiqueta, filtros] of variantes) {
      for (const tipo of tipos) {
        const sql = oraculo({ tipo, ...filtros });
        compararUniverso(await javaStats({ tipo, ...filtros }), sql, `${etiqueta}, tipo ${tipo}`);
        if (sql.count > 0) conAcciones++;
      }
    }
    // Para que no pase "en vacío": la mayoría de los universos filtrados tiene acciones que comparar.
    assert.ok(conAcciones >= 20, `universos filtrados con acciones: ${conAcciones} de ${variantes.length * tipos.length}`);
  });

  await paso("servicio Java == PostgreSQL con universos de 1, 2 y 3 acciones (el percentil con pocos datos)", async () => {
    const chicos = consultarSql(`
      SELECT a.tipo_clave, u.pais_codigo, count(*)
      FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
      GROUP BY 1, 2 HAVING count(*) <= 3 ORDER BY 3, 1, 2`);
    for (const n of [1, 2, 3]) {
      const delTamano = chicos.filter(([, , c]) => Number(c) === n).slice(0, 4);
      assert.ok(delTamano.length > 0, `hay universos de ${n} acciones para probar`);
      for (const [tipo, pais] of delTamano) {
        compararUniverso(await javaStats({ tipo, paises: [pais] }), oraculo({ tipo, paises: [pais] }), `${n} acción(es): ${tipo} en ${pais}`);
      }
    }
    // Y lo mismo con montos, que son opcionales: hay pagos sin monto que el promedio tiene que saltear.
    const conMonto = consultarSql(`
      SELECT a.tipo_clave, u.pais_codigo
      FROM acciones a JOIN usuarios u ON u.id = a.usuario_id
      GROUP BY 1, 2 HAVING count(a.monto_usd) BETWEEN 1 AND 3 ORDER BY 1, 2 LIMIT 6`);
    assert.ok(conMonto.length > 0, "hay universos chicos con montos para probar");
    for (const [tipo, pais] of conMonto) {
      compararUniverso(await javaStats({ tipo, paises: [pais] }), oraculo({ tipo, paises: [pais] }), `con montos: ${tipo} en ${pais}`);
    }
  });

  await paso("servicio Java == PostgreSQL en un universo sin ninguna acción: cuenta 0, sin mediana ni p90", async () => {
    for (const tipo of ["payment", "login"]) {
      const real = await javaStats({ tipo, paises: ["ZZ"] });
      assert.equal(real.count, 0, `universo vacío, ${tipo}`);
      compararUniverso(real, oraculo({ tipo, paises: ["ZZ"] }), `universo vacío, ${tipo}`);
    }
  });

  await paso("/api/timeline: cada acción se compara contra el universo elegido SIN el propio usuario, y su delta % sale de ese promedio", async () => {
    const get = await sesionApi();
    const otan = (await get("/api/groups")).presets.find((p) => p.key === "otan");
    assert.ok(otan, "el preset otan existe");
    const usuario = masActivos[0];
    const casos = [
      ["todos los países", { scope: "all", paises: null, edadMin: 18, edadMax: 65, genero: "all" }],
      [`país ${paisesGrandes[0]}`, { scope: `country:${paisesGrandes[0]}`, paises: [paisesGrandes[0]], edadMin: 20, edadMax: 60, genero: "M" }],
      ["preset otan", { scope: "preset:otan", paises: otan.countries, edadMin: 18, edadMax: 65, genero: "all" }],
    ];
    for (const [etiqueta, c] of casos) {
      const q = new URLSearchParams({ user_id: usuario, scope: c.scope, age_min: String(c.edadMin), age_max: String(c.edadMax), gender: c.genero });
      const datos = await get(`/api/timeline?${q}`);
      assert.ok(datos.timeline.length > 0, `${etiqueta}: el usuario tiene acciones`);
      const porTipo = new Map();
      for (const item of datos.timeline) {
        if (!porTipo.has(item.type)) {
          porTipo.set(item.type, oraculo({ tipo: item.type, paises: c.paises, edadMin: c.edadMin, edadMax: c.edadMax, genero: c.genero, excluir: usuario }));
        }
        const sql = porTipo.get(item.type);
        const etiquetaItem = `${etiqueta}, ${item.id} (${item.type})`;
        assert.ok(item.cohort, `${etiquetaItem}: la acción no trae cohorte`);
        assert.equal(item.cohort.count, sql.count, `${etiquetaItem}: tamaño del universo`);
        cercano(item.cohort.avg_duration_ms, sql.count === 0 ? 0 : sql.avgMs, REDONDEO_MS, `${etiquetaItem}: promedio de duración`);
        cercano(item.cohort.avg_amount_usd, sql.avgUsd, REDONDEO_USD, `${etiquetaItem}: promedio de monto`);
        // La mediana y el p90 viajan tal cual hasta el tooltip del badge.
        cercano(item.cohort.median_duration_ms, sql.medMs, REDONDEO_MS, `${etiquetaItem}: mediana de duración`);
        cercano(item.cohort.p90_duration_ms, sql.p90Ms, REDONDEO_MS, `${etiquetaItem}: p90 de duración`);
        cercano(item.cohort.median_amount_usd, sql.medUsd, REDONDEO_USD, `${etiquetaItem}: mediana de monto`);
        cercano(item.cohort.p90_amount_usd, sql.p90Usd, REDONDEO_USD, `${etiquetaItem}: p90 de monto`);
        mismoDelta(item.duration_delta_pct, deltaEsperado(item.duration_ms, item.cohort.avg_duration_ms), `${etiquetaItem}: delta de duración`);
        mismoDelta(item.amount_delta_pct, deltaEsperado(item.amount_usd, item.cohort.avg_amount_usd), `${etiquetaItem}: delta de monto`);
      }
    }
  });

  await paso("las tarjetas del panel pintan esos porcentajes con el color y el texto que corresponden", async () => {
    const conPagos = consultarSql(
      "SELECT usuario_id FROM acciones WHERE tipo_clave IN ('payment', 'refund') GROUP BY 1 ORDER BY count(*) DESC, 1 LIMIT 1"
    )[0][0];
    const browser = await chromium.launch();
    try {
      const page = await browser.newPage();
      await iniciarSesion(page);
      // Vista 1: todas las acciones del usuario más activo (las duraciones).
      const actual = await page.inputValue("#user-select");
      const usuario = masActivos.find((u) => u !== actual);
      const [r1] = await Promise.all([esperarTimeline(page, usuario, "type=all"), page.selectOption("#user-select", usuario)]);
      const vista1 = await verificarTarjetas(page, await r1.json());
      // Para que no pase "en vacío": hay tarjetas fuera del ±10%, o sea verdes y rojas, que comprobar.
      assert.ok(vista1.fueraDelPromedio >= 3, `tarjetas fuera del ±10%: ${vista1.fueraDelPromedio}`);

      // Vista 2: solo los pagos y reembolsos de quien más tiene (los montos, que es el segundo badge).
      const [r2] = await Promise.all([esperarTimeline(page, conPagos, "type=all"), page.selectOption("#user-select", conPagos)]);
      await r2.json();
      const [r3] = await Promise.all([esperarTimeline(page, conPagos, "type=payment"), page.selectOption("#type-select", "payment")]);
      const vista2 = await verificarTarjetas(page, await r3.json());
      assert.ok(vista2.conMonto >= 1, `tarjetas con monto: ${vista2.conMonto}`);
    } finally {
      await browser.close();
    }
  });

  await paso("sin el servicio de estadísticas: aviso en pantalla y badges 'Sin datos de comparación', sin tooltips con NaN", async () => {
    const conPagos = consultarSql(
      "SELECT usuario_id FROM acciones WHERE tipo_clave = 'payment' AND monto_usd IS NOT NULL GROUP BY 1 ORDER BY count(*) DESC, 1 LIMIT 1"
    )[0][0];
    const browser = await chromium.launch();
    try {
      const page = await browser.newPage();
      await iniciarSesion(page);
      // Lo que devuelve la API cuando el servicio de estadísticas no responde (linea-tiempo-cohortes-test.php
      // comprueba esa respuesta del lado de PHP): sin cohorte, sin deltas y stats_service_available en false.
      await page.route("**/api/timeline*", async (route) => {
        const respuesta = await route.fetch();
        const datos = await respuesta.json();
        datos.stats_service_available = false;
        datos.timeline = datos.timeline.map((it) => ({ ...it, cohort: null, duration_delta_pct: null, amount_delta_pct: null }));
        await route.fulfill({ response: respuesta, json: datos });
      });
      await Promise.all([esperarTimeline(page, conPagos, "type=all"), page.selectOption("#user-select", conPagos)]);
      const [respuesta] = await Promise.all([esperarTimeline(page, conPagos, "type=payment"), page.selectOption("#type-select", "payment")]);
      const datos = await respuesta.json();
      await page.waitForFunction((n) => document.querySelectorAll(".timeline-item").length === n, datos.timeline.length);

      assert.equal(await page.isHidden("#status-message"), false, "el aviso de servicio no disponible tiene que verse");
      assert.match(await page.textContent("#status-message"), /El servicio de estadísticas \(Java\) no respondió/);

      const badges = page.locator(".timeline-item .badge");
      const cantidad = await badges.count();
      assert.ok(cantidad >= 2, `badges en pantalla: ${cantidad}`);
      for (let i = 0; i < cantidad; i++) {
        const badge = badges.nth(i);
        assert.equal((await badge.textContent()).trim(), "Sin datos de comparación", `badge ${i}`);
        assert.ok((await badge.getAttribute("class")).split(" ").includes("badge--neutral"), `badge ${i}: color`);
        assert.equal(await badge.getAttribute("title"), null, `badge ${i}: sin estadísticas no hay tooltip (no "mediana: NaN")`);
      }
    } finally {
      await browser.close();
    }
  });

  process.exit(resumenPasos());
})();
