import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { hydrate } from "../app/lib/data.js";
import {
  buildChartBars,
  bucketYears,
  CONFIG,
  enrichRow,
  parseLifeTerm,
  termBucket,
} from "../app/lib/domain.js";
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

/* ---------- Copyright domain, on the real dataset ---------- */

const bundle = JSON.parse(readFileSync(new URL("../jurisdictions/index.json", import.meta.url), "utf-8"));
const data = hydrate(bundle, enrichRow);
buildIndex(data.rows, CONFIG);
const count = (query) =>
  filterRows(data.rows, pruneState(stateFromParams(new URLSearchParams(query), CONFIG), CONFIG, data.rows), CONFIG).length;
const facet = (id) => CONFIG.facets.find((f) => f.id === id);

test("parseLifeTerm reads 'life + N years' and nothing else", () => {
  assert.equal(parseLifeTerm("Author's life + 70 years"), 70);
  assert.equal(parseLifeTerm("Author's life + 70 years or 95 years (works for hire)"), 70);
  assert.equal(parseLifeTerm("Author's life + 50 years (first publication)"), 50);
  assert.equal(parseLifeTerm("author's LIFE+100 YEARS"), 100);
  assert.equal(parseLifeTerm("95 years from publication"), null);
  assert.equal(parseLifeTerm(""), null);
  assert.equal(parseLifeTerm(undefined), null);
  assert.equal(termBucket(70), "life-70");
  assert.equal(termBucket(null), "other");
  assert.equal(bucketYears("life-100"), 100);
  assert.equal(bucketYears("other"), Infinity);
});

test("hydrate flattens jurisdictions into rows with treaties and term buckets", () => {
  assert.equal(data.rows.length, bundle.jurisdictions.reduce((n, j) => n + j.laws.length, 0));
  assert.equal(new Set(data.rows.map((r) => r.id)).size, data.rows.length);
  const gb = data.rows.find((r) => r.country_code === "GB");
  assert.equal(gb.law_name, "Copyright, Designs and Patents Act 1988");
  assert.deepEqual(gb.treaties.slice(0, 3), ["Berne", "TRIPS", "WCT"]); // always in Berne/TRIPS/WCT/WPPT order
  assert.equal(gb.termBucket, "life-70");
  const ru = data.rows.find((r) => r.country_code === "RU");
  assert.ok(ru.treaties.includes("Berne") && ru.treaties.includes("TRIPS"));
  const tw = data.rows.find((r) => r.country_code === "TW");
  assert.deepEqual(tw.treaties, ["TRIPS"]);
  assert.ok(tw.treatiesNot.includes("Berne"));
  for (const r of data.rows) {
    // every treaty is in exactly one of the three states
    assert.deepEqual([...r.treaties, ...r.treatiesNot, ...r.treatiesUnconfirmed].sort(), ["Berne", "TRIPS", "WCT", "WPPT"].sort(), r.country_code);
  }
});

test("facet options are derived from the data, in a meaningful order", () => {
  assert.deepEqual(facetOptions(data.rows, facet("region")).map((o) => o.value), ["europe", "americas", "asia_pacific", "middle_east_africa"]);
  assert.deepEqual(facetOptions(data.rows, facet("treaty")).map((o) => o.value), ["Berne", "TRIPS", "WCT", "WPPT"]);
  // Term buckets come from the data and run from the shortest to the longest term.
  const years = [...new Set(data.rows.map((r) => r.termYears).filter((y) => y != null))].sort((x, y) => x - y);
  assert.deepEqual(
    facetOptions(data.rows, facet("term")).map((o) => o.value),
    years.map((y) => `life-${y}`)
  );
  assert.ok(years.includes(50) && years.includes(70), "the common terms must be present");
  // Only one protection type exists today, so the UI hides that filter until a second one appears.
  assert.deepEqual(facetOptions(data.rows, facet("ptype")).map((o) => o.value), ["Copyright"]);
});

test("copyright filters give the expected counts on the real data", () => {
  // Expected values are derived from the rows themselves, so adding jurisdictions does not break them.
  const expected = (predicate) => data.rows.filter(predicate).length;
  assert.equal(count(""), data.rows.length);
  assert.equal(count("treaty=WCT"), expected((r) => r.treaties.includes("WCT")));
  assert.equal(
    count("treaty=Berne,WCT&region=europe"), // must be party to BOTH
    expected((r) => r.region === "europe" && r.treaties.includes("Berne") && r.treaties.includes("WCT"))
  );
  assert.equal(count("term=life-50"), expected((r) => r.termYears === 50));
  assert.equal(count("term=life-50,life-70"), expected((r) => r.termYears === 50 || r.termYears === 70)); // OR inside the same facet
  assert.equal(
    count("term=life-50&region=asia_pacific"),
    expected((r) => r.termYears === 50 && r.region === "asia_pacific")
  );
  assert.ok(count("term=life-50,life-70") > count("term=life-50") && count("term=life-50") > 0);
  assert.equal(count("q=orphan&in=authority"), 0);
  assert.equal(count("country=mx"), 1);
  assert.equal(count("q=br"), 1); // whole-word match on the country code only
  assert.equal(count("treaty=Hague&term=life-9"), data.rows.length); // unknown values are ignored
});

test("repaired rows are searchable by their real fields", () => {
  assert.equal(count('q="Designs and Patents"'), 1);
  assert.equal(count("q=9,610"), 1);
  assert.ok(count("q=perpetual&in=rights") >= 3);
});

test("chart bars keep the same buckets whatever the filter, so the axis does not jump", () => {
  const all = buildChartBars(data.rows, data.rows);
  const buckets = [...new Set(data.rows.map((r) => r.termBucket))].sort((a, b) => bucketYears(a) - bucketYears(b));
  assert.deepEqual(all.map((b) => b.bucket), buckets);
  assert.equal(all.reduce((n, b) => n + b.value, 0), data.rows.length);

  const europe = data.rows.filter((r) => r.region === "europe");
  const sliced = buildChartBars(europe, data.rows);
  assert.deepEqual(sliced.map((b) => b.bucket), all.map((b) => b.bucket));
  for (const bar of sliced) {
    const inEurope = europe.filter((r) => r.termBucket === bar.bucket).length;
    assert.equal(bar.value, inEurope);
    assert.equal(bar.items.length, inEurope);
  }
  assert.equal(sliced.find((b) => b.bucket === "life-70").value > 0, true);
  assert.ok(sliced.some((b) => b.value === 0), "buckets that no longer match stay on the axis at zero");
});

test("niceScale picks round tick steps", () => {
  assert.deepEqual(niceScale(30), { max: 30, step: 10 });
  assert.deepEqual(niceScale(16), { max: 20, step: 5 });
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
