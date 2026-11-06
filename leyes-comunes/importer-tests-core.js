// Pruebas del importador a MySQL / MariaDB que no necesitan servidor. Las comparten privacy-laws/ y
// copyright-laws/: cada una tiene `tests/importer.test.js`, un envoltorio que llama a
// `defineImporterTests({ root, loadMaster, masterCsv, columns })` con lo suyo.
//
// El importador de verdad (llenar la base y compararla con el CSV) lo corre el job `database` de
// laws-modules.yml con tests/database_check.php; esto vigila lo que se puede ver sin base:
//   - las columnas que inserta son las del CSV y existen en el esquema,
//   - ningún valor del CSV excede su VARCHAR (el modo estricto lo rechazaría: «Data too long»),
//   - borrar la base exige --yes y se niega antes de conectarse,
//   - una fecha mal escrita es un error, no un NULL.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { spawnSync } from "node:child_process";

const PADRE = ["country_code", "country_name", "region"];
const hayPhp = spawnSync("php", ["-v"]).status === 0;

export function defineImporterTests({ root, loadMaster, masterCsv, columns }) {
  const wrapper = readFileSync(join(root, "import_database.php"), "utf-8");
  const core = readFileSync(join(root, "..", "leyes-comunes", "importador-core.php"), "utf-8");
  const schema = readFileSync(join(root, "database_schema.sql"), "utf-8");

  const lista = (clave) => {
    const m = wrapper.match(new RegExp(`'${clave}' => \\[([\\s\\S]*?)\\]`));
    assert.ok(m, `no encuentro '${clave}' en import_database.php`);
    return [...m[1].matchAll(/'(\w+)'/g)].map((x) => x[1]);
  };
  const tabla = wrapper.match(/'ley' => '(\w+)'/)[1];
  const tablaSql = schema.match(new RegExp(`CREATE TABLE ${tabla} \\(([\\s\\S]*?)\\n\\) ENGINE`))[1];

  test("el importador inserta todas las columnas del CSV, y todas existen en el esquema", () => {
    const insertadas = lista("columnas");
    const delCsv = columns.filter((c) => !PADRE.includes(c));
    assert.deepEqual([...insertadas].sort(), [...delCsv].sort());
    for (const columna of insertadas) {
      assert.match(tablaSql, new RegExp(`\\n\\s+${columna}\\s`), `${columna} falta en ${tabla} de database_schema.sql`);
    }
    for (const obligatoria of lista("obligatorias")) {
      assert.ok(columns.includes(obligatoria), `${obligatoria} es obligatoria pero no es una columna del CSV`);
    }
    for (const fecha of lista("fechas")) assert.ok(insertadas.includes(fecha), `${fecha} no se inserta`);
  });

  test("cada VARCHAR de la tabla de leyes aguanta el valor más largo del CSV", () => {
    const { records } = loadMaster(masterCsv);
    const largos = [];
    for (const [, columna, ancho] of tablaSql.matchAll(/^\s+(\w+)\s+VARCHAR\((\d+)\)/gm)) {
      for (const r of records) {
        const largo = [...(r[columna] ?? "")].length;
        if (largo > Number(ancho)) largos.push(`${r.country_code} ${columna}: ${largo} > VARCHAR(${ancho})`);
      }
    }
    assert.deepEqual(largos, [], "ensancha la columna en database_schema.sql (TEXT para texto libre)");
  });

  test("lee la conexión de DB_HOST, DB_PORT, DB_USER y DB_PASSWORD y usa utf8mb4", () => {
    for (const nombre of ["DB_HOST", "DB_PORT", "DB_USER", "DB_PASSWORD"]) {
      assert.match(core, new RegExp(`getenv\\('${nombre}'\\)`), `${nombre} no se lee`);
    }
    assert.match(core, /charset=utf8mb4/);
  });

  test("--import es todo o nada y se puede repetir", () => {
    for (const parte of [/beginTransaction\(\)/, /commit\(\)/, /rollBack\(\)/, /DELETE FROM \{\$c\['ley'\]\}/]) {
      assert.match(core, parte);
    }
  });

  test("borrar la base exige --yes, y se niega antes de conectarse", { skip: !hayPhp }, () => {
    for (const accion of ["--all", "--create-schema"]) {
      const r = spawnSync("php", ["import_database.php", accion], {
        cwd: root,
        encoding: "utf-8",
        env: { ...process.env, DB_HOST: "256.256.256.256", DB_PORT: "1" }, // si intentara conectarse, fallaría con otro mensaje
      });
      assert.equal(r.status, 2, `${accion} sin --yes debía salir con 2: ${r.stdout}`);
      assert.match(r.stdout, /--yes/);
      assert.doesNotMatch(r.stdout, /connection failed/i);
    }
  });

  test("parseFecha: AAAA-MM-DD pasa, vacío y N/A son NULL, lo demás es un error", { skip: !hayPhp }, () => {
    const probar = (valor) =>
      spawnSync(
        "php",
        ["-r", `require 'leyes-comunes/importador-core.php'; try { var_export(ImportadorLeyes::parseFecha(${JSON.stringify(valor)})); } catch (InvalidArgumentException $e) { echo 'ERROR'; }`],
        { cwd: join(root, ".."), encoding: "utf-8" }
      ).stdout;
    assert.equal(probar("2018-05-25"), "'2018-05-25'");
    assert.equal(probar(""), "NULL");
    assert.equal(probar("N/A"), "NULL");
    for (const mala of ["2018-02-30", "25/05/2018", "2018", "mañana", "2018-5-25"]) {
      assert.equal(probar(mala), "ERROR", `«${mala}» debía ser un error`);
    }
  });
}
