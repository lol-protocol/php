import { test } from "node:test";
import assert from "node:assert/strict";
import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import {
  COLUMNS,
  DATA_DIR,
  REGIONS,
  buildArtifacts,
  findOrphans,
  loadAndValidate,
  parseMaster,
  planWrites,
  toCsv,
  validateRecords,
} from "../scripts/lib/dataset.js";

const { records, errors } = loadAndValidate();
const readJson = (...parts) => JSON.parse(readFileSync(join(DATA_DIR, ...parts), "utf-8"));
const validate = (...rows) => validateRecords(rows, COLUMNS).errors;

test("master CSV parses strictly and validates", () => {
  assert.deepEqual(errors, []);
  assert.ok(records.length >= 50, `expected at least 50 laws, got ${records.length}`);
});

test("rows with commas inside fields stay aligned (regression: columns used to shift)", () => {
  const find = (code, text) => records.find((r) => r.country_code === code && r.law_name.includes(text));

  const gdpr = find("EU", "GDPR");
  assert.equal(gdpr.key_requirements, "Lawful basis, consent, data subject rights");
  assert.equal(gdpr.enforcement_authority, "European Data Protection Board & National DPAs");
  assert.equal(gdpr.website_url, "https://gdpr-info.eu/");

  assert.equal(find("US", "CCPA").penalties_range, "Up to $7,500 per violation");
  assert.equal(find("US", "COPPA").penalties_range, "Up to $43,280 per violation");
  assert.equal(find("CA", "PIPEDA").penalties_range, "Up to $100,000 CAD");
  assert.equal(find("IN", "Digital Personal").notes, "Recently passed, enforcement phase");
});

test("an unquoted comma is rejected instead of silently shifting columns", () => {
  const row = "EU,European Union,europe,GDPR,EC,2016-04-27,2018-05-25,a,b,Lawful basis, consent,c,d,e,f,g,https://x.eu/,English,GDPR,n";
  assert.throws(() => parseMaster(`${COLUMNS.join(",")}\n${row}\n`), /Invalid Record Length/);
});

test("validation reports bad data with the line number", () => {
  const base = records[0];
  const only = (patch) => validate({ ...base, ...patch });

  assert.match(only({ effective_date: "2018-13-40" })[0], /line 2: effective_date must be a real YYYY-MM-DD date/);
  assert.match(only({ enactment_date: "2019-01-01", effective_date: "2018-01-01" })[0], /before enactment_date/);
  assert.match(only({ region: "mars" })[0], /region must be one of/);
  assert.match(only({ country_code: "usa" })[0], /country_code must be 2 uppercase letters/);
  assert.match(only({ language: "https://shifted.example" })[0], /language must look like/);
  assert.match(only({ website_url: "javascript:alert(1)" })[0], /http\(s\) URL/);
  for (const host of ["http://127.0.0.1:8080/x", "http://localhost/x", "http://169.254.169.254/latest/", "http://10.0.0.5/", "http://192.168.1.1/", "http://172.20.0.1/", "http://[::1]/", "http://intranet.internal/"]) {
    assert.match(only({ website_url: host })[0], /public web/, host);
  }
  assert.deepEqual(only({ website_url: "https://example.org/172.16.0.1" }), []); // only the host counts
  assert.match(only({ law_name: " padded " })[0], /leading\/trailing whitespace/);
  assert.match(only({ enforcement_authority: "" })[0], /missing required field enforcement_authority/);

  assert.match(validate(base, { ...base })[0], /duplicate/);
  assert.match(validate(base, { ...base, law_name: "Another", country_name: "Elsewhere" })[0], /two names/);
  assert.match(validate(base, { ...base, law_name: "Another", region: "americas" })[0], /two regions/);
  assert.match(validateRecords([base], ["country_code"]).errors[0], /header must be exactly/);
});

test("every country belongs to a known region", () => {
  for (const record of records) assert.ok(REGIONS.includes(record.region), `${record.country_code}: ${record.region}`);
});

test("generated per-country files and index.json are up to date (run `npm run build`)", () => {
  const artifacts = buildArtifacts(records);
  const stale = planWrites(artifacts).filter((item) => item.status !== "same");
  assert.deepEqual(stale.map((item) => `${item.status}: ${item.rel}`), []);
  assert.deepEqual(findOrphans(artifacts), []);
});

test("each country has its own folder named with the 2-letter TLD", () => {
  const folders = readdirSync(DATA_DIR, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name);
  const countries = new Set(records.map((r) => r.country_code.toLowerCase()));
  assert.deepEqual([...folders].sort(), [...countries].sort());
  for (const folder of folders) {
    assert.match(folder, /^[a-z]{2}$/);
    assert.deepEqual(readdirSync(join(DATA_DIR, folder)).filter((f) => f !== "texts").sort(), ["info.json", "laws.csv", "laws.json"]);
  }
});

test("index.json agrees with the master CSV and the per-country files", () => {
  const bundle = readJson("index.json");
  assert.equal(bundle.module, "privacy-laws");
  assert.match(bundle.version, /^[0-9a-f]{12}$/);
  assert.deepEqual(bundle.columns, COLUMNS);
  assert.equal(bundle.countries.reduce((sum, c) => sum + c.laws.length, 0), records.length);

  for (const country of bundle.countries) {
    assert.equal(country.tld, country.code.toLowerCase());
    assert.equal(country.lawCount, country.laws.length);
    assert.deepEqual(readJson(country.tld, "info.json"), {
      code: country.code,
      name: country.name,
      tld: country.tld,
      region: country.region,
      lawCount: country.lawCount,
    });
    const full = readJson(country.tld, "laws.json");
    assert.equal(full.length, country.laws.length);
    full.forEach((law, index) => {
      assert.equal(law.country_code, country.code);
      assert.equal(law.law_name, country.laws[index].law_name);
    });
  }
});

test("generated CSV files defuse spreadsheet formulas", () => {
  const csv = toCsv(["a", "b", "c"], [{ a: "=cmd|' /C calc'!A0", b: "@SUM(1+1)", c: "plain, with comma" }, { a: "-1", b: "+1", c: 'say "hi"' }]);
  assert.equal(
    csv,
    `a,b,c\n"'=cmd|' /C calc'!A0","'@SUM(1+1)","plain, with comma"\n"'-1","'+1","say ""hi"""\n`
  );
});
