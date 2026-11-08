#!/usr/bin/env node
// Maintains jurisdictions/value_translations.csv (machine translations of the descriptive values).
//
//   npm run translations                                  coverage per language
//   npm run translations -- --missing=spa > todo.jsonl    values without a Spanish translation, as {"id","text"} lines
//   npm run translations -- --import=spa --todo=todo.jsonl --done=done.jsonl
//                                                         merge {"id","t"} lines (same ids as todo.jsonl) into the CSV
// The key of a translation is the exact English text: when a value is edited its translation stops being used
// (validate warns about the leftovers) and the value shows up again under --missing.
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { createTranslations, importedRows } from "../../leyes-comunes/translations-core.js";

// The coverage, the missing list and the import are shared with the other laws module; see
// ../../leyes-comunes/translations-core.js. This file supplies only this project's dataset.
export { importedRows };
export const { coverage, missing, main } = createTranslations({ dataset });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
