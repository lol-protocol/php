import { test } from "node:test";
import { strict as assert } from "node:assert";
import { readFileSync } from "node:fs";
import { parse } from "csv-parse/sync";

test("CSV Validation - Privacy Laws Master", async (t) => {
  const csvContent = readFileSync(
    "./countries/privacy_laws_master.csv",
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
      "jurisdiction",
      "enactment_date",
      "effective_date",
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

  await t.test("enactment_date is valid date", () => {
    records.forEach((record, index) => {
      const date = new Date(record.enactment_date);
      assert.ok(
        !isNaN(date.getTime()),
        `Record ${index}: Invalid enactment_date ${record.enactment_date}`
      );
    });
  });

  await t.test("effective_date is valid date", () => {
    records.forEach((record, index) => {
      const date = new Date(record.effective_date);
      assert.ok(
        !isNaN(date.getTime()),
        `Record ${index}: Invalid effective_date ${record.effective_date}`
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

  await t.test("website_url contains valid URL format", () => {
    const urlPattern = /^https?:\/\/.+/;
    records.forEach((record, index) => {
      if (record.website_url) {
        assert.match(
          record.website_url,
          urlPattern,
          `Record ${index}: Invalid URL ${record.website_url}`
        );
      }
    });
  });
});

test("Data Integrity Checks", async (t) => {
  const csvContent = readFileSync(
    "./countries/privacy_laws_master.csv",
    "utf-8"
  );
  const records = parse(csvContent, {
    columns: true,
    skip_empty_lines: true,
  });

  await t.test("enforcement_authority is not empty", () => {
    records.forEach((record, index) => {
      assert.ok(
        record.enforcement_authority !== "",
        `Record ${index}: Missing enforcement_authority`
      );
    });
  });

  await t.test("country_name matches known countries", () => {
    const knownCountries = new Set([
      "European Union",
      "United States",
      "Canada",
      "United Kingdom",
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
      "El Salvador",
    ]);

    records.forEach((record, index) => {
      assert.ok(
        knownCountries.has(record.country_name),
        `Record ${index}: Unknown country ${record.country_name}`
      );
    });
  });
});
