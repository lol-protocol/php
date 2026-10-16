// Machine translations of the descriptive values: keyed by the exact English text, published per language.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { spawnSync } from "node:child_process";
import {
  DATA_DIR,
  TRANSLATED_FIELDS,
  TRANSLATION_LANGS,
  buildArtifacts,
  loadAndValidate,
  translatableValues,
  translationDictionaries,
  translationFile,
  validateTranslations,
} from "../scripts/lib/dataset.js";
import { coverage, missing } from "../scripts/translations.js";

const loaded = loadAndValidate();
const { records } = loaded;
const sample = [...translatableValues(records)][0];

test("only descriptive values with content can be translated", () => {
  const values = translatableValues(records);
  assert.ok(values.size > 50);
  assert.ok(![...values].some((v) => /^not specified$/i.test(v) || v === ""));
  assert.ok(TRANSLATED_FIELDS.every((f) => f in records[0]));
});

test("a translation needs a known language, a source and a text, once per language", () => {
  const ok = { lang: "spa", source: sample, text: "texto" };
  assert.deepEqual(validateTranslations([ok], records), { errors: [], warnings: [] });
  assert.match(validateTranslations([{ ...ok, lang: "xxx" }], records).errors[0], /lang must be one of/);
  assert.match(validateTranslations([{ ...ok, text: "" }], records).errors[0], /source and text are required/);
  assert.match(validateTranslations([ok, ok], records).errors[0], /second translation/);
  assert.match(validateTranslations([], records, ["a", "b"]).errors[0], /header must be exactly/);
});

test("a translation whose English text was edited is a warning and is not published", () => {
  const stale = { lang: "fra", source: "an English text that no law has any more", text: "x" };
  const result = validateTranslations([stale], records);
  assert.deepEqual(result.errors, []);
  assert.match(result.warnings[0], /1 translation\(s\) no longer match/);
  const dictionaries = translationDictionaries([stale, { lang: "fra", source: sample, text: "ok" }], records);
  assert.deepEqual(dictionaries, { fra: { [sample]: "ok" } });
});

test("the build publishes one dictionary per language that has translations, and the files are in step", () => {
  const rows = [{ lang: "deu", source: sample, text: "Text" }];
  const artifacts = buildArtifacts(records, { translations: rows });
  assert.equal(artifacts.files.get(translationFile("deu")), `${JSON.stringify({ [sample]: "Text" }, null, 2)}\n`);
  assert.ok(!artifacts.files.has(translationFile("spa")));
  for (const lang of TRANSLATION_LANGS) {
    const published = (() => {
      try {
        return JSON.parse(readFileSync(join(DATA_DIR, translationFile(lang)), "utf-8"));
      } catch {
        return null;
      }
    })();
    assert.deepEqual(published, translationDictionaries(loaded.translations, records)[lang] ?? null, lang);
  }
});

test("coverage and the list of missing values agree", () => {
  const rows = [{ lang: "spa", source: sample, text: "x" }];
  assert.equal(coverage(records, rows).spa.translated, 1);
  assert.equal(missing(records, rows, "spa").length, coverage(records, rows).spa.total - 1);
  assert.ok(!missing(records, rows, "spa").includes(sample));
});

test("the translations command rejects an unknown language", () => {
  const bad = spawnSync(process.execPath, [join(DATA_DIR, "..", "scripts", "translations.js"), "--import=xxx"], { encoding: "utf-8" });
  assert.equal(bad.status, 2);
  assert.match(bad.stderr, /language must be one of/);
});
