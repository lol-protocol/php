// Shared engine behind privacy-laws/scripts/replace-url.js and copyright-laws/scripts/replace-url.js: swaps one
// reference URL for another everywhere the project stores it, the master CSV (its URL column) and the per-field
// sources (source_urls.csv, where each URL is stored once). A dead link used to mean editing the same URL by hand in several rows of two files.
// What differs between the two projects, their own ./lib/dataset.js and the name of the URL column, is passed in.

import { isHttpUrl, isPrivateHost } from "./dataset-core.js";

/** Replaces `from` by `to` in the URL column of the master and in the url column of the field sources. */
export function replaceUrl({ dataset, urlField, from, to, write = true }) {
  const { COLUMNS, MASTER_CSV, loadMaster, loadSources, writeCsv, writeSources } = dataset;
  if (!from || !to) throw new Error("both the old and the new URL are required");
  if (from === to) throw new Error("the old and the new URL are the same");
  if (!isHttpUrl(to) || isPrivateHost(to)) throw new Error(`the new URL must be a public http(s) URL, got ${JSON.stringify(to)}`);

  const { records } = loadMaster(MASTER_CSV);
  const side = loadSources(MASTER_CSV);

  let master = 0;
  for (const record of records) {
    if ((record[urlField] ?? "").includes(from)) {
      record[urlField] = record[urlField].split(from).join(to);
      master++;
    }
  }
  let sources = 0;
  for (const row of side.rows) {
    if (row.url === from) {
      row.url = to;
      sources++;
    }
  }
  if (write && master) writeCsv(MASTER_CSV, COLUMNS, records);
  // The URL is stored once in source_urls.csv: rewriting the sources rewrites that row (and merges it with `to` if it was there).
  if (write && sources) writeSources(MASTER_CSV, side.rows);
  return { master, sources };
}

/** main() bound to one project: `replace-url <old> <new>`. */
export function createReplaceUrl({ dataset, urlField }) {
  function main(argv = process.argv.slice(2)) {
    const [from, to] = argv.filter((a) => !a.startsWith("--"));
    if (!from || !to || argv.includes("--help")) {
      console.log("Usage: npm run replace-url -- <old URL> <new URL> [--dry]");
      console.log("Swaps the URL in the master CSV and in field_sources.csv; then run `npm run build` and `npm run changes`.");
      return from && to ? 0 : 2;
    }
    const dry = argv.includes("--dry");
    const { master, sources } = replaceUrl({ dataset, urlField, from, to, write: !dry });
    console.log(`${dry ? "Would replace" : "Replaced"} ${master} row(s) of the master CSV (${urlField}) and ${sources} field source(s).`);
    if (!master && !sources) {
      console.error("That URL does not appear in the data.");
      return 1;
    }
    if (!dry) console.log("Now run: npm run build && npm run changes");
    return 0;
  }
  return { replaceUrl: (options) => replaceUrl({ dataset, urlField, ...options }), main };
}
