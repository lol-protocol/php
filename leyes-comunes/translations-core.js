// Shared engine behind privacy-laws/scripts/translations.js and copyright-laws/scripts/translations.js: coverage
// of the machine translations of the descriptive values, the list of what is missing for a language and the import
// of finished translations. Options and usage are documented in each project's script, which is the one that
// runs. What differs between the two projects, their own ./lib/dataset.js, is passed in via createTranslations().
import { readFileSync } from "node:fs";

const lines = (path) => readFileSync(path, "utf-8").split("\n").filter((l) => l.trim()).map((l) => JSON.parse(l));

/**
 * New rows for `importLang` out of a --done list, skipping any id whose source already has a translation in
 * `rows` (reimporting a done.jsonl after a partial failure, or two sessions importing the same language, would
 * otherwise add a second row for the same source — caught later by validateTranslations, but only after it's
 * already committed).
 */
export function importedRows(rows, todo, done, importLang) {
  const have = new Set(rows.filter((r) => r.lang === importLang).map((r) => r.source));
  const added = [];
  let skipped = 0;
  for (const entry of done) {
    const source = todo.get(entry.id);
    if (!source || !entry.t?.trim()) continue;
    if (have.has(source)) {
      skipped++;
      continue;
    }
    added.push({ lang: importLang, source, text: entry.t.trim() });
    have.add(source);
  }
  return { added, skipped };
}

/** coverage(), missing() and main() bound to one project's dataset. */
export function createTranslations({ dataset }) {
  const { MASTER_CSV, TRANSLATIONS_FILE, TRANSLATION_COLUMNS, TRANSLATION_LANGS, loadMaster, loadSide, translatableValues, writeCsv } = dataset;

  function coverage(records, rows) {
    const live = translatableValues(records);
    return Object.fromEntries(
      TRANSLATION_LANGS.map((lang) => {
        const have = new Set(rows.filter((r) => r.lang === lang).map((r) => r.source));
        return [lang, { translated: [...live].filter((v) => have.has(v)).length, total: live.size }];
      })
    );
  }

  function missing(records, rows, lang) {
    const have = new Set(rows.filter((r) => r.lang === lang).map((r) => r.source));
    return [...translatableValues(records)].filter((v) => !have.has(v)).sort();
  }

  function main(argv = process.argv.slice(2)) {
    const arg = (name) => argv.find((a) => a.startsWith(`--${name}=`))?.slice(name.length + 3);
    const { records } = loadMaster();
    const side = loadSide(MASTER_CSV, TRANSLATIONS_FILE, TRANSLATION_COLUMNS);
    const wanted = arg("missing");
    const importLang = arg("import");
    for (const lang of [wanted, importLang].filter(Boolean)) {
      if (!TRANSLATION_LANGS.includes(lang)) throw new Error(`language must be one of ${TRANSLATION_LANGS.join(", ")}`);
    }
    if (wanted) {
      for (const [i, text] of missing(records, side.rows, wanted).entries()) console.log(JSON.stringify({ id: i + 1, text }));
      return 0;
    }
    if (importLang) {
      const todo = new Map(lines(arg("todo")).map((l) => [l.id, l.text]));
      const { added, skipped } = importedRows(side.rows, todo, lines(arg("done")), importLang);
      const rows = [...side.rows, ...added].sort((a, b) => a.lang.localeCompare(b.lang) || a.source.localeCompare(b.source));
      writeCsv(side.path, TRANSLATION_COLUMNS, rows);
      console.log(
        `✔ ${added.length} translation(s) into ${importLang} (${todo.size - added.length} of the todo list left)` +
          (skipped ? `, ${skipped} already translated and skipped` : "")
      );
      return 0;
    }
    const cov = coverage(records, side.rows);
    for (const [lang, c] of Object.entries(cov)) console.log(`${lang}: ${c.translated} of ${c.total} values (${Math.round((100 * c.translated) / c.total)}%)`);
    return 0;
  }

  return { coverage, missing, main };
}
