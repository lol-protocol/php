import { test } from "node:test";
import assert from "node:assert/strict";

// idioma.js lee localStorage al importarse (para recordar el idioma elegido);
// Node no trae esa API del navegador, así que se la simula antes del import.
globalThis.localStorage = { getItem: () => null, setItem: () => {} };

const { t, etiquetaGenero } = await import("../../interfaz/js/idioma.js");
const { state } = await import("../../interfaz/js/nucleo.js");

test("t(): interpola una variable en la plantilla", () => {
  assert.equal(t("common_years", { n: 34 }), "34 años");
});

test("t(): interpola varias variables distintas", () => {
  assert.equal(t("pagination_info", { page: 2, total: 5, count: 91 }), "Página 2 de 5 · 91 acciones");
});

test("t(): clave inexistente devuelve la clave tal cual (no rompe la UI)", () => {
  assert.equal(t("clave_que_no_existe"), "clave_que_no_existe");
});

test("t(): cambia de diccionario según state.lang", () => {
  state.lang = "en";
  assert.equal(t("topbar_logout"), "Log out");
  state.lang = "es";
  assert.equal(t("topbar_logout"), "Cerrar sesión");
});

test("etiquetaGenero: traduce el código de género", () => {
  state.lang = "es";
  assert.equal(etiquetaGenero("F"), "Femenino");
  state.lang = "en";
  assert.equal(etiquetaGenero("F"), "Female");
  state.lang = "es";
});
