import { test } from "node:test";
import { strict as assert } from "node:assert";
import { readFileSync, readdirSync, statSync } from "node:fs";
import { parse } from "csv-parse/sync";

// jurisdictions/<tld>/{info.json,laws.csv,laws.json} se generan del maestro con
// scripts/reorganize-by-jurisdiction.js. Si el maestro cambia y no se vuelve a generar
// (o se genera con un lector que desplaza campos), la app y la API muestran datos viejos.
const BASE = "./jurisdictions";
const leer = (ruta) => readFileSync(ruta, "utf-8");
const maestro = parse(leer(`${BASE}/copyright_laws_master.csv`), {
  columns: true,
  skip_empty_lines: true,
});
const porPais = new Map();
for (const ley of maestro) {
  if (!porPais.has(ley.country_code)) porPais.set(ley.country_code, []);
  porPais.get(ley.country_code).push(ley);
}

test("archivos por jurisdicción vs. maestro", async (t) => {
  await t.test("hay una carpeta por jurisdicción del maestro y ninguna de más", () => {
    const carpetas = readdirSync(BASE)
      .filter((n) => statSync(`${BASE}/${n}`).isDirectory())
      .sort();
    const esperadas = [...porPais.keys()].map((c) => c.toLowerCase()).sort();
    assert.deepEqual(carpetas, esperadas);
  });

  for (const [codigo, leyes] of porPais) {
    const tld = codigo.toLowerCase();

    await t.test(`${tld}/laws.json y laws.csv tienen las mismas leyes que el maestro`, () => {
      assert.deepEqual(JSON.parse(leer(`${BASE}/${tld}/laws.json`)), leyes);
      assert.deepEqual(
        parse(leer(`${BASE}/${tld}/laws.csv`), { columns: true, skip_empty_lines: true }),
        leyes
      );
    });

    await t.test(`${tld}/info.json describe la jurisdicción`, () => {
      const info = JSON.parse(leer(`${BASE}/${tld}/info.json`));
      assert.equal(info.code, codigo);
      assert.equal(info.name, leyes[0].country_name);
      assert.equal(info.tld, tld);
      assert.equal(info.lawCount, leyes.length);
      assert.ok(!isNaN(new Date(info.createdAt).getTime()), "createdAt no es una fecha");
    });
  }
});
