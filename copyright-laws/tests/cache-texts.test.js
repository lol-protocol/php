import { after, before, test } from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { hasPhp, startPhpServer } from "./helpers/php-server.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const fixtures = join(root, "tests", "fixtures");
const script = join(root, "scripts", "cache-texts.php");
const skip = hasPhp ? false : "php is not installed";

const php = (args, { input, env } = {}) =>
  spawnSync("php", [script, ...args], { encoding: "utf-8", input, env: { ...process.env, ...env } });

test("--convert keeps the essential text as Markdown and drops the page furniture", { skip }, () => {
  const html = readFileSync(join(fixtures, "law-page.html"), "utf-8");
  const { stdout, status } = php(["--convert"], { input: html });
  assert.equal(status, 0);
  assert.equal(
    stdout,
    [
      "# Ley Orgánica 3/2018, de 5 de diciembre",
      "",
      "Protección de datos personales y garantía de los derechos digitales. Aplica a todas las entidades & organismos.",
      "",
      "## Artículo 1 — Objeto",
      "",
      "- Adaptar el ordenamiento al RGPD",
      "- Garantizar derechos digitales",
      "  - Neutralidad de Internet",
      "  - Acceso universal",
      "",
      "1. Primero",
      "2. Segundo",
      "",
      "| Infracción | Sanción |",
      "| --- | --- |",
      "| Muy grave | Hasta 20 M€ \\| o 4 % |",
      "",
      "> La dignidad de la persona es inviolable.",
      "",
      "```",
      'art_1 = "Objeto"',
      "  indent kept",
      "```",
      "",
      "Texto con script incrustado y sin icono.",
      "",
    ].join("\n")
  );
  for (const noise of ["Menú", "cookies", "Contenido oculto", "Otro oculto", "Publicidad", "Aviso legal", "track", "alert(", "<"]) {
    assert.ok(!stdout.includes(noise), `"${noise}" should not survive`);
  }
});

test("--convert decodes the declared charset and survives broken markup", { skip }, () => {
  const latin1 = Buffer.concat([
    Buffer.from('<html><head><meta charset="iso-8859-1"></head><body><main><p>'),
    Buffer.from("Protección de datos: niño, año", "latin1"),
    Buffer.from("</p></main></body></html>"),
  ]);
  assert.match(php(["--convert"], { input: latin1 }).stdout, /Protección de datos: niño, año/);

  const broken = php(["--convert"], { input: "<div><p>Unclosed <b>bold<p>second <ul><li>one<li>two</div>" });
  assert.equal(broken.status, 0);
  assert.match(broken.stdout, /Unclosed bold/);
  assert.match(broken.stdout, /- one\n- two/);
});

test("usage errors exit with status 2", { skip }, () => {
  assert.equal(php(["--only=usa"]).status, 2);
  assert.equal(php(["--nope"]).status, 2);
  assert.equal(php(["--help"]).status, 0);
});

test("--convert keeps cells, nested tables, inline blocks and form-wrapped pages readable", { skip }, () => {
  const convert = (html) => php(["--convert"], { input: html }).stdout.trim();

  assert.equal(
    convert("<main><table><tr><td><p>Serious breach.</p><p>Repeated offence.</p></td><td><ul><li>Up to 20M</li><li>or 4 percent</li></ul></td></tr></table></main>"),
    "| Serious breach. Repeated offence. | Up to 20M or 4 percent |"
  );
  // a nested table belongs to its cell: its rows are not emitted a second time
  assert.equal(
    convert("<main><table><tr><td><table><tr><td>Inner A</td><td>Inner B</td></tr></table></td><td>Outer</td></tr></table></main>"),
    "| Inner A Inner B | Outer |"
  );
  assert.equal(convert("<main><p>Intro</p><a><div>Block A</div><div>Block B</div></a></main>"), "Intro\n\nBlock A Block B");
  // ASP.NET-style pages wrap everything in one <form>; small search/login forms are still dropped
  assert.equal(convert("<html><body><form><main><h1>Law</h1><p>Body of the law.</p></main></form></body></html>"), "# Law\n\nBody of the law.");
  assert.equal(convert('<html><body><main><p>Law text.</p></main><form><label>Search</label><input></form></body></html>'), "Law text.");
});

/* ---------- Full flow against a local fixture server ---------- */

let server;
let workDir;
let dataDir;
let pageDir;

before(async () => {
  if (!hasPhp) return;
  workDir = mkdtempSync(join(tmpdir(), "laws-cache-"));
  pageDir = join(workDir, "pages");
  dataDir = join(workDir, "data");
  mkdirSync(pageDir);
  mkdirSync(dataDir);
  cpSync(join(fixtures, "law-page.html"), join(pageDir, "law-page.html"));
  server = await startPhpServer(pageDir, { router: join(fixtures, "router.php"), env: { FIXTURE_DIR: pageDir } });

  const law = (law_name, path) => ({ law_name, linked_resources: `${server.url}${path}` });
  const bundle = {
    jurisdictions: [
      { code: "XA", tld: "xa", name: "A", region: "europe", lawCount: 5, laws: [
        law("Ley Orgánica Uno", "/law.html"),
        law("Law Redirect", "/redirect"),
        law("Law Plain", "/plain.txt"),
        law("Law Latin1 Ñandú", "/latin1.html"),
        law("Ley Orgánica Uno", "/law.html"),
      ] },
      { code: "XB", tld: "xb", name: "B", region: "europe", lawCount: 4, laws: [
        law("Law PDF", "/doc.pdf"),
        law("Law Empty", "/empty.html"),
        law("Law Forbidden", "/forbidden"),
        { law_name: "Law Down", linked_resources: "http://127.0.0.1:9/nothing" },
      ] },
    ],
  };
  writeFileSync(join(dataDir, "index.json"), JSON.stringify(bundle));
});

after(async () => {
  await server?.stop();
  if (workDir) rmSync(workDir, { recursive: true, force: true });
});

const run = (extra = []) => php(["--delay=0", "--timeout=5", "--allow-private-hosts", ...extra], { env: { LAWS_DATA_DIR: dataDir } });
const index = (tld) => JSON.parse(readFileSync(join(dataDir, tld, "texts", "index.json"), "utf-8"));

test("--dry-run lists the plan without touching the network or disk", { skip }, () => {
  const { stdout, status } = run(["--dry-run"]);
  assert.equal(status, 0);
  assert.match(stdout, /\[plan\] xa\/ley-organica-uno\.md/);
  assert.match(stdout, /\[plan\] xa\/ley-organica-uno-2\.md/); // duplicate names get a numeric suffix
  assert.match(stdout, /\[plan\] xa\/law-latin1-nandu\.md/); // accents are transliterated
  assert.ok(!existsSync(join(dataDir, "xa", "texts")));
});

test("first run stores text only; skipped and failed pages are reported", { skip }, () => {
  const { stdout, status } = run();
  assert.equal(status, 0, stdout);
  assert.match(stdout, /Done: 5 new, 0 updated, 0 unchanged, 2 skipped, 2 failed/);
  assert.match(stdout, /\[skip\] xb\/law-pdf {2}PDF documents are not converted/);
  assert.match(stdout, /\[skip\] xb\/law-empty {2}no readable text/);
  assert.match(stdout, /\[fail\] xb\/law-forbidden {2}HTTP 403/);
  assert.match(stdout, /\[fail\] xb\/law-down/);

  const md = readFileSync(join(dataDir, "xa", "texts", "ley-organica-uno.md"), "utf-8");
  assert.match(md, /^---\nsource: "http:\/\/127\.0\.0\.1:\d+\/law\.html"\nlaw: "Ley Orgánica Uno"\ntitle: "Ley Orgánica 3\/2018 – AEPD"\nfetched: "\d{4}-\d\d-\d\dT[\d:]+Z"\n---\n\n# Ley Orgánica/);
  assert.ok(!/<[a-z!/]/i.test(md), "no HTML tags may be stored");

  const latin1 = readFileSync(join(dataDir, "xa", "texts", "law-latin1-nandu.md"), "utf-8");
  assert.match(latin1, /Información personal niño año/); // iso-8859-1 page converted to UTF-8
  assert.ok(existsSync(join(dataDir, "xa", "texts", "law-redirect.md"))); // 302 followed

  const entries = index("xa");
  assert.equal(entries.length, 5);
  assert.deepEqual(entries.map((e) => e.status), ["ok", "ok", "ok", "ok", "ok"]);
  assert.match(entries.find((e) => e.slug === "ley-organica-uno").etag, /^"[0-9a-f]{32}"$/);
  assert.deepEqual(index("xb").map((e) => `${e.slug}:${e.status}`), ["law-down:error", "law-empty:skipped", "law-forbidden:error", "law-pdf:skipped"]);
  assert.ok(!existsSync(join(dataDir, "xb", "texts", "law-pdf.md")));
});

test("second run revalidates: 304 or identical text, nothing rewritten", { skip }, () => {
  const before = readFileSync(join(dataDir, "xa", "texts", "ley-organica-uno.md"), "utf-8");
  const { stdout } = run();
  assert.match(stdout, /\[same\] xa\/ley-organica-uno {2}\(304 not modified\)/);
  assert.match(stdout, /\[same\] xa\/law-plain .*\(text identical\)/);
  assert.match(stdout, /Done: 0 new, 0 updated, 5 unchanged, 2 skipped, 2 failed/);
  assert.equal(readFileSync(join(dataDir, "xa", "texts", "ley-organica-uno.md"), "utf-8"), before);
});

test("a changed page is updated; --only limits the run; --format=txt writes plain text", { skip }, () => {
  const page = join(pageDir, "law-page.html");
  writeFileSync(page, readFileSync(page, "utf-8").replace("Neutralidad de Internet", "Neutralidad de la red"));

  const updated = run(["--only=xa"]);
  assert.match(updated.stdout, /\[ok\] xa\/ley-organica-uno .*\(updated\)/);
  assert.match(updated.stdout, /Done: 0 new, 3 updated, 2 unchanged, 0 skipped, 0 failed/); // three URLs lead to law.html (one via redirect)
  assert.match(readFileSync(join(dataDir, "xa", "texts", "ley-organica-uno.md"), "utf-8"), /Neutralidad de la red/);
  assert.doesNotMatch(updated.stdout, /xb\//);

  const txt = run(["--only=xa", "--format=txt", "--force"]);
  assert.match(txt.stdout, /Done: 0 new, 5 updated/);
  assert.ok(!existsSync(join(dataDir, "xa", "texts", "ley-organica-uno.md")), "the old .md must not linger next to the .txt");
  assert.deepEqual(index("xa").map((e) => e.file).filter((f) => !f.endsWith(".txt")), []);
  const plain = readFileSync(join(dataDir, "xa", "texts", "ley-organica-uno.txt"), "utf-8");
  assert.match(plain, /^Ley Orgánica Uno\nSource: http:\/\/127\.0\.0\.1:\d+\/law\.html\nFetched: /);
  assert.doesNotMatch(plain, /^#/m);
});

/* ---------- Regressions found in review ---------- */

test("switching the format without --force rewrites the file instead of keeping a stale 304", { skip }, () => {
  const md = run(["--only=xa"]); // the previous test left .txt files behind
  assert.match(md.stdout, /Done: 0 new, 5 updated/);
  assert.ok(existsSync(join(dataDir, "xa", "texts", "ley-organica-uno.md")));
  assert.ok(!existsSync(join(dataDir, "xa", "texts", "ley-organica-uno.txt")));
  assert.deepEqual(index("xa").map((e) => e.file).filter((f) => !f.endsWith(".md")), []);
});

test("a changed reference URL is refetched, not answered from the old validators", { skip }, () => {
  const file = join(dataDir, "xa", "texts", "index.json");
  const entries = JSON.parse(readFileSync(file, "utf-8"));
  const plain = entries.find((e) => e.slug === "law-plain");
  const current = plain.source;
  plain.source = `${current}?old=1`;
  writeFileSync(file, JSON.stringify(entries));
  const { stdout } = run(["--only=xa"]);
  assert.doesNotMatch(stdout, /law-plain {2}\(304/);
  assert.equal(index("xa").find((e) => e.slug === "law-plain").source, current);
});

test("a path in index.json is never trusted for deleting files", { skip }, () => {
  const victim = join(workDir, "victim.txt");
  writeFileSync(victim, "keep me");
  const file = join(dataDir, "xa", "texts", "index.json");
  const entries = JSON.parse(readFileSync(file, "utf-8"));
  entries.find((e) => e.slug === "law-plain").file = "../../victim.txt";
  writeFileSync(file, JSON.stringify(entries));
  run(["--only=xa", "--format=txt", "--force"]);
  assert.equal(readFileSync(victim, "utf-8"), "keep me");
});

test("cached text of a law that left the dataset is removed, other files are left alone", { skip }, () => {
  const dir = join(dataDir, "xa", "texts");
  writeFileSync(join(dir, "retired-law.md"), "old text");
  writeFileSync(join(dir, "README-by-hand.docx"), "not ours");
  const { stdout } = run(["--only=xa"]);
  assert.match(stdout, /\[gone\] xa\/retired-law\.md/);
  assert.ok(!existsSync(join(dir, "retired-law.md")));
  assert.ok(existsSync(join(dir, "README-by-hand.docx")));
});

test("private and loopback addresses are refused unless explicitly allowed", { skip }, () => {
  const isolated = mkdtempSync(join(tmpdir(), "laws-ssrf-"));
  try {
    const bundle = { jurisdictions: [{ code: "XC", tld: "xc", name: "C", region: "europe", lawCount: 3, laws: [
      { law_name: "Internal", linked_resources: `${server.url}/law.html` },
      { law_name: "Metadata", linked_resources: "http://169.254.169.254/latest/meta-data/" },
      { law_name: "Other scheme", linked_resources: "file:///etc/passwd" },
    ] }] };
    writeFileSync(join(isolated, "index.json"), JSON.stringify(bundle));
    const result = php(["--delay=0", "--timeout=5"], { env: { LAWS_DATA_DIR: isolated } });
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /\[fail\] xc\/internal {2}refusing to fetch a private address \(127\.0\.0\.1\)/);
    assert.match(result.stdout, /\[fail\] xc\/metadata {2}refusing to fetch a private address \(169\.254\.169\.254\)/);
    assert.doesNotMatch(result.stdout, /xc\/other-scheme/); // not http(s): never even attempted
    assert.deepEqual(index_of(isolated, "xc").map((e) => e.status), ["error", "error"]);
    assert.ok(!existsSync(join(isolated, "xc", "texts", "internal.md")));
  } finally {
    rmSync(isolated, { recursive: true, force: true });
  }
});

const index_of = (dir, tld) => JSON.parse(readFileSync(join(dir, tld, "texts", "index.json"), "utf-8"));
