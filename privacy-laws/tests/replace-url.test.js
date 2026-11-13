// replace-url: one dead link is swapped in the master CSV and in the field sources in a single command.
import { test } from "node:test";
import assert from "node:assert/strict";
import { copyFileSync, mkdtempSync, readFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, join } from "node:path";
import * as real from "../scripts/lib/dataset.js";
import { createReplaceUrl } from "../../leyes-comunes/replace-url-core.js";

/** A copy of the data in a temporary folder, so the test never touches the real CSVs. */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), "replace-url-"));
  const master = join(dir, basename(real.MASTER_CSV));
  copyFileSync(real.MASTER_CSV, master);
  for (const file of [real.SOURCES_FILE, real.SOURCE_URLS_FILE]) copyFileSync(join(real.DATA_DIR, file), join(dir, file));
  const dataset = { ...real, MASTER_CSV: master };
  return { dir, master, dataset, run: createReplaceUrl({ dataset, urlField: "website_url" }) };
}

test("a URL is replaced in the master CSV and in every field source that uses it", () => {
  const { master, dataset, run, dir } = sandbox();
  const { records } = real.loadMaster(master);
  const from = records.find((r) => r.website_url.startsWith("https://"))?.website_url;
  assert.ok(from, "the data has a reference URL");
  const to = "https://example.org/replacement";

  const counted = run.replaceUrl({ from, to, write: false });
  assert.ok(counted.master >= 1);
  assert.equal(real.loadMaster(master).records.filter((r) => r.website_url === from).length, counted.master, "dry run writes nothing");

  const done = run.replaceUrl({ from, to });
  assert.deepEqual(done, counted);
  assert.equal(real.loadMaster(master).records.filter((r) => r.website_url === from).length, 0);
  assert.equal(real.loadMaster(master).records.filter((r) => r.website_url === to).length, done.master);
  const sources = real.loadSources(master).rows;
  assert.equal(sources.filter((s) => s.url === from).length, 0);
  assert.equal(sources.filter((s) => s.url === to).length, done.sources);
  assert.match(readFileSync(master, "utf-8"), /example\.org\/replacement/);
  void dir;
});

test("replace-url refuses a missing, identical or private target", () => {
  const { run } = sandbox();
  assert.throws(() => run.replaceUrl({ from: "https://a.example/x", to: "" }), /both the old and the new URL/);
  assert.throws(() => run.replaceUrl({ from: "https://a.example/x", to: "https://a.example/x" }), /the same/);
  assert.throws(() => run.replaceUrl({ from: "https://a.example/x", to: "http://127.0.0.1/x" }), /public http/);
  assert.deepEqual(run.replaceUrl({ from: "https://never.example/there", to: "https://b.example/y" }), { master: 0, sources: 0 });
});
