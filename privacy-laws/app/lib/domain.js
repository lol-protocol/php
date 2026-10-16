// Everything that is specific to privacy laws: searchable fields, facets,
// derived row fields, table columns and the comparison criteria.

export const REGION_ORDER = ["europe", "americas", "asia_pacific", "middle_east_africa"];
export const FRAMEWORK_ORDER = ["GDPR", "EU-Adequacy", "CoE-108", "APEC-CBPR"];
export const SOURCE_ORDER = ["verified", "researched", "curated"];
export const FLAG_ORDER = ["pending", "review"];

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
  "deceased_data",
  "research_exemption",
  "genetic_data",
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
    fields: ["scope", "applies_to", "key_requirements", "data_categories", "retention_period", "exemptions", "deceased_data", "research_exemption", "genetic_data"],
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
  // Rows that need a look: an upcoming change, or a last check over a year old.
  { id: "flag", kind: "multi", match: "any", values: (row) => row.flags, order: (a, b) => rank(FLAG_ORDER, a.value) - rank(FLAG_ORDER, b.value) },
  { id: "year", kind: "range", values: (row) => row.effectiveYear },
  // Verified first: the order in which a reader should trust the rows.
  { id: "source", kind: "multi", match: "any", values: (row) => [row.data_status], order: (a, b) => rank(SOURCE_ORDER, a.value) - rank(SOURCE_ORDER, b.value) },
  { id: "language", kind: "select", values: (row) => row.languages },
];

export const CONFIG = { searchScopes: SEARCH_SCOPES, facets: FACETS };

export const COMPARE_CRITERIA = [
  "enactment_date",
  "effective_date",
  "scope",
  "applies_to",
  "key_requirements",
  "data_categories",
  "retention_period",
  "penalties_range",
  "exemptions",
  "deceased_data",
  "research_exemption",
  "genetic_data",
  "frameworks",
  "frameworks_not",
];

// Every content field of a law, in the order the country card lists them.
export const DETAIL_FIELDS = [
  "jurisdiction",
  "enforcement_authority",
  ...COMPARE_CRITERIA.filter((f) => !f.startsWith("frameworks")),
];

// A row whose last check is older than this is shown as due for review.
export const STALE_AFTER_DAYS = 365;
export const isReviewDue = (lastChecked, today) =>
  Boolean(lastChecked) && (Date.parse(`${today}T00:00:00Z`) - Date.parse(`${lastChecked}T00:00:00Z`)) / 86_400_000 > STALE_AFTER_DAYS;

const splitList = (value) =>
  value
    ? value
        .split("/")
        .map((s) => s.trim())
        .filter(Boolean)
    : [];

const isBlank = (value) => /^(not specified|—|-)?$/i.test(String(value ?? "").trim());

export function enrichRow(row, today = new Date().toISOString().slice(0, 10)) {
  row.effectiveYear = Number(row.effective_date.slice(0, 4));
  row.frameworkList = splitList(row.frameworks);
  row.frameworksNot = splitList(row.frameworks_not);
  // Neither confirmed nor ruled out.
  row.frameworksUnconfirmed = FRAMEWORK_ORDER.filter((f) => !row.frameworkList.includes(f) && !row.frameworksNot.includes(f));
  row.sources ??= {};
  row.changes ??= [];
  row.flags = [row.pending_change ? "pending" : null, isReviewDue(row.last_checked, today) ? "review" : null].filter(Boolean);
  // How much of the record is filled in, and how much of that has a source.
  row.fieldsTotal = DETAIL_FIELDS.length;
  row.fieldsFilled = DETAIL_FIELDS.filter((f) => !isBlank(row[f])).length;
  row.fieldsSourced = DETAIL_FIELDS.filter((f) => row.sources[f] && !isBlank(row[f])).length;
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
