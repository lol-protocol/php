// End-to-end tests of the web app in Chromium: facets, chart, URL state, comparison, export, card, languages.
// Run with `npm run test:e2e` (needs PHP and `npx playwright install chromium`).
import { after, test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { hydrate } from "../../app/lib/data.js";
import { enrichRow } from "../../app/lib/domain.js";
import { focused, newPage, openApp, root, shownCount } from "./browser.js";

const app = await openApp();
const { skip } = app;
after(() => app.close());

const bundle = JSON.parse(readFileSync(join(root, "jurisdictions", "index.json"), "utf-8"));
const { rows } = hydrate(bundle, enrichRow);
const N = rows.length;
const J = bundle.jurisdictions.length;
const YEARS = [...new Set(rows.map((r) => r.termYears).filter((y) => y != null))].sort((a, b) => a - b);
const count = (predicate) => rows.filter(predicate).length;
const url = (query = "") => `${app.base}?lang=eng${query ? `&${query}` : ""}`;

test("initial view: every law, one chart bar per term, data-driven facets", { skip }, async () => {
  const { page, summary, problems } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  assert.equal(await summary(), `${N} of ${N} laws · ${J} jurisdictions`);
  assert.equal(await page.locator("#lawsTableBody tr").count(), N);
  assert.equal(await page.locator(".viz-col").count(), YEARS.length);
  assert.equal((await page.locator(".viz-x").allTextContents()).join(" "), YEARS.map((y) => `+${y}`).join(" "));
  assert.equal(await page.locator('input[name="ptype"]').count(), 0, "only 'Copyright' exists, so that facet stays hidden");
  assert.equal((await page.locator("#facets legend").allTextContents()).join("|"), "Region|Treaties|Protection term");
  assert.equal(await page.locator('label.check:has(input[value="WCT"])').getAttribute("title"), "WIPO Copyright Treaty (WCT)");
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("a single jurisdiction shows its card with treaty badges", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.fill("#q", "gb");
  await page.waitForTimeout(250);
  const gb = rows.find((r) => r.country_code === "GB");
  const row = await page.locator("#lawsTableBody tr").first().textContent();
  assert.ok(row.includes("Copyright, Designs and Patents Act 1988") && row.includes("Author's life + 70 years"), row);
  assert.match(await page.locator("#countryCard h2").textContent(), /United Kingdom/);
  assert.equal((await page.locator("#countryCard .badge-list .badge").allTextContents()).join(","), gb.treaties.join(","));
  await page.context().close();
});

test("treaties combine with AND, terms with OR, and the chart axis stays put", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.check('input[name="treaty"][value="WCT"]');
  assert.ok((await summary()).startsWith(`${count((r) => r.treaties.includes("WCT"))} of ${N}`), await summary());
  await page.check('input[name="treaty"][value="TRIPS"]');
  assert.ok((await summary()).startsWith(`${count((r) => r.treaties.includes("WCT") && r.treaties.includes("TRIPS"))} of ${N}`), await summary());
  await page.uncheck('input[name="treaty"][value="WCT"]');
  await page.uncheck('input[name="treaty"][value="TRIPS"]');

  await page.check('input[name="term"][value="life-50"]');
  assert.ok((await summary()).startsWith(`${count((r) => r.termYears === 50)} of ${N}`), await summary());
  await page.check('input[name="term"][value="life-70"]');
  assert.ok((await summary()).startsWith(`${count((r) => r.termYears === 50 || r.termYears === 70)} of ${N}`), await summary());
  assert.match((await page.locator(".chip").allTextContents()).join("|"), /Term: Life \+ 50 years/);
  await page.check('input[name="region"][value="europe"]');
  assert.ok(
    (await summary()).startsWith(`${count((r) => (r.termYears === 50 || r.termYears === 70) && r.region === "europe")} of ${N}`),
    await summary()
  );
  assert.equal(await page.locator(".viz-col").count(), YEARS.length);
  await page.locator('.viz-col[aria-label^="Life + 70"]').hover();
  assert.match(await page.locator(".viz-tip-lead").textContent(), /Life \+ 70 years/);
  await page.context().close();
});

test("search scope narrows where the query is looked for", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.selectOption("#scope", "rights");
  await page.fill("#q", "orphan");
  await page.waitForTimeout(250);
  assert.ok(shownCount(await summary()) >= 6, await summary());
  await page.selectOption("#scope", "authority");
  assert.ok((await summary()).startsWith(`0 of ${N}`), await summary());
  await page.context().close();
});

test("state is restored from the URL and junk parameters are ignored", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url("treaty=Berne,WCT&term=life-70&region=europe&in=name&q=ger"), { waitUntil: "networkidle" });
  assert.equal(await page.locator('input[name="treaty"]:checked').count(), 2);
  assert.equal(await page.inputValue("#scope"), "name");
  assert.equal(await page.inputValue("#q"), "ger");
  assert.ok(!(await summary()).startsWith(`${N} of`));
  await page.goto(url("treaty=Bogus&term=life-9&region=mars"), { waitUntil: "networkidle" });
  assert.ok((await summary()).startsWith(`${N} of ${N}`), await summary());
  await page.context().close();
});

test("comparison, focus return and export of the filtered rows", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.locator('#lawsTableBody input[type="checkbox"]').nth(1).check();
  await page.locator('#lawsTableBody input[type="checkbox"]').nth(2).check();
  await page.click("#compareBtn");
  assert.equal(await page.locator("#comparisonResults thead th").count(), 3);
  assert.equal(await page.locator("#comparisonResults tbody tr").count(), 9);
  await page.locator("#closeComparison").focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "BUTTON#compareBtn");

  await page.check('input[name="treaty"][value="Berne"]');
  await page.check('input[name="term"][value="life-100"]');
  const [download] = await Promise.all([page.waitForEvent("download"), page.click("#exportBtn")]);
  const lines = readFileSync(await download.path(), "utf-8").replace(/^﻿/, "").trim().split("\n");
  assert.ok(lines[0].startsWith("country_code,country_name,region,law_name"));
  assert.equal(lines.length - 1, shownCount(await summary()));
  assert.ok(lines.slice(1).every((l) => /life \+ 100 years/i.test(l)));
  await page.context().close();
});

test("chips and the country card keep keyboard focus", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url("region=europe&term=life-70"), { waitUntil: "networkidle" });
  await page.locator('#chips [data-chip="0"]').focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "BUTTON[chip 0]");
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "INPUT#q");
  assert.equal(await page.locator("#filterCount").evaluate((el) => getComputedStyle(el).display), "none");

  await page.locator("#lawsTableBody .link-button").first().focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "SECTION#countryCard");
  await page.context().close();
});

test("languages: Portuguese plurals and German labels", { skip }, async () => {
  const { page, summary, problems } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.selectOption("#languageSelect", "por");
  await page.waitForTimeout(150);
  assert.equal(await page.locator(".viz-title").textContent(), "Leis por prazo de proteção");
  assert.equal((await page.locator("#facets legend").allTextContents()).join("|"), "Região|Tratados|Prazo de proteção");
  assert.equal(await page.locator('label.check:has(input[value="Berne"])').getAttribute("title"), "Convenção de Berna");
  assert.equal(await summary(), `${N} de ${N} leis · ${J} jurisdições`);
  await page.selectOption("#languageSelect", "deu");
  assert.equal(await page.locator("#searchBtn").textContent(), "Suchen");
  assert.equal(await page.evaluate(() => document.documentElement.lang), "de");
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("on small screens secondary columns give way and the treaties column stays", { skip }, async () => {
  const { page } = await newPage(app.browser, { viewport: { width: 600, height: 900 } });
  await page.goto(url(), { waitUntil: "networkidle" });
  const headers = await page.locator("#lawsTable thead th").evaluateAll((ths) =>
    ths.filter((th) => getComputedStyle(th).display !== "none").map((th) => th.textContent.trim())
  );
  assert.ok(!headers.includes("Author rights") && headers.includes("Treaties"), headers.join(" | "));
  const cells = await page.locator("#lawsTableBody tr").first().locator("td").evaluateAll((tds) => tds.filter((td) => getComputedStyle(td).display !== "none").length);
  assert.equal(cells, headers.length);
  await page.context().close();
});
