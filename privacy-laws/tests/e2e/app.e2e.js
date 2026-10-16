// End-to-end tests of the web app in Chromium: URL state, filters, comparison, export, country card, languages.
// Run with `npm run test:e2e` (needs PHP and `npx playwright install chromium`).
import { after, test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { COMPARE_CRITERIA } from "../../app/lib/domain.js";
import { focused, newPage, openApp, root, shownCount } from "./browser.js";

const app = await openApp();
const { skip } = app;
after(() => app.close());

const bundle = JSON.parse(readFileSync(join(root, "countries", "index.json"), "utf-8"));
const laws = bundle.countries.flatMap((c) => c.laws.map((law) => ({ ...law, tld: c.tld, region: c.region })));
const TOTAL = laws.length;
const having = (...frameworks) => laws.filter((l) => frameworks.every((f) => l.frameworks.split("/").includes(f))).length;
const url = (query = "") => `${app.base}?lang=eng${query ? `&${query}` : ""}`;

test("state is restored from the URL and junk parameters are ignored", { skip }, async () => {
  const { page, summary, problems } = await newPage(app.browser);
  await page.goto(url("q=data&region=americas&year=2018-2024&in=name"), { waitUntil: "networkidle" });
  assert.match(await summary(), new RegExp(`of ${TOTAL} laws`));
  assert.equal(await page.inputValue("#q"), "data");
  assert.ok(await page.locator('input[name="region"][value="americas"]').isChecked());
  assert.equal(await page.inputValue('[name="year-from"]'), "2018");
  assert.equal(await page.inputValue("#scope"), "name");

  await page.goto(url("region=bogus&country=zz&year=abc"), { waitUntil: "networkidle" });
  assert.ok((await summary()).startsWith(`${TOTAL} of ${TOTAL}`), await summary());
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("comparison needs two countries; select-all and empty state behave", { skip }, async () => {
  const { page, summary, dialogs, problems } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  const boxes = page.locator('#lawsTableBody input[type="checkbox"]');

  await boxes.nth(0).check();
  await page.click("#compareBtn");
  assert.equal(dialogs.length, 1);
  assert.match(dialogs[0], /at least 2 different countries/);

  await boxes.nth(5).check();
  assert.match(await page.locator("#compareBtn").textContent(), /\(2\)/);
  await page.click("#compareBtn");
  assert.equal(await page.locator("#comparisonResults thead th").count(), 3);
  // Criteria where every selected law is empty are left out; the rest are marked as differing or not.
  const rowsShown = await page.locator("#comparisonResults tbody tr").count();
  assert.ok(rowsShown >= 3 && rowsShown <= COMPARE_CRITERIA.length, `${rowsShown} rows`);
  const differing = await page.locator("#comparisonResults tbody tr.is-diff").count();
  assert.ok(differing > 0);
  await page.check("#onlyDiff");
  assert.equal(await page.locator("#comparisonResults tbody tr:visible").count(), differing, "only the rows that differ stay visible");
  await page.uncheck("#onlyDiff");
  await page.locator("#closeComparison").focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "BUTTON#compareBtn", "closing the comparison returns focus to its button");

  await page.check("#selectAll");
  assert.equal(await page.locator("#lawsTableBody input:checked").count(), TOTAL);
  assert.match(await page.locator("#compareBtn").textContent(), new RegExp(`\\(${TOTAL}\\)`));
  await boxes.nth(0).uncheck();
  assert.ok(await page.locator("#selectAll").evaluate((e) => e.indeterminate));
  await page.click("#clearBtn");

  await boxes.nth(0).check();
  await page.fill("#q", "zzzz-nothing");
  await page.waitForTimeout(300);
  assert.ok(await page.locator("#emptyState").isVisible());
  assert.ok(!(await page.locator("#tableWrap").isVisible()));
  assert.doesNotMatch(await page.locator("#compareBtn").textContent(), /\(/, "a hidden selection is dropped");
  assert.equal(shownCount(await summary()), 0);
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("HTML typed into the search box stays inert", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.fill("#q", '<img src=x onerror="window.__xss=1"> "unterminated');
  await page.waitForTimeout(300);
  assert.equal(await page.evaluate(() => window.__xss), undefined);
  assert.equal(await page.locator("#lawsTableBody img").count(), 0);
  await page.context().close();
});

test("country card: opened from the table, focusable, with working downloads", { skip }, async () => {
  const { page, context, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.locator('#lawsTableBody [data-country="us"]').first().focus();
  await page.keyboard.press("Enter");
  await page.waitForTimeout(300);
  assert.equal(await focused(page), "SECTION#countryCard");
  assert.match(await page.locator("#countryCard h2").textContent(), /United States/);
  const usCount = laws.filter((l) => l.tld === "us").length;
  assert.ok((await summary()).startsWith(`${usCount} of ${TOTAL}`), await summary());
  assert.match((await page.locator("#countryCard dd").allTextContents()).join("|"), /countries\/us\//);
  assert.ok(page.url().includes("country=us"));

  const href = await page.locator('#countryCard a[href$="laws.json"]').getAttribute("href");
  const response = await context.request.get(href);
  assert.ok(response.ok());
  assert.equal((await response.json()).length, usCount);

  await page.click(".chip button");
  assert.ok((await summary()).startsWith(`${TOTAL} of ${TOTAL}`));
  await context.close();
});

test("chips: focus moves to the next chip, then to the search box", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url("region=europe&year=2018-"), { waitUntil: "networkidle" });
  assert.notEqual(await page.locator("#filterCount").evaluate((el) => getComputedStyle(el).display), "none");
  await page.locator('#chips [data-chip="0"]').focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "BUTTON[chip 0]");
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "INPUT#q");
  assert.equal(await page.locator("#filterCount").evaluate((el) => getComputedStyle(el).display), "none", "the empty badge is hidden");

  await page.goto(url("region=europe&year=2018-"), { waitUntil: "networkidle" });
  await page.locator("#chips [data-clear-filters]").focus();
  await page.keyboard.press("Enter");
  assert.equal(await focused(page), "INPUT#q");
  await page.context().close();
});

test("export holds exactly the filtered rows, with a BOM and quoted commas", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.fill("#q", "gdpr");
  await page.waitForTimeout(300);
  const [download] = await Promise.all([page.waitForEvent("download"), page.click("#exportBtn")]);
  const csv = readFileSync(await download.path(), "utf-8");
  const lines = csv.replace(/^﻿/, "").trim().split("\n");
  assert.ok(csv.startsWith("﻿"));
  assert.ok(lines[0].startsWith("country_code,country_name,region,law_name"));
  assert.equal(lines.length - 1, shownCount(await summary()));
  assert.ok(lines.some((l) => l.includes('"Lawful basis, consent, data subject rights"')));
  await page.context().close();
});

test("language comes from the URL, switches live and stays in the URL", { skip }, async () => {
  const { page, summary, problems } = await newPage(app.browser);
  await page.goto(`${app.base}?lang=fra`, { waitUntil: "networkidle" });
  assert.equal(await page.locator("#searchBtn").textContent(), "Rechercher");
  assert.equal(await page.evaluate(() => document.documentElement.lang), "fr");
  assert.equal(await page.inputValue("#languageSelect"), "fra");

  await page.selectOption("#languageSelect", "deu");
  await page.fill("#q", "gdpr");
  await page.waitForTimeout(300);
  assert.equal(await page.locator("#searchBtn").textContent(), "Suchen");
  assert.ok(page.url().includes("lang=deu"));
  assert.match(await summary(), /Gesetzen/);
  assert.equal(await page.locator(".viz-title").textContent(), "Inkrafttreten von Gesetzen pro Jahr");
  await page.check('input[name="region"][value="europe"]');
  assert.match(await page.locator(".chip").first().textContent(), /^Europa/);
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("the card separates frameworks taken part in, ruled out and not confirmed", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url("country=us"), { waitUntil: "networkidle" });
  const rowOf = async (label) => (await page.locator("#countryCard .card-grid > div", { hasText: label }).first().locator("dd").textContent()).trim();
  const us = laws.find((l) => l.tld === "us");
  assert.equal(await rowOf("Takes part in"), us.frameworks.split("/").join(""));
  assert.equal(await rowOf("Does not take part in"), us.frameworks_not.split("/").join(""));
  const unconfirmed = ["GDPR", "EU-Adequacy", "CoE-108", "APEC-CBPR"].filter((f) => !`${us.frameworks}/${us.frameworks_not}`.split("/").includes(f));
  assert.equal(await rowOf("Not confirmed"), unconfirmed.join(", ") || "—");
  await page.context().close();
});

test("frameworks facet: AND semantics, URL state, localised labels", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  if (!(await page.evaluate(() => document.querySelector("#advanced").open))) await page.click("#advanced summary");
  assert.equal(await page.locator('input[name="framework"]').count(), 4);

  await page.check('input[name="framework"][value="GDPR"]');
  await page.waitForTimeout(250);
  assert.ok((await summary()).startsWith(`${having("GDPR")} of ${TOTAL}`), await summary());
  assert.ok(page.url().includes("framework=GDPR"));
  assert.match(await page.locator(".chip").first().textContent(), /^Framework: GDPR/);

  await page.check('input[name="framework"][value="CoE-108"]');
  await page.waitForTimeout(250);
  assert.ok((await summary()).startsWith(`${having("GDPR", "CoE-108")} of ${TOTAL}`), await summary());
  await page.check('input[name="framework"][value="APEC-CBPR"]');
  await page.waitForTimeout(250);
  assert.ok(await page.locator("#emptyState").isVisible());
  await page.reload({ waitUntil: "networkidle" });
  assert.equal(await page.locator('input[name="framework"]:checked').count(), 3);

  await page.goto(`${app.base}?framework=GDPR&lang=deu`, { waitUntil: "networkidle" });
  assert.match(await page.locator(".chip").first().textContent(), /^Rahmenwerk: GDPR/);
  const label = await page.locator('input[name="framework"][value="GDPR"]').evaluate((el) => el.closest("label").title);
  assert.match(label, /Datenschutz-Grundverordnung/);
  await page.context().close();
});

test("laws not yet in force are flagged; out-of-range years do not block Search", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  const future = laws.find((l) => l.effective_date > new Date().toISOString().slice(0, 10));
  if (future) {
    await page.goto(url(`country=${future.tld}`), { waitUntil: "networkidle" });
    assert.match(await page.locator("#lawsTableBody").textContent(), /Not yet in force/);
  }
  await page.goto(url(), { waitUntil: "networkidle" });
  const before = await summary();
  await page.fill('[name="year-to"]', "2999");
  await page.locator('[name="year-to"]').dispatchEvent("change");
  await page.fill("#q", "gdpr");
  await page.keyboard.press("Enter");
  await page.waitForTimeout(300);
  assert.notEqual(await summary(), before);
  await page.context().close();
});

test("on small screens secondary columns give way and header/body stay aligned", { skip }, async () => {
  const { page } = await newPage(app.browser, { viewport: { width: 600, height: 900 } });
  await page.goto(url(), { waitUntil: "networkidle" });
  const headers = await page.locator("#lawsTable thead th").evaluateAll((ths) =>
    ths.filter((th) => getComputedStyle(th).display !== "none").map((th) => th.textContent.trim())
  );
  assert.ok(!headers.includes("Enacted") && headers.includes("In force"), headers.join(" | "));
  const cells = await page.locator("#lawsTableBody tr").first().locator("td").evaluateAll((tds) => tds.filter((td) => getComputedStyle(td).display !== "none").length);
  assert.equal(cells, headers.length);
  await page.context().close();
});

test("data status: a facet, a low-confidence badge and the details on the card", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  if (!(await page.evaluate(() => document.querySelector("#advanced").open))) await page.click("#advanced summary");
  await page.check('input[name="source"][value="researched"]');
  await page.waitForTimeout(250);
  const researched = laws.filter((l) => l.data_status === "researched");
  assert.ok((await summary()).startsWith(`${researched.length} of ${TOTAL}`), await summary());
  assert.match(await page.locator(".chip").first().textContent(), /^Status: Web research/);

  const low = laws.find((l) => l.confidence === "low");
  await page.goto(url(`country=${low.tld}`), { waitUntil: "networkidle" });
  assert.match(await page.locator("#lawsTableBody").textContent(), /Low confidence/);
  const card = await page.locator("#countryCard .card-grid > div", { hasText: "Data status" }).locator("dd").textContent();
  assert.match(card, /Web research \(secondary sources\) · low confidence · checked/);
  await page.context().close();
});

test("the card lists every field with its source, upcoming changes and the change history", { skip }, async () => {
  const { page, problems } = await newPage(app.browser);
  await page.goto(url("country=cl"), { waitUntil: "networkidle" });
  const card = page.locator("#countryCard");
  assert.match(await card.locator(".card-pending").textContent(), /Upcoming change · .*Law 21\.719 takes effect.*\(expected from Dec 1, 2026\)/);
  const details = card.locator("details.law-details");
  assert.equal(await details.count(), 1);
  assert.equal(await details.evaluate((el) => el.open), true, "a single law is shown expanded");
  const penalties = details.locator(".detail-grid > div", { hasText: "Penalties" });
  assert.match(await penalties.locator("dd").textContent(), /20,000 UTM/);
  const source = penalties.locator("a.field-source");
  assert.match(await source.getAttribute("aria-label"), /^Source: .+ · (high|medium|low) confidence · checked /);
  assert.equal(await source.getAttribute("rel"), "noopener noreferrer");
  assert.match(await details.locator(".history").textContent(), /Penalties: Up to 20,000 UTM.*\(was: Fines up to USD 20k\).*… and \d+ earlier changes?/);
  assert.match(await page.locator("#lawsTableBody").textContent(), /Change pending/);

  // Two laws: both collapsed, each with its own record.
  await page.goto(url("country=us"), { waitUntil: "networkidle" });
  assert.equal(await card.locator("details.law-details").count(), laws.filter((l) => l.tld === "us").length);
  assert.equal(await card.locator("details.law-details[open]").count(), 0);
  await card.locator("details.law-details summary").first().click();
  assert.ok((await card.locator("details.law-details[open] .detail-grid > div").count()) >= 8);
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("rows that need attention have their own filter, and the URL keeps it", { skip }, async () => {
  const { page, summary, problems } = await newPage(app.browser);
  const pending = laws.filter((l) => l.pending_change).length;
  assert.ok(pending > 0);
  await page.goto(url(), { waitUntil: "networkidle" });
  assert.match((await page.locator("#facets legend").allTextContents()).join("|"), /Needs attention/);
  await page.check('input[name="flag"][value="pending"]');
  assert.ok((await summary()).startsWith(`${pending} of ${TOTAL}`), await summary());
  assert.match((await page.locator(".chip").first().textContent()), /^Flag: Change pending/);
  assert.match(page.url(), /flag=pending/);
  await page.goto(url("flag=pending&flag=bogus"), { waitUntil: "networkidle" });
  assert.equal(await page.locator('input[name="flag"]:checked').count(), 1);
  assert.deepEqual(problems, []);
  await page.context().close();
});

test("the card shows how complete a record is; the list of recent changes opens a country", { skip }, async () => {
  const { page, summary } = await newPage(app.browser);
  await page.goto(url("country=cl"), { waitUntil: "networkidle" });
  const filled = await page.locator("#countryCard .card-grid > div", { hasText: "Completeness" }).locator("dd").textContent();
  assert.match(filled, /^\d+ of \d+ fields filled \(\d+%\) · \d+ with a source$/);

  await page.goto(url("region=europe"), { waitUntil: "networkidle" });
  const news = page.locator("#newsSection");
  assert.equal(await news.isVisible(), true);
  await news.locator("summary").click();
  const items = await news.locator("#newsList li").count();
  assert.ok(items >= 2 && items <= 16, `${items} items`);
  const dates = await news.locator("#newsList li > time:first-of-type").evaluateAll((els) => els.map((el) => el.getAttribute("datetime")));
  assert.deepEqual([...dates].sort().reverse(), dates, "newest first");
  const first = news.locator("#newsList [data-news-country]").first();
  const name = (await first.textContent()).trim();
  await first.click();
  assert.match(await page.locator("#countryCard h2").textContent(), new RegExp(name));
  assert.ok((await summary()).startsWith("1 of") || /^\d+ of/.test(await summary()));
  await page.context().close();
});

test("the comparison links each value to its source, and the export can include sources and history", { skip }, async () => {
  const { page } = await newPage(app.browser);
  await page.goto(url(), { waitUntil: "networkidle" });
  await page.locator('#lawsTableBody input[aria-label^="Select: Chile"]').check();
  await page.locator('#lawsTableBody input[aria-label^="Select: Brazil"]').check();
  await page.click("#compareBtn");
  assert.ok((await page.locator("#comparisonResults a.field-source").count()) >= 1, "at least one value shows its source");
  await page.click("#closeComparison");

  await page.check('input[name="region"][value="americas"]');
  await page.check("#exportExtra");
  const [download] = await Promise.all([page.waitForEvent("download"), page.click("#exportBtn")]);
  const text = readFileSync(await download.path(), "utf-8").replace(/^﻿/, "");
  const header = text.split("\n")[0];
  assert.match(header, /,field_sources,change_history$/);
  assert.match(text, /penalties_range: https?:\/\//);
  await page.context().close();
});
