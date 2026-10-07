import { after, before, test } from "node:test";
import assert from "node:assert/strict";
import { cpSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { hasPhp, startPhpServer } from "./helpers/php-server.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const skip = hasPhp ? false : "php is not installed";

let real; // serves this module as-is
let sandbox; // serves a copy of api.php with a throw-away data folder (texts endpoints)
let sandboxDir;

before(async () => {
  if (!hasPhp) return;
  real = await startPhpServer(root);

  sandboxDir = mkdtempSync(join(tmpdir(), "laws-api-"));
  cpSync(join(root, "api.php"), join(sandboxDir, "api.php"));
  const xa = join(sandboxDir, "jurisdictions", "xa");
  mkdirSync(join(xa, "texts"), { recursive: true });
  writeFileSync(join(xa, "texts", "index.json"), '[{"slug":"demo-law","status":"ok","file":"demo-law.md"}]\n');
  writeFileSync(join(xa, "texts", "demo-law.md"), "---\nsource: \"x\"\n---\n\n# Demo\n\nTexto con acentos: protección.\n");
  writeFileSync(join(xa, "texts", "plain-law.txt"), "Plain text law\n");
  mkdirSync(join(sandboxDir, "jurisdictions", "xb"), { recursive: true });
  sandbox = await startPhpServer(sandboxDir);
});

after(async () => {
  await real?.stop();
  await sandbox?.stop();
  if (sandboxDir) rmSync(sandboxDir, { recursive: true, force: true });
});

const get = (base, query, init) => fetch(`${base.url}/api.php?${query}`, init);

test("jurisdictions are listed from index.json with the dataset version", { skip }, async () => {
  const response = await get(real, "action=jurisdictions");
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type"), /^application\/json/);
  const { data, meta } = await response.json();
  assert.ok(data.length >= 50);
  assert.deepEqual(Object.keys(data[0]), ["code", "name", "tld", "region", "lawCount"]);
  assert.match(meta.version, /^[0-9a-f]{12}$/);
});

test("per-jurisdiction data is served as {data: …} with the requested format", { skip }, async () => {
  const laws = await (await get(real, "action=jurisdiction&tld=gb")).json();
  assert.equal(laws.data.length, 1);
  assert.equal(laws.data[0].law_name, "Copyright, Designs and Patents Act 1988");

  const info = await (await get(real, "action=jurisdiction_info&tld=eu")).json();
  assert.deepEqual(info.data, { code: "EU", name: "European Union", tld: "eu", region: "europe", lawCount: 1 });

  const csv = await get(real, "action=jurisdiction_csv&tld=eu");
  assert.match(csv.headers.get("content-type"), /^text\/csv/);
  assert.match(await csv.text(), /^country_code,country_name,region,law_name/);

  assert.equal((await get(real, "action=jurisdiction&tld=US")).status, 200); // upper case is accepted
  assert.equal((await get(real, "action=bundle")).status, 200);
});

test("tld is validated: no path traversal, no other module's data", { skip }, async () => {
  for (const tld of ["../../privacy-laws/countries/us", "us/../gb", "%00us", "u", "usa", "u1", "", "..", "%2e%2e"]) {
    const response = await get(real, `action=jurisdiction&tld=${tld}`);
    assert.equal(response.status, 400, `tld=${tld}`);
    assert.match((await response.json()).error, /2-letter/);
  }
  assert.equal((await get(real, "action=jurisdiction&tld=zz")).status, 404);
});

test("responses are cacheable: ETag/Last-Modified answer 304", { skip }, async () => {
  for (const query of ["action=jurisdictions", "action=bundle", "action=jurisdiction&tld=gb"]) {
    const first = await get(real, query);
    const etag = first.headers.get("etag");
    const modified = first.headers.get("last-modified");
    assert.match(etag, /^W\/"/);
    assert.match(first.headers.get("cache-control"), /max-age=\d+/);
    await first.arrayBuffer();

    const byEtag = await get(real, query, { headers: { "If-None-Match": etag } });
    assert.equal(byEtag.status, 304, `${query} If-None-Match`);
    const bySince = await get(real, query, { headers: { "If-Modified-Since": modified } });
    assert.equal(bySince.status, 304, `${query} If-Modified-Since`);
    const stale = await get(real, query, { headers: { "If-None-Match": 'W/"stale"' } });
    assert.equal(stale.status, 200, `${query} stale etag`);
    await stale.arrayBuffer();
  }
});

test("large responses are gzip-compressed", { skip }, async () => {
  const plain = await (await get(real, "action=bundle", { headers: { "Accept-Encoding": "identity" } })).arrayBuffer();
  // fetch() transparently decodes gzip, so compare against a raw request instead
  const raw = await new Promise((resolve, reject) => {
    import("node:http").then(({ get: httpGet }) => {
      httpGet(`${real.url}/api.php?action=bundle`, { headers: { "Accept-Encoding": "gzip" } }, (res) => {
        let size = 0;
        res.on("data", (chunk) => (size += chunk.length));
        res.on("end", () => resolve({ size, encoding: res.headers["content-encoding"] }));
      }).on("error", reject);
    });
  });
  assert.equal(raw.encoding, "gzip");
  assert.ok(raw.size < plain.byteLength / 3, `gzip ${raw.size} vs plain ${plain.byteLength}`);
});

test("only GET, HEAD and OPTIONS are allowed; unknown actions are rejected", { skip }, async () => {
  const post = await get(real, "action=jurisdictions", { method: "POST" });
  assert.equal(post.status, 405);
  assert.match(post.headers.get("allow"), /GET/);
  assert.equal((await get(real, "action=jurisdictions", { method: "OPTIONS" })).status, 204);
  const head = await get(real, "action=jurisdictions", { method: "HEAD" });
  assert.equal(head.status, 200);
  assert.equal((await head.text()), "");
  assert.equal((await get(real, "action=nope")).status, 400);
  assert.equal((await get(real, "action=jurisdictions")).headers.get("access-control-allow-origin"), "*");
});

test("cached reference texts: list, read as Markdown/plain text, validate the slug", { skip }, async () => {
  const list = await (await get(sandbox, "action=texts&tld=xa")).json();
  assert.equal(list.data[0].slug, "demo-law");
  assert.deepEqual((await (await get(sandbox, "action=texts&tld=xb")).json()).data, []); // known jurisdiction, nothing cached
  assert.equal((await get(sandbox, "action=texts&tld=zz")).status, 404);

  const md = await get(sandbox, "action=text&tld=xa&slug=demo-law");
  assert.match(md.headers.get("content-type"), /^text\/markdown; charset=utf-8/);
  assert.match(await md.text(), /# Demo[\s\S]*protección/);
  const txt = await get(sandbox, "action=text&tld=xa&slug=plain-law");
  assert.match(txt.headers.get("content-type"), /^text\/plain/);
  assert.equal(await txt.text(), "Plain text law\n");

  assert.equal((await get(sandbox, "action=text&tld=xa&slug=missing")).status, 404);
  for (const slug of ["../index", "demo-law.md", "Demo", "-x", ""]) {
    assert.equal((await get(sandbox, `action=text&tld=xa&slug=${encodeURIComponent(slug)}`)).status, 400, `slug=${slug}`);
  }
});
