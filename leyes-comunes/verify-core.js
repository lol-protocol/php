// Shared engine behind privacy-laws/scripts/verify.js and copyright-laws/scripts/verify.js: the repeatable part of
// checking the values marked "(unconfirmed)" against the text of their sources.
//
// The whole round, in the order it is done:
//   1. verify plan   lists the countries that still have unconfirmed values, writes one task file per country
//                    (tasks/<code>.json: the law, the field and the current value) and the `solo` list to paste
//                    into the "Leyes - textos de las fuentes" workflow (laws-textos-oficiales.yml), plus PROMPT.md,
//                    the instructions an agent (or a person) follows for each country;
//   2. run that workflow by hand with `campos` on: it publishes the source pages as text on a data branch;
//   3. read each country's pages against its task file and write findings to out/<code>.csv
//                    (`code,field,value,conf,source`, only what a text supports);
//   4. verify apply  applies every out/*.csv: one law at a time (a country with several laws has `law_name` rows that
//                    start each law's block), only confidence medium or high, with the source of each value; a value
//                    a text confirms loses its "(unconfirmed)" mark;
//   5. npm run translations (what is new has to be translated), npm run build, npm run changes, and a PR.
// Nothing here reads a page or judges a value: that is step 3, and it stays with whoever reads the text.
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { createApplyResearch, readResearch } from "./apply-research-core.js";

const MARK = /\(unconfirmed\)\s*$/i;

/** The countries that still have values marked unconfirmed: `{ code, values: [{ law, field, current }] }`, most values first. */
export function planVerification({ dataset, skip = [], only = [], limit = Infinity }) {
  const { COLUMNS, MASTER_CSV, loadMaster } = dataset;
  const { records } = loadMaster(MASTER_CSV);
  const byCountry = new Map();
  for (const record of records) {
    const code = record.country_code.toLowerCase();
    if (skip.includes(code) || (only.length && !only.includes(code))) continue;
    for (const field of COLUMNS) {
      const value = record[field] ?? "";
      if (MARK.test(value)) {
        if (!byCountry.has(code)) byCountry.set(code, []);
        byCountry.get(code).push({ law: record.law_name, field, current: value.replace(MARK, "") });
      }
    }
  }
  return [...byCountry]
    .map(([code, values]) => ({ code, values }))
    .sort((a, b) => b.values.length - a.values.length || a.code.localeCompare(b.code))
    .slice(0, limit);
}

/** Writes tasks/<code>.json and PROMPT.md under `dir`; returns the `solo` list for the workflow. */
export function writePlan(plan, dir, { texts = "<folder with the downloaded texts>", project = "" } = {}) {
  mkdirSync(join(dir, "tasks"), { recursive: true });
  mkdirSync(join(dir, "out"), { recursive: true });
  for (const country of plan) {
    writeFileSync(join(dir, "tasks", `${country.code}.json`), `${JSON.stringify({ country: country.code.toUpperCase(), unconfirmed: country.values }, null, 1)}\n`);
  }
  writeFileSync(join(dir, "PROMPT.md"), promptFor(dir, texts, project));
  return plan.map((c) => c.code).join(",");
}

function promptFor(dir, texts, project) {
  return `# Verify the "unconfirmed" values of ${project || "the dataset"} against their source texts

You check values of a laws dataset against page texts already downloaded to disk. No web access is needed or allowed.

- Task files: ${dir}/tasks/<code>.json (country code, and the list of {law, field, current} values currently marked unconfirmed).
- Downloaded pages: ${texts}/<folder of the module>/<code>/texts/ (lower-case code). index.json maps each .md file to its law ("law", which ends with the dataset field in parentheses when the page backs one field) and its "source" URL. Entries with status "error" or "skipped" have no text.
- Read the .md files with Grep/Read. For EACH value look in the texts of that law: if a page supports the current value or lets you correct it, write the supported value (English, concise, at most 220 characters, with article or section numbers only when the text gives them; translate if the text is not English).

Rules
- Never invent. A value must be supported by text you actually read in those files. If no text covers it, write nothing (no UNKNOWN rows).
- Confidence: high = the text states it plainly; medium = the text implies it or a reliable summary states it; low = weak (low is ignored by \`verify apply\`).
- source = the exact "source" URL of the file you read (from index.json).
- Output one CSV per country to ${dir}/out/<code>.csv, header exactly \`code,field,value,conf,source\` (code upper-case). A country with several laws gets a \`law_name\` row (field law_name, value = the exact law name, conf high) BEFORE the rows of each law. Quote values that contain commas.
- Finish with counts: confirmed / corrected / no evidence.
`;
}

/** Splits the rows of one findings CSV into one block per law: each \`law_name\` row starts a new block. */
export function splitByLaw(rows) {
  const blocks = [];
  let current = null;
  for (const row of rows) {
    if (row.field === "law_name") {
      current = [row];
      blocks.push(current);
    } else {
      if (!current) {
        current = [];
        blocks.push(current);
      }
      current.push(row);
    }
  }
  return blocks.filter((b) => b.length);
}

/** createVerify({ dataset, parse }): plan(), apply() and main() bound to one project. */
export function createVerify({ dataset, parse }) {
  const applier = createApplyResearch({ dataset, parse });

  /** Applies every out/*.csv under `dir`, one law at a time. Returns what was patched and what was skipped. */
  function apply({ dir, min = "medium", write = true, today }) {
    const outDir = join(dir, "out");
    const report = { files: 0, patched: [], skipped: [], empty: [] };
    if (!existsSync(outDir)) return report;
    for (const file of readdirSync(outDir).filter((f) => f.endsWith(".csv")).sort()) {
      report.files++;
      const rows = readResearch(join(outDir, file), parse);
      if (!rows.length) {
        report.empty.push(file);
        continue;
      }
      for (const block of splitByLaw(rows)) {
        const result = applier.applyResearch({ research: block, min, write, ...(today ? { today } : {}) });
        report.patched.push(...result.patched);
        report.skipped.push(...result.skipped);
      }
    }
    return report;
  }

  function main(argv = process.argv.slice(2)) {
    const option = (name) => argv.find((a) => a.startsWith(`--${name}=`))?.slice(name.length + 3);
    const list = (name) => (option(name) ?? "").split(",").map((s) => s.trim().toLowerCase()).filter(Boolean);
    const [command] = argv.filter((a) => !a.startsWith("--"));
    const dir = option("out") ?? option("from");
    if (command === "plan" && dir) {
      const plan = planVerification({ dataset, skip: list("skip"), only: list("only"), limit: Number(option("limit")) || Infinity });
      const solo = writePlan(plan, dir, { texts: option("texts") ?? undefined, project: dataset.MODULE });
      const total = plan.reduce((n, c) => n + c.values.length, 0);
      console.log(`${plan.length} countries, ${total} unconfirmed values → ${dir}/tasks and ${dir}/PROMPT.md`);
      console.log(`Workflow "Leyes - textos de las fuentes", \`solo\` (and tick \`campos\`):\n${solo}`);
      return 0;
    }
    if (command === "apply" && dir) {
      const report = apply({ dir, min: option("min") ?? "medium", write: !argv.includes("--dry") });
      console.log(`${argv.includes("--dry") ? "Would patch" : "Patched"} ${report.patched.length} law(s) from ${report.files} file(s); ${report.empty.length} without findings, ${report.skipped.length} skipped`);
      for (const p of report.patched) console.log(`  ~ ${p.code} ${p.law}: ${p.fields.join(", ")}`);
      for (const s of report.skipped) console.log(`  ! ${s.code} skipped: ${s.why}`);
      if (report.patched.length && !argv.includes("--dry")) console.log("Now run: npm run translations -- --missing=spa (and the other languages), npm run build, npm run changes");
      return 0;
    }
    console.log("Usage: npm run verify -- plan --out=<dir> [--only=us,ca] [--skip=us] [--limit=25] [--texts=<folder with the downloaded texts>]");
    console.log("       npm run verify -- apply --from=<dir> [--min=medium|high] [--dry]");
    return command ? 2 : 0;
  }

  return { plan: (options) => planVerification({ dataset, ...options }), apply, main };
}
