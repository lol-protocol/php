// The app downloads countries/index.json in one request. Sources and history live in it too,
// so the file grows with every addition: this test fails long before the page feels slow,
// and its message says what to do (load the history lazily from each country's laws.json).
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { gzipSync } from "node:zlib";
import { DATA_DIR } from "../scripts/lib/dataset.js";

const RAW_BUDGET_KB = 900;
const GZIP_BUDGET_KB = 170;

test("index.json stays within its download budget", () => {
  const raw = readFileSync(join(DATA_DIR, "index.json"));
  const kb = (n) => Math.round(n / 1024);
  const zipped = gzipSync(raw).length;
  console.log(`# index.json: ${kb(raw.length)} KB (${kb(zipped)} KB gzipped); budget ${RAW_BUDGET_KB} / ${GZIP_BUDGET_KB} KB`);
  assert.ok(kb(raw.length) <= RAW_BUDGET_KB, `index.json is ${kb(raw.length)} KB (budget ${RAW_BUDGET_KB}): move the change history out of the bundle`);
  assert.ok(kb(zipped) <= GZIP_BUDGET_KB, `index.json is ${kb(zipped)} KB gzipped (budget ${GZIP_BUDGET_KB})`);
});
