// Shared engine behind privacy-laws/scripts/sync-app.js and copyright-laws/scripts/sync-app.js.
// The web app of each project is served as static files from its own app/ folder, with no build step, so the files
// both apps use (leyes-comunes/app/**) are kept in the two app/ folders as plain copies. The copy in leyes-comunes/app/
// is the one to edit; this engine copies it over the project's (default) or, with --check, fails when they differ.
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";

/** Every file under `dir`, as paths relative to it. */
export function listFiles(dir) {
  const out = [];
  for (const name of readdirSync(dir).sort()) {
    const full = join(dir, name);
    if (statSync(full).isDirectory()) out.push(...listFiles(full).map((f) => join(name, f)));
    else out.push(name);
  }
  return out;
}

/** What differs between the shared folder and a project's app/ folder: files that are missing or not equal. */
export function appDifferences(sharedDir, appDir) {
  return listFiles(sharedDir).filter((file) => {
    const copy = join(appDir, file);
    return !existsSync(copy) || !readFileSync(copy).equals(readFileSync(join(sharedDir, file)));
  });
}

/** Copies the differing files over the project's app/ folder; returns their paths. */
export function syncApp(sharedDir, appDir) {
  const changed = appDifferences(sharedDir, appDir);
  for (const file of changed) {
    mkdirSync(dirname(join(appDir, file)), { recursive: true });
    copyFileSync(join(sharedDir, file), join(appDir, file));
  }
  return changed;
}

/** main() bound to one project's app/ folder: `sync-app [--check]`. */
export function createSyncApp({ sharedDir, appDir, project }) {
  return function main(argv = process.argv.slice(2)) {
    const shared = relative(process.cwd(), sharedDir) || sharedDir;
    if (argv.includes("--check")) {
      const different = appDifferences(sharedDir, appDir);
      if (!different.length) {
        console.log(`✔ the ${project} app/ files shared with the other project match ${shared}`);
        return 0;
      }
      console.error(`✘ ${different.length} shared file(s) of ${project}/app differ from ${shared}: ${different.join(", ")}`);
      console.error(`  Edit the copy in ${shared} and run: npm run sync:app`);
      return 1;
    }
    const changed = syncApp(sharedDir, appDir);
    console.log(changed.length ? `Copied ${changed.length} file(s) into ${project}/app: ${changed.join(", ")}` : `Nothing to copy: ${project}/app already matches ${shared}.`);
    return 0;
  };
}
