import { test } from "node:test";
import assert from "node:assert/strict";
import { readdirSync, readFileSync } from "node:fs";

// Cada cosa de la interfaz que se repetía en varios módulos vive ahora en uno solo, y estas pruebas cuidan que no vuelva
// a copiarse: los ids de los cinco filtros (estaban en 27 lugares de 5 archivos), el aviso de arriba del timeline
// (se armaba a mano en 3) y la secuencia que pinta una respuesta de /api/timeline (estaba entera en 2).
globalThis.window = { location: { hostname: "localhost" } };
const { IDS_FILTRO } = await import("../../interfaz/js/controles-filtro.js");

const DIR_JS = new URL("../../interfaz/js/", import.meta.url);
const modulos = readdirSync(DIR_JS)
  .filter((archivo) => archivo.endsWith(".js"))
  .map((archivo) => [archivo, readFileSync(new URL(archivo, DIR_JS), "utf8")]);
const dondeAparece = (patron) => modulos.filter(([, fuente]) => patron.test(fuente)).map(([archivo]) => archivo);

test("los ids de los cinco filtros se escriben solo en controles-filtro.js", () => {
  assert.ok(modulos.length >= 20, `se leyeron solo ${modulos.length} módulos`);
  for (const id of Object.values(IDS_FILTRO)) {
    assert.deepEqual(dondeAparece(new RegExp(`["'\`]${id}["'\`]`)), ["controles-filtro.js"], `el id ${id}`);
  }
});

test("el aviso de arriba del timeline (#status-message) se maneja solo desde tarjeta-usuario.js", () => {
  assert.deepEqual(dondeAparece(/getElementById\(\s*["']status-message["']\s*\)/), ["tarjeta-usuario.js"]);
});

test("las seis piezas de la pantalla del timeline se pintan solo desde respuesta-timeline.js", () => {
  for (const pieza of ["renderUserCard", "renderFilterSummary", "renderStatusMessage", "renderChart", "renderTimeline", "renderPagination"]) {
    // Las llamadas, no la definición (`function pieza(`) ni un método de otro objeto (`x.pieza(`).
    const llamada = new RegExp(`(?<![\\w.])(?<!function\\s)${pieza}\\(`);
    assert.deepEqual(dondeAparece(llamada), ["respuesta-timeline.js"], pieza);
  }
});

test("el pedido de datos y el cambio de idioma pintan con la misma función", () => {
  assert.deepEqual(dondeAparece(/(?<!function\s)renderRespuestaTimeline\(/).sort(), ["aplicacion.js", "idioma-refrescar.js"]);
});

// Los estilos que comparten el login, los modales y la barra de filtros viven en base.css (variables --accent-glow y
// --card-glow, y las piezas .campo y .caja-neon): antes el estilo de los campos estaba escrito 3 veces, el brillo del foco
// 5 y el resplandor de los cuadros 2.
const DIR_CSS = new URL("../../interfaz/css/", import.meta.url);
const hojas = readdirSync(DIR_CSS)
  .filter((archivo) => archivo.endsWith(".css"))
  .map((archivo) => [archivo, readFileSync(new URL(archivo, DIR_CSS), "utf8")]);
const hojasCon = (patron) => hojas.filter(([, fuente]) => patron.test(fuente)).map(([archivo]) => archivo);

test("el brillo del foco y el resplandor de los cuadros se escriben solo en base.css", () => {
  assert.ok(hojas.length >= 15, `se leyeron solo ${hojas.length} hojas de estilo`);
  assert.deepEqual(hojasCon(/rgba\(0, 240, 255, 0\.4\)/), ["base.css"], "brillo del foco y del mouse encima");
  assert.deepEqual(hojasCon(/0 0 24px rgba\(0, 240, 255, 0\.18\)/), ["base.css"], "resplandor de los cuadros con borde de acento");
});

test("todo campo de texto, número o desplegable del login y de la barra lleva la clase .campo", () => {
  for (const parte of ["pantalla-login.php", "topbar.php"]) {
    const html = readFileSync(new URL(`../../interfaz/partes/${parte}`, import.meta.url), "utf8");
    const campos = [...html.matchAll(/<(?:input|select)\b[^>]*>/g)].map((m) => m[0]);
    assert.ok(campos.length >= 2, `${parte}: no se leyó ningún campo`);
    for (const campo of campos) {
      assert.match(campo, /class="[^"]*\bcampo\b/, `${parte}: este campo no usa .campo: ${campo}`);
    }
  }
});
