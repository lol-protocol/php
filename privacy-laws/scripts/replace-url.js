#!/usr/bin/env node
// Replaces one reference URL by another in countries/privacy_laws_master.csv (website_url) and
// countries/field_sources.csv, for the day a source link dies.
//
//   npm run replace-url -- https://old.example/law.pdf https://new.example/law.pdf
//   npm run replace-url -- <old> <new> --dry      only count what would change
//
// Afterwards: npm run build && npm run changes
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { createReplaceUrl } from "../../leyes-comunes/replace-url-core.js";

// The replacement is shared with the other laws module; see ../../leyes-comunes/replace-url-core.js.
export const { replaceUrl, main } = createReplaceUrl({ dataset, urlField: "website_url" });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
