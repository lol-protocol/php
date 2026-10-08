import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { FRAMEWORKS, REGIONS } from "../scripts/lib/dataset.js";
import { COMPARE_CRITERIA, FRAMEWORK_ORDER, SEARCH_SCOPES } from "../app/lib/domain.js";

// Minimal browser surface so setLanguage()/applyTranslations() can run under Node.
const dataset = {};
const stored = {};
globalThis.document = {
  documentElement: { lang: "", dataset },
  title: "",
  querySelectorAll: () => [],
};
globalThis.localStorage = {
  getItem: (key) => stored[key] ?? null,
  setItem: (key, value) => {
    stored[key] = value;
  },
};

const {
  DEFAULT_LANGUAGE,
  LANGUAGES,
  TRANSLATIONS,
  detectLanguage,
  getLanguage,
  normalizeLanguage,
  plural,
  setLanguage,
  t,
} = await import("../app/translations/i18n.js");

const read = (path) => readFileSync(new URL(path, import.meta.url), "utf-8");
const english = TRANSLATIONS.eng;
const placeholders = (text) => [...text.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

test("languages use three-letter ISO 639-2 codes", () => {
  assert.deepEqual(Object.keys(LANGUAGES), ["eng", "spa", "fra", "deu", "por"]);
  assert.deepEqual(Object.keys(TRANSLATIONS), Object.keys(LANGUAGES));
  assert.equal(DEFAULT_LANGUAGE, "eng");
  for (const code of Object.keys(LANGUAGES)) assert.match(code, /^[a-z]{3}$/);
});

test("every language has exactly the same keys as English, none empty", () => {
  const expected = Object.keys(english).sort();
  for (const [code, strings] of Object.entries(TRANSLATIONS)) {
    assert.deepEqual(Object.keys(strings).sort(), expected, `${code} keys differ from eng`);
    for (const [key, value] of Object.entries(strings)) {
      assert.ok(typeof value === "string" && value.trim() !== "", `${code}.${key} is empty`);
    }
  }
});

test("placeholders match across languages", () => {
  for (const [code, strings] of Object.entries(TRANSLATIONS)) {
    for (const [key, value] of Object.entries(strings)) {
      assert.deepEqual(placeholders(value), placeholders(english[key]), `${code}.${key}`);
    }
  }
});

test("plural keys always come in .one/.other pairs", () => {
  for (const [code, strings] of Object.entries(TRANSLATIONS)) {
    for (const key of Object.keys(strings)) {
      if (key.endsWith(".one")) assert.ok(`${key.slice(0, -4)}.other` in strings, `${code}: ${key} has no .other`);
      if (key.endsWith(".other")) assert.ok(`${key.slice(0, -6)}.one` in strings, `${code}: ${key} has no .one`);
    }
  }
});

test("every key used by the page exists, and no key is left unused", () => {
  const html = read("../app/index.html");
  const js = read("../app/privacy-laws.js");
  const used = new Set();
  for (const m of js.matchAll(/\bt\(\s*["'`]([\w.]+)["'`]/g)) used.add(m[1]);
  for (const m of html.matchAll(/data-i18n(?:-[a-z-]+)?="([\w.]+)"/g)) used.add(m[1]);
  // Keys can also be handed to helpers ("filters.region", hintKey: "filters.treatyHint"…)
  const PREFIXES = "page|lang|search|scope|btn|filters|chip|region|treaty|term|results|table|compare|chart|unit|card|footer|status";
  for (const m of js.matchAll(new RegExp(`["'\`]((?:${PREFIXES})\\.[\\w.]+)["'\`]`, "g"))) {
    if (!(m[1] in english) && `${m[1]}.one` in english) continue; // a plural base such as "unit.law"
    used.add(m[1]);
  }
  const pluralBases = [...js.matchAll(/\bplural\(\s*["'`]([\w.]+)["'`]/g)].map((m) => m[1]);
  for (const base of pluralBases) {
    used.add(`${base}.one`);
    used.add(`${base}.other`);
  }
  // Keys built at runtime: region.<id>, scope.<id>, compare.<criterion>
  for (const region of REGIONS) used.add(`region.${region}`);
  for (const scope of SEARCH_SCOPES) used.add(`scope.${scope.id}`);
  for (const criterion of COMPARE_CRITERIA) used.add(`compare.${criterion}`);
  // The framework vocabulary lives in two places (dataset validation, app ordering): keep them identical.
  assert.deepEqual(FRAMEWORK_ORDER, FRAMEWORKS);
  for (const framework of FRAMEWORKS) used.add(`framework.${framework}`);

  const missing = [...used].filter((key) => !(key in english));
  assert.deepEqual(missing, [], "keys used but not translated");
  const unused = Object.keys(english).filter((key) => !used.has(key));
  assert.deepEqual(unused, [], "translated but never used");
});

test("language codes are normalised and detected (URL > storage > browser > default)", () => {
  assert.equal(normalizeLanguage("spa"), "spa");
  assert.equal(normalizeLanguage("ES-mx"), "spa");
  assert.equal(normalizeLanguage("fr"), "fra");
  assert.equal(normalizeLanguage(" DEU "), "deu");
  assert.equal(normalizeLanguage("pt_BR"), "por");
  for (const bad of ["", null, undefined, "xx", "klingon", "constructor", "__proto__", "toString"]) {
    assert.equal(normalizeLanguage(bad), null, String(bad));
  }

  assert.equal(detectLanguage({ search: "?lang=fra", stored: "spa", preferred: ["de"] }), "fra");
  assert.equal(detectLanguage({ search: "?lang=nope", stored: "spa", preferred: ["de"] }), "spa");
  assert.equal(detectLanguage({ search: "", stored: null, preferred: ["it-IT", "de-AT"] }), "deu");
  assert.equal(detectLanguage({}), "eng");
});

test("t() interpolates, falls back to English and then to the key", () => {
  assert.equal(getLanguage(), "eng");
  assert.equal(t("chip.country", { value: "Peru" }), "Country: Peru");
  assert.equal(t("chip.country", {}), "Country: {value}");
  assert.equal(t("does.not.exist"), "does.not.exist");
});

test("setLanguage switches language, <html lang> (BCP 47) and storage", () => {
  assert.equal(setLanguage("spa"), "spa");
  assert.equal(globalThis.document.documentElement.lang, "es");
  assert.equal(dataset.lang, "spa");
  assert.equal(stored.preferredLanguage, "spa");
  assert.equal(t("btn.search"), "Buscar");

  assert.equal(setLanguage("zz", { persist: false }), "eng"); // unsupported → default
  assert.equal(stored.preferredLanguage, "spa"); // not persisted
});

test("plurals follow each language's rules", () => {
  const forms = {};
  for (const code of Object.keys(LANGUAGES)) {
    setLanguage(code, { persist: false });
    forms[code] = [0, 1, 2].map((n) => plural("unit.law", n));
  }
  assert.deepEqual(forms.eng, ["0 laws", "1 law", "2 laws"]);
  assert.deepEqual(forms.spa, ["0 leyes", "1 ley", "2 leyes"]);
  assert.deepEqual(forms.fra, ["0 loi", "1 loi", "2 lois"]); // French: 0 is singular
  assert.deepEqual(forms.deu, ["0 Gesetze", "1 Gesetz", "2 Gesetze"]);
  assert.deepEqual(forms.por, ["0 lei", "1 lei", "2 leis"]); // pt: 0 takes the singular
  setLanguage("eng", { persist: false });
});
