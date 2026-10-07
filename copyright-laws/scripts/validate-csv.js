// node scripts/validate-csv.js — strict validation of jurisdictions/copyright_laws_master.csv
import { loadAndValidate } from "./lib/dataset.js";

let result;
try {
  result = loadAndValidate();
} catch (error) {
  console.error(`✖ ${error.message}`);
  process.exit(1);
}

result.warnings.forEach((w) => console.warn(`⚠ ${w}`));
if (result.errors.length) {
  console.error(`✖ ${result.errors.length} problem(s):`);
  result.errors.forEach((e) => console.error(`  - ${e}`));
  process.exit(1);
}
console.log(`✔ ${result.records.length} laws valid`);
