import { createHash } from "node:crypto";
import {
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  writeFileSync,
} from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { parse } from "csv-parse/sync";

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..");
export const MODULE = "copyright-laws";
export const DATA_DIR = join(ROOT, "jurisdictions");
export const MASTER_CSV = join(DATA_DIR, "copyright_laws_master.csv");
export const BUNDLE_FILE = "index.json";
export const SCHEMA_VERSION = 1;

export const COLUMNS = [
  "country_code",
  "country_name",
  "region",
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
  "treaties_not_party",
  "linked_resources",
  "notes",
  "pending_change",
  "pending_date",
  "data_status",
  "confidence",
  "last_checked",
];
// Where a row's data comes from: the original curated list, web research from secondary sources
// (with the researcher's confidence and the date), or a check against the official text.
export const DATA_STATUSES = ["verified", "researched", "curated"];
export const CONFIDENCE_LEVELS = ["high", "medium", "low"];
// A row whose last check is older than this is due for review (a warning, not an error).
export const STALE_AFTER_DAYS = 365;
// Bookkeeping columns: not content, so neither sourced per field nor recorded in the change history.
export const META_FIELDS = ["data_status", "confidence", "last_checked"];

// Side files next to the master CSV.
//   field_sources.csv  where one value of one law comes from (law_name may be empty when the jurisdiction has a single law)
//   changes.csv        what changed in a row and when (written by `npm run changes`)
export const SOURCES_FILE = "field_sources.csv";
export const CHANGES_FILE = "changes.csv";
export const SOURCE_COLUMNS = ["country_code", "law_name", "field", "url", "confidence", "checked"];
export const CHANGE_COLUMNS = ["date", "country_code", "law_name", "field", "old_value", "new_value"];
// `field` of a change that adds or removes a whole row.
export const ROW_CHANGE = "row";



export const REGIONS = [
  "europe",
  "americas",
  "asia_pacific",
  "middle_east_africa",
];

export const PROTECTION_TYPES = [
  "Copyright",
  "Related rights",
  "Database rights",
  "Moral rights",
];

export const TREATIES = ["Berne", "TRIPS", "WCT", "WPPT"];

const REQUIRED = [
  "country_code",
  "country_name",
  "region",
  "law_name",
  "protection_type",
  "term_of_protection",
  "enforcement_body",
];

const COUNTRY_FIELDS = ["country_code", "country_name", "region"];

export const tldOf = (code) => code.toLowerCase();

const isRealDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const d = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
};

export const todayIso = () => new Date().toISOString().slice(0, 10);
const daysBetween = (from, to) => (Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86_400_000;

const isHttpUrl = (value) => {
  try {
    const u = new URL(value);
    return u.protocol === "http:" || u.protocol === "https:";
  } catch {
    return false;
  }
};

// Reference URLs are fetched by the text cache and shown as links: keep them pointing at the public web.
const PRIVATE_IPV4 = /^(0\.|10\.|127\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/;
const isPrivateHost = (value) => {
  try {
    const host = new URL(value).hostname.toLowerCase().replace(/^\[|\]$/g, "");
    return (
      host === "localhost" ||
      /\.(localhost|local|internal|lan)$/.test(host) ||
      PRIVATE_IPV4.test(host) ||
      host === "::1" ||
      /^(fe80|fc|fd)/.test(host)
    );
  } catch {
    return false;
  }
};

export function parseMaster(text) {
  const records = parse(text, {
    columns: true,
    skip_empty_lines: true,
    bom: true,
  });
  const header = records.length ? Object.keys(records[0]) : [];
  return { records, header };
}

export function loadMaster(path = MASTER_CSV) {
  try {
    return parseMaster(readFileSync(path, "utf-8"));
  } catch (error) {
    error.message = `${path}: ${error.message}`;
    throw error;
  }
}

export function validateRecords(records, header = COLUMNS, { today = todayIso() } = {}) {
  const errors = [];
  const warnings = [];

  if (header.join(",") !== COLUMNS.join(",")) {
    errors.push(`header must be exactly: ${COLUMNS.join(",")}`);
    return { errors, warnings };
  }

  const jurisdictions = new Map();
  const seen = new Set();

  records.forEach((record, index) => {
    const line = index + 2;
    const fail = (message) => errors.push(`line ${line}: ${message}`);
    const warn = (message) => warnings.push(`line ${line}: ${message}`);

    for (const field of COLUMNS) {
      const value = record[field];
      if (value !== value.trim()) {
        fail(`${field} has leading/trailing whitespace: ${JSON.stringify(value)}`);
      }
    }
    for (const field of REQUIRED) {
      if (!record[field]) fail(`missing required field ${field}`);
    }

    if (!/^[A-Z]{2}$/.test(record.country_code)) {
      fail(`country_code must be 2 uppercase letters, got ${JSON.stringify(record.country_code)}`);
    }
    if (!REGIONS.includes(record.region)) {
      fail(`region must be one of ${REGIONS.join("|")}, got ${JSON.stringify(record.region)}`);
    }

    const known = jurisdictions.get(record.country_code);
    if (!known) {
      jurisdictions.set(record.country_code, {
        name: record.country_name,
        region: record.region,
      });
    } else {
      if (known.name !== record.country_name) {
        fail(`${record.country_code} has two names: ${known.name} / ${record.country_name}`);
      }
      if (known.region !== record.region) {
        fail(`${record.country_code} has two regions: ${known.region} / ${record.region}`);
      }
    }

    const key = `${record.country_code}|${record.law_name}`;
    if (seen.has(key)) fail(`duplicate ${record.country_code} + law_name "${record.law_name}"`);
    seen.add(key);

    if (!PROTECTION_TYPES.includes(record.protection_type)) {
      fail(`protection_type must be one of ${PROTECTION_TYPES.join("|")}, got ${JSON.stringify(record.protection_type)}`);
    }

    if (!/\d/.test(record.term_of_protection)) {
      fail(`term_of_protection should state a number of years, got ${JSON.stringify(record.term_of_protection)}`);
    }

    if (record.treaties_signatory) {
      const listed = record.treaties_signatory.split("/");
      for (const treaty of listed) {
        if (!TREATIES.includes(treaty)) {
          fail(`unknown treaty ${JSON.stringify(treaty)} (allowed: ${TREATIES.join(", ")})`);
        }
      }
      if (new Set(listed).size !== listed.length) fail(`treaties_signatory lists the same treaty twice: ${record.treaties_signatory}`);
    }
    // Three states per treaty: listed in treaties_signatory (party), in treaties_not_party (confirmed not a party), or in neither (not confirmed).
    if (record.treaties_not_party) {
      const listed = record.treaties_not_party.split("/");
      for (const treaty of listed) {
        if (!TREATIES.includes(treaty)) fail(`unknown treaty ${JSON.stringify(treaty)} in treaties_not_party (allowed: ${TREATIES.join(", ")})`);
      }
      if (new Set(listed).size !== listed.length) fail(`treaties_not_party lists the same treaty twice: ${record.treaties_not_party}`);
      const both = listed.filter((t) => (record.treaties_signatory || "").split("/").includes(t));
      if (both.length) fail(`${both.join(", ")} is listed both as party and as not a party`);
    }

    if (!/^(Yes|No|Not specified)( \(.+\))?$/.test(record.registration_required)) {
      fail(`registration_required must be "Yes", "No" or "Not specified" (optionally followed by a note), got ${JSON.stringify(record.registration_required)}`);
    }

    if (!DATA_STATUSES.includes(record.data_status)) {
      fail(`data_status must be one of ${DATA_STATUSES.join("|")}, got ${JSON.stringify(record.data_status)}`);
    }
    if (record.confidence && !CONFIDENCE_LEVELS.includes(record.confidence)) {
      fail(`confidence must be empty or one of ${CONFIDENCE_LEVELS.join("|")}, got ${JSON.stringify(record.confidence)}`);
    }
    if (record.last_checked && !isRealDate(record.last_checked)) {
      fail(`last_checked must be a real YYYY-MM-DD date, got ${JSON.stringify(record.last_checked)}`);
    }
    if (record.data_status !== "curated" && !record.last_checked) {
      fail(`last_checked is required when data_status is ${record.data_status}`);
    }
    if (record.data_status !== "curated" && !record.confidence) {
      fail(`confidence is required when data_status is ${record.data_status}`);
    }
    if (isRealDate(record.last_checked) && daysBetween(record.last_checked, today) > STALE_AFTER_DAYS) {
      warn(`${record.country_code} "${record.law_name}" was last checked on ${record.last_checked}: due for review`);
    }
    if (record.pending_date && !isRealDate(record.pending_date)) {
      fail(`pending_date must be empty or a real YYYY-MM-DD date, got ${JSON.stringify(record.pending_date)}`);
    }
    if (record.pending_date && !record.pending_change) fail("pending_date is set but pending_change is empty");
    if (isRealDate(record.pending_date) && record.pending_date <= today) {
      warn(`${record.country_code} "${record.law_name}": the pending change was due on ${record.pending_date}; update the row`);
    }
    if (record.linked_resources && !isHttpUrl(record.linked_resources)) {
      fail(`linked_resources must be an http(s) URL, got ${JSON.stringify(record.linked_resources)}`);
    }
    if (record.linked_resources && isHttpUrl(record.linked_resources) && isPrivateHost(record.linked_resources)) {
      fail(`linked_resources must point at the public web, not a local or private host: ${JSON.stringify(record.linked_resources)}`);
    }
  });

  return { errors, warnings };
}

/** Reads a side file next to the master CSV; a missing file is an empty table. */
export function loadSide(masterPath, file, columns) {
  const path = join(dirname(masterPath), file);
  if (!existsSync(path)) return { rows: [], header: columns, path };
  const { records, header } = parseMaster(readFileSync(path, "utf-8"));
  return { rows: records, header: records.length ? header : columns, path };
}

/** The row a side-file entry belongs to: same law name, or the jurisdiction's only law when law_name is empty. */
export function findLaw(records, code, lawName) {
  const laws = records.filter((r) => r.country_code === code);
  if (lawName) return laws.find((r) => r.law_name === lawName) ?? null;
  return laws.length === 1 ? laws[0] : null;
}

export function validateSources(sources, records, header = SOURCE_COLUMNS) {
  const errors = [];
  if (header.join(",") !== SOURCE_COLUMNS.join(",")) return [`${SOURCES_FILE}: header must be exactly: ${SOURCE_COLUMNS.join(",")}`];
  const seen = new Set();
  sources.forEach((s, index) => {
    const fail = (message) => errors.push(`${SOURCES_FILE} line ${index + 2}: ${message}`);
    const law = findLaw(records, s.country_code, s.law_name);
    if (!law) fail(`no law ${s.country_code} ${JSON.stringify(s.law_name)} in the master CSV${s.law_name ? "" : " (law_name is required when the jurisdiction has several laws)"}`);
    if (!COLUMNS.includes(s.field) || META_FIELDS.includes(s.field) || COUNTRY_FIELDS.includes(s.field)) fail(`field must be a content column, got ${JSON.stringify(s.field)}`);
    if (!isHttpUrl(s.url) || isPrivateHost(s.url)) fail(`url must be a public http(s) URL, got ${JSON.stringify(s.url)}`);
    if (s.confidence && !CONFIDENCE_LEVELS.includes(s.confidence)) fail(`confidence must be empty or one of ${CONFIDENCE_LEVELS.join("|")}`);
    if (!isRealDate(s.checked)) fail(`checked must be a real YYYY-MM-DD date, got ${JSON.stringify(s.checked)}`);
    const key = `${s.country_code}|${law?.law_name ?? s.law_name}|${s.field}`;
    if (seen.has(key)) fail(`second source for ${s.country_code} ${s.field}`);
    seen.add(key);
  });
  return errors;
}

/**
 * A value that changed after its source was last looked at may no longer be backed by it.
 * Entries recorded for the day the source was checked are fine: the check came with the edit.
 */
export function staleSourceWarnings(sources, records, changes) {
  const warnings = [];
  for (const s of sources) {
    const law = findLaw(records, s.country_code, s.law_name);
    if (!law || !isRealDate(s.checked)) continue;
    const later = changes
      .filter((c) => c.country_code === law.country_code && c.law_name === law.law_name && c.field === s.field && c.date > s.checked)
      .map((c) => c.date)
      .sort()
      .pop();
    if (later) warnings.push(`${law.country_code} "${law.law_name}" ${s.field}: changed on ${later}, but its source was last checked on ${s.checked}`);
  }
  return warnings;
}

export function validateChanges(changes, header = CHANGE_COLUMNS) {
  const errors = [];
  if (header.join(",") !== CHANGE_COLUMNS.join(",")) return [`${CHANGES_FILE}: header must be exactly: ${CHANGE_COLUMNS.join(",")}`];
  changes.forEach((c, index) => {
    const fail = (message) => errors.push(`${CHANGES_FILE} line ${index + 2}: ${message}`);
    if (!isRealDate(c.date)) fail(`date must be a real YYYY-MM-DD date, got ${JSON.stringify(c.date)}`);
    if (!/^[A-Z]{2}$/.test(c.country_code)) fail(`country_code must be 2 uppercase letters, got ${JSON.stringify(c.country_code)}`);
    if (!c.law_name) fail("law_name is required");
    if (c.field !== ROW_CHANGE && !COLUMNS.includes(c.field)) fail(`field must be a column or "${ROW_CHANGE}", got ${JSON.stringify(c.field)}`);
  });
  return errors;
}

export function loadAndValidate(path = MASTER_CSV, options = {}) {
  const { records, header } = loadMaster(path);
  const { errors, warnings } = validateRecords(records, header, options);
  const sources = loadSide(path, SOURCES_FILE, SOURCE_COLUMNS);
  const changes = loadSide(path, CHANGES_FILE, CHANGE_COLUMNS);
  if (!errors.length) {
    errors.push(...validateSources(sources.rows, records, sources.header), ...validateChanges(changes.rows, changes.header));
    if (!errors.length) warnings.push(...staleSourceWarnings(sources.rows, records, changes.rows));
  }
  return { records, errors, warnings, sources: sources.rows, changes: changes.rows };
}

/**
 * What changed between two versions of the master CSV, as change-history entries.
 * Rows are matched by country + law name; a country with one unmatched row on each side is a rename.
 * Columns missing from either version (added or dropped since) and bookkeeping columns are left out.
 */
export function diffRecords(before, after, date) {
  const entries = [];
  const key = (r) => `${r.country_code}|${r.law_name}`;
  const beforeByKey = new Map(before.map((r) => [key(r), r]));
  const afterKeys = new Set(after.map(key));
  const pairs = [];
  const added = [];
  for (const row of after) {
    const old = beforeByKey.get(key(row));
    if (old) pairs.push([old, row]);
    else added.push(row);
  }
  const removed = before.filter((r) => !afterKeys.has(key(r)));
  for (const row of [...added]) {
    const sameCountryAdded = added.filter((r) => r.country_code === row.country_code);
    const sameCountryRemoved = removed.filter((r) => r.country_code === row.country_code);
    if (sameCountryAdded.length === 1 && sameCountryRemoved.length === 1) {
      pairs.push([sameCountryRemoved[0], row]);
      added.splice(added.indexOf(row), 1);
      removed.splice(removed.indexOf(sameCountryRemoved[0]), 1);
    }
  }
  const fields = COLUMNS.filter((c) => !META_FIELDS.includes(c) && !COUNTRY_FIELDS.includes(c));
  for (const [old, row] of pairs) {
    for (const field of fields) {
      if (!(field in old) || !(field in row) || old[field] === row[field]) continue;
      entries.push({ date, country_code: row.country_code, law_name: row.law_name, field, old_value: old[field], new_value: row[field] });
    }
  }
  for (const row of added) entries.push({ date, country_code: row.country_code, law_name: row.law_name, field: ROW_CHANGE, old_value: "", new_value: "added" });
  for (const row of removed) entries.push({ date, country_code: row.country_code, law_name: row.law_name, field: ROW_CHANGE, old_value: "", new_value: "removed" });
  return entries;
}

/** CSV with quotes only where needed (the format of the hand-edited master and side files). */
export function writeCsv(path, columns, rows) {
  const quote = (value) => {
    const text = String(value ?? "");
    return /[",\r\n]/.test(text) || text !== text.trim() ? `"${text.replace(/"/g, '""')}"` : text;
  };
  writeFileSync(path, `${[columns.join(","), ...rows.map((r) => columns.map((c) => quote(r[c])).join(","))].join("\n")}\n`);
}

const jsonFile = (value) => `${JSON.stringify(value, null, 2)}\n`;

// A cell that starts with =, +, - or @ is read as a formula by spreadsheets: prefix a quote (same rule as the app's CSV export).
const FORMULA_START = /^[=+\-@\t\r]/;

export function toCsv(columns, rows) {
  const quote = (value) => {
    let text = String(value ?? "");
    if (FORMULA_START.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g, '""')}"`;
  };
  const lines = [columns.join(",")];
  for (const row of rows) lines.push(columns.map((c) => quote(row[c])).join(","));
  return `${lines.join("\n")}\n`;
}

/**
 * Each law as published: its columns plus `sources` ({field: {url, confidence, checked}})
 * and `changes` (newest first: {date, field, from, to}).
 */
export function withSideData(records, sources = [], changes = []) {
  return records.map((record) => {
    const own = (entry) => findLaw(records, entry.country_code, entry.law_name) === record;
    const law = { ...record, sources: {}, changes: [] };
    for (const s of sources.filter(own)) law.sources[s.field] = { url: s.url, confidence: s.confidence, checked: s.checked };
    law.changes = changes
      .filter((c) => c.country_code === record.country_code && c.law_name === record.law_name)
      .map((c) => ({ date: c.date, field: c.field, from: c.old_value, to: c.new_value }))
      .reverse() // the file is in recording order: on the same day, the later entry comes first
      .sort((a, b) => (a.date < b.date ? 1 : a.date > b.date ? -1 : 0));
    return law;
  });
}

export function buildArtifacts(records, { sources = [], changes = [] } = {}) {
  const byCode = new Map();
  for (const record of withSideData(records, sources, changes)) {
    if (!byCode.has(record.country_code)) byCode.set(record.country_code, []);
    byCode.get(record.country_code).push(record);
  }

  const jurisdictions = [...byCode.entries()]
    .map(([code, laws]) => ({
      code,
      tld: tldOf(code),
      name: laws[0].country_name,
      region: laws[0].region,
      lawCount: laws.length,
      laws,
    }))
    .sort((a, b) => (a.name < b.name ? -1 : a.name > b.name ? 1 : 0));

  const files = new Map();
  for (const j of jurisdictions) {
    files.set(
      `${j.tld}/info.json`,
      jsonFile({ code: j.code, name: j.name, tld: j.tld, region: j.region, lawCount: j.lawCount })
    );
    files.set(`${j.tld}/laws.json`, jsonFile(j.laws));
    files.set(`${j.tld}/laws.csv`, toCsv(COLUMNS, j.laws));
  }

  const strip = (law) => {
    const copy = { ...law };
    for (const field of COUNTRY_FIELDS) delete copy[field];
    return copy;
  };
  const bundleJurisdictions = jurisdictions.map((j) => ({
    code: j.code,
    tld: j.tld,
    name: j.name,
    region: j.region,
    lawCount: j.lawCount,
    laws: j.laws.map(strip),
  }));
  const version = createHash("sha256")
    .update(JSON.stringify({ columns: COLUMNS, jurisdictions: bundleJurisdictions }))
    .digest("hex")
    .slice(0, 12);

  return { jurisdictions, files, bundle: { version, jurisdictions: bundleJurisdictions } };
}

function renderBundle({ version, jurisdictions }, existingText) {
  let generatedAt = new Date().toISOString();
  if (existingText) {
    try {
      const previous = JSON.parse(existingText);
      if (previous.version === version && previous.generatedAt) generatedAt = previous.generatedAt;
    } catch {
      // unreadable previous bundle: fall through and stamp a fresh date
    }
  }
  return `${JSON.stringify({
    module: MODULE,
    schema: SCHEMA_VERSION,
    version,
    generatedAt,
    columns: COLUMNS,
    jurisdictions,
  })}\n`;
}

export function planWrites(artifacts, dataDir = DATA_DIR) {
  const plan = [];
  const read = (rel) => {
    const path = join(dataDir, rel);
    return existsSync(path) ? readFileSync(path, "utf-8") : null;
  };

  for (const [rel, content] of artifacts.files) {
    const current = read(rel);
    plan.push({
      rel,
      content,
      status: current === null ? "new" : current === content ? "same" : "changed",
    });
  }

  const currentBundle = read(BUNDLE_FILE);
  const bundleText = renderBundle(artifacts.bundle, currentBundle);
  plan.push({
    rel: BUNDLE_FILE,
    content: bundleText,
    status: currentBundle === null ? "new" : currentBundle === bundleText ? "same" : "changed",
  });
  return plan;
}

export function findOrphans(artifacts, dataDir = DATA_DIR) {
  const expected = new Set(artifacts.jurisdictions.map((j) => j.tld));
  return readdirSync(dataDir, { withFileTypes: true })
    .filter((d) => d.isDirectory() && /^[a-z]{2}$/.test(d.name) && !expected.has(d.name))
    .map((d) => d.name);
}

export function applyWrites(plan, dataDir = DATA_DIR) {
  for (const item of plan) {
    if (item.status === "same") continue;
    const path = join(dataDir, item.rel);
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, item.content);
  }
}
