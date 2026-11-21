#!/usr/bin/env node
// The repeatable part of checking the values marked "(unconfirmed)" against the text of their sources:
//
//   npm run verify -- plan --out=<dir> [--only=us,ca] [--skip=us] [--limit=25] [--texts=<folder>]
//       tasks/<code>.json per country, PROMPT.md for whoever reads the texts, and the `solo` list for the workflow
//       "Leyes - textos de las fuentes" (laws-textos-oficiales.yml, with `campos`)
//   npm run verify -- apply --from=<dir> [--min=medium|high] [--dry]
//       applies every <dir>/out/<code>.csv (code,field,value,conf,source), one law at a time
//
// The full procedure is in CONTRIBUTING.md ("Check unconfirmed values against their sources").
import { parse } from "csv-parse/sync";
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { createVerify } from "../../leyes-comunes/verify-core.js";

// The engine is shared with the other laws module; see ../../leyes-comunes/verify-core.js.
export const { plan, apply, main } = createVerify({ dataset, parse });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
