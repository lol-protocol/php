#!/usr/bin/env node
// Appends what changed in the master CSV to countries/changes.csv (the per-row history shown on the country card).
//
//   npm run changes                         compare with the last commit (HEAD)
//   npm run changes -- --since=origin/master compare with another git revision
//   npm run changes -- --date=2026-10-15    date the entries (default: today)
//   npm run changes -- --check --since=origin/master   record nothing; fail when the CSV differs from the
//                                                      revision in ways changes.csv does not mention (CI)
//
// Run it after editing the master CSV and before committing. A renamed law keeps its history:
// older entries (and its field sources) are moved to the new name.
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { followRenames, unrecorded, createRecordChanges } from "../../leyes-comunes/record-changes-core.js";

// The comparison and the writing of changes.csv are shared with the other laws module; see
// ../../leyes-comunes/record-changes-core.js. This file supplies only this project's dataset.
export { followRenames, unrecorded };
export const { recordChanges, main } = createRecordChanges({ dataset });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
