// Reproduce datos/ejemplos/peticiones-api.http contra la API real (sin navegador):
// cada bloque tiene que devolver el código HTTP que declara ("# esperado: N", por
// defecto 200) y el archivo tiene que cubrir todas las rutas de index.php. Así el
// archivo de ejemplos no se desactualiza sin avisar -- ya pasó: el bloque de
// logout seguía sin X-CSRF-Token cuando se agregó la protección CSRF, y daba 403.
//
// Intérprete mínimo del formato .http: "###" separa bloques, "@var = valor" define
// variables, "# @name x" nombra una petición, "{{x.response.body.$.campo}}" lee un
// campo de la respuesta JSON de esa petición. Sin navegador, así que la cookie de
// sesión se guarda a mano.
const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");
const { assert, paso, resumenPasos, ejecutarSql } = require("./ayudante-e2e.cjs");

const RAIZ = path.join(__dirname, "../..");
const ARCHIVO_HTTP = path.join(RAIZ, "datos/ejemplos/peticiones-api.http");
const INDEX_PHP = path.join(RAIZ, "servidor-php/publico/index.php");
const FILTRO_DE_EJEMPLO = "ejemplo-http"; // el nombre que guarda el bloque que crea un filtro

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

  await paso("el archivo tiene un ejemplo de cada ruta de index.php (README: \"todos los endpoints\")", async () => {
    const rutasReales = [...fs.readFileSync(INDEX_PHP, "utf8").matchAll(/\$path === '(\/api\/[a-z-]+)'/g)].map((m) => m[1]);
    assert.equal(rutasReales.length >= 12, true, `index.php: se leyeron solo ${rutasReales.length} rutas (¿cambió el formato del router?)`);
    const cubiertas = new Set(bloques.map(rutaDe));
    const faltan = rutasReales.filter((r) => !cubiertas.has(r));
    assert.deepEqual(faltan, [], `rutas de index.php sin ejemplo en peticiones-api.http: ${faltan.join(", ")}`);
    // La ruta con id (regex en index.php, no figura arriba): el DELETE de un filtro.
    assert.equal(bloques.some((b) => b.metodo === "DELETE" && rutaDe(b) === "/api/filtros/{id}"), true);
  });

  process.exit(resumenPasos());
})();
