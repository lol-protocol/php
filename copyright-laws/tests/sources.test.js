// The source files: each URL once in source_urls.csv (id = hash of the URL), field_sources.csv pointing at it.
import { test } from "node:test";
import assert from "node:assert/strict";
import { copyFileSync, mkdtempSync, readFileSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, join } from "node:path";
import * as real from "../scripts/lib/dataset.js";
import { createSources } from "../../leyes-comunes/sources-core.js";

/** A copy of the data in a temporary folder, so the test never touches the real CSVs. */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), "sources-"));
  const master = join(dir, basename(real.MASTER_CSV));
  copyFileSync(real.MASTER_CSV, master);
  for (const file of [real.SOURCES_FILE, real.SOURCE_URLS_FILE]) copyFileSync(join(real.DATA_DIR, file), join(dir, file));
  const dataset = { ...real, MASTER_CSV: master };
  return { dir, master, dataset, run: createSources({ dataset }) };
}

test("the real source files are consistent: ids are the hash of their URL, each URL once, none unused", () => {
  const { problems, rows } = real.loadSources(real.MASTER_CSV);
  assert.deepEqual(problems, []);
  assert.ok(rows.length > 100 && rows.every((r) => /^https?:\/\//.test(r.url)));
  // Read the file with the CSV parser: a URL with a comma is quoted.
  const stored = real.loadSide(real.MASTER_CSV, real.SOURCE_URLS_FILE, real.SOURCE_URL_COLUMNS).rows.map((r) => r.url);
  assert.equal(stored.length, new Set(rows.map((r) => r.url)).size, "one row per distinct URL");
  assert.deepEqual(stored, [...stored].sort(), "URLs sorted");
});

test("the id depends only on the URL, so two people adding the same URL agree", () => {
  assert.equal(real.sourceId("https://example.org/a"), real.sourceId("https://example.org/a"));
  assert.notEqual(real.sourceId("https://example.org/a"), real.sourceId("https://example.org/b"));
  assert.match(real.sourceId("https://example.org/a"), /^[0-9a-f]{10}$/);
});

test("loadSources reports a forged id, a missing id, a URL stored twice and an unused URL", () => {
  const { dir, master } = sandbox();
  const urls = join(dir, real.SOURCE_URLS_FILE);
  const text = readFileSync(urls, "utf-8");
  const [header, first, second, ...rest] = text.trim().split("\n");
  const [id1, url1] = first.split(",");
  // forged id, the same URL twice under its right id, and an unused URL
  writeFileSync(urls, [header, `0000000000,${url1}`, first, first, `${real.sourceId("https://unused.example/x")},https://unused.example/x`, second, ...rest].join("\n") + "\n");
  const { problems } = real.loadSources(master);
  assert.ok(problems.some((p) => /is not the id of its URL/.test(p)), problems.join("\n"));
  assert.ok(problems.some((p) => /appears twice/.test(p)));
  assert.ok(problems.some((p) => /stored twice/.test(p)));
  assert.ok(problems.some((p) => /used by no source/.test(p)));
  assert.ok(id1.length === 10);
  // and a source pointing at an id the file does not have
  writeFileSync(urls, [header, ...rest].join("\n") + "\n");
  assert.ok(real.loadSources(master).problems.some((p) => /is not in source_urls\.csv/.test(p)));
});

test("sources add stores a new URL once, replaces the source of a value, and reuses a URL that is already there", () => {
  const { master, dataset, run } = sandbox();
  const { records } = real.loadMaster(master);
  const single = records.find((r) => records.filter((x) => x.country_code === r.country_code).length === 1);
  const url = "https://example.org/new-source";
  const before = real.loadSources(master).rows.length;

  const added = run.addSource({ code: single.country_code.toLowerCase(), field: "notes", url, confidence: "low", checked: "2026-11-01" });
  assert.equal(added.replaced, real.loadSources(master).rows.filter((s) => s.country_code === single.country_code && s.field === "notes").length > 1);
  const after = real.loadSources(master);
  assert.deepEqual(after.problems, []);
  assert.equal(after.rows.filter((s) => s.url === url).length, 1);
  assert.ok(after.rows.length >= before);

  // the same value again with another URL: replaced, not duplicated; the first URL is unused now and disappears
  run.addSource({ code: single.country_code, field: "notes", url: "https://example.org/other", confidence: "high" });
  const again = real.loadSources(master);
  assert.equal(again.rows.filter((s) => s.country_code === single.country_code && s.field === "notes").length, 1);
  assert.equal(again.rows.some((s) => s.url === url), false);
  assert.deepEqual(again.problems, []);
  assert.deepEqual(real.validateSources(again.rows, records), []);
  void dataset;
});

test("sources add refuses what the validation would reject", () => {
  const { run } = sandbox();
  assert.throws(() => run.addSource({ code: "ZZ", field: "notes", url: "https://example.org/x" }), /no law for ZZ/);
  assert.throws(() => run.addSource({ code: "GB", field: "data_status", url: "https://example.org/x" }), /content column/);
  assert.throws(() => run.addSource({ code: "GB", field: "notes", url: "http://127.0.0.1/x" }), /public http/);
  assert.throws(() => run.addSource({ code: "GB", field: "notes", url: "https://example.org/x", confidence: "sure" }), /confidence must be/);
});

test("sources fix recomputes the id of a URL edited by hand and keeps its sources", () => {
  const { dir, master, run } = sandbox();
  const urls = join(dir, real.SOURCE_URLS_FILE);
  const lines = readFileSync(urls, "utf-8").trim().split("\n");
  const [id, url] = lines[1].split(",");
  const before = real.loadSources(master).rows.filter((s) => s.url === url).length;
  assert.ok(before >= 1);
  lines[1] = `${id},${url}-fixed`; // somebody edited the URL but not its id
  writeFileSync(urls, lines.join("\n") + "\n");
  assert.ok(real.loadSources(master).problems.length > 0);
  const done = run.fixSources({});
  assert.ok(done.sources > 100);
  const after = real.loadSources(master);
  assert.deepEqual(after.problems, []);
  assert.equal(after.rows.filter((s) => s.url === `${url}-fixed`).length, before);
});
