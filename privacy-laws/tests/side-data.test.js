// Per-field sources, the change history and the row-level checks that go with them
// (confidence, pending changes, rows due for review).
import { test } from "node:test";
import assert from "node:assert/strict";
import { mkdtempSync, readFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import {
  CHANGE_COLUMNS,
  COLUMNS,
  DATA_DIR,
  ROW_CHANGE,
  SOURCE_COLUMNS,
  diffRecords,
  loadAndValidate,
  loadSide,
  validateChanges,
  validateRecords,
  validateSources,
  withSideData,
  writeCsv,
} from "../scripts/lib/dataset.js";
import { recordChanges } from "../scripts/record-changes.js";

const { records, sources, changes } = loadAndValidate();
const base = records.find((r) => r.data_status === "researched");
const check = (patch, options) => validateRecords([{ ...base, ...patch }], COLUMNS, options);

test("researched rows need a confidence level", () => {
  assert.match(check({ confidence: "" }).errors[0], /confidence is required when data_status is researched/);
  assert.deepEqual(check({ data_status: "curated", confidence: "", last_checked: "" }).errors, []);
  assert.ok(records.filter((r) => r.data_status !== "curated").every((r) => r.confidence), "every researched row has a confidence");
});

test("a pending change needs a description, and a passed date is a warning", () => {
  assert.match(check({ pending_change: "", pending_date: "2027-01-01" }).errors[0], /pending_change is empty/);
  assert.match(check({ pending_change: "x", pending_date: "2027-02-30" }).errors[0], /pending_date must be empty or a real/);
  const passed = check({ pending_change: "New law", pending_date: "2026-01-01" }, { today: "2026-06-01" });
  assert.deepEqual(passed.errors, []);
  assert.match(passed.warnings[0], /pending change was due on 2026-01-01/);
  assert.deepEqual(check({ pending_change: "New law", pending_date: "2027-01-01" }, { today: "2026-06-01" }).warnings, []);
});

test("a row last checked more than a year ago is flagged for review", () => {
  const old = check({ last_checked: "2025-01-01" }, { today: "2026-06-01" });
  assert.deepEqual(old.errors, []);
  assert.match(old.warnings[0], /due for review/);
  assert.deepEqual(check({ last_checked: "2026-01-01" }, { today: "2026-06-01" }).warnings, []);
});

test("field sources must point at a real law, a content column and a public URL", () => {
  const single = records.find((r) => records.filter((x) => x.country_code === r.country_code).length === 1);
  const ok = { country_code: single.country_code, law_name: "", field: "penalties_range", url: "https://example.org/a", confidence: "medium", checked: "2026-10-15" };
  assert.deepEqual(validateSources([ok], records), []);
  assert.match(validateSources([{ ...ok, field: "confidence" }], records)[0], /content column/);
  assert.match(validateSources([{ ...ok, url: "http://127.0.0.1/x" }], records)[0], /public http/);
  assert.match(validateSources([{ ...ok, checked: "yesterday" }], records)[0], /checked must be a real/);
  assert.match(validateSources([{ ...ok, country_code: "ZZ" }], records)[0], /no law ZZ/);
  assert.match(validateSources([ok, ok], records)[0], /second source/);
  // The United States has two laws: an entry must say which one.
  assert.match(validateSources([{ ...ok, country_code: "US" }], records)[0], /law_name is required/);
  assert.match(validateSources([], records, ["a", "b"])[0], /header must be exactly/);
});

test("the change history only accepts dated entries about known columns", () => {
  const ok = { date: "2026-10-15", country_code: "FR", law_name: "x", field: "penalties_range", old_value: "a", new_value: "b" };
  assert.deepEqual(validateChanges([ok, { ...ok, field: ROW_CHANGE }]), []);
  assert.match(validateChanges([{ ...ok, field: "nope" }])[0], /field must be a column/);
  assert.match(validateChanges([{ ...ok, date: "2026-13-01" }])[0], /date must be a real/);
});

test("the real side files are valid and attach to the laws", () => {
  assert.ok(sources.length > 100, `${sources.length} field sources`);
  assert.ok(changes.length > 100, `${changes.length} recorded changes`);
  const laws = withSideData(records, sources, changes);
  assert.equal(laws.reduce((n, law) => n + Object.keys(law.sources).length, 0), sources.length, "every source belongs to one law");
  const cl = laws.find((law) => law.country_code === "CL");
  assert.ok(cl.changes.some((c) => c.field === "law_name" && /19\.628/.test(c.from)), "Chile's history shows the replaced law");
  assert.ok(cl.changes.every((c, i, all) => i === 0 || all[i - 1].date >= c.date), "newest first");
  const published = JSON.parse(readFileSync(join(DATA_DIR, "cl", "laws.json"), "utf-8"))[0];
  assert.deepEqual(Object.keys(published.sources).sort(), Object.keys(cl.sources).sort());
  assert.equal(published.changes.length, cl.changes.length);
});

test("diffRecords reports changed fields, renames, additions and removals", () => {
  const a = { ...base, country_code: "AA", law_name: "Old Act", penalties_range: "1", confidence: "low" };
  const b = { ...base, country_code: "BB", law_name: "Gone Act" };
  const after = [
    { ...a, law_name: "New Act", penalties_range: "2", confidence: "high" }, // renamed + changed; confidence is bookkeeping
    { ...base, country_code: "CC", law_name: "Fresh Act" },
  ];
  const entries = diffRecords([a, b], after, "2026-10-15");
  const fields = entries.map((e) => `${e.country_code}:${e.field}:${e.new_value}`);
  assert.deepEqual(fields.sort(), ["AA:law_name:New Act", "AA:penalties_range:2", "BB:row:removed", "CC:row:added"].sort());
  assert.ok(entries.every((e) => e.date === "2026-10-15"));
  // A column that one side lacks (added later) is not a change.
  const { notes, ...withoutNotes } = a;
  assert.deepEqual(diffRecords([withoutNotes], [{ ...a, notes: "new" }], "2026-10-15"), []);
});

test("record-changes appends the diff and moves history and sources to a renamed law", () => {
  const dir = mkdtempSync(join(tmpdir(), "changes-"));
  const master = join(dir, "master.csv");
  const before = [{ ...base, country_code: "AA", law_name: "Old Act", penalties_range: "1" }];
  writeCsv(master, COLUMNS, before);
  const committed = readFileSync(master, "utf-8");
  writeCsv(master, COLUMNS, [{ ...before[0], law_name: "New Act", penalties_range: "2" }]);
  writeCsv(join(dir, "changes.csv"), CHANGE_COLUMNS, [
    { date: "2026-01-01", country_code: "AA", law_name: "Old Act", field: "notes", old_value: "", new_value: "x" },
  ]);
  writeCsv(join(dir, "field_sources.csv"), SOURCE_COLUMNS, [
    { country_code: "AA", law_name: "Old Act", field: "notes", url: "https://example.org/", confidence: "low", checked: "2026-01-01" },
  ]);
  const { entries, all, sources: side } = recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow: () => committed });
  assert.equal(entries.length, 2);
  assert.deepEqual(all.map((e) => `${e.date} ${e.law_name} ${e.field}`), [
    "2026-01-01 New Act notes",
    "2026-10-15 New Act law_name",
    "2026-10-15 New Act penalties_range",
  ]);
  assert.equal(side.rows[0].law_name, "New Act");
  assert.equal(loadSide(master, "missing.csv", ["a"]).rows.length, 0, "a missing side file is an empty table");
});
