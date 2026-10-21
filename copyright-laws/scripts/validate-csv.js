// node scripts/validate-csv.js — strict validation of jurisdictions/copyright_laws_master.csv
//   --fail-on-warnings   also exit 1 when there are warnings (rows due for review, pending changes
//                        whose date has passed, sources older than the value they back): the weekly job
import { appendFileSync } from "node:fs";
import { loadAndValidate } from "./lib/dataset.js";

const failOnWarnings = process.argv.includes("--fail-on-warnings");

let result;
try {
  result = loadAndValidate();
} catch (error) {
  console.error(`✖ ${error.message}`);
  process.exit(1);
}

result.warnings.forEach((w) => console.warn(`⚠ ${w}`));
if (process.env.GITHUB_STEP_SUMMARY && (result.warnings.length || result.errors.length)) {
  const lines = ["### copyright-laws data", "", `${result.errors.length} errors, ${result.warnings.length} warnings.`, ""];
  for (const line of [...result.errors, ...result.warnings].slice(0, 100)) lines.push(`- ${line}`);
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${lines.join("\n")}\n`);
}
if (result.errors.length) {
  console.error(`✖ ${result.errors.length} problem(s):`);
  result.errors.forEach((e) => console.error(`  - ${e}`));
  process.exit(1);
}
console.log(`✔ ${result.records.length} laws valid, ${result.sources.length} field sources, ${result.changes.length} recorded changes`);
if (failOnWarnings && result.warnings.length) {
  console.error(`✖ ${result.warnings.length} warning(s) to review`);
  process.exit(1);
}
