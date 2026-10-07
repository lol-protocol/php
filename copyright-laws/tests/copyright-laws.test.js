import { test } from "node:test";
import { strict as assert } from "node:assert";
import { readFileSync } from "node:fs";
import { parse } from "csv-parse/sync";

test("CSV Validation - Copyright Laws Master", async (t) => {
  const csvContent = readFileSync(
    "./jurisdictions/copyright_laws_master.csv",
    "utf-8"
  );
  const records = parse(csvContent, {
    columns: true,
    skip_empty_lines: true,
  });

  await t.test("has records", () => {
    assert.ok(records.length > 0, "CSV should contain records");
  });

  await t.test("all records have required fields", () => {
    const requiredFields = [
      "country_code",
      "country_name",
      "law_name",
      "protection_type",
      "term_of_protection",
    ];

    records.forEach((record, index) => {
      requiredFields.forEach((field) => {
        assert.ok(
          record[field] !== undefined && record[field] !== "",
          `Record ${index} missing required field: ${field}`
        );
      });
    });
  });

  await t.test("country_code is ISO 3166-1 alpha-2 format", () => {
    const isoPattern = /^[A-Z]{2}$/;
    records.forEach((record, index) => {
      assert.match(
        record.country_code,
        isoPattern,
        `Record ${index}: Invalid ISO code ${record.country_code}`
      );
    });
  });

  await t.test("protection_type is valid category", () => {
    const validTypes = new Set([
      "Copyright",
      "Related rights",
      "Database rights",
      "Moral rights",
    ]);
    records.forEach((record, index) => {
      assert.ok(
        validTypes.has(record.protection_type),
        `Record ${index}: Unknown protection_type ${record.protection_type}`
      );
    });
  });

  await t.test("no duplicate country_code + law_name combinations", () => {
    const seen = new Set();
    records.forEach((record, index) => {
      const key = `${record.country_code}_${record.law_name}`;
      assert.ok(
        !seen.has(key),
        `Record ${index}: Duplicate combination ${key}`
      );
      seen.add(key);
    });
  });

  await t.test("linked_resources contains valid URL format when present", () => {
    const urlPattern = /^https?:\/\/.+/;
    records.forEach((record, index) => {
      if (record.linked_resources) {
        assert.match(
          record.linked_resources,
          urlPattern,
          `Record ${index}: Invalid URL ${record.linked_resources}`
        );
      }
    });
  });

  await t.test("term_of_protection is descriptive and non-empty", () => {
    records.forEach((record, index) => {
      assert.ok(
        record.term_of_protection.length > 5,
        `Record ${index}: Term too short or missing detail`
      );
    });
  });
});

test("Treaty Affiliation Checks", async (t) => {
  const csvContent = readFileSync(
    "./jurisdictions/copyright_laws_master.csv",
    "utf-8"
  );
  const records = parse(csvContent, {
    columns: true,
    skip_empty_lines: true,
  });

  await t.test("treaties_signatory contains valid treaty abbreviations", () => {
    const validTreaties = new Set(["Berne", "TRIPS", "WCT"]);
    records.forEach((record, index) => {
      if (record.treaties_signatory) {
        const treaties = record.treaties_signatory.split("/");
        treaties.forEach((treaty) => {
          assert.ok(
            validTreaties.has(treaty.trim()),
            `Record ${index}: Unknown treaty ${treaty}`
          );
        });
      }
    });
  });

  await t.test("enforcement_body is not empty", () => {
    records.forEach((record, index) => {
      assert.ok(
        record.enforcement_body !== "",
        `Record ${index}: Missing enforcement_body`
      );
    });
  });
});

test("Jurisdiction Data Completeness", async (t) => {
  const csvContent = readFileSync(
    "./jurisdictions/copyright_laws_master.csv",
    "utf-8"
  );
  const records = parse(csvContent, {
    columns: true,
    skip_empty_lines: true,
  });

  await t.test("country_name matches known jurisdictions", () => {
    const knownJurisdictions = new Set([
      "European Union",
      "United States",
      "United Kingdom",
      "Canada",
      "Brazil",
      "Japan",
      "Australia",
      "India",
      "South Africa",
      "Mexico",
      "Switzerland",
      "Singapore",
      "New Zealand",
      "South Korea",
      "Chile",
      "Thailand",
      "Netherlands",
      "France",
      "Spain",
    ]);

    records.forEach((record, index) => {
      assert.ok(
        knownJurisdictions.has(record.country_name),
        `Record ${index}: Unknown jurisdiction ${record.country_name}`
      );
    });
  });

  await t.test("registration_required field is yes/no or boolean-like", () => {
    records.forEach((record, index) => {
      const value = record.registration_required.toLowerCase();
      assert.ok(
        ["yes", "no"].includes(value),
        `Record ${index}: registration_required should be yes/no, got ${record.registration_required}`
      );
    });
  });
});
