// Pruebas de comprobar-copias.js: la guarda que mantiene idénticas las copias de privacy-laws/ y copyright-laws/.
// Sin dependencias: node --test leyes-comunes/
import assert from "node:assert/strict";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import test from "node:test";
import { COPIAS, comprobar } from "./comprobar-copias.js";

/** Una raíz de mentira con las dos carpetas y los archivos dados: { ruta: [textoEnPrivacy, textoEnCopyright] }. */
function raizCon(archivos) {
  const raiz = mkdtempSync(join(tmpdir(), "copias-"));
  for (const [ruta, [a, b]] of Object.entries(archivos)) {
    for (const [modulo, texto] of [["privacy-laws", a], ["copyright-laws", b]]) {
      if (texto === null) continue;
      const destino = join(raiz, modulo, ruta);
      mkdirSync(dirname(destino), { recursive: true });
      writeFileSync(destino, texto);
    }
  }
  return raiz;
}

test("sin diferencias no hay problemas", () => {
  const raiz = raizCon({ "app/lib/util.js": ["igual", "igual"] });
  try {
    assert.deepEqual(comprobar(raiz, ["app/lib/util.js"]), []);
  } finally {
    rmSync(raiz, { recursive: true });
  }
});

test("una copia que se separó se nombra", () => {
  const raiz = raizCon({ "app/lib/util.js": ["uno", "uno // deriva"], "app/lib/chart.js": ["x", "x"] });
  try {
    assert.deepEqual(comprobar(raiz, ["app/lib/util.js", "app/lib/chart.js"]), ["app/lib/util.js: ya no es igual en privacy-laws y copyright-laws"]);
  } finally {
    rmSync(raiz, { recursive: true });
  }
});

test("un archivo que falta en uno de los dos módulos (o en ambos) se nombra", () => {
  const raiz = raizCon({ "a.js": ["x", null], "b.js": [null, "x"], "c.js": [null, null] });
  try {
    assert.deepEqual(comprobar(raiz, ["a.js", "b.js", "c.js"]), [
      "a.js: falta en copyright-laws",
      "b.js: falta en privacy-laws",
      "c.js: falta en privacy-laws y copyright-laws",
    ]);
  } finally {
    rmSync(raiz, { recursive: true });
  }
});

test("una diferencia de un solo byte (un salto de línea final) también cuenta", () => {
  const raiz = raizCon({ "a.css": ["body{}", "body{}\n"] });
  try {
    assert.equal(comprobar(raiz, ["a.css"]).length, 1);
  } finally {
    rmSync(raiz, { recursive: true });
  }
});

test("la lista real no tiene repetidos, y las copias del repositorio siguen iguales", () => {
  assert.equal(new Set(COPIAS).size, COPIAS.length);
  assert.deepEqual(comprobar(), []);
});
