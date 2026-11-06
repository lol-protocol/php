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
import {
  BUNDLE_FILE,
  CHANGES_FILE,
  CHANGE_COLUMNS,
  CONFIDENCE_LEVELS,
  COUNTRY_FIELDS,
  DATA_STATUSES,
  ROW_CHANGE,
  SCHEMA_VERSION,
  SOURCES_FILE,
  SOURCE_COLUMNS,
  STALE_AFTER_DAYS,
  TRANSLATIONS_FILE,
  TRANSLATION_COLUMNS,
  TRANSLATION_LANGS,
  daysBetween,
  findLaw,
  isHttpUrl,
  isPrivateHost,
  isRealDate,
  jsonFile,
  newerSourceWarnings,
  staleSourceWarnings,
  tldOf,
  toCsv,
  todayIso,
  translationFile,
  withSideData,
  writeCsv,
} from "../../../leyes-comunes/dataset-core.js";

// Lo que antes se definía aquí y ahora vive en leyes-comunes/dataset-core.js (igual en privacy-laws y copyright-laws).
export {
  BUNDLE_FILE,
  CHANGES_FILE,
  CHANGE_COLUMNS,
  CONFIDENCE_LEVELS,
  DATA_STATUSES,
  ROW_CHANGE,
  SCHEMA_VERSION,
  SOURCES_FILE,
  SOURCE_COLUMNS,
  STALE_AFTER_DAYS,
  TRANSLATIONS_FILE,
  TRANSLATION_COLUMNS,
  TRANSLATION_LANGS,
  findLaw,
  newerSourceWarnings,
  staleSourceWarnings,
  tldOf,
  toCsv,
  todayIso,
  translationFile,
  withSideData,
  writeCsv,
};

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..");
export const MODULE = "copyright-laws";
export const DATA_DIR = join(ROOT, "jurisdictions");
export const MASTER_CSV = join(DATA_DIR, "copyright_laws_master.csv");

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
// Bookkeeping columns: not content, so neither sourced per field nor recorded in the change history.
export const META_FIELDS = ["data_status", "confidence", "last_checked"];

// Side files next to the master CSV.
//   field_sources.csv  where one value of one law comes from (law_name may be empty when the jurisdiction has a single law)
//   changes.csv        what changed in a row and when (written by `npm run changes`)
export const TRANSLATED_FIELDS = [
  "term_of_protection",
  "author_rights",
  "moral_rights",
  "orphan_works",
  "digital_protection",
  "fair_use_exceptions",
  "registration_required",
];

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
    if (isRealDate(record.last_checked) && record.last_checked > today) {
      fail(`last_checked is in the future (${record.last_checked}); it would hide the row from the review reminder`);
    }
    if (record.data_status !== "curated" && !record.last_checked) {
      fail(`last_checked is required when data_status is ${record.data_status}`);
    }
    if (record.data_status !== "curated" && !record.confidence) {
      fail(`confidence is required when data_status is ${record.data_status}`);
    }
    if (record.data_status === "curated" && !record.last_checked) {
      warn(`${record.country_code} "${record.law_name}" is curated and has never been checked: add last_checked once its sources are verified`);
    }
    if (!record.linked_resources) {
      fail("linked_resources is required: every row needs the source it comes from");
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
    else if (s.checked > todayIso()) fail(`checked is in the future (${s.checked})`);
    // The "(unconfirmed)" mark in the text and the source's confidence say the same thing: they cannot disagree.
    if (law && COLUMNS.includes(s.field) && /\(unconfirmed\)\s*$/i.test(law[s.field] ?? "") && (s.confidence === "medium" || s.confidence === "high")) {
      fail(`${s.country_code} ${s.field} ends in "(unconfirmed)" but its source says confidence ${s.confidence}: fix the text or the confidence`);
    }
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

/** The English values that can be translated: every non-empty descriptive value of every law. */
export function translatableValues(records) {
  const values = new Set();
  for (const record of records) {
    for (const field of TRANSLATED_FIELDS) {
      const value = (record[field] ?? "").trim();
      if (value && !/^not specified$/i.test(value)) values.add(value);
    }
  }
  return values;
}

export function validateTranslations(rows, records, header = TRANSLATION_COLUMNS) {
  const errors = [];
  const warnings = [];
  if (header.join(",") !== TRANSLATION_COLUMNS.join(",")) return { errors: [`${TRANSLATIONS_FILE}: header must be exactly: ${TRANSLATION_COLUMNS.join(",")}`], warnings };
  const live = translatableValues(records);
  const seen = new Set();
  let orphans = 0;
  rows.forEach((row, index) => {
    const fail = (message) => errors.push(`${TRANSLATIONS_FILE} line ${index + 2}: ${message}`);
    if (!TRANSLATION_LANGS.includes(row.lang)) fail(`lang must be one of ${TRANSLATION_LANGS.join("|")}, got ${JSON.stringify(row.lang)}`);
    if (!row.source || !row.text) fail("source and text are required");
    if (row.text !== row.text.trim()) fail("text has leading/trailing whitespace");
    const key = `${row.lang}|${row.source}`;
    if (seen.has(key)) fail(`second translation of ${JSON.stringify(row.source.slice(0, 60))} into ${row.lang}`);
    seen.add(key);
    if (!live.has(row.source)) orphans++;
  });
  if (orphans) warnings.push(`${TRANSLATIONS_FILE}: ${orphans} translation(s) no longer match any value (they are not published); remove them or translate the new text`);
  return { errors, warnings };
}

/** { lang: { english text: translation } } for the values that still exist. */
export function translationDictionaries(rows, records) {
  const live = translatableValues(records);
  const dictionaries = {};
  for (const row of rows) {
    if (!live.has(row.source)) continue;
    (dictionaries[row.lang] ??= {})[row.source] = row.text;
  }
  for (const lang of Object.keys(dictionaries)) {
    dictionaries[lang] = Object.fromEntries(Object.entries(dictionaries[lang]).sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0)));
  }
  return dictionaries;
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
  const translations = loadSide(path, TRANSLATIONS_FILE, TRANSLATION_COLUMNS);
  if (!errors.length) {
    const t = validateTranslations(translations.rows, records, translations.header);
    errors.push(...t.errors);
    warnings.push(...t.warnings);
    errors.push(...validateSources(sources.rows, records, sources.header), ...validateChanges(changes.rows, changes.header));
    if (!errors.length) warnings.push(...staleSourceWarnings(sources.rows, records, changes.rows), ...newerSourceWarnings(sources.rows, records));
  }
  return { records, errors, warnings, sources: sources.rows, changes: changes.rows, translations: translations.rows };
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

/**
 * Each law as published: its columns plus `sources` ({field: {url, confidence, checked}})
 * and `changes` (newest first: {date, field, from, to}).
 */

export function buildArtifacts(records, { sources = [], changes = [], translations = [] } = {}) {
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

  for (const [lang, dictionary] of Object.entries(translationDictionaries(translations, records))) {
    files.set(translationFile(lang), jsonFile(dictionary));
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
