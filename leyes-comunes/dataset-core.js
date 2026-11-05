// Lo que comparten privacy-laws/scripts/lib/dataset.js y copyright-laws/scripts/lib/dataset.js: nombres de archivos,
// niveles de confianza, validación de fechas y URL, avisos de fuentes, y la escritura de CSV. Estaba copiado en los dos
// módulos; lo que sí cambia entre ellos (columnas, regiones, reglas de cada fila, artefactos) se queda en cada dataset.js.
// Solo usa módulos de Node: no depende de ningún paquete, así que se puede importar desde esta carpeta.
import { writeFileSync } from "node:fs";

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
