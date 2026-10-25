// Shared engine behind privacy-laws/scripts/check-links.js and copyright-laws/scripts/check-links.js,
// which were, until this split, the same file kept in sync by hand. What differs between the two
// projects — the URL column, the unit label for the summary, the User-Agent and each project's own
// ./lib/dataset.js (different CSV columns) — is passed in via createCheckLinks(); everything else
// (HTTP checking, classification, concurrency, CLI parsing, reporting) lives here once.
//
// HEAD first, GET when a server refuses HEAD. Exit status 1 when a link is broken:
// 404/410 (any other 4xx), a host that no longer exists (DNS), an expired or wrong certificate.
// Warnings do not fail the run: 401/403/429 ("bots not welcome"), 5xx that persist after one
// retry (a server error or a firewall turning away cloud runners says nothing about whether
// the page is gone), timeouts and temporary DNS failures (many government sites throttle or
// geo-block cloud runners), an incomplete certificate chain (browsers repair it, Node does not) and
// a redirect loop (a cookie wall: the site sets a cookie and redirects, browsers keep it, fetch does not)
// and a TLS handshake the server refuses (it answers, but not to Node's TLS settings or to a cloud runner;
// a dead host fails earlier, in DNS or with a refused connection). An expired or wrong certificate is
// still broken. A refused connection is broken too, except at the hosts listed in
// hosts-que-rechazan-runners.txt, which are alive and turn GitHub's runners away.
// With GITHUB_STEP_SUMMARY set (GitHub Actions) a Markdown summary is appended to it.
import { appendFileSync, readFileSync, writeFileSync } from "node:fs";

// Hosts that are alive but refuse GitHub's runners (see hosts-que-rechazan-runners.txt).
const REFUSING_HOSTS = readFileSync(new URL("./hosts-que-rechazan-runners.txt", import.meta.url), "utf-8")
  .split("\n")
  .map((line) => line.replace(/#.*/, "").trim().toLowerCase())
  .filter(Boolean);

function refusesRunners(url) {
  try {
    const host = new URL(url).hostname.toLowerCase();
    return REFUSING_HOSTS.some((h) => host === h || host.endsWith(`.${h}`));
  } catch {
    return false;
  }
}

const SOFT = new Set([401, 403, 429]);

/** @returns {{status: "ok"|"warning"|"broken", code: number|null, detail: string, finalUrl: string}} */
// Network failures that say little about whether the page is gone.
const SOFT_ERRORS = /timeout|ETIMEDOUT|UND_ERR_CONNECT_TIMEOUT|UND_ERR_SOCKET|ECONNRESET|EAI_AGAIN|UNABLE_TO_VERIFY_LEAF_SIGNATURE|UNABLE_TO_GET_ISSUER_CERT|redirect count exceeded|TLS_ALERT_HANDSHAKE_FAILURE/;

export function classify(code, error, finalUrl) {
  if (error && /ECONNREFUSED/.test(error) && refusesRunners(finalUrl)) {
    return { status: "warning", code: null, detail: `${error} (this host is known to refuse cloud runners)`, finalUrl };
  }
  if (error && SOFT_ERRORS.test(error)) return { status: "warning", code: null, detail: `${error} (may work in a browser)`, finalUrl };
  if (error) return { status: "broken", code: null, detail: error, finalUrl };
  if (code >= 200 && code < 400) return { status: "ok", code, detail: "", finalUrl };
  if (SOFT.has(code)) return { status: "warning", code, detail: `HTTP ${code} (the site may block automated checks)`, finalUrl };
  if (code >= 500) return { status: "warning", code, detail: `HTTP ${code} (server error, also on retry; may be temporary or block automated checks)`, finalUrl };
  return { status: "broken", code, detail: `HTTP ${code}`, finalUrl };
}

/** Runs `worker` over `items`, at most `limit` at a time, keeping the input order. Needs no per-project config. */
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
  const options = { only: [], json: null, csv: undefined, timeoutMs: 15_000, concurrency: 4, sources: true };
  for (const arg of argv) {
    let m;
    if (arg === "--no-sources") options.sources = false;
    else if ((m = arg.match(/^--only=(.+)$/))) options.only = m[1].split(",").map((s) => s.trim().toLowerCase()).filter(Boolean);
    else if ((m = arg.match(/^--json=(.+)$/))) options.json = m[1];
    else if ((m = arg.match(/^--csv=(.+)$/))) options.csv = m[1];
    else if ((m = arg.match(/^--timeout=(\d{1,3})$/))) options.timeoutMs = Number(m[1]) * 1000;
    else if ((m = arg.match(/^--concurrency=(\d{1,2})$/))) options.concurrency = Math.max(1, Number(m[1]));
    else throw new Error(`unknown option ${arg}`);
  }
  return options;
}

/**
 * Builds a project's checkUrl, collectUrls and main, bound to what it alone knows.
 *
 * @param {object} config
 * @param {string} config.urlField column of the master CSV that holds each law's reference URL
 * @param {string} config.unit what a row is, for the summary heading ("country", "jurisdiction")
 * @param {string} config.userAgent sent with every request, so a server's logs point back to this project
 * @param {{MASTER_CSV: string, SOURCES_FILE: string, SOURCE_COLUMNS: string[], loadMaster: Function, loadSide: Function, findLaw: Function}} config.dataset
 *        the project's own ./lib/dataset.js, imported with `import * as dataset`
 */
export function createCheckLinks({ urlField, unit, userAgent, dataset }) {
  async function request(url, method, timeoutMs) {
    const response = await fetch(url, {
      method,
      redirect: "follow",
      signal: AbortSignal.timeout(timeoutMs),
      headers: { "User-Agent": userAgent, Accept: "text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.5" },
    });
    await response.body?.cancel(); // only the status matters
    return response;
  }

  async function checkUrl(url, { timeoutMs = 15_000, retryDelayMs = 5_000 } = {}) {
    try {
      let response = await request(url, "HEAD", timeoutMs);
      // Many servers answer HEAD with 403/405/501 (or 404) while GET works.
      if (response.status >= 400) response = await request(url, "GET", timeoutMs);
      // One more try for a 5xx: overloaded servers often recover within seconds.
      if (response.status >= 500) {
        await new Promise((resolve) => setTimeout(resolve, retryDelayMs));
        response = await request(url, "GET", timeoutMs);
      }
      return classify(response.status, null, response.url || url);
    } catch (error) {
      const cause = error.cause?.code || error.cause?.message;
      const detail = error.name === "TimeoutError" ? `timeout after ${timeoutMs / 1000} s` : cause ? `${error.message} (${cause})` : error.message;
      return classify(null, detail, url);
    }
  }

  /** Every URL to check, with the laws (and values) that rely on it. */
  function collectUrls(records, sources = [], only = []) {
    const byUrl = new Map();
    const add = (url, label) => {
      if (!byUrl.has(url)) byUrl.set(url, []);
      if (!byUrl.get(url).includes(label)) byUrl.get(url).push(label);
    };
    const wanted = (code) => !only.length || only.includes(code.toLowerCase());
    for (const record of records) {
      if (record[urlField] && wanted(record.country_code)) add(record[urlField], `${record.country_code} ${record.law_name}`);
    }
    for (const source of sources) {
      const law = dataset.findLaw(records, source.country_code, source.law_name);
      if (source.url && law && wanted(law.country_code)) add(source.url, `${law.country_code} ${law.law_name} (source of ${source.field})`);
    }
    return byUrl;
  }

  async function main(argv = process.argv.slice(2)) {
    const options = parseArgs(argv);
    const { records } = dataset.loadMaster(options.csv);
    const sources = options.sources ? dataset.loadSide(options.csv ?? dataset.MASTER_CSV, dataset.SOURCES_FILE, dataset.SOURCE_COLUMNS).rows : [];
    const byUrl = collectUrls(records, sources, options.only);

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
      const lines = [`### Reference links (${unit} data)`, "", `${urls.length} checked: ${broken.length} broken, ${warnings.length} warnings.`, ""];
      if (broken.length || warnings.length) {
        lines.push("| Law | URL | Problem |", "| --- | --- | --- |", ...broken.map(row), ...warnings.map(row), "");
      }
      appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${lines.join("\n")}\n`);
    }
    return broken.length ? 1 : 0;
  }

  return { checkUrl, collectUrls, main };
}
