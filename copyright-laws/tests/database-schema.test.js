// import_database.php fills the MySQL / MariaDB tables from the master CSV. The CI job "database"
// runs it for real; these checks catch the same drift without a server: a value that no longer
// fits its column (strict mode refuses it: "Data too long for column"), and the connection
// settings the job relies on.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { MASTER_CSV, ROOT, loadMaster } from "../scripts/lib/dataset.js";

const php = readFileSync(join(ROOT, "import_database.php"), "utf-8");
const schema = readFileSync(join(ROOT, "database_schema.sql"), "utf-8");
const lawTable = php.match(/INSERT INTO (\w+)\s*\(/)[1];
const tableSql = schema.match(new RegExp(`CREATE TABLE ${lawTable} \\(([\\s\\S]*?)\\n\\) ENGINE`))[1];

test("every VARCHAR column of the law table is wide enough for the longest CSV value", () => {
  const { records } = loadMaster(MASTER_CSV);
  const tooLong = [];
  for (const [, column, width] of tableSql.matchAll(/^\s+(\w+)\s+VARCHAR\((\d+)\)/gm)) {
    for (const record of records) {
      const length = [...(record[column] ?? "")].length;
      if (length > Number(width)) tooLong.push(`${record.country_code} ${column}: ${length} > VARCHAR(${width})`);
    }
  }
  assert.deepEqual(tooLong, [], "widen the column in database_schema.sql (TEXT for free text)");
});

test("the importer reads its connection from DB_HOST, DB_PORT, DB_USER and DB_PASSWORD", () => {
  for (const name of ["DB_HOST", "DB_PORT", "DB_USER", "DB_PASSWORD"]) {
    assert.match(php, new RegExp(`getenv\\('${name}'\\)`), `${name} is not read`);
  }
  assert.match(php, /charset=utf8mb4/);
});

test("--import is all-or-nothing and can run again", () => {
  assert.match(php, /beginTransaction\(\)/);
  assert.match(php, /commit\(\)/);
  assert.match(php, /rollBack\(\)/);
  assert.match(php, new RegExp(`DELETE FROM ${lawTable}`));
});
