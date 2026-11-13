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
  diffRecords,
  loadAndValidate,
  loadSide,
  todayIso,
  newerSourceWarnings,
  validateChanges,
  validateRecords,
  validateSources,
  withSideData,
  writeCsv,
  writeSources,
} from "../scripts/lib/dataset.js";
import { recordChanges, unrecorded } from "../scripts/record-changes.js";

// "Today" for the row checks: rows researched today must not look like they were checked in the future.
const TODAY = todayIso();
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
  const passed = check({ pending_change: "New law", pending_date: "2026-01-01" }, { today: TODAY });
  assert.deepEqual(passed.errors, []);
  assert.match(passed.warnings[0], /pending change was due on 2026-01-01/);
  assert.deepEqual(check({ pending_change: "New law", pending_date: "2027-01-01" }, { today: TODAY }).warnings, []);
});

test("a row last checked more than a year ago is flagged for review", () => {
  const old = check({ last_checked: "2025-01-01" }, { today: "2026-06-01" });
  assert.deepEqual(old.errors, []);
  assert.match(old.warnings[0], /due for review/);
  assert.deepEqual(check({ last_checked: "2026-01-01" }, { today: "2026-06-01" }).warnings, []);
});

test("field sources must point at a real law, a content column and a public URL", () => {
  const single = records.find((r) => records.filter((x) => x.country_code === r.country_code).length === 1);
  const ok = { country_code: single.country_code, law_name: "", field: "fair_use_exceptions", url: "https://example.org/a", confidence: "medium", checked: "2026-10-15" };
  assert.deepEqual(validateSources([ok], records), []);
  assert.match(validateSources([{ ...ok, field: "confidence" }], records)[0], /content column/);
  assert.match(validateSources([{ ...ok, url: "http://127.0.0.1/x" }], records)[0], /public http/);
  assert.match(validateSources([{ ...ok, checked: "yesterday" }], records)[0], /checked must be a real/);
  assert.match(validateSources([{ ...ok, country_code: "ZZ" }], records)[0], /no law ZZ/);
  assert.match(validateSources([ok, ok], records)[0], /second source/);
  // "(unconfirmed)" in the text and a medium/high confidence in the source cannot both hold.
  const marked = records.map((r) => (r === single ? { ...r, fair_use_exceptions: "Private copy (unconfirmed)" } : r));
  assert.match(validateSources([ok], marked)[0], /ends in "\(unconfirmed\)" but its source says confidence medium/);
  assert.deepEqual(validateSources([{ ...ok, confidence: "low" }], marked), []);
  // A jurisdiction with two laws: an entry must say which one.
  const two = [...records, { ...single, law_name: "Second Act" }];
  assert.match(validateSources([ok], two)[0], /law_name is required/);
  assert.deepEqual(validateSources([{ ...ok, law_name: "Second Act" }], two), []);
  assert.match(validateSources([], records, ["a", "b"])[0], /header must be exactly/);
});

test("the change history only accepts dated entries about known columns", () => {
  const ok = { date: "2026-10-15", country_code: "FR", law_name: "x", field: "fair_use_exceptions", old_value: "a", new_value: "b" };
  assert.deepEqual(validateChanges([ok, { ...ok, field: ROW_CHANGE }]), []);
  assert.match(validateChanges([{ ...ok, field: "nope" }])[0], /field must be a column/);
  assert.match(validateChanges([{ ...ok, date: "2026-13-01" }])[0], /date must be a real/);
});

test("the real side files are valid and attach to the laws", () => {
  assert.ok(sources.length > 100, `${sources.length} field sources`);
  assert.ok(changes.length > 100, `${changes.length} recorded changes`);
  const laws = withSideData(records, sources, changes);
  assert.equal(laws.reduce((n, law) => n + Object.keys(law.sources).length, 0), sources.length, "every source belongs to one law");
  const cl = laws.find((law) => law.country_code === "SA");
  assert.ok(cl.changes.some((c) => c.field === "law_name" && /Saudi Copyright Law/.test(c.from)), "Saudi Arabia's history shows the replaced law");
  assert.ok(cl.changes.every((c, i, all) => i === 0 || all[i - 1].date >= c.date), "newest first");
  const published = JSON.parse(readFileSync(join(DATA_DIR, "sa", "laws.json"), "utf-8"))[0];
  assert.deepEqual(Object.keys(published.sources).sort(), Object.keys(cl.sources).sort());
  assert.equal(published.changes.length, cl.changes.length);
});

test("diffRecords reports changed fields, renames, additions and removals", () => {
  const a = { ...base, country_code: "AA", law_name: "Old Act", fair_use_exceptions: "1", confidence: "low" };
  const b = { ...base, country_code: "BB", law_name: "Gone Act" };
  const after = [
    { ...a, law_name: "New Act", fair_use_exceptions: "2", confidence: "high" }, // renamed + changed; confidence is bookkeeping
    { ...base, country_code: "CC", law_name: "Fresh Act" },
  ];
  const entries = diffRecords([a, b], after, "2026-10-15");
  const fields = entries.map((e) => `${e.country_code}:${e.field}:${e.new_value}`);
  assert.deepEqual(fields.sort(), ["AA:law_name:New Act", "AA:fair_use_exceptions:2", "BB:row:removed", "CC:row:added"].sort());
  assert.ok(entries.every((e) => e.date === "2026-10-15"));
  // A column that one side lacks (added later) is not a change.
  const { notes, ...withoutNotes } = a;
  assert.deepEqual(diffRecords([withoutNotes], [{ ...a, notes: "new" }], "2026-10-15"), []);
});

test("record-changes appends the diff and moves history and sources to a renamed law", () => {
  const dir = mkdtempSync(join(tmpdir(), "changes-"));
  const master = join(dir, "master.csv");
  const before = [{ ...base, country_code: "AA", law_name: "Old Act", fair_use_exceptions: "1" }];
  writeCsv(master, COLUMNS, before);
  const committed = readFileSync(master, "utf-8");
  writeCsv(master, COLUMNS, [{ ...before[0], law_name: "New Act", fair_use_exceptions: "2" }]);
  writeCsv(join(dir, "changes.csv"), CHANGE_COLUMNS, [
    { date: "2026-01-01", country_code: "AA", law_name: "Old Act", field: "notes", old_value: "", new_value: "x" },
  ]);
  writeSources(master, [
    { country_code: "AA", law_name: "Old Act", field: "notes", url: "https://example.org/", confidence: "low", checked: "2026-01-01" },
  ]);
  const { entries, all, sources: side } = recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow: () => committed });
  assert.equal(entries.length, 2);
  assert.deepEqual(all.map((e) => `${e.date} ${e.law_name} ${e.field}`), [
    "2026-01-01 New Act notes",
    "2026-10-15 New Act law_name",
    "2026-10-15 New Act fair_use_exceptions",
  ]);
  assert.equal(side.rows[0].law_name, "New Act");
  assert.equal(loadSide(master, "missing.csv", ["a"]).rows.length, 0, "a missing side file is an empty table");
});

test("--check names the differences that changes.csv does not record", () => {
  const row = { ...base, country_code: "AA", law_name: "Act", fair_use_exceptions: "1" };
  const edited = [{ ...row, fair_use_exceptions: "2" }];
  const entries = diffRecords([row], edited, "2026-10-15");
  assert.equal(entries.length, 1);
  assert.deepEqual(unrecorded(entries, []).map((e) => e.field), ["fair_use_exceptions"]);
  const recorded = [{ date: "2026-10-15", country_code: "AA", law_name: "Act", field: "fair_use_exceptions", old_value: "1", new_value: "2" }];
  assert.deepEqual(unrecorded(entries, recorded), []);
  // Another value of the same field is not the edit that was made.
  assert.equal(unrecorded(entries, [{ ...recorded[0], new_value: "3" }]).length, 1);
});

test("a curated row with no last_checked is a warning, not an error", () => {
  const curated = check({ data_status: "curated", confidence: "", last_checked: "" });
  assert.deepEqual(curated.errors, []);
  assert.match(curated.warnings[0], /curated and has never been checked/);
  assert.deepEqual(check({ data_status: "curated", confidence: "", last_checked: "2026-10-01" }, { today: TODAY }).warnings, []);
});

test("a last_checked in the future is an error (it would hide the row from the review reminder)", () => {
  assert.match(check({ last_checked: "2099-01-01" }).errors[0], /last_checked is in the future/);
  assert.deepEqual(check({ last_checked: "2026-10-17" }, { today: "2026-10-17" }).errors, []);
});

test("a URL with spaces or backslashes is rejected (browsers repair them, a data file should not rely on it)", () => {
  assert.match(check({ linked_resources: "https://example.org/a b.pdf" }).errors.join("\n"), /linked_resources|url/i);
  assert.match(check({ linked_resources: "https://example.org/a\\b.pdf" }).errors.join("\n"), /linked_resources|url/i);
});

test("a source checked after its row's last_checked is flagged", () => {
  const law = { ...records[0], last_checked: "2026-01-01" };
  const sources = [{ country_code: law.country_code, law_name: law.law_name, field: "notes", url: "https://example.org/", confidence: "low", checked: "2026-02-01" }];
  assert.match(newerSourceWarnings(sources, [law])[0], /bring last_checked up to date/);
  assert.deepEqual(newerSourceWarnings([{ ...sources[0], checked: "2026-01-01" }], [law]), []);
  assert.deepEqual(newerSourceWarnings(sources, records), [], "the committed data has no source newer than its row");
});

test("record-changes is idempotent: running it again on the same edit adds nothing", () => {
  const dir = mkdtempSync(join(tmpdir(), "changes-twice-"));
  const master = join(dir, "master.csv");
  const before = [{ ...base, country_code: "AA", law_name: "Act", fair_use_exceptions: "1" }];
  writeCsv(master, COLUMNS, before);
  const committed = readFileSync(master, "utf-8");
  writeCsv(master, COLUMNS, [{ ...before[0], fair_use_exceptions: "2" }]);
  const first = recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow: () => committed });
  assert.equal(first.entries.length, 1);
  writeCsv(first.changes.path, CHANGE_COLUMNS, first.all);
  const second = recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow: () => committed });
  assert.equal(second.entries.length, 0, "the edit is already in changes.csv");
  assert.equal(second.all.length, 1);
  // Rows repeated by an older run collapse to one.
  writeCsv(first.changes.path, CHANGE_COLUMNS, [...first.all, ...first.all]);
  assert.equal(recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow: () => committed }).all.length, 1);
});

test("--check is not fooled by an old entry whose value the field has cycled back to", () => {
  const dir = mkdtempSync(join(tmpdir(), "changes-cycle-"));
  const master = join(dir, "master.csv");
  const row = { ...base, country_code: "AA", law_name: "Act", fair_use_exceptions: "1" };
  writeCsv(master, COLUMNS, [row]);
  const committedMaster = readFileSync(master, "utf-8");
  // Already recorded, both before `since`: the field went 0 -> 2, then back to 1.
  const oldHistory = [
    { date: "2020-01-01", country_code: "AA", law_name: "Act", field: "fair_use_exceptions", old_value: "0", new_value: "2" },
    { date: "2021-01-01", country_code: "AA", law_name: "Act", field: "fair_use_exceptions", old_value: "2", new_value: "1" },
  ];
  writeCsv(join(dir, "changes.csv"), CHANGE_COLUMNS, oldHistory);
  const committedChanges = readFileSync(join(dir, "changes.csv"), "utf-8");
  // Since `since`, the field goes back to "2" — the same value the 2020 row already used — but nobody recorded
  // this new change: changes.csv on disk still only has the two old rows.
  writeCsv(master, COLUMNS, [{ ...row, fair_use_exceptions: "2" }]);
  const gitShow = (rev, path) => (path.endsWith("changes.csv") ? committedChanges : committedMaster);
  const { entries, addedSince } = recordChanges({ since: "HEAD", date: "2026-10-15", masterPath: master, gitShow });
  assert.equal(entries.length, 1);
  assert.deepEqual(addedSince, [], "nothing was added to changes.csv since the base revision");
  assert.equal(unrecorded(entries, addedSince).length, 1, "the new change back to an old value still needs its own entry");
  // What this guards against: matching against the whole history, not just what changes.csv gained since
  // `since`, would let the 2020 row (same country/law/field/new_value) hide this unrelated, unrecorded change.
  assert.equal(unrecorded(entries, oldHistory).length, 0, "the loose match alone wrongly treats it as already recorded");
});
