// Reproduce datos/ejemplos/peticiones-api.http contra la API real (sin navegador):
// cada bloque tiene que devolver el código HTTP que declara ("# esperado: N", por
// defecto 200) y el archivo tiene que cubrir todas las rutas de la tabla de rutas
// (servidor-php/codigo/rutas.php). Así el archivo de ejemplos no se desactualiza
// sin avisar -- ya pasó: el bloque de logout seguía sin X-CSRF-Token cuando se
// agregó la protección CSRF, y daba 403. Además comprueba, ruta por ruta de esa
// misma tabla, que sin sesión todas responden 401 salvo las que manejan la sesión.
//
// Intérprete mínimo del formato .http: "###" separa bloques, "@var = valor" define
// variables, "# @name x" nombra una petición, "{{x.response.body.$.campo}}" lee un
// campo de la respuesta JSON de esa petición. Sin navegador, así que la cookie de
// sesión se guarda a mano.
const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");
const { execFileSync } = require("node:child_process");
const { assert, paso, resumenPasos, ejecutarSql } = require("./ayudante-e2e.cjs");

const RAIZ = path.join(__dirname, "../..");
const ARCHIVO_HTTP = path.join(RAIZ, "datos/ejemplos/peticiones-api.http");
const LISTAR_RUTAS_PHP = path.join(__dirname, "listar-rutas.php");
const FILTRO_DE_EJEMPLO = "ejemplo-http"; // el nombre que guarda el bloque que crea un filtro

// Las rutas que maneja la sesión por su cuenta se piden sin sesión previa y tienen que llegar a su función (no un 401).
// /api/login no se pide acá: cada intento fallido suma al bloqueo por IP, y 5 dejan afuera al resto de las pruebas.
const PEDIDOS_SIN_SESION = {
  "/api/session": { metodo: "GET", estado: 200 },
  "/api/logout": { metodo: "POST", estado: 403 }, // llega a api_logout(), que pide el token CSRF y no lo encuentra
};

/** @return {{vars: Record<string,string>, bloques: Array<object>}} */
function parsear(texto) {
  const vars = {};
  const bloques = [];
  texto.split(/^###/m).forEach((parte, i) => {
    const lineas = parte.split("\n");
    const titulo = i === 0 ? "" : lineas.shift().trim(); // lo anterior al primer "###" es solo cabecera + variables
    const bloque = { titulo, nombre: null, esperado: 200, metodo: null, url: null, headers: {}, cuerpo: "" };
    const cuerpo = [];
    let enCuerpo = false;
    for (const linea of lineas) {
      if (!bloque.metodo) {
        const v = linea.match(/^@(\w+)\s*=\s*(.*)$/);
        const n = linea.match(/^#\s*@name\s+(\w+)/);
        const e = linea.match(/^#\s*esperado:\s*(\d{3})/);
        const r = linea.match(/^(GET|POST|PUT|PATCH|DELETE)\s+(\S+)/);
        if (v) vars[v[1]] = v[2].trim();
        else if (n) bloque.nombre = n[1];
        else if (e) bloque.esperado = Number(e[1]);
        else if (r) [, bloque.metodo, bloque.url] = r;
      } else if (!enCuerpo) {
        const h = linea.match(/^([\w-]+):\s*(.*)$/);
        if (linea.trim() === "") enCuerpo = true;
        else if (h) bloque.headers[h[1]] = h[2];
      } else {
        cuerpo.push(linea);
      }
    }
    bloque.cuerpo = cuerpo.join("\n").trim();
    if (bloque.metodo) bloques.push(bloque);
  });
  return { vars, bloques };
}

function sustituir(texto, vars, respuestas) {
  return texto.replace(/\{\{\s*([^}]+?)\s*\}\}/g, (_, expr) => {
    const ref = expr.match(/^(\w+)\.response\.body\.\$\.(.+)$/);
    if (ref) {
      const previa = respuestas[ref[1]];
      assert.ok(previa, `{{${expr}}}: no hay una petición previa con "# @name ${ref[1]}"`);
      const valor = ref[2].split(".").reduce((o, k) => o?.[k], previa.json);
      assert.ok(valor !== undefined && valor !== null, `{{${expr}}}: la respuesta no trae ese campo`);
      return String(valor);
    }
    assert.ok(expr in vars, `{{${expr}}}: variable no definida`);
    return vars[expr];
  });
}

function pedir({ metodo, url, headers, cuerpo }, cookies) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const cab = { ...headers };
    if (Object.keys(cookies).length > 0) {
      cab.Cookie = Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join("; ");
    }
    if (cuerpo) cab["Content-Length"] = Buffer.byteLength(cuerpo);
    const req = http.request({ hostname: u.hostname, port: u.port, path: u.pathname + u.search, method: metodo, headers: cab }, (res) => {
      const trozos = [];
      res.on("data", (t) => trozos.push(t));
      res.on("end", () => {
        for (const c of res.headers["set-cookie"] ?? []) {
          const [par] = c.split(";");
          const i = par.indexOf("=");
          cookies[par.slice(0, i)] = par.slice(i + 1);
        }
        const texto = Buffer.concat(trozos).toString("utf8");
        let json = null;
        try {
          json = JSON.parse(texto);
        } catch {
          // no era JSON: igual se devuelve el texto crudo
        }
        resolve({ status: res.statusCode, texto, json });
      });
    });
    req.on("error", reject);
    if (cuerpo) req.write(cuerpo);
    req.end();
  });
}

/** Ruta de la petición sin host ni query; el id de /api/filtros/{id} se colapsa. */
function rutaDe(bloque) {
  const ruta = bloque.url.replace(/^\{\{baseUrl\}\}/, "").split("?")[0];
  return ruta.startsWith("/api/filtros/") ? "/api/filtros/{id}" : ruta;
}

/** La tabla de rutas del router, leída con PHP: {"/api/users": false, "/api/session": true, ...} (true = maneja la sesión). */
function rutasDeLaApi() {
  return JSON.parse(execFileSync("php", [LISTAR_RUTAS_PHP], { encoding: "utf8" }));
}

(async () => {
  const { vars, bloques } = parsear(fs.readFileSync(ARCHIVO_HTTP, "utf8"));
  const cookies = {};
  const respuestas = {};

  await paso("el archivo se parsea en bloques de petición completos", async () => {
    assert.equal(bloques.length >= 15, true, `se leyeron solo ${bloques.length} bloques`);
    assert.equal(bloques.every((b) => b.titulo !== "" && b.url), true);
    assert.ok(vars.baseUrl, "falta @baseUrl");
  });

  // Por si una corrida anterior quedó a medio camino (hay un bloque que crea un filtro).
  ejecutarSql(`DELETE FROM filtros_guardados WHERE nombre = '${FILTRO_DE_EJEMPLO}';`);

  try {
    for (const b of bloques) {
      await paso(b.titulo, async () => {
        const peticion = {
          metodo: b.metodo,
          url: sustituir(b.url, vars, respuestas),
          headers: Object.fromEntries(Object.entries(b.headers).map(([k, v]) => [k, sustituir(v, vars, respuestas)])),
          cuerpo: sustituir(b.cuerpo, vars, respuestas),
        };
        const res = await pedir(peticion, cookies);
        if (b.nombre) respuestas[b.nombre] = res;
        assert.equal(
          res.status,
          b.esperado,
          `${b.metodo} ${new URL(peticion.url).pathname} devolvió ${res.status} en vez de ${b.esperado}: ${res.texto.slice(0, 200)}`
        );
      });
    }
  } finally {
    ejecutarSql(`DELETE FROM filtros_guardados WHERE nombre = '${FILTRO_DE_EJEMPLO}';`);
  }

  await paso("el archivo tiene un ejemplo de cada ruta de la tabla de rutas (README: \"todos los endpoints\")", async () => {
    const rutasReales = Object.keys(rutasDeLaApi());
    assert.equal(rutasReales.length >= 12, true, `rutas.php: se leyeron solo ${rutasReales.length} rutas (¿cambió la tabla?)`);
    const cubiertas = new Set(bloques.map(rutaDe));
    const faltan = rutasReales.filter((r) => !cubiertas.has(r));
    assert.deepEqual(faltan, [], `rutas de rutas.php sin ejemplo en peticiones-api.http: ${faltan.join(", ")}`);
  });

  await paso("/api/alerts: cada tipo llega bajo el mismo id que en /api/alerts-config y con el mismo sobre", async () => {
    const sesion = {};
    const login = await pedir(
      { metodo: "POST", url: `${vars.baseUrl}/api/login`, headers: { "Content-Type": "application/json" }, cuerpo: JSON.stringify({ username: "admin", password: "admin123" }) },
      sesion
    );
    assert.equal(login.status, 200);
    const leer = async (ruta) => (await pedir({ metodo: "GET", url: `${vars.baseUrl}${ruta}`, headers: {}, cuerpo: "" }, sesion)).json;
    const alertas = await leer("/api/alerts");
    const habilitadas = Object.entries((await leer("/api/alerts-config")).alertas).filter(([, activa]) => activa).map(([id]) => id);
    assert.ok(habilitadas.length >= 1, "hay algún tipo de alerta habilitado");
    assert.deepEqual(Object.keys(alertas).sort(), habilitadas.sort(), "un tipo de alerta por cada uno de los habilitados, con su id de la configuración");
    for (const id of habilitadas) {
      assert.deepEqual(Object.keys(alertas[id]), ["total_events", "total_users_affected", "top"], `${id}: el sobre`);
      for (const fila of alertas[id].top) {
        assert.deepEqual(Object.keys(fila).slice(0, 4), ["user_id", "user_name", "event_count", "last_seen"], `${id}: las columnas comunes de cada fila`);
      }
    }
    await pedir({ metodo: "POST", url: `${vars.baseUrl}/api/logout`, headers: { "X-CSRF-Token": login.json.csrf_token }, cuerpo: "" }, sesion);
  });

  // Una sesión nueva (sin cookies): la del archivo de ejemplos ya hizo login y logout.
  const pedirSinSesion = (metodo, ruta) => pedir({ metodo, url: vars.baseUrl + ruta.replace("{id}", "1"), headers: {}, cuerpo: "" }, {});

  await paso("sin sesión, toda ruta que no maneja la sesión por su cuenta responde 401 no_autenticado", async () => {
    const rutas = rutasDeLaApi();
    const protegidas = Object.keys(rutas).filter((r) => rutas[r] === false);
    assert.equal(protegidas.length >= 9, true, `rutas.php: solo ${protegidas.length} rutas exigen sesión (¿cambió la tabla?)`);
    for (const ruta of protegidas) {
      const res = await pedirSinSesion("GET", ruta);
      assert.deepEqual([res.status, res.json?.codigo], [401, "no_autenticado"], `GET ${ruta} sin sesión: ${res.status} ${res.texto.slice(0, 120)}`);
    }
  });

  await paso("las rutas que manejan la sesión llegan a su función aun sin sesión", async () => {
    const rutas = rutasDeLaApi();
    const propias = Object.keys(rutas).filter((r) => rutas[r] === true);
    const sinCaso = propias.filter((r) => r !== "/api/login" && !(r in PEDIDOS_SIN_SESION));
    assert.deepEqual(sinCaso, [], `rutas que manejan la sesión sin caso en este archivo: ${sinCaso.join(", ")}`);
    for (const [ruta, { metodo, estado }] of Object.entries(PEDIDOS_SIN_SESION)) {
      assert.ok(ruta in rutas && rutas[ruta] === true, `${ruta} ya no figura entre las que manejan la sesión`);
      const res = await pedirSinSesion(metodo, ruta);
      assert.equal(res.status, estado, `${metodo} ${ruta} sin sesión: ${res.status} ${res.texto.slice(0, 120)}`);
    }
  });

  process.exit(resumenPasos());
})();
