#!/usr/bin/env node
// Checks that every reference URL in the master CSV still answers.
//
//   npm run check:links                       all URLs (4 at a time, 15 s timeout)
//   npm run check:links -- --only=us,br       only these jurisdictions
//   npm run check:links -- --json=report.json also write a machine-readable report
//   npm run check:links -- --no-sources       only each law's reference page, not the per-value sources
//
// The checking, classification and reporting logic is shared with privacy-laws/scripts/check-links.js;
// see ../../leyes-comunes/check-links-core.js for what it does and why it lives there. This file supplies
// only what is specific to copyright-laws: the URL column, the unit label and the User-Agent.
import { pathToFileURL } from "node:url";
import * as dataset from "./lib/dataset.js";
import { classify, mapLimit, createCheckLinks } from "../../leyes-comunes/check-links-core.js";

export { classify, mapLimit };

export const { checkUrl, collectUrls, main } = createCheckLinks({
  urlField: "linked_resources",
  unit: "jurisdiction",
  userAgent: "copyright-laws-link-check/1.0 (+https://github.com/lol-protocol/php)",
  dataset,
});

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().then(
    (code) => process.exit(code),
    (error) => {
      console.error(`Error: ${error.message}`);
      process.exit(2);
    }
  );
}
