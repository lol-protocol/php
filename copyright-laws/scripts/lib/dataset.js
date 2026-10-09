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

const COUNTRY_FIELDS = ["country_code", "country_name", "region"];

export const tldOf = (code) => code.toLowerCase();

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

  const jurisdictions = new Map();
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

    if (!/^(Yes|No)( \(.+\))?$/.test(record.registration_required)) {
      fail(`registration_required must be "Yes"/"No" (optionally followed by a note), got ${JSON.stringify(record.registration_required)}`);
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
