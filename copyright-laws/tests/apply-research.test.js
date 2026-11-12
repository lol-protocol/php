// apply-research: findings of a research file become rows and cells, each with its source, or nothing at all.
import { test } from "node:test";
import assert from "node:assert/strict";
import { copyFileSync, mkdtempSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, join } from "node:path";
import { parse } from "csv-parse/sync";
import * as real from "../scripts/lib/dataset.js";
import { createApplyResearch, groupFacts } from "../../leyes-comunes/apply-research-core.js";

/** A copy of the data in a temporary folder, so the test never touches the real CSVs. */
function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), "apply-research-"));
  const master = join(dir, basename(real.MASTER_CSV));
  copyFileSync(real.MASTER_CSV, master);
  copyFileSync(join(real.DATA_DIR, real.SOURCES_FILE), join(dir, real.SOURCES_FILE));
  const dataset = { ...real, MASTER_CSV: master };
  return { dataset, master, run: createApplyResearch({ dataset, parse }) };
}
// The real data may hold rows checked today, so the tests date their own edits with today too.
const TODAY = real.todayIso();
const facts = (code, rows) => rows.map(([field, value, conf, source = "https://example.org/src"]) => ({ code, field, value, conf, source }));

const NEW = [
  ["law_name", "Copyright Act 2001", "medium"],
  ["protection_type", "Copyright", "medium"],
  ["term_of_protection", "Author's life + 50 years", "medium"],
  ["enforcement_body", "Copyright Office (unknown acronym)", "low"],
  ["author_rights", "Economic rights", "low"],
  ["registration_required", "No", "medium"],
  ["linked_resources", "https://example.org/law", "medium"],
];

test("a country with no law becomes a researched row with a source per fact and the weakest confidence", () => {
  const { dataset, master, run } = sandbox();
  const report = run.applyResearch({ research: facts("ZZ", NEW), batch: { ZZ: { name: "Zedland", region: "europe" } }, today: TODAY });
  assert.equal(report.added.length, 1);
  const { records } = real.loadMaster(master);
  const row = records.find((r) => r.country_code === "ZZ");
  assert.equal(row.country_name, "Zedland");
  assert.equal(row.data_status, "researched");
  assert.equal(row.confidence, "low");
  assert.equal(row.last_checked, TODAY);
  assert.equal(row.term_of_protection, "Author's life + 50 years");
  // The mark goes on descriptive values only; a name keeps its plain text and its source says "low".
  assert.equal(row.author_rights, "Economic rights (unconfirmed)");
  assert.equal(row.enforcement_body, "Copyright Office (unknown acronym)");
  const sources = real.loadSide(master, dataset.SOURCES_FILE, dataset.SOURCE_COLUMNS).rows.filter((s) => s.country_code === "ZZ");
  assert.equal(sources.length, NEW.length);
  assert.equal(sources.find((s) => s.field === "enforcement_body").confidence, "low");
  assert.deepEqual(real.validateSources(real.loadSide(master, dataset.SOURCES_FILE, dataset.SOURCE_COLUMNS).rows, records), []);
});

test("a new country without a required column is skipped, and NOLAW rows are only reported", () => {
  const { master, run } = sandbox();
  const before = real.loadMaster(master).records.length;
  const report = run.applyResearch({
    research: [...facts("ZZ", NEW.slice(0, 3)), { code: "QQ", field: "status", value: "NOLAW: no copyright act found", conf: "low", source: "" }],
    batch: { ZZ: { name: "Zedland", region: "europe" } },
  });
  assert.match(report.skipped[0].why, /enforcement_body/);
  // Complete as to required columns but not as to format: the project's validation says why.
  const odd = run.applyResearch({ research: facts("ZY", [...NEW.filter(([f]) => f !== "registration_required"), ["registration_required", "maybe", "medium"]]), batch: { ZY: { name: "Zyland", region: "europe" } } });
  assert.match(odd.skipped[0].why, /registration_required must be/);
  assert.deepEqual(report.nolaw, [{ code: "QQ", reason: "no copyright act found" }]);
  assert.equal(real.loadMaster(master).records.length, before);
});

test("an existing law gets only the cells that differ, with the unconfirmed mark for weak facts", () => {
  const { master, run } = sandbox();
  const law = real.loadMaster(master).records.find((r) => r.orphan_works);
  const same = [["orphan_works", law.orphan_works.replace(/\s*\(unconfirmed\)$/, ""), "high"]];
  const confirmed = run.applyResearch({ research: facts(law.country_code, same), today: TODAY });
  const changed = run.applyResearch({ research: facts(law.country_code, [["moral_rights", "Attribution and integrity (art. 9)", "low"]]), today: TODAY });
  assert.ok(confirmed.patched.length <= 1);
  assert.deepEqual(changed.patched[0].fields, ["moral_rights"]);
  assert.equal(real.loadMaster(master).records.find((r) => r.country_code === law.country_code && r.law_name === law.law_name).moral_rights, "Attribution and integrity (art. 9) (unconfirmed)");
});

test("facts below --min and UNKNOWN values are ignored; the strongest fact for a field wins", () => {
  const grouped = groupFacts(
    [
      { code: "ZZ", field: "orphan_works", value: "UNKNOWN", conf: "low", source: "" },
      { code: "ZZ", field: "moral_rights", value: "weak", conf: "low", source: "https://a.example/x" },
      { code: "ZZ", field: "moral_rights", value: "strong", conf: "high", source: "https://b.example/y" },
      { code: "ZZ", field: "digital_protection", value: "weak only", conf: "low", source: "" },
    ],
    { min: "medium" },
  );
  assert.deepEqual([...grouped.get("ZZ").facts.keys()], ["moral_rights"]);
  assert.equal(grouped.get("ZZ").facts.get("moral_rights").value, "strong");
});

test("a bad new row is skipped, and a patch that would break validation writes nothing", () => {
  const { master, run } = sandbox();
  const before = real.loadMaster(master).records.length;
  const skipped = run.applyResearch({ research: facts("ZZ", NEW), batch: { ZZ: { name: "Zedland", region: "nowhere" } } });
  assert.match(skipped.skipped[0].why, /region must be one of/);
  const law = real.loadMaster(master).records[0];
  assert.throws(
    () => run.applyResearch({ research: facts(law.country_code, [["protection_type", "Not a type", "high"]]) }),
    /would not validate, nothing was written/,
  );
  assert.equal(real.loadMaster(master).records.length, before);
  assert.equal(real.loadMaster(master).records[0].protection_type, law.protection_type);
});
