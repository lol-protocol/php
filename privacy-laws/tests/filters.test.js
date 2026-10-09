import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { hydrate } from "../app/lib/data.js";
import { buildChartBars, CONFIG, enrichRow, labelAxis } from "../app/lib/domain.js";
import { niceScale } from "../app/lib/chart.js";
import {
  activeFilters,
  buildIndex,
  clearFilters,
  emptyState,
  facetOptions,
  filterRows,
  pruneState,
  removeFilter,
  stateFromParams,
  stateToParams,
  tokenize,
} from "../app/lib/filters.js";
import { csvCell, escapeHtml, normalize, safeUrl, toCsv } from "../app/lib/util.js";

/* ---------- Generic engine, on a tiny synthetic dataset ---------- */

const config = {
  searchScopes: [
    { id: "all", fields: ["name", "text", "code"] },
    { id: "name", fields: ["name"] },
  ],
  facets: [
    { id: "region", kind: "multi", match: "any", values: (r) => [r.region] },
    { id: "tag", kind: "multi", match: "all", values: (r) => r.tags },
    { id: "year", kind: "range", values: (r) => r.year },
    { id: "lang", kind: "select", values: (r) => r.langs },
  ],
};
const rows = buildIndex(
  [
    { id: "a", tld: "pe", code: "PE", name: "Perú", text: "Ley de protección de datos", region: "americas", tags: ["x", "y"], year: 2011, langs: ["Spanish"] },
    { id: "b", tld: "us", code: "US", name: "United States", text: "Consumer privacy act", region: "americas", tags: ["x"], year: 2020, langs: ["English"] },
    { id: "c", tld: "ru", code: "RU", name: "Russia", text: "Federal law on personal data", region: "europe", tags: [], year: 2006, langs: ["Russian", "English"] },
    { id: "d", tld: "au", code: "AU", name: "Australia", text: "Privacy Act 1988", region: "asia_pacific", tags: ["x", "y"], year: null, langs: ["English"] },
  ],
  config
);
const ids = (patch = {}, top = {}) => {
  const state = { ...emptyState(config), ...top };
  state.facets = { ...state.facets, ...patch };
  return filterRows(rows, state, config).map((r) => r.id);
};

test("tokenize keeps quoted phrases together, drops stray quotes and normalises", () => {
  assert.deepEqual(tokenize('Data "subject  rights" Perú "oops'), ["data", "subject rights", "peru", "oops"]);
  assert.deepEqual(tokenize("   "), []);
  assert.deepEqual(tokenize(null), []);
});

test("text search is accent- and case-insensitive and ANDs every word", () => {
  assert.deepEqual(ids({}, { q: "PERÚ" }), ["a"]);
  assert.deepEqual(ids({}, { q: "peru datos" }), ["a"]);
  assert.deepEqual(ids({}, { q: "privacy act" }), ["b", "d"]);
  assert.deepEqual(ids({}, { q: "privacy nonexistent" }), []);
  assert.deepEqual(ids({}, { q: '"personal data"' }), ["c"]);
});

test("one- and two-letter tokens match whole words only", () => {
  assert.deepEqual(ids({}, { q: "us" }), ["b"]); // not "Russia", "Australia", "Perú ... datos"
  assert.deepEqual(ids({}, { q: "ru" }), ["c"]);
  assert.deepEqual(ids({}, { q: "ust" }), ["d"]); // three letters: plain substring, found inside "Australia"
});

test("search scope limits which fields are looked at", () => {
  assert.deepEqual(ids({}, { q: "privacy" }), ["b", "d"]);
  assert.deepEqual(ids({}, { q: "privacy", scope: "name" }), []);
  assert.deepEqual(ids({}, { q: "russia", scope: "name" }), ["c"]);
  assert.deepEqual(ids({}, { q: "russia", scope: "bogus" }), ["c"]); // unknown scope falls back to the default
});

test("multi facets: OR by default, AND with match:all", () => {
  assert.deepEqual(ids({ region: ["americas", "europe"] }), ["a", "b", "c"]);
  assert.deepEqual(ids({ tag: ["x"] }), ["a", "b", "d"]);
  assert.deepEqual(ids({ tag: ["x", "y"] }), ["a", "d"]);
});

test("range facet: open ends and rows without a value", () => {
  assert.deepEqual(ids({ year: { from: 2010, to: null } }), ["a", "b"]);
  assert.deepEqual(ids({ year: { from: null, to: 2010 } }), ["c"]);
  assert.deepEqual(ids({ year: { from: 2011, to: 2011 } }), ["a"]);
  assert.deepEqual(ids({ year: { from: null, to: null } }), ["a", "b", "c", "d"]); // inactive
});

test("select facet matches any of the row's values; country narrows by folder", () => {
  assert.deepEqual(ids({ lang: "English" }), ["b", "c", "d"]);
  assert.deepEqual(ids({ lang: "Russian" }), ["c"]);
  assert.deepEqual(ids({}, { country: "us" }), ["b"]);
});

test("criteria combine with AND", () => {
  assert.deepEqual(ids({ region: ["americas"], year: { from: 2015, to: null }, lang: "English" }, { q: "act" }), ["b"]);
});

test("facetOptions counts each row once per value and honours custom order", () => {
  assert.deepEqual(facetOptions(rows, config.facets[3]), [
    { value: "English", count: 3 },
    { value: "Russian", count: 1 },
    { value: "Spanish", count: 1 },
  ]);
  assert.deepEqual(facetOptions(rows, config.facets[2]), { min: 2006, max: 2020 });
  const reversed = { ...config.facets[0], order: (a, b) => (a.value < b.value ? 1 : -1) };
  assert.deepEqual(facetOptions(rows, reversed).map((o) => o.value), ["europe", "asia_pacific", "americas"]);
});

test("state round-trips through the URL and survives junk", () => {
  const state = emptyState(config);
  Object.assign(state, { q: "data protection", scope: "name", country: "us" });
  state.facets = { region: ["americas", "europe"], tag: ["x"], year: { from: 2010, to: null }, lang: "English" };

  const query = stateToParams(state, config).toString();
  assert.deepEqual(stateFromParams(new URLSearchParams(query), config), state);
  assert.equal(stateToParams(emptyState(config), config).toString(), "");

  const junk = stateFromParams(new URLSearchParams("in=zzz&country=../x&year=abc&region=,,&q=" + "a".repeat(500)), config);
  assert.equal(junk.scope, "all");
  assert.equal(junk.country, "");
  assert.deepEqual(junk.facets.year, { from: null, to: null });
  assert.deepEqual(junk.facets.region, []);
  assert.equal(junk.q.length, 200);
});

test("pruneState removes values that do not exist in the data", () => {
  const state = emptyState(config);
  state.country = "zz";
  state.facets.region = ["americas", "mars"];
  state.facets.lang = "Klingon";
  const pruned = pruneState(state, config, rows);
  assert.equal(pruned.country, "");
  assert.deepEqual(pruned.facets.region, ["americas"]);
  assert.equal(pruned.facets.lang, "");
});

test("chips can be listed, removed one by one and cleared without losing the query", () => {
  let state = emptyState(config);
  state.q = "act";
  state.country = "us";
  state.facets.region = ["americas", "europe"];
  state.facets.year = { from: 2000, to: 2010 };

  const chips = activeFilters(state, config);
  assert.deepEqual(chips.map((c) => `${c.facet}:${c.value}`), ["country:us", "region:americas", "region:europe", "year:2000–2010"]);

  state = removeFilter(state, config, chips[1]);
  assert.deepEqual(state.facets.region, ["europe"]);
  state = removeFilter(state, config, chips[0]);
  assert.equal(state.country, "");
  state = removeFilter(state, config, { facet: "year", value: "x" });
  assert.deepEqual(state.facets.year, { from: null, to: null });

  const cleared = clearFilters({ ...state, q: "keep me", scope: "name" }, config);
  assert.equal(cleared.q, "keep me");
  assert.equal(cleared.scope, "name");
  assert.deepEqual(activeFilters(cleared, config), []);
});

/* ---------- Privacy domain, on the real dataset ---------- */

const bundle = JSON.parse(readFileSync(new URL("../countries/index.json", import.meta.url), "utf-8"));
const data = hydrate(bundle, enrichRow);
buildIndex(data.rows, CONFIG);
const count = (query) =>
  filterRows(data.rows, pruneState(stateFromParams(new URLSearchParams(query), CONFIG), CONFIG, data.rows), CONFIG).length;

test("hydrate flattens countries into rows with stable ids and derived fields", () => {
  assert.equal(data.rows.length, bundle.countries.reduce((n, c) => n + c.laws.length, 0));
  assert.equal(new Set(data.rows.map((r) => r.id)).size, data.rows.length);
  const gdpr = data.rows.find((r) => r.country_code === "EU");
  assert.equal(gdpr.id, "eu-0");
  assert.equal(gdpr.region, "europe");
  assert.equal(gdpr.effectiveYear, 2018);
  const swiss = data.rows.find((r) => r.country_code === "CH");
  assert.deepEqual(swiss.languages, ["German", "French", "Italian"]);
});

test("privacy filters give the expected counts on the real data", () => {
  // Expected values are derived from the rows themselves, so adding countries does not break them.
  const expected = (predicate) => data.rows.filter(predicate).length;
  assert.equal(count(""), data.rows.length);
  assert.equal(
    count("region=americas&year=2018-2024"),
    expected((r) => r.region === "americas" && r.effectiveYear >= 2018 && r.effectiveYear <= 2024)
  );
  assert.equal(
    count("language=Spanish&region=europe"),
    expected((r) => r.region === "europe" && r.languages.includes("Spanish"))
  );
  assert.equal(count("q=us"), 2);
  assert.equal(count("q=br"), 1);
  assert.equal(count("country=us"), 2);
  assert.equal(
    count("year=2018-2018&region=europe"),
    expected((r) => r.region === "europe" && r.effectiveYear === 2018)
  );
  assert.ok(count("year=2018-2018&region=europe") >= 17);
});

test("every framework is in exactly one of three states", () => {
  for (const r of data.rows) {
    assert.deepEqual([...r.frameworkList, ...r.frameworksNot, ...r.frameworksUnconfirmed].sort(), ["APEC-CBPR", "CoE-108", "EU-Adequacy", "GDPR"], r.country_code);
  }
});

test("the framework facet requires every selected framework", () => {
  const withAll = (...wanted) => data.rows.filter((r) => wanted.every((f) => r.frameworkList.includes(f))).length;
  assert.ok(withAll("GDPR") > 0 && withAll("CoE-108") > 0 && withAll("APEC-CBPR") > 0);
  assert.equal(count("framework=GDPR"), withAll("GDPR"));
  assert.equal(count("framework=GDPR,CoE-108"), withAll("GDPR", "CoE-108"));
  assert.ok(withAll("GDPR", "CoE-108") < withAll("GDPR") + withAll("CoE-108"));
  assert.equal(count("framework=GDPR,APEC-CBPR"), 0, "no country is both an EU/EEA state and an APEC CBPR participant");
  assert.equal(count("framework=Nonsense"), data.rows.length, "unknown frameworks are ignored, not turned into an empty result");
});

test("chart bars cover every year in range, including empty ones", () => {
  const bars = buildChartBars(data.rows);
  const years = data.rows.map((r) => r.effectiveYear);
  const [first, last] = [Math.min(...years), Math.max(...years)];
  assert.equal(bars[0].label, String(first));
  assert.equal(bars.at(-1).label, String(last));
  assert.equal(bars.length, last - first + 1);
  assert.equal(bars.reduce((n, b) => n + b.value, 0), data.rows.length);
  const perYear = new Map();
  for (const year of years) perYear.set(year, (perYear.get(year) ?? 0) + 1);
  assert.equal(Math.max(...bars.map((b) => b.value)), Math.max(...perYear.values()));
  assert.deepEqual(buildChartBars([]), []);
});

test("axis labels never sit on adjacent bars", () => {
  const bars = labelAxis(buildChartBars(data.rows));
  const shown = bars.map((b, i) => (b.showLabel ? i : -1)).filter((i) => i >= 0);
  assert.ok(shown.length > 3);
  for (let i = 1; i < shown.length; i++) assert.ok(shown[i] - shown[i - 1] >= 3, `labels too close at ${shown[i - 1]}/${shown[i]}`);
  assert.equal(labelAxis([{ key: "2000" }, { key: "2001" }]).every((b) => b.showLabel !== false), true);
});

test("niceScale picks round tick steps", () => {
  assert.deepEqual(niceScale(17), { max: 20, step: 5 });
  assert.deepEqual(niceScale(1), { max: 1, step: 1 });
  assert.deepEqual(niceScale(30), { max: 30, step: 10 });
  assert.deepEqual(niceScale(0), { max: 1, step: 1 });
});

/* ---------- Safe rendering helpers ---------- */

test("escapeHtml, safeUrl, normalize and CSV helpers", () => {
  assert.equal(escapeHtml(`<img src=x onerror="a()"> & 'q'`), "&lt;img src=x onerror=&quot;a()&quot;&gt; &amp; &#39;q&#39;");
  assert.equal(safeUrl("https://gdpr-info.eu/"), "https://gdpr-info.eu/");
  assert.equal(safeUrl("javascript:alert(1)"), "");
  assert.equal(safeUrl("data:text/html,<b>"), "");
  assert.equal(safeUrl("not a url"), "");
  assert.equal(normalize("Perú ÑANDÚ"), "peru nandu");

  assert.equal(csvCell('say "hi", ok'), '"say ""hi"", ok"');
  assert.equal(csvCell("=SUM(A1:A2)"), `"'=SUM(A1:A2)"`); // formula injection defused
  assert.equal(csvCell("+1"), `"'+1"`);
  assert.equal(csvCell(null), '""');
  assert.equal(toCsv(["a", "b"], [{ a: "1", b: "x,y" }]), 'a,b\n"1","x,y"\n');
});
