import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

const { IDS_FILTRO, controlFiltro, leerFiltros, escribirFiltros, avisarCambioDeFiltros, escucharCambios } =
  await import("../../interfaz/js/controles-filtro.js");

/** Un <input>/<select> mínimo: guarda el valor como texto (como hace el DOM) y entiende addEventListener/dispatchEvent. */
class ControlFalso {
  #valor = "";
  constructor(valor = "") {
    this.value = valor;
    this.escuchas = {};
    this.eventos = []; // los tipos de evento que le dispararon con dispatchEvent
  }
  get value() {
    return this.#valor;
  }
  set value(v) {
    this.#valor = String(v);
  }
  addEventListener(tipo, fn) {
    (this.escuchas[tipo] ??= []).push(fn);
  }
  dispatchEvent(evento) {
    this.eventos.push(evento.type);
    (this.escuchas[evento.type] ?? []).forEach((fn) => fn(evento));
    return true;
  }
}

/** Un document con los cinco controles (los ids salen del módulo: acá no importa cuáles son). Los demás ids no existen. */
function montarControles(valores = {}) {
  const controles = Object.fromEntries(Object.keys(IDS_FILTRO).map((nombre) => [nombre, new ControlFalso(valores[nombre] ?? "")]));
  const porId = new Map(Object.entries(IDS_FILTRO).map(([nombre, id]) => [id, controles[nombre]]));
  globalThis.document = { getElementById: (id) => porId.get(id) ?? null };
  return controles;
}

const espera = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

test("los cinco filtros se llaman scope, ageMin, ageMax, gender y type", () => {
  assert.deepEqual(Object.keys(IDS_FILTRO).sort(), ["ageMax", "ageMin", "gender", "scope", "type"]);
});

test("cada id existe en la barra superior (partes/topbar.php)", () => {
  const html = readFileSync(new URL("../../interfaz/partes/topbar.php", import.meta.url), "utf8");
  for (const [nombre, id] of Object.entries(IDS_FILTRO)) {
    assert.ok(html.includes(`id="${id}"`), `${nombre}: topbar.php no tiene id="${id}"`);
  }
});

test("controlFiltro devuelve el control de ese filtro, y un nombre que no existe es un error claro", () => {
  const controles = montarControles();
  assert.equal(controlFiltro("type"), controles.type);
  assert.equal(controlFiltro("ageMin"), controles.ageMin);
  assert.throws(() => controlFiltro("pais"), /pais/);
});

test("leerFiltros devuelve lo que hay en los cinco controles, como texto y sin interpretarlo", () => {
  montarControles({ scope: "country:AR", ageMin: "20", ageMax: "30", gender: "F", type: "payment" });
  assert.deepEqual(leerFiltros(), { scope: "country:AR", ageMin: "20", ageMax: "30", gender: "F", type: "payment" });

  const controles = montarControles({ scope: "all", ageMin: "", ageMax: "65", gender: "all", type: "all" });
  assert.deepEqual(leerFiltros(), { scope: "all", ageMin: "", ageMax: "65", gender: "all", type: "all" }, "una edad vacía sigue vacía");
  controles.type.value = "refund";
  assert.equal(leerFiltros().type, "refund", "lee el valor de ahora, no uno guardado");
});

test("escribirFiltros pone solo los que se nombran y no avisa a nadie", () => {
  const controles = montarControles({ scope: "x", ageMin: "x", ageMax: "x", gender: "x", type: "x" });
  escribirFiltros({ scope: "all", ageMin: 18, type: "refund" });
  assert.deepEqual(leerFiltros(), { scope: "all", ageMin: "18", ageMax: "x", gender: "x", type: "refund" });
  assert.deepEqual(Object.values(controles).flatMap((c) => c.eventos), [], "escribir no dispara eventos (recargar es decisión de quien llama)");
});

test("escribirFiltros con un nombre que no existe falla sin tocar nada", () => {
  montarControles({ scope: "x", ageMin: "x", ageMax: "x", gender: "x", type: "x" });
  assert.throws(() => escribirFiltros({ scpe: "all" }), /scpe/);
  assert.equal(leerFiltros().scope, "x");
});

test("avisarCambioDeFiltros dispara un change en el control de país, y en ningún otro", () => {
  const controles = montarControles();
  avisarCambioDeFiltros();
  assert.deepEqual(controles.scope.eventos, ["change"]);
  assert.deepEqual([controles.ageMin, controles.ageMax, controles.gender, controles.type].flatMap((c) => c.eventos), []);
});

test("escucharCambios: los desplegables avisan al instante, una sola vez por cambio", () => {
  const controles = montarControles();
  let avisos = 0;
  escucharCambios(() => avisos++, 20);
  for (const nombre of ["scope", "gender", "type"]) {
    const antes = avisos;
    controles[nombre].dispatchEvent(new Event("change"));
    assert.equal(avisos, antes + 1, `${nombre}: un change es un aviso, sin esperas`);
    controles[nombre].dispatchEvent(new Event("input"));
    assert.equal(avisos, antes + 1, `${nombre}: un input no suma otro aviso (el change ya lo da)`);
  }
});

test("escucharCambios: las edades esperan a que se deje de tipear y varias teclas seguidas son un solo aviso", async () => {
  const controles = montarControles();
  let avisos = 0;
  escucharCambios(() => avisos++, 30);
  for (const nombre of ["ageMin", "ageMax"]) {
    const antes = avisos;
    for (let tecla = 0; tecla < 3; tecla++) controles[nombre].dispatchEvent(new Event("input"));
    assert.equal(avisos, antes, `${nombre}: no avisa mientras se tipea`);
    await espera(120);
    assert.equal(avisos, antes + 1, `${nombre}: un solo aviso cuando se deja de tipear`);
  }
});
