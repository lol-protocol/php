// Pure filtering engine (no DOM): tokenised multi-field search + faceted filters.
//
// A module config describes what can be searched and filtered:
//   searchScopes: [{ id, fields: [rowKey, …] }]            first scope is the default
//   facets: [{ id, kind: "multi"|"select"|"range", match?: "any"|"all",
//              values: (row) => string[] | number | null }]
import { normalize } from "./util.js";

const SHORT_TOKEN = 2;

/** Split a query into normalised tokens; "quoted phrases" stay together. */
export function tokenize(query) {
  const tokens = [];
  for (const m of String(query ?? "").matchAll(/"([^"]+)"|(\S+)/g)) {
    const raw = (m[1] ?? m[2].replace(/"/g, "")).trim();
    const token = normalize(raw).replace(/\s+/g, " ");
    if (token) tokens.push(token);
  }
  return tokens;
}

const escapeRegExp = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

// One- and two-letter tokens ("us", "br") match whole words only, otherwise
// they would hit half of the dataset ("Russia", "business", "Brazilian"…).
function makeMatcher(token) {
  if (token.length <= SHORT_TOKEN) {
    const re = new RegExp(
      `(^|[^\\p{L}\\p{N}])${escapeRegExp(token)}($|[^\\p{L}\\p{N}])`,
      "u"
    );
    return (haystack) => re.test(haystack);
  }
  return (haystack) => haystack.includes(token);
}

/** Precompute one normalised haystack per search scope on every row. */
export function buildIndex(rows, config) {
  for (const row of rows) {
    row._hay = {};
    for (const scope of config.searchScopes) {
      row._hay[scope.id] = normalize(scope.fields.map((f) => row[f]).join(" | "));
    }
  }
  return rows;
}

export function emptyState(config) {
  const facets = {};
  for (const f of config.facets) {
    facets[f.id] =
      f.kind === "multi" ? [] : f.kind === "range" ? { from: null, to: null } : "";
  }
  return { q: "", scope: config.searchScopes[0].id, country: "", facets };
}

export function facetActive(facet, selection) {
  if (facet.kind === "multi") return Array.isArray(selection) && selection.length > 0;
  if (facet.kind === "range") return !!selection && (selection.from != null || selection.to != null);
  return !!selection;
}

function matchFacet(facet, row, selection) {
  const values = facet.values(row);
  if (facet.kind === "range") {
    if (values == null) return false;
    return (
      (selection.from == null || values >= selection.from) &&
      (selection.to == null || values <= selection.to)
    );
  }
  if (facet.kind === "select") return values.includes(selection);
  if (facet.match === "all") return selection.every((v) => values.includes(v));
  return values.some((v) => selection.includes(v));
}

/** AND across the query and every active facet; OR (or AND for match:"all") within a facet. */
export function filterRows(rows, state, config) {
  const matchers = tokenize(state.q).map(makeMatcher);
  const scope = config.searchScopes.some((s) => s.id === state.scope)
    ? state.scope
    : config.searchScopes[0].id;
  const active = config.facets.filter((f) => facetActive(f, state.facets[f.id]));

  return rows.filter((row) => {
    if (state.country && row.tld !== state.country) return false;
    if (matchers.length) {
      const haystack = row._hay[scope];
      for (const matches of matchers) if (!matches(haystack)) return false;
    }
    for (const facet of active) {
      if (!matchFacet(facet, row, state.facets[facet.id])) return false;
    }
    return true;
  });
}

/** Distinct values (with row counts) for multi/select facets; {min,max} for range facets. */
export function facetOptions(rows, facet) {
  if (facet.kind === "range") {
    let min = null;
    let max = null;
    for (const row of rows) {
      const v = facet.values(row);
      if (v == null) continue;
      if (min == null || v < min) min = v;
      if (max == null || v > max) max = v;
    }
    return { min, max };
  }
  const counts = new Map();
  for (const row of rows) {
    for (const v of new Set(facet.values(row))) counts.set(v, (counts.get(v) ?? 0) + 1);
  }
  const options = [...counts].map(([value, count]) => ({ value, count }));
  options.sort(facet.order ?? ((a, b) => (a.value < b.value ? -1 : a.value > b.value ? 1 : 0)));
  return options;
}

export function stateToParams(state, config) {
  const params = new URLSearchParams();
  if (state.q) params.set("q", state.q);
  if (state.scope !== config.searchScopes[0].id) params.set("in", state.scope);
  if (state.country) params.set("country", state.country);
  for (const f of config.facets) {
    const v = state.facets[f.id];
    if (!facetActive(f, v)) continue;
    if (f.kind === "multi") params.set(f.id, v.join(","));
    else if (f.kind === "select") params.set(f.id, v);
    else params.set(f.id, `${v.from ?? ""}-${v.to ?? ""}`);
  }
  return params;
}

export function stateFromParams(params, config) {
  const state = emptyState(config);
  state.q = (params.get("q") ?? "").slice(0, 200);

  const scope = params.get("in");
  if (config.searchScopes.some((s) => s.id === scope)) state.scope = scope;

  const country = params.get("country");
  if (/^[a-z]{2}$/.test(country ?? "")) state.country = country;

  for (const f of config.facets) {
    const raw = params.get(f.id);
    if (raw == null) continue;
    if (f.kind === "multi") {
      state.facets[f.id] = raw.split(",").map((s) => s.trim()).filter(Boolean);
    } else if (f.kind === "select") {
      state.facets[f.id] = raw;
    } else {
      const m = /^(\d{1,4})?-(\d{1,4})?$/.exec(raw);
      if (m) state.facets[f.id] = { from: m[1] ? Number(m[1]) : null, to: m[2] ? Number(m[2]) : null };
    }
  }
  return state;
}

/** Drop selections that do not exist in the dataset (stale or hand-edited URLs). */
export function pruneState(state, config, rows) {
  const next = { ...state, facets: { ...state.facets } };
  if (next.country && !rows.some((r) => r.tld === next.country)) next.country = "";
  for (const f of config.facets) {
    if (f.kind === "range") continue;
    const known = new Set(facetOptions(rows, f).map((o) => o.value));
    const sel = next.facets[f.id];
    if (f.kind === "multi") next.facets[f.id] = sel.filter((v) => known.has(v));
    else if (sel && !known.has(sel)) next.facets[f.id] = "";
  }
  return next;
}

/** Chips for every active criterion except the free-text query. */
export function activeFilters(state, config) {
  const chips = [];
  if (state.country) chips.push({ facet: "country", value: state.country });
  for (const f of config.facets) {
    const v = state.facets[f.id];
    if (!facetActive(f, v)) continue;
    if (f.kind === "multi") for (const value of v) chips.push({ facet: f.id, value });
    else if (f.kind === "select") chips.push({ facet: f.id, value: v });
    else chips.push({ facet: f.id, value: `${v.from ?? ""}–${v.to ?? ""}` });
  }
  return chips;
}

export function removeFilter(state, config, chip) {
  const next = { ...state, facets: { ...state.facets } };
  if (chip.facet === "country") {
    next.country = "";
    return next;
  }
  const facet = config.facets.find((f) => f.id === chip.facet);
  if (!facet) return next;
  if (facet.kind === "multi") next.facets[facet.id] = next.facets[facet.id].filter((v) => v !== chip.value);
  else if (facet.kind === "select") next.facets[facet.id] = "";
  else next.facets[facet.id] = { from: null, to: null };
  return next;
}

export function clearFilters(state, config) {
  const fresh = emptyState(config);
  return { ...fresh, q: state.q, scope: state.scope };
}
