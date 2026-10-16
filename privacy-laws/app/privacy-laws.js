import {
  bindLanguageSelect,
  formatDate,
  getLanguage,
  initLanguage,
  onLanguageChange,
  plural,
  t,
} from "./translations/i18n.js";
import { countryFileUrl, loadBundle } from "./lib/data.js";
import {
  buildChartBars,
  COMPARE_CRITERIA,
  CONFIG,
  DETAIL_FIELDS,
  enrichRow,
  FACETS,
  FRAMEWORK_ORDER,
  isReviewDue,
  SEARCH_SCOPES,
} from "./lib/domain.js";
import {
  activeFilters,
  buildIndex,
  clearFilters,
  emptyState,
  facetOptions,
  filterRows,
  pruneState,
  removeFilter,
  stateFromParams,
  stateToParams,
} from "./lib/filters.js";
import { renderBarChart } from "./lib/chart.js";
import { debounce, escapeHtml, safeUrl, toCsv } from "./lib/util.js";

const $ = (id) => document.getElementById(id);
const esc = escapeHtml;

const app = {
  onlyDiff: false,
  data: null,
  state: emptyState(CONFIG),
  results: [],
  chips: [],
  options: {},
  selected: new Set(),
  chartAsTable: false,
  urlHasLang: new URLSearchParams(location.search).has("lang"),
};

const regionLabel = (region) => t(`region.${region}`);
const frameworkName = (framework) => t(`framework.${framework}`);
// "Not specified" (or an empty cell) is shown muted and in the interface language, so real data stands out.
const NOT_SPECIFIED = /^(not specified|—|-)?$/i;
const valueHtml = (value) =>
  NOT_SPECIFIED.test(String(value ?? "").trim())
    ? `<span class="muted">${esc(t("value.notSpecified"))}</span>`
    : esc(value);
const dateHtml = (iso) => `<time datetime="${esc(iso)}">${esc(formatDate(iso))}</time>`;
/** "www.example.org" → "example.org": shows where a link leads (official site, WIPO Lex, a law firm…). */
const hostOf = (url) => {
  try {
    return new URL(url).hostname.replace(/^www\./, "");
  } catch {
    return "";
  }
};
function sourceLink(url, lawName) {
  return url
    ? `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" class="source-link" title="${esc(t("table.open"))}" aria-label="${esc(
        `${t("table.open")}: ${lawName} (${hostOf(url)})`
      )}">${hostOf(url).split(".").map(esc).join(".<wbr>")}<span aria-hidden="true"> ↗</span></a>`
    : valueHtml("");
}
// Comparison rows whose values are lists of treaties/frameworks, shown as badges with full names.
const LIST_CRITERIA = { frameworks: frameworkName, frameworks_not: frameworkName };

const sourceLabel = (status) => t(`source.${status}`);
const flagLabel = (flag) => t(`flag.${flag}`);
/** "Web research (secondary sources) · medium confidence · checked 8 Oct 2026" */
function sourceDetail(row) {
  const parts = [sourceLabel(row.data_status)];
  if (row.confidence) parts.push(t(`confidence.${row.confidence}`));
  if (row.last_checked) parts.push(t("card.checked", { date: formatDate(row.last_checked) }));
  if (isReviewDue(row.last_checked, TODAY)) parts.push(t("card.reviewDue"));
  return parts.join(" · ");
}
function completenessText(row) {
  return t("card.completenessValue", {
    filled: row.fieldsFilled,
    total: row.fieldsTotal,
    percent: Math.round((100 * row.fieldsFilled) / row.fieldsTotal),
    sourced: row.fieldsSourced,
  });
}
// Laws can be enacted with an effective date still ahead (ISO dates compare as strings).
const TODAY = new Date().toISOString().slice(0, 10);

// Labels for any column (the change history can name any of them).
const FIELD_LABEL_KEYS = { law_name: "table.law", jurisdiction: "compare.jurisdiction", enforcement_authority: "table.authority", website_url: "table.reference", notes: "card.notes" };
function fieldLabel(field) {
  const key = FIELD_LABEL_KEYS[field] ?? `compare.${field}`;
  const text = t(key);
  return text === key ? field.replace(/_/g, " ") : text;
}
const shorten = (value, max = 140) => {
  const text = String(value ?? "").trim();
  return text.length > max ? `${text.slice(0, max - 1)}…` : text;
};

/** A small link after a value: where that one value comes from. */
function fieldSourceHtml(source) {
  const url = source && safeUrl(source.url);
  if (!url) return "";
  const detail = [
    t("card.fieldSource", { host: hostOf(url) }),
    source.confidence ? t(`confidence.${source.confidence}`) : "",
    source.checked ? t("card.checked", { date: formatDate(source.checked) }) : "",
  ]
    .filter(Boolean)
    .join(" · ");
  return ` <a class="field-source" href="${esc(url)}" target="_blank" rel="noopener noreferrer" title="${esc(detail)}" aria-label="${esc(
    detail
  )}">${esc(hostOf(url))}<span aria-hidden="true"> ↗</span></a>`;
}

function pendingHtml(row) {
  if (!row.pending_change) return "";
  const date = row.pending_date ? formatDate(row.pending_date) : "";
  const when = !date ? "" : row.pending_date <= TODAY ? t("card.pendingOverdue", { date }) : t("card.pendingFrom", { date });
  return `<p class="card-pending${row.pending_date && row.pending_date <= TODAY ? " is-overdue" : ""}"><strong>${esc(t("card.pending"))}</strong> · ${esc(
    row.law_name
  )}: ${esc(row.pending_change)}${when ? ` <span class="nowrap">(${esc(when)})</span>` : ""}</p>`;
}

/** One recorded change as a list item; `who` prefixes it with the law it belongs to (the recent-changes list). */
function changeItem(change, who = "") {
  if (change.field === "row") return `<li>${dateHtml(change.date)} · ${who}${esc(t("card.historyAdded"))}</li>`;
  const isDate = /_date$/.test(change.field);
  const was = change.from && !NOT_SPECIFIED.test(change.from.trim())
    ? ` <span class="muted">(${esc(t("card.historyWas", { value: isDate ? formatDate(change.from) : shorten(change.from, 100) }))})</span>`
    : "";
  const now = isDate && change.to ? dateHtml(change.to) : valueHtml(shorten(change.to));
  return `<li>${dateHtml(change.date)} · ${who}<strong>${esc(fieldLabel(change.field))}</strong>: ${now}${was}</li>`;
}

const HISTORY_SHOWN = 8;
function historyHtml(row) {
  if (!row.changes.length) return "";
  const more = row.changes.length - HISTORY_SHOWN;
  return `
    <h3 class="history-title">${esc(t("card.history"))}</h3>
    <ul class="history">${row.changes.slice(0, HISTORY_SHOWN).map((change) => changeItem(change)).join("")}${
      more > 0 ? `<li class="muted">${esc(plural("card.historyMore", more))}</li>` : ""
    }</ul>`;
}

const NEWS_SHOWN = 15;
/** The latest recorded changes across the laws currently listed, newest first. */
function renderNews() {
  const section = $("newsSection");
  const entries = app.results
    .flatMap((row) => row.changes.map((change) => ({ row, change })))
    .sort((a, b) => (a.change.date < b.change.date ? 1 : a.change.date > b.change.date ? -1 : 0));
  section.hidden = entries.length === 0;
  if (!entries.length) return;
  const who = ({ row }) =>
    `<button type="button" class="link-button" data-news-country="${esc(row.tld)}" title="${esc(t("table.onlyCountry", { name: row.country_name }))}">${esc(row.country_name)}</button> — ${esc(row.law_name)}: `;
  const more = entries.length - NEWS_SHOWN;
  $("newsList").innerHTML =
    entries.slice(0, NEWS_SHOWN).map((entry) => changeItem(entry.change, who(entry))).join("") +
    (more > 0 ? `<li class="muted">${esc(plural("news.more", more))}</li>` : "");
}

function lawDetailsHtml(row, open) {
  // The law's name sits in <summary>, where a link is not allowed: its source goes in the list.
  const fields = row.sources.law_name ? ["law_name", ...DETAIL_FIELDS] : DETAIL_FIELDS;
  const items = fields.map((field) => {
    const value = row[field] ?? "";
    const shown = /_date$/.test(field) && value ? dateHtml(value) : valueHtml(value);
    return `<div><dt>${esc(fieldLabel(field))}</dt><dd>${shown}${fieldSourceHtml(row.sources[field])}</dd></div>`;
  }).join("");
  return `
    <details class="law-details"${open ? " open" : ""}>
      <summary>${esc(t("card.details", { law: row.law_name }))}</summary>
      <dl class="detail-grid">${items}</dl>
      ${historyHtml(row)}
    </details>`;
}
const countryName = (tld) => app.data.countries.find((c) => c.tld === tld)?.name ?? tld.toUpperCase();

function formatRange({ from, to }) {
  if (from != null && to != null) return `${from}–${to}`;
  return from != null ? `≥ ${from}` : `≤ ${to}`;
}

/* ---------- Static parts (rebuilt when the language changes) ---------- */

function renderScopeSelect() {
  $("scope").innerHTML = SEARCH_SCOPES.map(
    (scope) => `<option value="${esc(scope.id)}">${esc(t(`scope.${scope.id}`))}</option>`
  ).join("");
  $("scope").value = app.state.scope;
}

function renderCountryList() {
  $("countryList").innerHTML = app.data.countries
    .map((country) => `<option value="${esc(country.name)}"></option>`)
    .join("");
}

function checkboxFacet(id, legendKey, options, labelFor, { hintKey, titleFor } = {}) {
  const items = options
    .map(
      ({ value, count }) => `
      <label class="check"${titleFor ? ` title="${esc(titleFor(value))}"` : ""}>
        <input type="checkbox" name="${id}" value="${esc(value)}" />
        <span>${esc(labelFor(value))}</span> <span class="count">(${count})</span>
      </label>`
    )
    .join("");
  const hint = hintKey ? `<small class="field-hint">${esc(t(hintKey))}</small>` : "";
  return `
    <fieldset class="facet" data-facet="${id}">
      <legend>${esc(t(legendKey))}</legend>${hint}
      <div class="facet-options">${items}</div>
    </fieldset>`;
}

function renderFacets() {
  const { min, max } = app.options.year;
  const languages = app.options.language
    .map(({ value, count }) => `<option value="${esc(value)}">${esc(value)} (${count})</option>`)
    .join("");

  // A framework nobody takes part in yet cannot narrow anything, so the filter stays hidden.
  const frameworks = app.options.framework.length
    ? checkboxFacet("framework", "filters.framework", app.options.framework, (v) => v, {
        hintKey: "filters.frameworkHint",
        titleFor: frameworkName,
      })
    : "";

  $("facets").innerHTML = `
    ${checkboxFacet("region", "filters.region", app.options.region, regionLabel)}
    ${frameworks}
    ${checkboxFacet("source", "filters.source", app.options.source, sourceLabel, { hintKey: "filters.sourceHint" })}
    ${app.options.flag.length ? checkboxFacet("flag", "filters.flag", app.options.flag, flagLabel, { hintKey: "filters.flagHint" }) : ""}
    <fieldset class="facet">
      <legend>${esc(t("filters.year"))}</legend>
      <div class="range-inputs">
        <label>${esc(t("filters.yearFrom"))}
          <input type="number" name="year-from" min="${min}" max="${max}" placeholder="${min}" inputmode="numeric" />
        </label>
        <label>${esc(t("filters.yearTo"))}
          <input type="number" name="year-to" min="${min}" max="${max}" placeholder="${max}" inputmode="numeric" />
        </label>
      </div>
    </fieldset>
    <div class="facet">
      <label for="f-language">${esc(t("filters.language"))}</label>
      <select id="f-language" name="language">
        <option value="">${esc(t("filters.anyLanguage"))}</option>${languages}
      </select>
    </div>`;
  syncFacetControls();
}

function renderFooter() {
  $("footerData").textContent = t("footer.data", {
    date: formatDate(app.data.meta.generatedAt),
    version: app.data.meta.version,
    laws: app.data.rows.length,
    countries: plural("unit.country", app.data.countries.length),
  });
}

function renderStatic() {
  renderScopeSelect();
  renderFacets();
  renderFooter();
}

/* ---------- Dynamic parts (re-rendered on every state change) ---------- */

function syncFacetControls() {
  const { region, framework, source, flag, year, language } = app.state.facets;
  const facets = $("facets");
  for (const input of facets.querySelectorAll('input[name="region"]')) {
    input.checked = region.includes(input.value);
  }
  for (const input of facets.querySelectorAll('input[name="framework"]')) {
    input.checked = framework.includes(input.value);
  }
  for (const input of facets.querySelectorAll('input[name="source"]')) {
    input.checked = source.includes(input.value);
  }
  for (const input of facets.querySelectorAll('input[name="flag"]')) {
    input.checked = flag.includes(input.value);
  }
  facets.querySelector('[name="year-from"]').value = year.from ?? "";
  facets.querySelector('[name="year-to"]').value = year.to ?? "";
  facets.querySelector('[name="language"]').value = language;
  $("scope").value = app.state.scope;
}

function chipLabel(chip) {
  switch (chip.facet) {
    case "country":
      return t("chip.country", { value: countryName(chip.value) });
    case "region":
      return regionLabel(chip.value);
    case "framework":
      return t("chip.framework", { value: chip.value });
    case "source":
      return t("chip.source", { value: sourceLabel(chip.value) });
    case "flag":
      return t("chip.flag", { value: flagLabel(chip.value) });
    case "year":
      return t("chip.year", { value: formatRange(app.state.facets.year) });
    default:
      return t("chip.language", { value: chip.value });
  }
}

function renderChips() {
  app.chips = activeFilters(app.state, CONFIG);
  const host = $("chips");
  host.hidden = app.chips.length === 0;
  host.innerHTML = app.chips.length
    ? app.chips
        .map((chip, index) => {
          const label = chipLabel(chip);
          return `<span class="chip">${esc(label)}<button type="button" data-chip="${index}" aria-label="${esc(
            t("chip.remove", { value: label })
          )}">×</button></span>`;
        })
        .join("") +
      `<button type="button" class="link-button" data-clear-filters>${esc(t("filters.clearAll"))}</button>`
    : "";

  const panelFilters = app.chips.filter((chip) => chip.facet !== "country").length;
  const badge = $("filterCount");
  badge.hidden = panelFilters === 0;
  badge.textContent = panelFilters ? plural("filters.active", panelFilters) : "";
}

function renderSummary() {
  const countries = new Set(app.results.map((row) => row.tld)).size;
  $("resultsSummary").textContent = t("results.summary", {
    shown: app.results.length,
    total: app.data.rows.length,
    countries: plural("unit.country", countries),
  });
}

function renderChart() {
  const host = $("chart");
  const bars = buildChartBars(app.results);
  if (!bars.length) {
    host.replaceChildren();
    return;
  }
  const first = bars[0].label;
  const last = bars[bars.length - 1].label;
  renderBarChart(host, {
    title: t("chart.title"),
    subtitle: t("chart.subtitle", {
      laws: plural("unit.law", app.results.length),
      span: first === last ? first : `${first}–${last}`,
    }),
    bars,
    formatValue: (n) => plural("unit.law", n),
    labels: {
      showTable: t("chart.showTable"),
      showChart: t("chart.showChart"),
      category: t("chart.colYear"),
      value: t("chart.colLaws"),
      details: t("chart.colDetails"),
      more: (count) => t("chart.more", { count }),
    },
    showTable: app.chartAsTable,
    onToggle: (isTable) => {
      app.chartAsTable = isTable;
    },
  });
}

function rowHtml(row) {
  const url = safeUrl(row.website_url);
  const checked = app.selected.has(row.id) ? " checked" : "";
  const label = `${t("table.select")}: ${row.country_name} — ${row.law_name}`;
  return `
    <tr>
      <td><input type="checkbox" data-id="${esc(row.id)}" aria-label="${esc(label)}"${checked} /></td>
      <td><button type="button" class="link-button" data-country="${esc(row.tld)}" title="${esc(
        t("table.onlyCountry", { name: row.country_name })
      )}">${esc(row.country_name)}</button></td>
      <td>${esc(row.law_name)}${
        row.confidence === "low" ? ` <span class="badge badge-outline">${esc(t("table.lowConfidence"))}</span>` : ""
      }${row.pending_change ? ` <span class="badge badge-outline" title="${esc(row.pending_change)}">${esc(t("table.pending"))}</span>` : ""}</td>
      <td class="nowrap col-optional">${dateHtml(row.enactment_date)}</td>
      <td class="nowrap">${dateHtml(row.effective_date)}${
        row.effective_date > TODAY ? ` <span class="badge">${esc(t("table.upcoming"))}</span>` : ""
      }</td>
      <td>${esc(row.enforcement_authority)}</td>
      <td class="col-optional"><small>${valueHtml(row.penalties_range)}</small></td>
      <td class="col-source">${sourceLink(url, row.law_name)}</td>
    </tr>`;
}

function syncSelectAll() {
  const picked = app.results.filter((row) => app.selected.has(row.id)).length;
  const box = $("selectAll");
  box.checked = app.results.length > 0 && picked === app.results.length;
  box.indeterminate = picked > 0 && picked < app.results.length;
}

function renderTable() {
  const empty = app.results.length === 0;
  $("tableWrap").hidden = empty;
  $("emptyState").hidden = !empty;
  $("lawsTableBody").innerHTML = app.results.map(rowHtml).join("");
  syncSelectAll();
}

function renderCountryCard() {
  const host = $("countryCard");
  const tlds = [...new Set(app.results.map((row) => row.tld))];
  if (tlds.length !== 1) {
    host.hidden = true;
    host.replaceChildren();
    return;
  }
  const country = app.data.countries.find((c) => c.tld === tlds[0]);
  const frameworks = [
    ...new Set(app.data.rows.filter((row) => row.tld === country.tld).flatMap((row) => row.frameworkList)),
  ].sort((a, b) => FRAMEWORK_ORDER.indexOf(a) - FRAMEWORK_ORDER.indexOf(b));
  const laws = app.data.rows.filter((row) => row.tld === country.tld);
  const notIn = FRAMEWORK_ORDER.filter((f) => !frameworks.includes(f) && laws.some((row) => row.frameworksNot.includes(f)));
  const unconfirmed = FRAMEWORK_ORDER.filter((f) => !frameworks.includes(f) && !notIn.includes(f));
  const badgeList = (list, extraClass = "") =>
    list.length
      ? list.map((f) => `<span class="badge${extraClass}" title="${esc(frameworkName(f))}">${esc(f)}</span>`).join("")
      : "—";
  const badges = badgeList(frameworks);
  const notes = [...new Set(laws.map((row) => row.notes).filter(Boolean))];
  host.hidden = false;
  host.innerHTML = `
    <h2>${esc(country.name)} <span class="badge">${esc(country.code)}</span></h2>
    <dl class="card-grid">
      <div><dt>${esc(t("card.region"))}</dt><dd>${esc(regionLabel(country.region))}</dd></div>
      <div><dt>${esc(t("card.laws"))}</dt><dd>${country.lawCount}</dd></div>
      <div><dt>${esc(t("card.frameworks"))}</dt><dd class="badge-list">${badges}</dd></div>
      <div><dt>${esc(t("card.frameworksNot"))}</dt><dd class="badge-list">${badgeList(notIn, " badge-outline")}</dd></div>
      <div><dt>${esc(t("card.unconfirmed"))}</dt><dd>${unconfirmed.length ? esc(unconfirmed.join(", ")) : "—"}</dd></div>
      <div><dt>${esc(t("card.folder"))}</dt><dd><code>countries/${esc(country.tld)}/</code></dd></div>
      <div><dt>${esc(t("card.source"))}</dt><dd>${laws.map((row) => esc(sourceDetail(row))).join("<br>")}</dd></div>
      <div><dt>${esc(t("card.completeness"))}</dt><dd>${laws.map((row) => esc(completenessText(row))).join("<br>")}</dd></div>
      <div><dt>${esc(t("card.updated"))}</dt><dd>${esc(formatDate(app.data.meta.generatedAt))}</dd></div>
    </dl>
    ${notes.length ? `<p class="card-notes"><strong>${esc(t("card.notes"))}:</strong> ${esc(notes.join(" · "))}</p>` : ""}
    ${laws.map(pendingHtml).join("")}
    ${laws.map((row) => lawDetailsHtml(row, laws.length === 1)).join("")}
    <p class="card-links">
      <a href="${esc(countryFileUrl(country.tld, "laws.json"))}" download>${esc(t("card.json"))}</a>
      <a href="${esc(countryFileUrl(country.tld, "laws.csv"))}" download>${esc(t("card.csv"))}</a>
    </p>`;
}

function renderCompareButton() {
  const count = app.selected.size;
  $("compareBtn").textContent = count ? t("btn.compareCount", { count }) : t("btn.compare");
}

function pickedRows() {
  return app.results.filter((row) => app.selected.has(row.id));
}

function renderComparison() {
  const section = $("comparisonSection");
  if (section.hidden) return;
  const picked = pickedRows();
  if (new Set(picked.map((row) => row.tld)).size < 2) {
    section.hidden = true;
    return;
  }
  const head = picked
    .map((row) => `<th scope="col">${esc(row.country_name)}<br /><small>${esc(row.law_name)}</small></th>`)
    .join("");
  const cell = (criterion, row) => {
    const value = row[criterion] ?? "";
    if (LIST_CRITERIA[criterion]) {
      const items = value.split("/").filter(Boolean);
      return items.length
        ? `<span class="badge-list">${items.map((v) => `<span class="badge${criterion.endsWith("_not") || criterion.endsWith("not_party") ? " badge-outline" : ""}" title="${esc(LIST_CRITERIA[criterion](v))}">${esc(v)}</span>`).join("")}</span>`
        : valueHtml("");
    }
    return (/_date$/.test(criterion) && value ? dateHtml(value) : valueHtml(value)) + fieldSourceHtml(row.sources[criterion]);
  };
  let differing = 0;
  const body = COMPARE_CRITERIA.map((criterion) => {
    const values = picked.map((row) => String(row[criterion] ?? "").trim());
    if (values.every((v) => NOT_SPECIFIED.test(v))) return ""; // nothing to compare
    const differs = new Set(values.map((v) => v.toLowerCase())).size > 1;
    if (differs) differing++;
    return `
      <tr class="${differs ? "is-diff" : "is-same"}">
        <th scope="row">${esc(t(`compare.${criterion}`))}</th>
        ${picked.map((row) => `<td>${cell(criterion, row)}</td>`).join("")}
      </tr>`;
  }).join("");
  $("comparisonResults").innerHTML = `
    <p class="comparison-tools">
      <label class="check"><input type="checkbox" id="onlyDiff"${app.onlyDiff ? " checked" : ""} /> <span>${esc(
        t("compare.onlyDiff", { count: differing })
      )}</span></label>
    </p>
    <table class="comparison-table${app.onlyDiff ? " only-diff" : ""}">
      <thead><tr><th scope="col">${esc(t("compare.criterion"))}</th>${head}</tr></thead>
      <tbody>${body}</tbody>
    </table>`;
}

function writeUrl() {
  const params = stateToParams(app.state, CONFIG);
  if (app.urlHasLang) params.set("lang", getLanguage());
  const query = params.toString();
  history.replaceState(null, "", query ? `?${query}` : location.pathname);
}

function update() {
  app.state.q = $("q").value;
  app.results = filterRows(app.data.rows, app.state, CONFIG);

  const visible = new Set(app.results.map((row) => row.id));
  for (const id of app.selected) if (!visible.has(id)) app.selected.delete(id);

  syncFacetControls();
  renderChips();
  renderSummary();
  renderChart();
  renderTable();
  renderCountryCard();
  renderNews();
  renderCompareButton();
  renderComparison();
  writeUrl();
}

/* ---------- Events ---------- */

function handleCompare() {
  const picked = pickedRows();
  if (new Set(picked.map((row) => row.tld)).size < 2) {
    alert(t("compare.needTwo"));
    return;
  }
  const section = $("comparisonSection");
  section.hidden = false;
  renderComparison();
  section.scrollIntoView({ block: "start", behavior: scrollBehavior() });
}

/** One cell per law: where each value comes from, and what changed when. */
function exportExtras(row) {
  const sources = Object.entries(row.sources)
    .map(([field, source]) => `${field}: ${source.url}${source.checked ? ` (${source.checked})` : ""}`)
    .join(" | ");
  const history = row.changes
    .map((change) => (change.field === "row" ? `${change.date} ${change.to}` : `${change.date} ${change.field}: ${change.from || "(empty)"} -> ${change.to}`))
    .join(" | ");
  return { field_sources: sources, change_history: history };
}

function handleExport() {
  const extra = $("exportExtra").checked;
  const columns = extra ? [...app.data.meta.columns, "field_sources", "change_history"] : app.data.meta.columns;
  const rows = extra ? app.results.map((row) => ({ ...row, ...exportExtras(row) })) : app.results;
  const csv = `﻿${toCsv(columns, rows)}`;
  const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = `privacy-laws-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const scrollBehavior = () =>
  matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth";

function readYear(name) {
  const value = $("facets").querySelector(`[name="${name}"]`).value.trim();
  return /^\d{4}$/.test(value) ? Number(value) : null;
}

function bindEvents() {
  const applyQuery = debounce(update, 150);
  $("q").addEventListener("input", applyQuery);
  $("searchForm").addEventListener("submit", (event) => {
    event.preventDefault();
    applyQuery.flush();
  });
  $("scope").addEventListener("change", () => {
    app.state.scope = $("scope").value;
    update();
  });

  $("facets").addEventListener("change", (event) => {
    const { name, value } = event.target;
    const { facets } = app.state;
    if (name === "region" || name === "framework" || name === "source" || name === "flag") {
      facets[name] = [...$("facets").querySelectorAll(`input[name="${name}"]:checked`)].map((i) => i.value);
    } else if (name === "year-from" || name === "year-to") {
      let from = readYear("year-from");
      let to = readYear("year-to");
      if (from != null && to != null && from > to) [from, to] = [to, from];
      facets.year = { from, to };
    } else if (name === "language") {
      facets.language = value;
    }
    update();
  });

  $("chips").addEventListener("click", (event) => {
    const remove = event.target.closest("[data-chip]");
    let focusTarget;
    if (remove) {
      const index = Number(remove.dataset.chip);
      app.state = removeFilter(app.state, CONFIG, app.chips[index]);
      // The clicked button is about to be re-rendered: hand focus to the chip that takes its place.
      focusTarget = () => $("chips").querySelector(`[data-chip="${index}"]`) ?? $("chips").querySelector("[data-clear-filters]") ?? $("q");
    } else if (event.target.closest("[data-clear-filters]")) {
      app.state = clearFilters(app.state, CONFIG);
      focusTarget = () => $("q");
    } else {
      return;
    }
    update();
    focusTarget().focus();
  });

  $("clearBtn").addEventListener("click", () => {
    app.state = emptyState(CONFIG);
    app.selected.clear();
    $("q").value = "";
    update();
    $("q").focus();
  });

  $("lawsTableBody").addEventListener("change", (event) => {
    const box = event.target.closest("input[data-id]");
    if (!box) return;
    if (box.checked) app.selected.add(box.dataset.id);
    else app.selected.delete(box.dataset.id);
    syncSelectAll();
    renderCompareButton();
  });
  $("lawsTableBody").addEventListener("click", (event) => {
    const button = event.target.closest("[data-country]");
    if (!button) return;
    app.state.country = button.dataset.country;
    update();
    // The button that was clicked is re-rendered, so move focus to the card it opened.
    $("countryCard").focus({ preventScroll: true });
    $("countryCard").scrollIntoView({ block: "start", behavior: scrollBehavior() });
  });
  $("newsList").addEventListener("click", (event) => {
    const button = event.target.closest("[data-news-country]");
    if (!button) return;
    app.state.country = button.dataset.newsCountry;
    update();
    $("countryCard").focus({ preventScroll: true });
    $("countryCard").scrollIntoView({ block: "start", behavior: scrollBehavior() });
  });
  $("selectAll").addEventListener("change", (event) => {
    for (const row of app.results) {
      if (event.target.checked) app.selected.add(row.id);
      else app.selected.delete(row.id);
    }
    for (const box of $("lawsTableBody").querySelectorAll("input[data-id]")) {
      box.checked = event.target.checked;
    }
    syncSelectAll();
    renderCompareButton();
  });

  $("compareBtn").addEventListener("click", handleCompare);
  $("exportBtn").addEventListener("click", handleExport);
  $("comparisonResults").addEventListener("change", (event) => {
    if (event.target.id !== "onlyDiff") return;
    app.onlyDiff = event.target.checked;
    $("comparisonResults").querySelector(".comparison-table").classList.toggle("only-diff", app.onlyDiff);
  });
  $("closeComparison").addEventListener("click", () => {
    $("comparisonSection").hidden = true;
    $("compareBtn").focus();
  });
}

/* ---------- Boot ---------- */

async function init() {
  initLanguage();
  bindLanguageSelect($("languageSelect"));

  try {
    app.data = await loadBundle(enrichRow);
  } catch (error) {
    console.error(error);
    $("status").textContent = t("status.error");
    $("status").hidden = false;
    return;
  }

  buildIndex(app.data.rows, CONFIG);
  app.options = Object.fromEntries(FACETS.map((facet) => [facet.id, facetOptions(app.data.rows, facet)]));
  app.state = pruneState(
    stateFromParams(new URLSearchParams(location.search), CONFIG),
    CONFIG,
    app.data.rows
  );

  $("q").value = app.state.q;
  renderCountryList();
  renderStatic();
  bindEvents();
  update();
  $("advanced").open =
    matchMedia("(min-width: 769px)").matches || activeFilters(app.state, CONFIG).length > 0;

  onLanguageChange(() => {
    renderStatic();
    update();
  });
}

init();
