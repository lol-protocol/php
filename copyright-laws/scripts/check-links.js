#!/usr/bin/env node
// Checks that every reference URL in the master CSV still answers.
//
//   npm run check:links                       all URLs (4 at a time, 15 s timeout)
//   npm run check:links -- --only=us,br       only these jurisdictions
//   npm run check:links -- --json=report.json also write a machine-readable report
//
// HEAD first, GET when a server refuses HEAD. Exit status 1 when a link is broken
// (404, 410, 5xx, DNS/TLS/timeout errors). 401/403/429 usually mean "bots not welcome",
// not a dead page: they are reported as warnings and do not fail the run.
// With GITHUB_STEP_SUMMARY set (GitHub Actions) a Markdown summary is appended to it.
import { appendFileSync, writeFileSync } from "node:fs";
import { pathToFileURL } from "node:url";
import { loadMaster } from "./lib/dataset.js";

const URL_FIELD = "linked_resources";
const UNIT = "jurisdiction";
const USER_AGENT = "copyright-laws-link-check/1.0 (+https://github.com/lol-protocol/php)";
const SOFT = new Set([401, 403, 429]);

/** @returns {{status: "ok"|"warning"|"broken", code: number|null, detail: string, finalUrl: string}} */
export function classify(code, error, finalUrl) {
  if (error) return { status: "broken", code: null, detail: error, finalUrl };
  if (code >= 200 && code < 400) return { status: "ok", code, detail: "", finalUrl };
  if (SOFT.has(code)) return { status: "warning", code, detail: `HTTP ${code} (the site may block automated checks)`, finalUrl };
  return { status: "broken", code, detail: `HTTP ${code}`, finalUrl };
}

async function request(url, method, timeoutMs) {
  const response = await fetch(url, {
    method,
    redirect: "follow",
    signal: AbortSignal.timeout(timeoutMs),
    headers: { "User-Agent": USER_AGENT, Accept: "text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.5" },
  });
  await response.body?.cancel(); // only the status matters
  return response;
}

export async function checkUrl(url, { timeoutMs = 15_000 } = {}) {
  try {
    let response = await request(url, "HEAD", timeoutMs);
    // Many servers answer HEAD with 403/405/501 (or 404) while GET works.
    if (response.status >= 400) response = await request(url, "GET", timeoutMs);
    return classify(response.status, null, response.url || url);
  } catch (error) {
    const cause = error.cause?.code || error.cause?.message;
    const detail = error.name === "TimeoutError" ? `timeout after ${timeoutMs / 1000} s` : cause ? `${error.message} (${cause})` : error.message;
    return classify(null, detail, url);
  }
}

/** Runs `worker` over `items`, at most `limit` at a time, keeping the input order. */
export async function mapLimit(items, limit, worker) {
  const results = new Array(items.length);
  let next = 0;
  const lanes = Array.from({ length: Math.min(limit, items.length) }, async () => {
    while (next < items.length) {
      const index = next++;
      results[index] = await worker(items[index], index);
    }
  });
  await Promise.all(lanes);
  return results;
}

function parseArgs(argv) {
  const options = { only: [], json: null, csv: undefined, timeoutMs: 15_000, concurrency: 4 };
  for (const arg of argv) {
    let m;
    if ((m = arg.match(/^--only=(.+)$/))) options.only = m[1].split(",").map((s) => s.trim().toLowerCase()).filter(Boolean);
    else if ((m = arg.match(/^--json=(.+)$/))) options.json = m[1];
    else if ((m = arg.match(/^--csv=(.+)$/))) options.csv = m[1];
    else if ((m = arg.match(/^--timeout=(\d{1,3})$/))) options.timeoutMs = Number(m[1]) * 1000;
    else if ((m = arg.match(/^--concurrency=(\d{1,2})$/))) options.concurrency = Math.max(1, Number(m[1]));
    else throw new Error(`unknown option ${arg}`);
  }
  return options;
}

export async function main(argv = process.argv.slice(2)) {
  const options = parseArgs(argv);
  const { records } = loadMaster(options.csv);
  const byUrl = new Map();
  for (const record of records) {
    const url = record[URL_FIELD];
    if (!url) continue;
    if (options.only.length && !options.only.includes(record.country_code.toLowerCase())) continue;
    if (!byUrl.has(url)) byUrl.set(url, []);
    byUrl.get(url).push(`${record.country_code} ${record.law_name}`);
  }

  const urls = [...byUrl.keys()];
  const results = await mapLimit(urls, options.concurrency, async (url) => {
    const result = { url, laws: byUrl.get(url), ...(await checkUrl(url, options)) };
    const mark = { ok: "ok  ", warning: "warn", broken: "FAIL" }[result.status];
    console.log(`[${mark}] ${result.code ?? "---"}  ${url}${result.detail ? `  ${result.detail}` : ""}`);
    return result;
  });

  const broken = results.filter((r) => r.status === "broken");
  const warnings = results.filter((r) => r.status === "warning");
  console.log(`\n${urls.length} URLs: ${urls.length - broken.length - warnings.length} ok, ${warnings.length} warnings, ${broken.length} broken`);

  if (options.json) writeFileSync(options.json, `${JSON.stringify({ checkedAt: new Date().toISOString(), results }, null, 2)}\n`);
  if (process.env.GITHUB_STEP_SUMMARY) {
    const row = (r) => `| ${r.laws.join("<br>")} | ${r.url} | ${r.detail} |`;
    const lines = [`### Reference links (${UNIT} data)`, "", `${urls.length} checked: ${broken.length} broken, ${warnings.length} warnings.`, ""];
    if (broken.length || warnings.length) {
      lines.push("| Law | URL | Problem |", "| --- | --- | --- |", ...broken.map(row), ...warnings.map(row), "");
    }
    appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${lines.join("\n")}\n`);
  }
  return broken.length ? 1 : 0;
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().then(
    (code) => process.exit(code),
    (error) => {
      console.error(`Error: ${error.message}`);
      process.exit(2);
    }
  );
}
