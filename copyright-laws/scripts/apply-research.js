#!/usr/bin/env node
// Writes the findings of a research file (code,field,value,conf,source) into the master CSV and field_sources.csv:
// a jurisdiction with no law yet becomes a new row, one that has a law gets its cells patched, each with its source.
//
//   npm run apply-research -- findings.csv --batch=batch.txt     batch.txt: lines `CODE | name | region`
//   npm run apply-research -- findings.csv --min=medium --dry    ignore weak facts; only report
//
// Afterwards: npm run build && npm run changes
import { parse } from "csv-parse/sync";
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { createApplyResearch } from "../../leyes-comunes/apply-research-core.js";

// The engine is shared with the other laws module; see ../../leyes-comunes/apply-research-core.js.
export const { applyResearch, main } = createApplyResearch({ dataset, parse });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
