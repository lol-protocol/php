// Shared engine behind privacy-laws/scripts/sources.js and copyright-laws/scripts/sources.js: the two source files
// (source_urls.csv, each URL once with an id that is the hash of the URL, and field_sources.csv, which points at it)
// without computing a hash by hand.
//
//   add <CODE> <field> <url> [--confidence=high|medium|low] [--law="law name"] [--checked=YYYY-MM-DD]
//       sets the source of one value (replaces the one it had; --law is needed when the country has several laws)
//   fix  rewrites both files in their canonical form: ids recomputed from the URLs (so a URL edited by hand in
//        source_urls.csv keeps its sources), URLs sorted, URLs nobody uses dropped
import { isHttpUrl, isPrivateHost } from "./dataset-core.js";

/** The `add` command: returns the stored source. Throws on anything the validation would reject. */
export function addSource({ dataset, code, field, url, confidence = "medium", law = "", checked, write = true }) {
  const { COLUMNS, CONFIDENCE_LEVELS, COUNTRY_FIELDS, META_FIELDS, MASTER_CSV, findLaw, loadMaster, loadSources, todayIso, writeSources } = dataset;
  const { records } = loadMaster(MASTER_CSV);
  const upper = String(code ?? "").toUpperCase();
  const target = findLaw(records, upper, law);
  if (!target) throw new Error(`no law for ${upper}${law ? ` named "${law}"` : " (the country has none, or several: pass --law)"}`);
  if (!COLUMNS.includes(field) || META_FIELDS.includes(field) || COUNTRY_FIELDS.includes(field)) throw new Error(`field must be a content column, got ${JSON.stringify(field)}`);
  if (!isHttpUrl(url) || isPrivateHost(url)) throw new Error(`the URL must be a public http(s) URL, got ${JSON.stringify(url)}`);
  if (!CONFIDENCE_LEVELS.includes(confidence)) throw new Error(`confidence must be one of ${CONFIDENCE_LEVELS.join("|")}`);
  const side = loadSources(MASTER_CSV);
  const several = records.filter((r) => r.country_code === upper).length > 1;
  const entry = { country_code: upper, law_name: several ? target.law_name : "", field, url, confidence, checked: checked ?? todayIso() };
  const index = side.rows.findIndex((s) => s.country_code === upper && s.field === field && findLaw(records, s.country_code, s.law_name) === target);
  if (index >= 0) side.rows[index] = { ...entry, law_name: side.rows[index].law_name };
  else side.rows.push(entry);
  if (write) writeSources(MASTER_CSV, side.rows);
  return { ...entry, replaced: index >= 0 };
}

/** The `fix` command: rewrites the files canonically, resolving each source by the id it has in the file. */
export function fixSources({ dataset, write = true }) {
  const { MASTER_CSV, SOURCES_FILE, SOURCE_URLS_FILE, loadSide, writeSources } = dataset;
  const urls = new Map(loadSide(MASTER_CSV, SOURCE_URLS_FILE, ["source_id", "url"]).rows.map((r) => [r.source_id, r.url]));
  const raw = loadSide(MASTER_CSV, SOURCES_FILE, ["country_code", "law_name", "field", "source_id", "confidence", "checked"]).rows;
  const rows = raw.map(({ source_id: id, ...rest }) => {
    if (!urls.has(id)) throw new Error(`${SOURCES_FILE}: source_id ${JSON.stringify(id)} is not in ${SOURCE_URLS_FILE}`);
    return { ...rest, url: urls.get(id) };
  });
  const before = urls.size;
  if (write) writeSources(MASTER_CSV, rows);
  return { sources: rows.length, urlsBefore: before, urlsAfter: new Set(rows.map((r) => r.url)).size };
}

/** main() bound to one project's dataset. */
export function createSources({ dataset }) {
  function main(argv = process.argv.slice(2)) {
    const option = (name) => argv.find((a) => a.startsWith(`--${name}=`))?.slice(name.length + 3);
    const [command, ...rest] = argv.filter((a) => !a.startsWith("--"));
    if (command === "add" && rest.length === 3) {
      const [code, field, url] = rest;
      const done = addSource({ dataset, code, field, url, confidence: option("confidence") ?? "medium", law: option("law") ?? "", checked: option("checked") });
      console.log(`${done.replaced ? "Replaced" : "Added"} the source of ${done.country_code} ${done.field}: ${done.url} (${done.confidence}, ${done.checked})`);
      console.log("Now run: npm run build && npm run changes");
      return 0;
    }
    if (command === "fix" && !rest.length) {
      const r = fixSources({ dataset });
      console.log(`Rewrote ${r.sources} source(s) and ${r.urlsAfter} URL(s) (was ${r.urlsBefore}).`);
      return 0;
    }
    console.log('Usage: npm run sources -- add <CODE> <field> <url> [--confidence=high|medium|low] [--law="law name"] [--checked=YYYY-MM-DD]');
    console.log("       npm run sources -- fix");
    return command ? 2 : 0;
  }
  return { addSource: (options) => addSource({ dataset, ...options }), fixSources: (options) => fixSources({ dataset, ...options }), main };
}
