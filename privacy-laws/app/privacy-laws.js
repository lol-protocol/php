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
  enrichRow,
  FACETS,
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

function renderFacets() {
  const regions = app.options.region
    .map(
      ({ value, count }) => `
      <label class="check">
        <input type="checkbox" name="region" value="${esc(value)}" />
        <span>${esc(regionLabel(value))}</span> <span class="count">(${count})</span>
      </label>`
    )
    .join("");
  const { min, max } = app.options.year;
  const languages = app.options.language
    .map(({ value, count }) => `<option value="${esc(value)}">${esc(value)} (${count})</option>`)
    .join("");

  $("facets").innerHTML = `
    <fieldset class="facet">
      <legend>${esc(t("filters.region"))}</legend>
      <div class="facet-options">${regions}</div>
    </fieldset>
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
  const { region, year, language } = app.state.facets;
  const facets = $("facets");
  for (const input of facets.querySelectorAll('input[name="region"]')) {
    input.checked = region.includes(input.value);
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
      <td>${esc(row.law_name)}</td>
      <td class="nowrap">${esc(row.enactment_date)}</td>
      <td class="nowrap">${esc(row.effective_date)}</td>
      <td>${esc(row.enforcement_authority)}</td>
      <td><small>${esc(row.penalties_range)}</small></td>
      <td>${
        url
          ? `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" title="${esc(
              t("table.open")
            )}" aria-label="${esc(`${t("table.open")}: ${row.law_name}`)}">📖</a>`
          : "-"
      }</td>
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
  host.hidden = false;
  host.innerHTML = `
    <h2>${esc(country.name)} <span class="badge">${esc(country.code)}</span></h2>
    <dl class="card-grid">
      <div><dt>${esc(t("card.region"))}</dt><dd>${esc(regionLabel(country.region))}</dd></div>
      <div><dt>${esc(t("card.laws"))}</dt><dd>${country.lawCount}</dd></div>
      <div><dt>${esc(t("card.folder"))}</dt><dd><code>countries/${esc(country.tld)}/</code></dd></div>
      <div><dt>${esc(t("card.updated"))}</dt><dd>${esc(formatDate(app.data.meta.generatedAt))}</dd></div>
    </dl>
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
  const body = COMPARE_CRITERIA.map(
    (criterion) => `
      <tr>
        <th scope="row">${esc(t(`compare.${criterion}`))}</th>
        ${picked.map((row) => `<td>${esc(row[criterion] || "—")}</td>`).join("")}
      </tr>`
  ).join("");
  $("comparisonResults").innerHTML = `
    <table class="comparison-table">
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

function handleExport() {
  const csv = `﻿${toCsv(app.data.meta.columns, app.results)}`;
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
    if (name === "region") {
      facets.region = [...$("facets").querySelectorAll('input[name="region"]:checked')].map((i) => i.value);
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
    if (remove) {
      app.state = removeFilter(app.state, CONFIG, app.chips[Number(remove.dataset.chip)]);
    } else if (event.target.closest("[data-clear-filters]")) {
      app.state = clearFilters(app.state, CONFIG);
    } else {
      return;
    }
    update();
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
  $("closeComparison").addEventListener("click", () => {
    $("comparisonSection").hidden = true;
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
