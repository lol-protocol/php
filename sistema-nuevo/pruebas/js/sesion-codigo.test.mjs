import { test } from "node:test";
import assert from "node:assert/strict";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

const { fetchJson } = await import("../../interfaz/js/sesion.js");
const { esFuncionApagada } = await import("../../interfaz/js/errores.js");

/** Un fetch que responde con ese estado y ese cuerpo JSON. */
function responder(estado, cuerpo) {
  globalThis.fetch = async () => ({ status: estado, ok: estado >= 200 && estado < 300, json: async () => cuerpo });
}

test("fetchJson: el error de una función apagada conserva su código, y esFuncionApagada lo reconoce", async () => {
  responder(404, { error: "función apagada", codigo: "funcion_apagada" });
  await assert.rejects(fetchJson("/api/kpis"), (err) => err.codigo === "funcion_apagada" && esFuncionApagada(err));
});

test("fetchJson: un error con otro código no cuenta como función apagada", async () => {
  responder(500, { error: "error interno del servidor", codigo: "error_interno" });
  await assert.rejects(fetchJson("/api/kpis"), (err) => err.codigo === "error_interno" && !esFuncionApagada(err));
});

test("fetchJson: un error sin cuerpo JSON no trae código", async () => {
  globalThis.fetch = async () => ({ status: 502, ok: false, json: async () => { throw new Error("no es JSON"); } });
  await assert.rejects(fetchJson("/api/kpis"), (err) => err.codigo === undefined && !esFuncionApagada(err));
});
