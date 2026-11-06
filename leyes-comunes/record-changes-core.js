// Shared engine behind privacy-laws/scripts/record-changes.js and copyright-laws/scripts/record-changes.js:
// compares the master CSV with a git revision and appends what changed to changes.csv (with --check it records
// nothing and fails when the CSV differs in ways changes.csv does not mention). Options and usage are documented in
// each project's script, which is the one that runs. What differs between the two projects, their own
// ./lib/dataset.js (paths and columns of their CSVs), is passed in via createRecordChanges().
import { execFileSync } from "node:child_process";
import { relative } from "node:path";

/** Moves history and sources of renamed laws to the new name. */
export function followRenames(entries, side, nameField = "law_name") {
  const renames = entries.filter((e) => e.field === "law_name");
  for (const rename of renames) {
    for (const row of side) {
      if (row.country_code === rename.country_code && row[nameField] === rename.old_value) row[nameField] = rename.new_value;
    }
  }
  return side;
}

/** Todas las columnas iguales: la misma fila ya esta en changes.csv, asi que volver a anotarla no agrega nada. */
const sameRow = (a, b) =>
  a.date === b.date && a.country_code === b.country_code && a.law_name === b.law_name && a.field === b.field && a.old_value === b.old_value && a.new_value === b.new_value;

const sameChange = (entry, recorded) =>
  recorded.country_code === entry.country_code &&
  recorded.law_name === entry.law_name &&
  recorded.field === entry.field &&
  recorded.new_value === entry.new_value;

/** Differences between the revision and the CSV that no entry of changes.csv describes. */
export function unrecorded(entries, recorded) {
  return entries.filter((entry) => !recorded.some((r) => sameChange(entry, r)));
}

/** recordChanges() and main() bound to one project's dataset. */
export function createRecordChanges({ dataset }) {
  const { CHANGE_COLUMNS, CHANGES_FILE, MASTER_CSV, ROOT, ROW_CHANGE, SOURCE_COLUMNS, SOURCES_FILE, diffRecords, loadMaster, loadSide, parseMaster, todayIso, writeCsv } = dataset;

  function parseArgs(argv) {
    const options = { since: "HEAD", date: todayIso(), check: false };
    for (const arg of argv) {
      let m;
      if (arg === "--check") options.check = true;
      else if ((m = arg.match(/^--since=(.+)$/))) options.since = m[1];
      else if ((m = arg.match(/^--date=(\d{4}-\d{2}-\d{2})$/))) options.date = m[1];
      else throw new Error(`unknown option ${arg}`);
    }
    return options;
  }

  function recordChanges({ since, date, masterPath = MASTER_CSV, gitShow }) {
    const before = parseMaster(gitShow(since, relative(ROOT, masterPath))).records;
    const after = loadMaster(masterPath).records;
    const changes = loadSide(masterPath, CHANGES_FILE, CHANGE_COLUMNS);
    // Correrlo dos veces sin commitear entre medias comparaba con el mismo HEAD y anotaba todo otra vez: solo
    // se agrega lo que changes.csv todavia no tiene (y, si hay filas repetidas de antes, se quedan una).
    const entries = diffRecords(before, after, date).filter((e) => !changes.rows.some((r) => sameRow(e, r)));
    const sources = loadSide(masterPath, SOURCES_FILE, SOURCE_COLUMNS);
    followRenames(entries, changes.rows);
    followRenames(entries, sources.rows);
    const all = [...changes.rows, ...entries].filter((row, i, rows) => rows.findIndex((other) => sameRow(row, other)) === i).sort(
      (a, b) => a.date.localeCompare(b.date) || a.country_code.localeCompare(b.country_code) || a.law_name.localeCompare(b.law_name)
    );
    return { entries, all, changes, sources };
  }

  function main(argv = process.argv.slice(2)) {
    const options = parseArgs(argv);
    const gitShow = (rev, path) => execFileSync("git", ["show", `${rev}:./${path}`], { cwd: ROOT, encoding: "utf-8", maxBuffer: 64 * 1024 * 1024 });
    const { entries, all, changes, sources } = recordChanges({ ...options, gitShow });
    if (options.check) {
      const missing = unrecorded(entries, changes.rows);
      if (!missing.length) {
        console.log(`✔ every change since ${options.since} is in ${CHANGES_FILE} (${entries.length} compared)`);
        return 0;
      }
      console.error(`✖ ${missing.length} change(s) since ${options.since} are not in ${CHANGES_FILE}. Run: npm run changes -- --since=${options.since}`);
      for (const m of missing.slice(0, 15)) console.error(`  - ${m.country_code} ${m.law_name}: ${m.field}`);
      if (missing.length > 15) console.error(`  … and ${missing.length - 15} more`);
      return 1;
    }
    if (!entries.length) {
      console.log(`✔ no changes since ${options.since}`);
      return 0;
    }
    writeCsv(changes.path, CHANGE_COLUMNS, all);
    if (entries.some((e) => e.field === "law_name") && sources.rows.length) writeCsv(sources.path, SOURCE_COLUMNS, sources.rows);
    const rows = new Set(entries.map((e) => `${e.country_code} ${e.law_name}`));
    const added = entries.filter((e) => e.field === ROW_CHANGE).length;
    console.log(`✔ ${entries.length} change(s) in ${rows.size} row(s) since ${options.since} (${added} added/removed) → ${CHANGES_FILE}`);
    console.log("  Run npm run build to publish them.");
    return 0;
  }

  return { recordChanges, main };
}
