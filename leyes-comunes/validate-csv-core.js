// Shared engine behind privacy-laws/scripts/validate-csv.js and copyright-laws/scripts/validate-csv.js: runs the
// project's validation, prints the problems and warnings and appends a summary to GITHUB_STEP_SUMMARY. What differs
// between the two projects, its own ./lib/dataset.js and the title of the summary, is passed in. Returns the exit code.
import { appendFileSync } from "node:fs";

export function validateCsv({ loadAndValidate, title, argv = process.argv }) {
  const failOnWarnings = argv.includes("--fail-on-warnings");

  let result;
  try {
    result = loadAndValidate();
  } catch (error) {
    console.error(`✖ ${error.message}`);
    return 1;
  }

  result.warnings.forEach((w) => console.warn(`⚠ ${w}`));
  if (process.env.GITHUB_STEP_SUMMARY && (result.warnings.length || result.errors.length)) {
    const lines = [`### ${title}`, "", `${result.errors.length} errors, ${result.warnings.length} warnings.`, ""];
    for (const line of [...result.errors, ...result.warnings].slice(0, 100)) lines.push(`- ${line}`);
    appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${lines.join("\n")}\n`);
  }
  if (result.errors.length) {
    console.error(`✖ ${result.errors.length} problem(s):`);
    result.errors.forEach((e) => console.error(`  - ${e}`));
    return 1;
  }
  console.log(`✔ ${result.records.length} laws valid, ${result.sources.length} field sources, ${result.changes.length} recorded changes`);
  if (failOnWarnings && result.warnings.length) {
    console.error(`✖ ${result.warnings.length} warning(s) to review`);
    return 1;
  }
  return 0;
}
