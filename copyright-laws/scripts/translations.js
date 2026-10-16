#!/usr/bin/env node
// Maintains jurisdictions/value_translations.csv (machine translations of the descriptive values).
//
//   npm run translations                                  coverage per language
//   npm run translations -- --missing=spa > todo.jsonl    values without a Spanish translation, as {"id","text"} lines
//   npm run translations -- --import=spa --todo=todo.jsonl --done=done.jsonl
//                                                         merge {"id","t"} lines (same ids as todo.jsonl) into the CSV
// The key of a translation is the exact English text: when a value is edited its translation stops being used
// (validate warns about the leftovers) and the value shows up again under --missing.
import { readFileSync } from "node:fs";
import { pathToFileURL } from "node:url";
import {
  MASTER_CSV,
  TRANSLATIONS_FILE,
  TRANSLATION_COLUMNS,
  TRANSLATION_LANGS,
  loadMaster,
  loadSide,
  translatableValues,
  writeCsv,
} from "./lib/dataset.js";

const lines = (path) => readFileSync(path, "utf-8").split("\n").filter((l) => l.trim()).map((l) => JSON.parse(l));

export function coverage(records, rows) {
  const live = translatableValues(records);
  return Object.fromEntries(
    TRANSLATION_LANGS.map((lang) => {
      const have = new Set(rows.filter((r) => r.lang === lang).map((r) => r.source));
      return [lang, { translated: [...live].filter((v) => have.has(v)).length, total: live.size }];
    })
  );
}

export function missing(records, rows, lang) {
  const have = new Set(rows.filter((r) => r.lang === lang).map((r) => r.source));
  return [...translatableValues(records)].filter((v) => !have.has(v)).sort();
}

export function main(argv = process.argv.slice(2)) {
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
    const rows = [...side.rows];
    let added = 0;
    for (const done of lines(arg("done"))) {
      const source = todo.get(done.id);
      if (source && done.t?.trim()) {
        rows.push({ lang: importLang, source, text: done.t.trim() });
        added++;
      }
    }
    rows.sort((a, b) => a.lang.localeCompare(b.lang) || a.source.localeCompare(b.source));
    writeCsv(side.path, TRANSLATION_COLUMNS, rows);
    console.log(`✔ ${added} translation(s) into ${importLang} (${todo.size - added} of the todo list left)`);
    return 0;
  }
  const cov = coverage(records, side.rows);
  for (const [lang, c] of Object.entries(cov)) console.log(`${lang}: ${c.translated} of ${c.total} values (${Math.round((100 * c.translated) / c.total)}%)`);
  return 0;
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
