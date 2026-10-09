// Everything that is specific to copyright laws: searchable fields, facets
// (region, treaties, protection term, protection type), derived row fields,
// the term-of-protection chart data and the comparison criteria.

export const REGION_ORDER = ["europe", "americas", "asia_pacific", "middle_east_africa"];
export const TREATY_ORDER = ["Berne", "TRIPS", "WCT", "WPPT"];

const TEXT_FIELDS = [
  "country_name",
  "country_code",
  "law_name",
  "protection_type",
  "term_of_protection",
  "author_rights",
  "moral_rights",
  "orphan_works",
  "digital_protection",
  "fair_use_exceptions",
  "registration_required",
  "enforcement_body",
  "treaties_signatory",
  "notes",
];

export const SEARCH_SCOPES = [
  { id: "all", fields: TEXT_FIELDS },
  { id: "name", fields: ["country_name", "country_code", "law_name"] },
  {
    id: "rights",
    fields: ["author_rights", "moral_rights", "orphan_works", "fair_use_exceptions", "digital_protection"],
  },
  { id: "authority", fields: ["enforcement_body"] },
];

const LIFE_TERM = /life\s*\+\s*(\d+)\s*years?/i;

/** "Author's life + 70 years (EU harmonized)" → 70; anything else → null. */
export function parseLifeTerm(text) {
  const match = LIFE_TERM.exec(text ?? "");
  return match ? Number(match[1]) : null;
}

export const termBucket = (years) => (years == null ? "other" : `life-${years}`);
export const bucketYears = (bucket) => (bucket.startsWith("life-") ? Number(bucket.slice(5)) : Infinity);

const rank = (order, value) => {
  const index = order.indexOf(value);
  return index < 0 ? order.length : index;
};

export const FACETS = [
  {
    id: "region",
    kind: "multi",
    match: "any",
    values: (row) => [row.region],
    order: (a, b) => rank(REGION_ORDER, a.value) - rank(REGION_ORDER, b.value),
  },
  {
    // A jurisdiction must be party to *every* selected treaty.
    id: "treaty",
    kind: "multi",
    match: "all",
    values: (row) => row.treaties,
    order: (a, b) => rank(TREATY_ORDER, a.value) - rank(TREATY_ORDER, b.value) || (a.value < b.value ? -1 : 1),
  },
  {
    id: "term",
    kind: "multi",
    match: "any",
    values: (row) => [row.termBucket],
    order: (a, b) => {
      const x = bucketYears(a.value);
      const y = bucketYears(b.value);
      return x === y ? 0 : x < y ? -1 : 1;
    },
  },
  { id: "ptype", kind: "multi", match: "any", values: (row) => [row.protection_type] },
];

export const CONFIG = { searchScopes: SEARCH_SCOPES, facets: FACETS };

export const COMPARE_CRITERIA = [
  "protection_type",
  "term_of_protection",
  "author_rights",
  "moral_rights",
  "orphan_works",
  "digital_protection",
  "fair_use_exceptions",
  "registration_required",
  "treaties_signatory",
  "treaties_not_party",
  "notes",
];

const splitList = (value) =>
  value
    ? value
        .split("/")
        .map((s) => s.trim())
        .filter(Boolean)
    : [];

export function enrichRow(row) {
  row.treaties = splitList(row.treaties_signatory);
  row.treatiesNot = splitList(row.treaties_not_party);
  // Neither confirmed nor ruled out.
  row.treatiesUnconfirmed = TREATY_ORDER.filter((t) => !row.treaties.includes(t) && !row.treatiesNot.includes(t));
  row.termYears = parseLifeTerm(row.term_of_protection);
  row.termBucket = termBucket(row.termYears);
  return row;
}

/**
 * One bar per protection-term bucket. Buckets come from the whole dataset
 * (`allRows`) so the axis stays put while filters change; filtered-out buckets
 * simply drop to zero.
 */
export function buildChartBars(rows, allRows = rows) {
  const buckets = [...new Set(allRows.map((row) => row.termBucket))].sort((a, b) => {
    const x = bucketYears(a);
    const y = bucketYears(b);
    return x === y ? 0 : x < y ? -1 : 1;
  });
  const grouped = new Map(buckets.map((bucket) => [bucket, []]));
  for (const row of rows) grouped.get(row.termBucket)?.push(row);
  return buckets.map((bucket) => ({
    key: bucket,
    bucket,
    years: bucketYears(bucket),
    value: grouped.get(bucket).length,
    items: grouped.get(bucket).map((row) => ({ primary: row.country_name, secondary: row.law_name })),
  }));
}
