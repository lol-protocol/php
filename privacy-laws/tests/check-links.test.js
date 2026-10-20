import { after, before, test } from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { checkUrl, classify, collectUrls, mapLimit } from "../scripts/check-links.js";
import { COLUMNS, SOURCE_COLUMNS, loadMaster, toCsv } from "../scripts/lib/dataset.js";
import { hasPhp, startPhpServer } from "./helpers/php-server.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const skip = hasPhp ? false : "php is not installed";

test("status codes are classified: bot walls and server errors warn, dead pages fail", () => {
  assert.equal(classify(200, null, "u").status, "ok");
  assert.equal(classify(301, null, "u").status, "ok");
  for (const code of [401, 403, 429]) assert.equal(classify(code, null, "u").status, "warning", String(code));
  for (const code of [500, 502, 503]) assert.equal(classify(code, null, "u").status, "warning", String(code));
  for (const code of [400, 404, 410]) assert.equal(classify(code, null, "u").status, "broken", String(code));
  assert.equal(classify(null, "getaddrinfo ENOTFOUND", "u").status, "broken");
  assert.equal(classify(null, "fetch failed (CERT_HAS_EXPIRED)", "u").status, "broken");
  for (const soft of ["timeout after 15 s", "fetch failed (UND_ERR_CONNECT_TIMEOUT)", "fetch failed (UNABLE_TO_VERIFY_LEAF_SIGNATURE)", "fetch failed (ECONNRESET)", "fetch failed (EAI_AGAIN)", "fetch failed (redirect count exceeded)"]) {
    assert.equal(classify(null, soft, "u").status, "warning", soft);
  }
});

test("a 5xx is retried once: a server that recovers is ok, one that keeps failing only warns", async (t) => {
  const answer = (statuses) => {
    const calls = [];
    t.mock.method(globalThis, "fetch", async (url, init) => {
      calls.push(init.method);
      return new Response(null, { status: statuses[Math.min(calls.length, statuses.length) - 1] });
    });
    return calls;
  };
  let calls = answer([503, 503, 200]); // HEAD, GET, retry
  assert.equal((await checkUrl("https://example.org/x", { retryDelayMs: 0 })).status, "ok");
  assert.deepEqual(calls, ["HEAD", "GET", "GET"]);
  t.mock.restoreAll();
  calls = answer([503]);
  const result = await checkUrl("https://example.org/x", { retryDelayMs: 0 });
  assert.equal(result.status, "warning");
  assert.equal(result.code, 503);
  assert.equal(calls.length, 3);
  t.mock.restoreAll();
  calls = answer([404]); // a 4xx is not retried
  assert.equal((await checkUrl("https://example.org/x", { retryDelayMs: 0 })).status, "broken");
  assert.equal(calls.length, 2);
});

test("mapLimit keeps the order and never runs more than the limit at once", async () => {
  let running = 0;
  let peak = 0;
  const out = await mapLimit([30, 10, 20, 5, 1], 2, async (ms, i) => {
    running++;
    peak = Math.max(peak, running);
    await new Promise((r) => setTimeout(r, ms));
    running--;
    return i;
  });
  assert.deepEqual(out, [0, 1, 2, 3, 4]);
  assert.equal(peak, 2);
});

let server;
let work;
before(async () => {
  if (!hasPhp) return;
  server = await startPhpServer(join(root, "tests", "fixtures"), { router: join(root, "tests", "fixtures", "links-router.php") });
  work = mkdtempSync(join(tmpdir(), "laws-links-"));
});
after(async () => {
  await server?.stop();
  if (work) rmSync(work, { recursive: true, force: true });
});

test("the CLI checks every URL of the CSV, reports and fails only on broken links", { skip }, () => {
  const template = loadMaster().records[0];
  const row = (code, path) => ({ ...template, country_code: code, law_name: `Law ${code}`, website_url: path.startsWith("http") ? path : `${server.url}${path}` });
  const rows = [row("AA", "/ok"), row("AB", "/head-refused"), row("AC", "/moved"), row("AD", "/blocked"), row("AE", "/gone"), row("AF", "/error"), row("AG", "http://127.0.0.1:9/closed"), row("AH", "/ok")];
  const csv = join(work, "master.csv");
  writeFileSync(csv, toCsv(COLUMNS, rows));
  const summaryFile = join(work, "summary.md");
  writeFileSync(summaryFile, "");

  const run = spawnSync(process.execPath, [join(root, "scripts", "check-links.js"), `--csv=${csv}`, `--json=${join(work, "report.json")}`, "--timeout=5"], {
    encoding: "utf-8",
    env: { ...process.env, GITHUB_STEP_SUMMARY: summaryFile },
  });
  assert.equal(run.status, 1, run.stdout + run.stderr);
  assert.match(run.stdout, /7 URLs: 3 ok, 2 warnings, 2 broken/); // a refused connection (port 9) is broken, not a timeout // the two /ok rows share one URL

  const { results } = JSON.parse(readFileSync(join(work, "report.json"), "utf-8"));
  const by = (path) => results.find((r) => r.url.endsWith(path));
  assert.equal(by("/head-refused").status, "ok"); // HEAD refused, GET works
  assert.equal(by("/moved").finalUrl.endsWith("/ok"), true);
  assert.equal(by("/blocked").status, "warning");
  assert.equal(by("/gone").code, 404);
  assert.equal(by("/error").code, 500);
  assert.equal(by("/error").status, "warning"); // a server error, also on retry, is not a dead page
  assert.equal(by("/closed").status, "broken");
  assert.deepEqual(by("/ok").laws, ["AA Law AA", "AH Law AH"]);

  const summary = readFileSync(summaryFile, "utf-8");
  assert.match(summary, /### Reference links \(country data\)/);
  assert.match(summary, /\| AE Law AE \| .*\/gone \| HTTP 404 \|/);

  const only = spawnSync(process.execPath, [join(root, "scripts", "check-links.js"), `--csv=${csv}`, "--only=aa,ab", "--timeout=5"], { encoding: "utf-8" });
  assert.equal(only.status, 0, only.stdout);
  assert.match(only.stdout, /2 URLs: 2 ok/);
  assert.equal(spawnSync(process.execPath, [join(root, "scripts", "check-links.js"), "--nope"], { encoding: "utf-8" }).status, 2);
});

test("per-value sources are checked too, labelled with the value they back", () => {
  const template = loadMaster().records[0];
  const records = [
    { ...template, country_code: "AA", law_name: "Law A", website_url: "https://example.org/a" },
    { ...template, country_code: "BB", law_name: "Law B1", website_url: "https://example.org/b" },
    { ...template, country_code: "BB", law_name: "Law B2", website_url: "https://example.org/b" },
  ];
  const sources = [
    { country_code: "AA", law_name: "", field: "penalties_range", url: "https://example.org/a", confidence: "high", checked: "2026-10-15" },
    { country_code: "AA", law_name: "", field: "exemptions", url: "https://example.org/s", confidence: "low", checked: "2026-10-15" },
    { country_code: "BB", law_name: "Law B2", field: "notes", url: "https://example.org/s", confidence: "low", checked: "2026-10-15" },
    { country_code: "ZZ", law_name: "", field: "notes", url: "https://example.org/orphan", confidence: "low", checked: "2026-10-15" },
  ];
  const byUrl = collectUrls(records, sources);
  assert.deepEqual([...byUrl.keys()].sort(), ["https://example.org/a", "https://example.org/b", "https://example.org/s"]);
  assert.deepEqual(byUrl.get("https://example.org/a"), ["AA Law A", "AA Law A (source of penalties_range)"]);
  assert.deepEqual(byUrl.get("https://example.org/s"), ["AA Law A (source of exemptions)", "BB Law B2 (source of notes)"]);
  assert.deepEqual([...collectUrls(records, sources, ["bb"]).keys()].sort(), ["https://example.org/b", "https://example.org/s"]);
  assert.equal(SOURCE_COLUMNS.length, 6);
});
