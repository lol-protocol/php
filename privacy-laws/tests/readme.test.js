import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { loadMaster } from "../scripts/lib/dataset.js";

// What the README claims is tied to what is in the folder, so it cannot drift unnoticed
// (an earlier README promised "200+ countries" and listed folders that did not exist).
const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const readme = readFileSync(join(root, "README.md"), "utf-8");
const { records } = loadMaster();

test("the README's counts are the master CSV's", () => {
  const m = readme.match(/\((\d+) laws, (\d+) countries\)/);
  assert.ok(m, "missing the \"(N laws, M countries)\" line");
  assert.deepEqual([Number(m[1]), Number(m[2])], [records.length, new Set(records.map((r) => r.country_code)).size]);
});

test("the README's languages are the ones in translations/i18n.js", async () => {
  const { TRANSLATIONS } = await import("../app/translations/i18n.js");
  const codes = readme.match(/three-letter ISO 639-2 codes: ((?:`[a-z]{3}`(?:, )?)+)/);
  assert.ok(codes, "missing the list of interface languages");
  assert.deepEqual(codes[1].match(/[a-z]{3}/g), Object.keys(TRANSLATIONS));
});

test("everything the Layout section lists exists", () => {
  const block = readme.split("## Layout")[1].match(/```\n([\s\S]*?)```/)[1];
  const stack = [];
  const missing = [];
  let checked = 0;
  for (const line of block.split("\n").slice(1)) {
    const i = line.search(/[├└]── /);
    if (i < 0) continue;
    const level = i / 4;
    const names = line.slice(i + 4).split(/\s*#/)[0].trim().split(/\s+/);
    stack.length = level;
    stack[level] = names[0].replace(/\/$/, "");
    if (stack.some((p) => /[{}*]/.test(p))) continue;
    for (const name of names) {
      checked++;
      const path = join(root, ...stack.slice(0, level), name.replace(/\/$/, ""));
      if (!existsSync(path)) missing.push(path.slice(root.length + 1));
    }
  }
  assert.ok(checked >= 8, `only ${checked} paths were checked`);
  assert.deepEqual(missing, []);
});

test("the README keeps the notice that this is not legal advice", () => {
  assert.match(readme, /not legal advice/i);
});
