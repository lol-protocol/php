import { readFileSync, writeFileSync, mkdirSync } from "fs";
import { parse } from "csv-parse/sync";
import { join } from "path";

const CSV_PATH = "./countries/privacy_laws_master.csv";
const COUNTRIES_DIR = "./countries";

// Map of country codes to TLDs
const countryTlds = {
  EU: "eu",
  US: "us",
  CA: "ca",
  GB: "gb",
  BR: "br",
  JP: "jp",
  AU: "au",
  IN: "in",
  ZA: "za",
  MX: "mx",
  CH: "ch",
  SG: "sg",
  NZ: "nz",
  KR: "kr",
  SV: "sv",
};

const regionMap = {
  EU: "europe",
  GB: "europe",
  CH: "europe",
  FR: "europe",
  ES: "europe",
  NL: "europe",
  US: "americas",
  CA: "americas",
  BR: "americas",
  MX: "americas",
  CL: "americas",
  SV: "americas",
  JP: "asia_pacific",
  AU: "asia_pacific",
  SG: "asia_pacific",
  NZ: "asia_pacific",
  KR: "asia_pacific",
  TH: "asia_pacific",
  IN: "asia_pacific",
  ZA: "middle_east_africa",
};

// Si la carpeta ya tiene info.json se conserva su createdAt: así volver a generar
// no cambia ningún archivo cuyos datos no cambiaron.
function creadoAntes(rutaInfo) {
  try {
    return JSON.parse(readFileSync(rutaInfo, "utf-8")).createdAt || new Date().toISOString();
  } catch {
    return new Date().toISOString();
  }
}

async function reorganizeByCountry() {
  try {
    // Read master CSV
    const csvContent = readFileSync(CSV_PATH, "utf-8");
    const records = parse(csvContent, {
      columns: true,
      skip_empty_lines: true,
    });

    console.log(`✓ Loaded ${records.length} records from master CSV`);

    // Group by country
    const byCountry = {};
    records.forEach((record) => {
      if (!byCountry[record.country_code]) {
        byCountry[record.country_code] = [];
      }
      byCountry[record.country_code].push(record);
    });

    console.log(
      `✓ Grouped into ${Object.keys(byCountry).length} countries`
    );

    // Create directories and files per country
    for (const [countryCode, laws] of Object.entries(byCountry)) {
      const tld = countryTlds[countryCode] || countryCode.toLowerCase();
      const countryDir = join(COUNTRIES_DIR, tld);

      // Create directory
      mkdirSync(countryDir, { recursive: true });

      // Create info.json
      const countryInfo = {
        code: countryCode,
        name: laws[0].country_name,
        tld: tld,
        region: regionMap[countryCode] || "other",
        lawCount: laws.length,
        createdAt: creadoAntes(join(countryDir, "info.json")),
      };

      writeFileSync(
        join(countryDir, "info.json"),
        JSON.stringify(countryInfo, null, 2)
      );

      // Create laws.csv
      const headers = Object.keys(laws[0]);
      let csv = headers.join(",") + "\n";
      laws.forEach((law) => {
        csv += headers
          .map((h) => `"${(law[h] || "").replace(/"/g, '""')}"`)
          .join(",");
        csv += "\n";
      });

      writeFileSync(join(countryDir, "laws.csv"), csv);

      // Create laws.json
      writeFileSync(
        join(countryDir, "laws.json"),
        JSON.stringify(laws, null, 2)
      );

      console.log(
        `✓ Created ${tld}/ with ${laws.length} law(s): ${laws.map((l) => l.law_name).join(", ")}`
      );
    }

    console.log("\n✅ Reorganization complete!");
    console.log("Structure created:");
    console.log("  countries/");
    Object.keys(countryTlds).forEach((code) => {
      const tld = countryTlds[code];
      console.log(`    ${tld}/`);
      console.log(`      ├── info.json`);
      console.log(`      ├── laws.csv`);
      console.log(`      └── laws.json`);
    });
  } catch (error) {
    console.error("❌ Error:", error.message);
    process.exit(1);
  }
}

reorganizeByCountry();
