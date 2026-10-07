// Builds jurisdictions/{tld}/{info.json,laws.json,laws.csv} and jurisdictions/index.json
// from jurisdictions/copyright_laws_master.csv.
//   node scripts/reorganize-by-jurisdiction.js           write generated files
//   node scripts/reorganize-by-jurisdiction.js --check   fail if generated files are stale
import {
  applyWrites,
  findOrphans,
  buildArtifacts,
  loadAndValidate,
  planWrites,
} from "./lib/dataset.js";

const check = process.argv.includes("--check");

let loaded;
try {
  loaded = loadAndValidate();
} catch (error) {
  console.error(`✖ ${error.message}`);
  process.exit(1);
}

if (loaded.errors.length) {
  console.error(`✖ master CSV is invalid (${loaded.errors.length} problem(s)):`);
  loaded.errors.forEach((e) => console.error(`  - ${e}`));
  process.exit(1);
}

const artifacts = buildArtifacts(loaded.records);
const plan = planWrites(artifacts);
const orphans = findOrphans(artifacts);
const stale = plan.filter((p) => p.status !== "same");

if (check) {
  if (stale.length || orphans.length) {
    console.error("✖ generated data is out of date. Run: npm run build");
    stale.slice(0, 20).forEach((p) => console.error(`  - ${p.status}: ${p.rel}`));
    if (stale.length > 20) console.error(`  … and ${stale.length - 20} more`);
    orphans.forEach((o) => console.error(`  - orphan folder not in master CSV: ${o}/`));
    process.exit(1);
  }
  console.log(`✔ generated data is up to date (${artifacts.jurisdictions.length} jurisdictions, ${loaded.records.length} laws)`);
  process.exit(0);
}

applyWrites(plan);
const count = (status) => plan.filter((p) => p.status === status).length;
console.log(
  `✔ ${loaded.records.length} laws in ${artifacts.jurisdictions.length} jurisdictions → ` +
    `${count("new")} new, ${count("changed")} changed, ${count("same")} unchanged files`
);
orphans.forEach((o) =>
  console.warn(`⚠ folder ${o}/ is not in the master CSV any more (left untouched; delete it if the jurisdiction was removed)`)
);
