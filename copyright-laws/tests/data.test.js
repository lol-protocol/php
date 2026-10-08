import { test } from "node:test";
import assert from "node:assert/strict";
import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import {
  COLUMNS,
  DATA_DIR,
  PROTECTION_TYPES,
  REGIONS,
  TREATIES,
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

test("law names with commas stay aligned (regression: columns used to shift)", () => {
  const byCode = (code) => records.find((r) => r.country_code === code);

  const gb = byCode("GB");
  assert.equal(gb.law_name, "Copyright, Designs and Patents Act 1988");
  assert.equal(gb.protection_type, "Copyright");
  assert.equal(gb.term_of_protection, "Author's life + 70 years");
  assert.match(gb.treaties_signatory, /^Berne\/TRIPS\/WCT/); // more memberships may be appended as they are confirmed
  assert.equal(gb.linked_resources, "https://www.gov.uk/topic/intellectual-property");

  assert.equal(byCode("CA").law_name, "Copyright Act (R.S.C., 1985)");
  assert.equal(byCode("BR").law_name, "Law No. 9,610/1998");
  assert.equal(byCode("CL").law_name, "Law No. 17,336");
  for (const code of ["CA", "BR", "CL"]) assert.equal(byCode(code).protection_type, "Copyright");
});

test("an unquoted comma is rejected instead of silently shifting columns", () => {
  const row = "GB,United Kingdom,europe,Copyright, Designs and Patents Act 1988,Copyright,Life + 70 years,a,b,c,d,e,No,f,Berne,https://x.uk/";
  assert.throws(() => parseMaster(`${COLUMNS.join(",")}\n${row}\n`), /Invalid Record Length/);
});

test("validation reports bad data with the line number", () => {
  const base = records[0];
  const only = (patch) => validate({ ...base, ...patch });

  assert.match(only({ protection_type: "Patent" })[0], /line 2: protection_type must be one of/);
  assert.match(only({ term_of_protection: "Copyright" })[0], /should state a number of years/);
  assert.match(only({ treaties_signatory: "Berne/Hague" })[0], /unknown treaty "Hague"/);
  for (const host of ["http://127.0.0.1:8080/x", "http://localhost/x", "http://169.254.169.254/latest/", "http://10.0.0.5/", "http://[::1]/"]) {
    assert.match(only({ linked_resources: host })[0], /public web/, host);
  }
  assert.match(only({ treaties_signatory: "Berne/Berne" })[0], /same treaty twice/);
  assert.match(only({ registration_required: "Fair dealing exceptions" })[0], /registration_required must be/);
  assert.match(only({ linked_resources: "ftp://x" })[0], /http\(s\) URL/);
  assert.match(only({ region: "mars" })[0], /region must be one of/);
  assert.match(only({ country_code: "gb" })[0], /country_code must be 2 uppercase letters/);
  assert.match(only({ law_name: " padded" })[0], /leading\/trailing whitespace/);
  assert.match(only({ enforcement_body: "" })[0], /missing required field enforcement_body/);

  assert.deepEqual(only({ treaties_signatory: "" }), []); // a jurisdiction may be party to no treaty
  assert.deepEqual(only({ registration_required: "No (but beneficial)" }), []);
  assert.match(validate(base, { ...base })[0], /duplicate/);
  assert.match(validate(base, { ...base, law_name: "Another", country_name: "Elsewhere" })[0], /two names/);
  assert.match(validateRecords([base], ["country_code"]).errors[0], /header must be exactly/);
});

test("regions, protection types and treaties come from the allowed lists", () => {
  for (const record of records) {
    assert.ok(REGIONS.includes(record.region), `${record.country_code}: ${record.region}`);
    assert.ok(PROTECTION_TYPES.includes(record.protection_type), `${record.country_code}: ${record.protection_type}`);
    for (const treaty of record.treaties_signatory.split("/").filter(Boolean)) assert.ok(TREATIES.includes(treaty), treaty);
  }
});

test("generated per-jurisdiction files and index.json are up to date (run `npm run build`)", () => {
  const artifacts = buildArtifacts(records);
  const stale = planWrites(artifacts).filter((item) => item.status !== "same");
  assert.deepEqual(stale.map((item) => `${item.status}: ${item.rel}`), []);
  assert.deepEqual(findOrphans(artifacts), []);
});

test("each jurisdiction has its own folder named with the 2-letter TLD", () => {
  const folders = readdirSync(DATA_DIR, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name);
  const expected = new Set(records.map((r) => r.country_code.toLowerCase()));
  assert.deepEqual([...folders].sort(), [...expected].sort());
  for (const folder of folders) {
    assert.match(folder, /^[a-z]{2}$/);
    assert.deepEqual(readdirSync(join(DATA_DIR, folder)).filter((f) => f !== "texts").sort(), ["info.json", "laws.csv", "laws.json"]);
  }
});

test("index.json agrees with the master CSV and the per-jurisdiction files", () => {
  const bundle = readJson("index.json");
  assert.equal(bundle.module, "copyright-laws");
  assert.match(bundle.version, /^[0-9a-f]{12}$/);
  assert.deepEqual(bundle.columns, COLUMNS);
  assert.equal(bundle.jurisdictions.reduce((sum, j) => sum + j.laws.length, 0), records.length);

  for (const jurisdiction of bundle.jurisdictions) {
    assert.equal(jurisdiction.tld, jurisdiction.code.toLowerCase());
    assert.equal(jurisdiction.lawCount, jurisdiction.laws.length);
    assert.deepEqual(readJson(jurisdiction.tld, "info.json"), {
      code: jurisdiction.code,
      name: jurisdiction.name,
      tld: jurisdiction.tld,
      region: jurisdiction.region,
      lawCount: jurisdiction.lawCount,
    });
    const full = readJson(jurisdiction.tld, "laws.json");
    assert.equal(full.length, jurisdiction.laws.length);
    full.forEach((law, index) => {
      assert.equal(law.country_code, jurisdiction.code);
      assert.equal(law.law_name, jurisdiction.laws[index].law_name);
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
