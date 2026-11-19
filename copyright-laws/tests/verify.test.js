// verify: the repeatable part of checking "(unconfirmed)" values against the text of their sources.
import { test } from "node:test";
import assert from "node:assert/strict";
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, join } from "node:path";
import { parse } from "csv-parse/sync";
import * as real from "../scripts/lib/dataset.js";
import { createVerify, splitByLaw } from "../../leyes-comunes/verify-core.js";

/** A copy of the data in a temporary folder, so the test never touches the real CSVs. */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), "verify-"));
  const master = join(dir, basename(real.MASTER_CSV));
  copyFileSync(real.MASTER_CSV, master);
  for (const file of [real.SOURCES_FILE, real.SOURCE_URLS_FILE]) copyFileSync(join(real.DATA_DIR, file), join(dir, file));
  const dataset = { ...real, MASTER_CSV: master };
  const work = join(dir, "work");
  mkdirSync(join(work, "out"), { recursive: true });
  return { master, work, verify: createVerify({ dataset, parse }) };
}
const MARK = /\(unconfirmed\)\s*$/;
const csv = (rows) => ["code,field,value,conf,source", ...rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))].join("\n") + "\n";

test("plan lists the countries with unconfirmed values, most first, and honours only, skip and limit", () => {
  const { verify } = sandbox();
  const all = verify.plan({});
  assert.ok(all.length > 5);
  assert.ok(all.every((c) => c.values.length && c.values.every((v) => v.law && v.field && !MARK.test(v.current))));
  assert.deepEqual(all.map((c) => c.values.length), [...all.map((c) => c.values.length)].sort((a, b) => b - a), "most values first");
  assert.deepEqual(verify.plan({ only: [all[0].code] }).map((c) => c.code), [all[0].code]);
  assert.ok(!verify.plan({ skip: [all[0].code] }).some((c) => c.code === all[0].code));
  assert.equal(verify.plan({ limit: 3 }).length, 3);
});

test("verify plan writes a task file per country, the prompt and the list for the workflow", () => {
  const { work, verify } = sandbox();
  const log = [];
  const original = console.log;
  console.log = (...a) => log.push(a.join(" "));
  try {
    assert.equal(verify.main(["plan", `--out=${work}`, "--limit=2"]), 0);
  } finally {
    console.log = original;
  }
  const [first] = verify.plan({ limit: 2 });
  const task = JSON.parse(readFileSync(join(work, "tasks", `${first.code}.json`), "utf-8"));
  assert.equal(task.country, first.code.toUpperCase());
  assert.deepEqual(task.unconfirmed, first.values);
  assert.match(readFileSync(join(work, "PROMPT.md"), "utf-8"), /Never invent/);
  assert.match(log.join("\n"), new RegExp(`${first.code},`));
});

test("apply patches the value a text supports, drops its mark and records its source; low confidence is ignored", () => {
  const { master, work, verify } = sandbox();
  const { records } = real.loadMaster(master);
  const law = records.find((r) => real.TRANSLATED_FIELDS.some((f) => MARK.test(r[f])) && records.filter((x) => x.country_code === r.country_code).length === 1);
  const field = real.TRANSLATED_FIELDS.find((f) => MARK.test(law[f]));
  const code = law.country_code;
  writeFileSync(join(work, "out", `${code}.csv`), csv([[code, field, "A value the page supports (art. 9)", "low", "https://example.org/weak"]]));
  const ignored = verify.apply({ dir: work });
  assert.deepEqual(ignored.patched, [], "low is below the default minimum");
  assert.match(real.loadMaster(master).records.find((r) => r.country_code === code)[field], MARK);

  writeFileSync(join(work, "out", `${code}.csv`), csv([[code, field, "A value the page supports (art. 9)", "medium", "https://example.org/supports"]]));
  const done = verify.apply({ dir: work, today: real.todayIso() });
  assert.equal(done.patched.length, 1);
  const after = real.loadMaster(master).records.find((r) => r.country_code === code);
  assert.equal(after[field], "A value the page supports (art. 9)");
  const source = real.loadSources(master).rows.find((s) => s.country_code === code && s.field === field);
  assert.equal(source.url, "https://example.org/supports");
  assert.equal(source.confidence, "medium");
  assert.ok(!verify.plan({ only: [code.toLowerCase()] }).some((c) => c.values.some((v) => v.field === field)), "it no longer shows in the plan");
});

test("splitByLaw starts a block at each law_name row", () => {
  const rows = [
    { code: "US", field: "law_name", value: "A" },
    { code: "US", field: "notes", value: "x" },
    { code: "US", field: "law_name", value: "B" },
    { code: "US", field: "scope", value: "y" },
  ];
  assert.deepEqual(splitByLaw(rows).map((b) => b.length), [2, 2]);
  assert.deepEqual(splitByLaw([{ code: "FR", field: "scope", value: "z" }]).length, 1);
  assert.deepEqual(splitByLaw([]), []);
});

test("apply reports files without findings and does nothing without an out folder", () => {
  const { work, verify } = sandbox();
  writeFileSync(join(work, "out", "XX.csv"), "code,field,value,conf,source\n");
  const r = verify.apply({ dir: work });
  assert.deepEqual(r.empty, ["XX.csv"]);
  assert.equal(verify.apply({ dir: join(work, "nowhere") }).files, 0);
  assert.ok(existsSync(work));
});
