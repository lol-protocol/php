// The app/ files both laws projects use live once in leyes-comunes/app/ and are copied into each app/ folder
// (the app is served as static files, with no build step). A copy edited by hand would silently drift.
import { test } from "node:test";
import assert from "node:assert/strict";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { appDifferences, listFiles, syncApp } from "../../leyes-comunes/sync-app-core.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
const shared = join(root, "leyes-comunes", "app");
const app = join(root, "copyright-laws", "app");

test("the shared app files in copyright-laws/app equal leyes-comunes/app (npm run sync:app copies them)", () => {
  assert.ok(listFiles(shared).length >= 6, "leyes-comunes/app holds the shared files");
  assert.deepEqual(appDifferences(shared, app), [], "edit leyes-comunes/app/<file> and run: npm run sync:app");
});

test("syncApp copies what is missing or edited and leaves the rest alone", () => {
  const dir = mkdtempSync(join(tmpdir(), "sync-app-"));
  try {
    const sharedDir = join(dir, "shared");
    const appDir = join(dir, "app");
    mkdirSync(join(sharedDir, "lib"), { recursive: true });
    mkdirSync(join(appDir, "lib"), { recursive: true });
    writeFileSync(join(sharedDir, "lib", "a.js"), "one\n");
    writeFileSync(join(sharedDir, "lib", "b.js"), "two\n");
    writeFileSync(join(appDir, "lib", "a.js"), "edited by hand\n");
    assert.deepEqual(appDifferences(sharedDir, appDir).sort(), [join("lib", "a.js"), join("lib", "b.js")]);
    assert.equal(syncApp(sharedDir, appDir).length, 2);
    assert.equal(readFileSync(join(appDir, "lib", "a.js"), "utf-8"), "one\n");
    assert.deepEqual(syncApp(sharedDir, appDir), []);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
