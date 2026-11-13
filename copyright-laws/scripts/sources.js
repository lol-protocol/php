#!/usr/bin/env node
// Edits the source files (jurisdictions/source_urls.csv, each reference URL once with an id that is the hash of the URL,
// and jurisdictions/field_sources.csv, which points at it) without computing a hash by hand.
//
//   npm run sources -- add <CODE> <field> <url> [--confidence=high|medium|low] [--law="law name"] [--checked=YYYY-MM-DD]
//   npm run sources -- fix       ids recomputed after a URL was edited by hand, URLs sorted, unused ones dropped
//
// Afterwards: npm run build && npm run changes
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { createSources } from "../../leyes-comunes/sources-core.js";

// The engine is shared with the other laws module; see ../../leyes-comunes/sources-core.js.
export const { addSource, fixSources, main } = createSources({ dataset });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
