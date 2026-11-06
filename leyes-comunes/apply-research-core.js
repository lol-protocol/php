// Shared engine behind privacy-laws/scripts/apply-research.js and copyright-laws/scripts/apply-research.js.
// Turns a research file, one fact per row (`code,field,value,conf,source`), into edits of the master CSV and of
// field_sources.csv, so that what was found by searching is not copied by hand and every value keeps its source:
//   - a country that has no law in the master yet becomes a new row (needs every required column; the name and region
//     come from a batch file with lines `CODE | name | region`, the same file the research was done from);
//   - a country that already has a law gets its empty or different cells patched (the law is the one named by a
//     `law_name` row, else the country's only law).
// Low-confidence values get the "(unconfirmed)" mark and the source's confidence says the same; the row's own
// confidence is the weakest of its facts. A row `CODE,status,NOLAW,conf,url` only reports that no law was found.
// Nothing is written when the result would not pass the project's own validation.
import { readFileSync } from "node:fs";
import { isHttpUrl } from "./dataset-core.js";

const RANK = { high: 3, medium: 2, low: 1 };
const clean = (value) => String(value ?? "").replace(/\s+/g, " ").trim();
const isUnknown = (value) => !value || /^unknown\b/i.test(value);
const mark = (value, confidence) => (confidence === "low" ? `${value} (unconfirmed)` : value);
const unmark = (value) => value.replace(/\s*\(unconfirmed\)\s*$/i, "");

/** Parses `code,field,value,conf,source` (quoted values allowed) with `parse`, the csv-parse of the calling project. */
export function readResearch(path, parse) {
  return parse(readFileSync(path, "utf-8"), { columns: true, relax_column_count: true, skip_empty_lines: true, trim: true });
}

/** `CODE | name | region` lines, as an object keyed by code. */
export function readBatch(path) {
  const out = {};
  for (const line of readFileSync(path, "utf-8").split("\n")) {
    const [code, name, region] = line.split("|").map((x) => x.trim());
    if (code && name) out[code] = { name, region };
  }
  return out;
}

/** Groups the facts of a research file by country: { facts: Map(field -> {value, conf, source}), nolaw }. */
export function groupFacts(rows, { min = "low" } = {}) {
  const places = new Map();
  for (const r of rows) {
    const place = places.get(r.code) ?? { code: r.code, facts: new Map(), nolaw: null };
    places.set(r.code, place);
    const conf = clean(r.conf).toLowerCase();
    const value = clean(r.value);
    if (r.field === "status") {
      if (/^NOLAW/i.test(value)) place.nolaw = clean(value.replace(/^NOLAW:?\s*/i, ""));
      continue;
    }
    if (isUnknown(value) || (RANK[conf] ?? 0) < RANK[min]) continue;
    const before = place.facts.get(r.field);
    if (!before || RANK[conf] > RANK[before.conf]) place.facts.set(r.field, { value: unmark(value), conf, source: clean(r.source) });
  }
  return places;
}

/** applyResearch() bound to one project's dataset. */
export function createApplyResearch({ dataset, parse }) {
  const { COLUMNS, MASTER_CSV, REQUIRED_FIELDS, SOURCE_COLUMNS, SOURCES_FILE, loadMaster, loadSide, todayIso, validateRecords, writeCsv } = dataset;

  function applyResearch({ research, batch = {}, min = "low", today = todayIso(), write = true }) {
    const { records } = loadMaster(MASTER_CSV);
    const side = loadSide(MASTER_CSV, SOURCES_FILE, SOURCE_COLUMNS);
    const report = { added: [], patched: [], skipped: [], nolaw: [] };

    const upsertSource = (law, field, fact) => {
      if (!isHttpUrl(fact.source)) return;
      const named = records.filter((r) => r.country_code === law.country_code).length > 1;
      const index = side.rows.findIndex((s) => s.country_code === law.country_code && s.field === field && (!named || s.law_name === law.law_name));
      const entry = { country_code: law.country_code, law_name: index >= 0 ? side.rows[index].law_name : named ? law.law_name : "", field, url: fact.source, confidence: fact.conf, checked: today };
      if (index >= 0) side.rows[index] = entry;
      else side.rows.push(entry);
    };

    for (const place of groupFacts(research, { min }).values()) {
      const laws = records.filter((r) => r.country_code === place.code);
      if (place.nolaw !== null && !place.facts.size) {
        report.nolaw.push({ code: place.code, reason: place.nolaw });
        continue;
      }
      if (laws.length) {
        const wanted = place.facts.get("law_name")?.value;
        const law = wanted ? laws.find((l) => l.law_name === wanted) : laws.length === 1 ? laws[0] : null;
        if (!law) {
          report.skipped.push({ code: place.code, why: wanted ? `no law named "${wanted}"` : "several laws and no law_name row" });
          continue;
        }
        const changed = [];
        for (const [field, fact] of place.facts) {
          if (field === "law_name" || !COLUMNS.includes(field) || field === "country_code") continue;
          const text = mark(fact.value, fact.conf);
          if (law[field] === text) continue;
          law[field] = text;
          law.last_checked = today;
          upsertSource(law, field, fact);
          changed.push(field);
        }
        if (changed.length) report.patched.push({ code: place.code, law: law.law_name, fields: changed });
        continue;
      }
      // A new country: every required column must come from the research or the batch file.
      const row = Object.fromEntries(COLUMNS.map((c) => [c, ""]));
      const named = batch[place.code];
      row.country_code = place.code;
      row.country_name = named?.name ?? "";
      row.region = named?.region ?? "";
      for (const [field, fact] of place.facts) if (COLUMNS.includes(field) && !["country_code", "country_name", "region"].includes(field)) row[field] = mark(fact.value, fact.conf);
      const confs = [...place.facts.values()].map((f) => f.conf);
      row.data_status = "researched";
      row.confidence = confs.sort((a, b) => RANK[a] - RANK[b])[0] ?? "low";
      row.last_checked = today;
      // The project's own validation says what a row still lacks (required columns, formats): a row that
      // would not pass is left out and reported, the others go ahead.
      const line = `line ${records.length + 2}: `;
      const problems = validateRecords([...records, row], COLUMNS, { today }).errors.filter((e) => e.startsWith(line)).map((e) => e.slice(line.length));
      const missing = REQUIRED_FIELDS.filter((f) => !row[f]);
      if (missing.length || problems.length) {
        report.skipped.push({ code: place.code, why: missing.length ? `missing ${missing.join(", ")}` : problems.join("; ") });
        continue;
      }
      records.push(row);
      for (const [field, fact] of place.facts) if (COLUMNS.includes(field) && field !== "country_name") upsertSource(row, field, fact);
      report.added.push({ code: place.code, law: row.law_name, confidence: row.confidence });
    }

    const { errors } = validateRecords(records, COLUMNS, { today });
    if (errors.length) throw new Error(`the result would not validate, nothing was written:\n  ${errors.slice(0, 8).join("\n  ")}`);
    if (write && (report.added.length || report.patched.length)) {
      writeCsv(MASTER_CSV, COLUMNS, records);
      writeCsv(side.path, SOURCE_COLUMNS, side.rows);
    }
    return report;
  }

  function main(argv = process.argv.slice(2)) {
    const file = argv.find((a) => !a.startsWith("--"));
    const option = (name) => argv.find((a) => a.startsWith(`--${name}=`))?.slice(name.length + 3);
    if (!file || argv.includes("--help")) {
      console.log("Usage: npm run apply-research -- <research.csv> [--batch=<CODE | name | region file>] [--min=low|medium|high] [--dry]");
      console.log("The csv has the header code,field,value,conf,source. Afterwards: npm run build && npm run changes");
      return file ? 0 : 2;
    }
    const report = applyResearch({ research: readResearch(file, parse), batch: option("batch") ? readBatch(option("batch")) : {}, min: option("min") ?? "low", write: !argv.includes("--dry") });
    console.log(`${argv.includes("--dry") ? "Would add" : "Added"} ${report.added.length} new law(s), patched ${report.patched.length}, ${report.nolaw.length} without a law, ${report.skipped.length} skipped`);
    for (const a of report.added) console.log(`  + ${a.code} ${a.law} [${a.confidence}]`);
    for (const p of report.patched) console.log(`  ~ ${p.code} ${p.law}: ${p.fields.join(", ")}`);
    for (const n of report.nolaw) console.log(`  - ${n.code} no law found: ${n.reason}`);
    for (const s of report.skipped) console.log(`  ! ${s.code} skipped: ${s.why}`);
    if (!argv.includes("--dry") && (report.added.length || report.patched.length)) console.log("Now run: npm run build && npm run changes");
    return 0;
  }
  return { applyResearch, main };
}
