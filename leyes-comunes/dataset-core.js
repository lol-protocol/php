// Lo que comparten privacy-laws/scripts/lib/dataset.js y copyright-laws/scripts/lib/dataset.js: nombres de archivos,
// niveles de confianza, validación de fechas y URL, avisos de fuentes, y la escritura de CSV. Estaba copiado en los dos
// módulos; lo que sí cambia entre ellos (columnas, regiones, reglas de cada fila, artefactos) se queda en cada dataset.js.
// Solo usa módulos de Node: no depende de ningún paquete, así que se puede importar desde esta carpeta.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";

export const BUNDLE_FILE = "index.json";

export const SCHEMA_VERSION = 1;

// Where a row's data comes from: the original curated list, web research from secondary sources
// (with the researcher's confidence and the date), or a check against the official text.
export const DATA_STATUSES = ["verified", "researched", "curated"];

export const CONFIDENCE_LEVELS = ["high", "medium", "low"];

// A row whose last check is older than this is due for review (a warning, not an error).
export const STALE_AFTER_DAYS = 365;

// Archivos al lado del CSV maestro: field_sources.csv (de dónde sale cada valor) y changes.csv (qué cambió y cuándo).
export const SOURCES_FILE = "field_sources.csv";

export const CHANGES_FILE = "changes.csv";

// Machine translations of the descriptive values (`lang,source,text`): the app shows them in the interface language,
// marked as machine translations. The key is the exact English text, so an edited value simply loses its translation.
export const TRANSLATIONS_FILE = "value_translations.csv";

export const TRANSLATION_COLUMNS = ["lang", "source", "text"];

export const TRANSLATION_LANGS = ["spa", "fra", "deu", "por"];

export const translationFile = (lang) => `translations.${lang}.json`;

export const SOURCE_COLUMNS = ["country_code", "law_name", "field", "url", "confidence", "checked"];

export const CHANGE_COLUMNS = ["date", "country_code", "law_name", "field", "old_value", "new_value"];

// `field` of a change that adds or removes a whole row.
export const ROW_CHANGE = "row";

export const COUNTRY_FIELDS = ["country_code", "country_name", "region"];

export const tldOf = (code) => code.toLowerCase();

export const isRealDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const d = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
};

export const todayIso = () => new Date().toISOString().slice(0, 10);

export const daysBetween = (from, to) => (Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86_400_000;

export const isHttpUrl = (value) => {
  // new URL() acepta espacios y barras invertidas (los navegadores los arreglan solos); en una fuente de datos son un error de tipeo.
  if (/[\s\\]/.test(value)) return false;
  try {
    const u = new URL(value);
    return u.protocol === "http:" || u.protocol === "https:";
  } catch {
    return false;
  }
};

// Reference URLs are fetched by the text cache and shown as links: keep them pointing at the public web.
export const PRIVATE_IPV4 = /^(0\.|10\.|127\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)/;

export const isPrivateHost = (value) => {
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

/** The row a side-file entry belongs to: same law name, or the only law of the country when law_name is empty. */
export function findLaw(records, code, lawName) {
  const laws = records.filter((r) => r.country_code === code);
  if (lawName) return laws.find((r) => r.law_name === lawName) ?? null;
  return laws.length === 1 ? laws[0] : null;
}

export function newerSourceWarnings(sources, records) {
  const warnings = [];
  for (const s of sources) {
    const law = findLaw(records, s.country_code, s.law_name);
    if (law?.last_checked && isRealDate(s.checked) && s.checked > law.last_checked) {
      warnings.push(`${law.country_code} "${law.law_name}" ${s.field}: its source was checked on ${s.checked}, after the row's last_checked (${law.last_checked}); bring last_checked up to date`);
    }
  }
  return warnings;
}

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

export function writeCsv(path, columns, rows) {
  const quote = (value) => {
    const text = String(value ?? "");
    return /[",\r\n]/.test(text) || text !== text.trim() ? `"${text.replace(/"/g, '""')}"` : text;
  };
  writeFileSync(path, `${[columns.join(","), ...rows.map((r) => columns.map((c) => quote(r[c])).join(","))].join("\n")}\n`);
}

export const jsonFile = (value) => `${JSON.stringify(value, null, 2)}\n`;

// A cell that starts with =, +, - or @ is read as a formula by spreadsheets: prefix a quote (same rule as the app's CSV export).
export const FORMULA_START = /^[=+\-@\t\r]/;

/** CSV with quotes only where needed (the format of the hand-edited master and side files). */
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

/**
 * Lo que en privacy-laws y copyright-laws era idéntico pero necesitaba algo de cada módulo: leer el CSV maestro y
 * sus archivos laterales, validar traducciones y cambios, validar todo junto, comparar dos versiones del CSV y
 * escribir los artefactos. Cada dataset.js lo llama una vez con lo suyo y re-exporta el resultado.
 *
 * @param {object} cfg
 * @param {string} cfg.masterCsv        ruta del CSV maestro
 * @param {string} cfg.dataDir          carpeta de salida de los artefactos
 * @param {string[]} cfg.columns        columnas del CSV maestro
 * @param {string[]} cfg.metaFields     columnas de control (no cuentan como cambio de una ley)
 * @param {string[]} cfg.translatedFields  columnas con texto traducible
 * @param {Function} cfg.parseMaster    texto CSV -> { records, header } (usa csv-parse, que vive en cada módulo)
 * @param {Function} cfg.validateRecords  reglas de cada fila (distintas en cada módulo)
 * @param {Function} cfg.validateSources  reglas de field_sources.csv (distintas en cada módulo)
 */
export function createDataset({ masterCsv, dataDir: defaultDataDir, columns, metaFields, translatedFields, parseMaster, validateRecords, validateSources }) {
  function loadMaster(path = masterCsv) {
    try {
      return parseMaster(readFileSync(path, "utf-8"));
    } catch (error) {
      error.message = `${path}: ${error.message}`;
      throw error;
    }
  }

  /** Reads a side file next to the master CSV; a missing file is an empty table. */
  function loadSide(masterPath, file, columns) {
    const path = join(dirname(masterPath), file);
    if (!existsSync(path)) return { rows: [], header: columns, path };
    const { records, header } = parseMaster(readFileSync(path, "utf-8"));
    return { rows: records, header: records.length ? header : columns, path };
  }

  /** The English values that can be translated: every non-empty descriptive value of every law. */
  function translatableValues(records) {
    const values = new Set();
    for (const record of records) {
      for (const field of translatedFields) {
        const value = (record[field] ?? "").trim();
        if (value && !/^not specified$/i.test(value)) values.add(value);
      }
    }
    return values;
  }

  function validateTranslations(rows, records, header = TRANSLATION_COLUMNS) {
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
  function translationDictionaries(rows, records) {
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

  function validateChanges(changes, header = CHANGE_COLUMNS) {
    const errors = [];
    if (header.join(",") !== CHANGE_COLUMNS.join(",")) return [`${CHANGES_FILE}: header must be exactly: ${CHANGE_COLUMNS.join(",")}`];
    changes.forEach((c, index) => {
      const fail = (message) => errors.push(`${CHANGES_FILE} line ${index + 2}: ${message}`);
      if (!isRealDate(c.date)) fail(`date must be a real YYYY-MM-DD date, got ${JSON.stringify(c.date)}`);
      if (!/^[A-Z]{2}$/.test(c.country_code)) fail(`country_code must be 2 uppercase letters, got ${JSON.stringify(c.country_code)}`);
      if (!c.law_name) fail("law_name is required");
      if (c.field !== ROW_CHANGE && !columns.includes(c.field)) fail(`field must be a column or "${ROW_CHANGE}", got ${JSON.stringify(c.field)}`);
    });
    return errors;
  }

  function loadAndValidate(path = masterCsv, options = {}) {
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
  function diffRecords(before, after, date) {
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
    const fields = columns.filter((c) => !metaFields.includes(c) && !COUNTRY_FIELDS.includes(c));
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

  function applyWrites(plan, dataDir = defaultDataDir) {
    for (const item of plan) {
      if (item.status === "same") continue;
      const path = join(dataDir, item.rel);
      mkdirSync(dirname(path), { recursive: true });
      writeFileSync(path, item.content);
    }
  }

  return { loadMaster, loadSide, translatableValues, validateTranslations, translationDictionaries, validateChanges, loadAndValidate, diffRecords, applyWrites };
}
