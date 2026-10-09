// The page must show exactly what the filter engine computes for the same URL, and pass axe-core in every main state.
import { after, test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { hydrate } from "../../app/lib/data.js";
import { CONFIG, enrichRow } from "../../app/lib/domain.js";
import { buildIndex, filterRows, pruneState, stateFromParams } from "../../app/lib/filters.js";
import { axeViolations, newPage, openApp, root, shownCount } from "./browser.js";

const app = await openApp();
const { skip } = app;
after(() => app.close());

const { rows } = hydrate(JSON.parse(readFileSync(join(root, "jurisdictions", "index.json"), "utf-8")), enrichRow);
buildIndex(rows, CONFIG);
const engineCount = (query) => filterRows(rows, pruneState(stateFromParams(new URLSearchParams(query), CONFIG), CONFIG, rows), CONFIG).length;

const QUERIES = [
  "treaty=Berne,WCT&region=europe",
  "term=life-50&region=asia_pacific",
  "q=orphan&in=rights&term=life-70",
  "treaty=TRIPS&term=life-70,life-60",
  "treaty=WPPT&region=middle_east_africa",
  "country=mx",
  "q=br",
];

test("summary and table agree with the filter engine for shared URLs", { skip }, async () => {
  const { page } = await newPage(app.browser);
  for (const query of QUERIES) {
    await page.goto(`${app.base}?lang=eng&${query}`, { waitUntil: "networkidle" });
    const expected = engineCount(query);
    assert.equal(shownCount(await page.locator("#resultsSummary").textContent()), expected, `summary for ?${query}`);
    assert.equal(await page.locator("#lawsTableBody tr").count(), expected, `rows for ?${query}`);
  }
  await page.context().close();
});

for (const colorScheme of ["light", "dark"]) {
  for (const [width, height] of [[1280, 900], [320, 700]]) {
    test(`no axe-core violations (${colorScheme}, ${width}px) across the main states`, { skip }, async () => {
      const { page } = await newPage(app.browser, { colorScheme, viewport: { width, height } });
      const found = [];
      const audit = async (state) => found.push(...(await axeViolations(page)).map((v) => `${state}: ${v}`));

      await page.goto(`${app.base}?lang=eng`, { waitUntil: "networkidle" });
      await audit("initial");
      if (await page.locator("#advanced").evaluate((e) => !e.open)) await page.click("#advanced > summary");
      await page.check('#facets input[type="checkbox"] >> nth=0');
      await page.waitForTimeout(250);
      await audit("filtered");
      await page.click(".viz-toggle");
      await audit("chart as table");
      await page.click(".viz-toggle");
      await page.locator("#lawsTableBody [data-country]").first().click();
      await page.waitForTimeout(250);
      await audit("country card");
      await page.click(".chip button");
      await page.locator('#lawsTableBody input[type="checkbox"]').nth(0).check();
      await page.locator('#lawsTableBody input[type="checkbox"]').nth(3).check();
      await page.click("#compareBtn");
      await page.waitForTimeout(250);
      await audit("comparison");
      await page.fill("#q", "zzzz-none");
      await page.waitForTimeout(300);
      await audit("empty");
      await page.selectOption("#languageSelect", "deu");
      await page.fill("#q", "");
      await page.waitForTimeout(300);
      await audit("deu");

      assert.deepEqual(found, []);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 0, `the page is ${overflow}px wider than the viewport`);
      await page.context().close();
    });
  }
}
