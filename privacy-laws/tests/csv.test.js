import { test } from "node:test";
import { strict as assert } from "node:assert";
import { readFileSync } from "node:fs";
import { parse } from "csv-parse/sync";
import { parseCSV } from "../app/csv.js";

test("parseCSV (lector del frontend)", async (t) => {
  await t.test("campo entre comillas con comas", () => {
    const [fila] = parseCSV('a,b,c\n1,"x, y",3\n');
    assert.deepEqual(fila, { a: "1", b: "x, y", c: "3" });
  });

  await t.test("comilla escapada con \"\" y salto de línea dentro del campo", () => {
    const [fila] = parseCSV('a,b\n"dijo ""hola""","l1\nl2"\n');
    assert.deepEqual(fila, { a: 'dijo "hola"', b: "l1\nl2" });
  });

  await t.test("fin de línea CRLF, sin salto final y líneas vacías", () => {
    const filas = parseCSV("a,b\r\n1,2\r\n\r\n3,4");
    assert.deepEqual(filas, [
      { a: "1", b: "2" },
      { a: "3", b: "4" },
    ]);
  });

  await t.test("campo vacío al final de la fila", () => {
    assert.deepEqual(parseCSV("a,b\n1,\n"), [{ a: "1", b: "" }]);
  });

  await t.test("fila con otro número de campos: falla en vez de desplazar", () => {
    assert.throws(() => parseCSV("a,b\n1,2,3\n"), /fila 2 tiene 3 campos/);
  });

  await t.test("comilla sin cerrar: falla", () => {
    assert.throws(() => parseCSV('a,b\n1,"2\n'), /comilla sin cerrar/);
  });

  await t.test("lee el maestro igual que csv-parse (el lector de referencia)", () => {
    const texto = readFileSync("./countries/privacy_laws_master.csv", "utf-8");
    const esperado = parse(texto, { columns: true, skip_empty_lines: true, trim: true });
    assert.deepEqual(parseCSV(texto), esperado);
  });
});
