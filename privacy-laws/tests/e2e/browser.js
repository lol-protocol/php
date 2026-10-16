// Shared set-up for the browser tests: a PHP server for the module and a Chromium from Playwright.
// Without PHP, Playwright or a browser the tests are skipped, unless E2E_REQUIRED is set (CI).
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { hasPhp, startPhpServer } from "../helpers/php-server.js";

export const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
const require = createRequire(import.meta.url);

async function launch() {
  if (!hasPhp) return { reason: "php is not installed" };
  let chromium;
  try {
    ({ chromium } = await import("playwright"));
  } catch {
    return { reason: "playwright is not installed (run npm install)" };
  }
  try {
    const options = process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {};
    return { browser: await chromium.launch(options) };
  } catch (error) {
    return { reason: `Chromium could not start (npx playwright install chromium): ${error.message.split("\n")[0]}` };
  }
}

/** @returns {Promise<{skip: string|false, browser?: import("playwright").Browser, base?: string, close: () => Promise<void>}>} */
export async function openApp() {
  const { browser, reason } = await launch();
  if (!browser) {
    if (process.env.E2E_REQUIRED) throw new Error(`browser tests are required but cannot run: ${reason}`);
    return { skip: reason, close: async () => {} };
  }
  const server = await startPhpServer(root);
  return {
    skip: false,
    browser,
    base: `${server.url}/app/index.html`,
    async close() {
      await browser.close();
      await server.stop();
    },
  };
}

/** A fresh page that records console errors, page errors and dialogs (dialogs are dismissed). */
export async function newPage(browser, options = {}) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: "en-US", acceptDownloads: true, ...options });
  const page = await context.newPage();
  const problems = [];
  const dialogs = [];
  page.on("console", (message) => {
    if (["error", "warning"].includes(message.type())) problems.push(`[${message.type()}] ${message.text()}`);
  });
  page.on("pageerror", (error) => problems.push(`[pageerror] ${error.message}`));
  page.on("dialog", async (dialog) => {
    dialogs.push(dialog.message());
    await dialog.dismiss();
  });
  return { page, context, problems, dialogs, summary: () => page.locator("#resultsSummary").textContent() };
}

/** Shown/total from "12 of 102 laws · …" (English UI). */
export const shownCount = (summary) => Number(summary.match(/^(\d+)/)[1]);

let axeSource;
/** axe-core violations on the current page, as "rule: selector | selector" strings. */
export async function axeViolations(page) {
  axeSource ??= readFileSync(require.resolve("axe-core/axe.min.js"), "utf-8");
  await page.mouse.move(0, 0);
  await page.waitForTimeout(450); // let hover transitions settle, or contrast is measured mid-fade
  await page.addScriptTag({ content: axeSource });
  return page.evaluate(async () =>
    // eslint-disable-next-line no-undef
    (await axe.run(document, { resultTypes: ["violations"] })).violations.map(
      (v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(" | ")}`
    )
  );
}

/** Which element has focus, in a readable form. */
export const focused = (page) =>
  page.evaluate(() => {
    const a = document.activeElement;
    if (!a) return "none";
    return `${a.tagName}${a.id ? `#${a.id}` : ""}${a.dataset.chip != null ? `[chip ${a.dataset.chip}]` : ""}`;
  });
