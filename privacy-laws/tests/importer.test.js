// import_database.php inserts every column of the master CSV into MySQL. Nothing runs it in CI
// (no database there), so this test keeps its INSERT in step with the CSV columns.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { COLUMNS, ROOT } from "../scripts/lib/dataset.js";

const php = readFileSync(join(ROOT, "import_database.php"), "utf-8");
const schema = readFileSync(join(ROOT, "database_schema.sql"), "utf-8");
const insert = php.match(/INSERT INTO (\w+)\s*\(([^)]*)\)\s*VALUES \(([^)]*)\)/);
const execute = php.match(/\$lawStmt->execute\(\[([\s\S]*?)\]\);/);

test("the law INSERT has as many placeholders as columns and as many values as placeholders", () => {
  assert.ok(insert && execute, "could not find the law INSERT / execute call");
  const columns = insert[2].split(",").map((c) => c.trim());
  assert.equal(insert[3].split("?").length - 1, columns.length, "placeholders vs columns");
  const values = execute[1].split("\n").filter((l) => l.trim());
  assert.equal(values.length, columns.length, "execute() arguments vs columns");
});

test("every CSV column reaches the database, and every database column exists in the schema", () => {
  const columns = insert[2].split(",").map((c) => c.trim()).filter((c) => c !== "country_id");
  const fromCsv = COLUMNS.filter((c) => !["country_code", "country_name", "region"].includes(c));
  assert.deepEqual([...columns].sort(), [...fromCsv].sort());
  const table = schema.match(new RegExp(`CREATE TABLE ${insert[1]} \\(([\\s\\S]*?)\\n\\) ENGINE`))[1];
  for (const column of columns) assert.match(table, new RegExp(`\\n\\s+${column}\\s`), `${column} is missing from ${insert[1]} in database_schema.sql`);
});
