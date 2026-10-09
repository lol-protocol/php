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
export const MODULE = "privacy-laws";
export const DATA_DIR = join(ROOT, "countries");
export const MASTER_CSV = join(DATA_DIR, "privacy_laws_master.csv");
export const BUNDLE_FILE = "index.json";
export const SCHEMA_VERSION = 1;

export const COLUMNS = [
  "country_code",
  "country_name",
  "region",
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
  "website_url",
  "language",
  "frameworks",
  "frameworks_not",
  "notes",
];

export const REGIONS = [
  "europe",
  "americas",
  "asia_pacific",
  "middle_east_africa",
];

// International privacy frameworks a country takes part in (`/`-separated in the CSV).
//   GDPR         EU/EEA state: the GDPR applies directly
//   EU-Adequacy  European Commission adequacy decision in force (full or partial)
//   CoE-108      Party to Council of Europe Convention 108 (or 108+)
//   APEC-CBPR    Participant in the APEC / Global CBPR system
export const FRAMEWORKS = ["GDPR", "EU-Adequacy", "CoE-108", "APEC-CBPR"];

const REQUIRED = [
  "country_code",
  "country_name",
  "region",
  "law_name",
  "jurisdiction",
  "enactment_date",
  "effective_date",
  "enforcement_authority",
  "website_url",
  "language",
];

const COUNTRY_FIELDS = ["country_code", "country_name", "region"];

export const tldOf = (code) => code.toLowerCase();

const isRealDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const d = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
};

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

export function validateRecords(records, header = COLUMNS) {
  const errors = [];
  const warnings = [];

  if (header.join(",") !== COLUMNS.join(",")) {
    errors.push(`header must be exactly: ${COLUMNS.join(",")}`);
    return { errors, warnings };
  }

  const countries = new Map();
  const seen = new Set();

  records.forEach((record, index) => {
    const line = index + 2;
    const fail = (message) => errors.push(`line ${line}: ${message}`);

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

    const known = countries.get(record.country_code);
    if (!known) {
      countries.set(record.country_code, {
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

    for (const field of ["enactment_date", "effective_date"]) {
      if (!isRealDate(record[field])) fail(`${field} must be a real YYYY-MM-DD date, got ${JSON.stringify(record[field])}`);
    }
    if (
      isRealDate(record.enactment_date) &&
      isRealDate(record.effective_date) &&
      record.effective_date < record.enactment_date
    ) {
      fail(`effective_date ${record.effective_date} is before enactment_date ${record.enactment_date}`);
    }

    if (!isHttpUrl(record.website_url)) {
      fail(`website_url must be an http(s) URL, got ${JSON.stringify(record.website_url)}`);
    }
    if (record.website_url && isHttpUrl(record.website_url) && isPrivateHost(record.website_url)) {
      fail(`website_url must point at the public web, not a local or private host: ${JSON.stringify(record.website_url)}`);
    }

    if (record.frameworks) {
      const listed = record.frameworks.split("/");
      for (const framework of listed) {
        if (!FRAMEWORKS.includes(framework)) fail(`unknown framework ${JSON.stringify(framework)} (allowed: ${FRAMEWORKS.join(", ")})`);
      }
      if (new Set(listed).size !== listed.length) fail(`frameworks lists the same value twice: ${record.frameworks}`);
    }
    // Three states per framework: in frameworks (takes part), in frameworks_not (confirmed not), or in neither (not confirmed).
    if (record.frameworks_not) {
      const listed = record.frameworks_not.split("/");
      for (const framework of listed) {
        if (!FRAMEWORKS.includes(framework)) fail(`unknown framework ${JSON.stringify(framework)} in frameworks_not (allowed: ${FRAMEWORKS.join(", ")})`);
      }
      if (new Set(listed).size !== listed.length) fail(`frameworks_not lists the same value twice: ${record.frameworks_not}`);
      const both = listed.filter((f) => (record.frameworks || "").split("/").includes(f));
      if (both.length) fail(`${both.join(", ")} is listed both in frameworks and in frameworks_not`);
    }

    if (!/^[A-Z][A-Za-z]+(\/[A-Z][A-Za-z]+)*$/.test(record.language)) {
      fail(`language must look like "English" or "French/German", got ${JSON.stringify(record.language)}`);
    }
  });

  return { errors, warnings };
}

export function loadAndValidate(path = MASTER_CSV) {
  const { records, header } = loadMaster(path);
  const { errors, warnings } = validateRecords(records, header);
  return { records, errors, warnings };
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

export function buildArtifacts(records) {
  const byCode = new Map();
  for (const record of records) {
    if (!byCode.has(record.country_code)) byCode.set(record.country_code, []);
    byCode.get(record.country_code).push(record);
  }

  const countries = [...byCode.entries()]
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
  for (const c of countries) {
    files.set(
      `${c.tld}/info.json`,
      jsonFile({ code: c.code, name: c.name, tld: c.tld, region: c.region, lawCount: c.lawCount })
    );
    files.set(`${c.tld}/laws.json`, jsonFile(c.laws));
    files.set(`${c.tld}/laws.csv`, toCsv(COLUMNS, c.laws));
  }

  const strip = (law) => {
    const copy = { ...law };
    for (const field of COUNTRY_FIELDS) delete copy[field];
    return copy;
  };
  const bundleCountries = countries.map((c) => ({
    code: c.code,
    tld: c.tld,
    name: c.name,
    region: c.region,
    lawCount: c.lawCount,
    laws: c.laws.map(strip),
  }));
  const version = createHash("sha256")
    .update(JSON.stringify({ columns: COLUMNS, countries: bundleCountries }))
    .digest("hex")
    .slice(0, 12);

  return { countries, files, bundle: { version, countries: bundleCountries } };
}

function renderBundle({ version, countries }, existingText) {
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
    countries,
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

  const bundleText = renderBundle(artifacts.bundle, read(BUNDLE_FILE));
  const currentBundle = read(BUNDLE_FILE);
  plan.push({
    rel: BUNDLE_FILE,
    content: bundleText,
    status: currentBundle === null ? "new" : currentBundle === bundleText ? "same" : "changed",
  });
  return plan;
}

export function findOrphans(artifacts, dataDir = DATA_DIR) {
  const expected = new Set(artifacts.countries.map((c) => c.tld));
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
