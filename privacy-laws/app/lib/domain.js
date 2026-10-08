// Everything that is specific to privacy laws: searchable fields, facets,
// derived row fields, table columns and the comparison criteria.

export const REGION_ORDER = ["europe", "americas", "asia_pacific", "middle_east_africa"];
export const FRAMEWORK_ORDER = ["GDPR", "EU-Adequacy", "CoE-108", "APEC-CBPR"];

const TEXT_FIELDS = [
  "country_name",
  "country_code",
  "law_name",
  "jurisdiction",
  "enactment_date",
  "effective_date",
  "scope",
  "applies_to",
  "key_requirements",
  "data_categories",
  "retention_period",
  "enforcement_authority",
  "penalties_range",
  "exemptions",
  "language",
  "frameworks",
  "notes",
];

export const SEARCH_SCOPES = [
  { id: "all", fields: TEXT_FIELDS },
  { id: "name", fields: ["country_name", "country_code", "law_name"] },
  { id: "authority", fields: ["jurisdiction", "enforcement_authority"] },
  {
    id: "requirements",
    fields: ["scope", "applies_to", "key_requirements", "data_categories", "retention_period", "exemptions"],
  },
  { id: "penalties", fields: ["penalties_range"] },
];

const rank = (order, value) => {
  const index = order.indexOf(value);
  return index < 0 ? order.length : index;
};
const byRegionOrder = (a, b) => rank(REGION_ORDER, a.value) - rank(REGION_ORDER, b.value);

export const FACETS = [
  { id: "region", kind: "multi", match: "any", values: (row) => [row.region], order: byRegionOrder },
  {
    // A country must take part in *every* selected framework.
    id: "framework",
    kind: "multi",
    match: "all",
    values: (row) => row.frameworkList,
    order: (a, b) => rank(FRAMEWORK_ORDER, a.value) - rank(FRAMEWORK_ORDER, b.value),
  },
  { id: "year", kind: "range", values: (row) => row.effectiveYear },
  { id: "language", kind: "select", values: (row) => row.languages },
];

export const CONFIG = { searchScopes: SEARCH_SCOPES, facets: FACETS };

export const COMPARE_CRITERIA = [
  "scope",
  "applies_to",
  "key_requirements",
  "data_categories",
  "retention_period",
  "penalties_range",
  "exemptions",
  "frameworks",
];

export function enrichRow(row) {
  row.effectiveYear = Number(row.effective_date.slice(0, 4));
  row.frameworkList = row.frameworks
    ? row.frameworks
        .split("/")
        .map((s) => s.trim())
        .filter(Boolean)
    : [];
  row.languages = row.language
    .split("/")
    .map((s) => s.trim())
    .filter(Boolean);
  return row;
}

/** One bar per calendar year (zero years included) so the axis is a true time scale. */
export function buildChartBars(rows) {
  const byYear = new Map();
  for (const row of rows) {
    if (!byYear.has(row.effectiveYear)) byYear.set(row.effectiveYear, []);
    byYear.get(row.effectiveYear).push(row);
  }
  if (!byYear.size) return [];
  const years = [...byYear.keys()];
  const first = Math.min(...years);
  const last = Math.max(...years);
  const bars = [];
  for (let year = first; year <= last; year++) {
    const laws = byYear.get(year) ?? [];
    bars.push({
      key: String(year),
      label: String(year),
      value: laws.length,
      items: laws.map((r) => ({ primary: r.country_name, secondary: r.law_name })),
    });
  }
  labelAxis(bars);
  return bars;
}

// Label every Nth year so axis labels never collide; keep the first and last
// year when they are not crowded by a neighbouring label.
export function labelAxis(bars) {
  const count = bars.length;
  if (count <= 10) return bars;
  const step = count > 20 ? 5 : 2;
  const labelled = [];
  bars.forEach((bar, index) => {
    bar.showLabel = Number(bar.key) % step === 0;
    if (bar.showLabel) labelled.push(index);
  });
  for (const edge of [0, count - 1]) {
    if (bars[edge].showLabel) continue;
    const nearest = Math.min(...labelled.map((index) => Math.abs(index - edge)), Infinity);
    if (nearest >= 3) bars[edge].showLabel = true;
  }
  return bars;
}
