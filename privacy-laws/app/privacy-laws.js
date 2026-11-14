import { bindLanguageSelect, formatDate, initLanguage, onLanguageChange, plural, t } from "./translations/i18n.js";
import { countryFileUrl, loadBundle } from "./lib/data.js";
import { buildChartBars, CONFIG, enrichRow, FACETS, FRAMEWORK_ORDER } from "./lib/domain.js";
import { activeFilters, buildIndex, clearFilters, emptyState, facetOptions, filterRows, pruneState, removeFilter, stateFromParams } from "./lib/filters.js";
import { renderBarChart } from "./lib/chart.js";
import { debounce, safeUrl, toCsv } from "./lib/util.js";
import { $, app, checkboxFacet, completenessText, dateHtml, esc, exportExtras, flagLabel, handleCompare, hooks, lawDetailsHtml, pendingHtml, regionLabel, renderChips, renderCompareButton, renderComparison, renderNews, renderScopeSelect, renderTable, scrollBehavior, sourceDetail, sourceLabel, sourceLink, syncSelectAll, syncTranslations, TODAY, valueHtml, writeUrl } from "./lib/view.js";

const frameworkName = (framework) => t(`framework.${framework}`);

// Comparison rows whose values are lists of treaties/frameworks, shown as badges with full names.
const LIST_CRITERIA = { frameworks: frameworkName, frameworks_not: frameworkName };
Object.assign(hooks, { chipLabel, rowHtml, listCriteria: LIST_CRITERIA });

const countryName = (tld) => app.data.countries.find((c) => c.tld === tld)?.name ?? tld.toUpperCase();

function formatRange({ from, to }) {
  if (from != null && to != null) return `${from}–${to}`;
  return from != null ? `≥ ${from}` : `≤ ${to}`;
}

/* ---------- Static parts (rebuilt when the language changes) ---------- */

function renderCountryList() {
  $("countryList").innerHTML = app.data.countries
    .map((country) => `<option value="${esc(country.name)}"></option>`)
    .join("");
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

  await syncTranslations();
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

  onLanguageChange(async () => {
    await syncTranslations();
    renderStatic();
    update();
  });
}

init();
