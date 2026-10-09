import { test } from "node:test";
import { strict as assert } from "node:assert";
import { readFileSync, existsSync } from "node:fs";
import { join } from "node:path";
import { parse } from "csv-parse/sync";

// El README de esta carpeta decía "200+ jurisdictions" con 50 filas en el maestro, y listaba carpetas
// y archivos que no existen (region_*/, material_types/, src/CopyrightLaws/, CONTRIBUTING.md, LICENSE). Estas pruebas
// atan lo que el README afirma a lo que hay.
const readme = readFileSync("./README.md", "utf-8");
const maestro = parse(readFileSync("./jurisdictions/copyright_laws_master.csv", "utf-8"), {
  columns: true,
  skip_empty_lines: true,
});

test("README de copyright-laws", async (t) => {
  await t.test("las cifras de «Overview» son las del maestro", () => {
    const m = readme.match(/\*\*(\d+) laws, one per jurisdiction\*\*/);
    assert.ok(m, "falta la línea «**N laws, one per jurisdiction**»");
    const jurisdicciones = new Set(maestro.map((r) => r.country_code)).size;
    assert.deepEqual([Number(m[1]), jurisdicciones], [maestro.length, maestro.length]);
    const buscar = readme.match(/with no filters, all (\d+)\)/);
    assert.ok(buscar, "falta «(with no filters, all N)» en el Quick Start");
    assert.equal(Number(buscar[1]), maestro.length);
  });

  await t.test("los idiomas de la interfaz son los de translations/i18n.js", () => {
    const m = readme.match(/\*\*Interface in (\d+) languages\*\*/);
    assert.ok(m, "falta «**Interface in N languages**»");
    const i18n = readFileSync("./app/translations/i18n.js", "utf-8");
    assert.equal(Number(m[1]), (i18n.match(/^ {2}[a-z]{3}: \{/gm) || []).length);
  });

  await t.test("todo lo que lista «Directory Structure» existe", () => {
    const bloque = readme.split("## Directory Structure")[1].match(/```\n([\s\S]*?)```/)[1];
    const pila = [];
    const faltan = [];
    let revisados = 0;
    for (const linea of bloque.split("\n").slice(1)) {
      const i = linea.search(/[├└]── /);
      if (i < 0) continue;
      const nivel = i / 4;
      const nombre = linea.slice(i + 4).split(/\s{2,}|\s#/)[0].trim();
      pila[nivel] = nombre.replace(/\/$/, "");
      pila.length = nivel + 1;
      if (pila.some((p) => /[<*]/.test(p))) continue;
      revisados++;
      if (!existsSync(join(".", ...pila))) faltan.push(pila.join("/"));
    }
    assert.ok(revisados > 15, `solo se revisaron ${revisados} rutas`);
    assert.deepEqual(faltan, []);
  });

  await t.test("conserva el aviso de que no es asesoría legal", () => {
    assert.match(readme, /not legal advice/i);
  });
});
