import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

const { mensajeDeError } = await import("../../interfaz/js/errores.js");
const { state } = await import("../../interfaz/js/nucleo.js");
const { default: ES } = await import("../../interfaz/js/i18n/es.js");
const { default: EN } = await import("../../interfaz/js/i18n/en.js");

const RAIZ_PHP = fileURLToPath(new URL("../../servidor-php/", import.meta.url));

function archivosPhp(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
    e.isDirectory() ? archivosPhp(join(dir, e.name)) : e.name.endsWith(".php") ? [join(dir, e.name)] : []
  );
}

/** Los códigos de error que el backend puede mandar: api_error(<status>, '<codigo>', ...). */
function codigosDelBackend() {
  const codigos = new Set();
  for (const archivo of archivosPhp(RAIZ_PHP)) {
    for (const m of readFileSync(archivo, "utf8").matchAll(/api_error\(\s*\d+\s*,\s*'([a-z_]+)'/g)) codigos.add(m[1]);
  }
  return codigos;
}

const claves = (diccionario) => Object.keys(diccionario).filter((k) => k.startsWith("err_")).sort();

test("mensajeDeError: con un código conocido usa la traducción del idioma de la interfaz, no el texto del servidor", () => {
  const data = { error: "usuario o contraseña incorrectos", codigo: "credenciales_invalidas" };
  state.lang = "en";
  assert.equal(mensajeDeError(data, "genérico"), EN.err_credenciales_invalidas);
  assert.notEqual(mensajeDeError(data, "genérico"), data.error);
  state.lang = "es";
  assert.equal(mensajeDeError(data, "genérico"), ES.err_credenciales_invalidas);
});

test("mensajeDeError: un código que la interfaz no traduce (o sin código) cae al texto del servidor", () => {
  state.lang = "en";
  assert.equal(mensajeDeError({ error: "texto del servidor", codigo: "codigo_nuevo" }, "genérico"), "texto del servidor");
  assert.equal(mensajeDeError({ error: "texto del servidor" }, "genérico"), "texto del servidor");
  state.lang = "es";
});

test("mensajeDeError: sin código ni texto (cuerpo vacío o no JSON) usa el mensaje genérico", () => {
  assert.equal(mensajeDeError({}, "genérico"), "genérico");
  assert.equal(mensajeDeError(undefined, "genérico"), "genérico");
});

test("todo código de error que manda el backend está traducido al español y al inglés", () => {
  const codigos = codigosDelBackend();
  assert.ok(codigos.size >= 10, `códigos encontrados en el backend: ${codigos.size}`); // que el regex no pase en vacío
  for (const codigo of codigos) {
    for (const [idioma, diccionario] of [["es", ES], ["en", EN]]) {
      assert.ok(diccionario[`err_${codigo}`], `falta err_${codigo} en ${idioma}`);
    }
  }
});

test("no quedan traducciones de errores que el backend ya no manda, y es y en tienen las mismas", () => {
  const codigos = [...codigosDelBackend()].map((c) => `err_${c}`).sort();
  assert.deepEqual(claves(ES), codigos);
  assert.deepEqual(claves(EN), codigos);
});
