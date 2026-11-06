#!/usr/bin/env node
// Copies the app/ files that both laws projects use (leyes-comunes/app/**) into copyright-laws/app/.
//
//   npm run sync:app            copy what differs
//   npm run sync:app -- --check fail when a shared file here differs from leyes-comunes/app (tests and CI run this)
//
// Edit the file in leyes-comunes/app/, then run this in both projects.
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import { createSyncApp } from "../../leyes-comunes/sync-app-core.js";

const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");

export const main = createSyncApp({ sharedDir: join(root, "leyes-comunes", "app"), appDir: join(root, "copyright-laws", "app"), project: "copyright-laws" });

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    process.exit(main());
  } catch (error) {
    console.error(`Error: ${error.message}`);
    process.exit(2);
  }
}
