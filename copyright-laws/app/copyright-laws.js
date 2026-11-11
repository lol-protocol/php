import { bindLanguageSelect, formatDate, initLanguage, onLanguageChange, plural, t } from "./translations/i18n.js";
import { jurisdictionFileUrl, loadBundle } from "./lib/data.js";
import { buildChartBars, COMPARE_CRITERIA, CONFIG, enrichRow, FACETS, FIELD_LABEL_KEYS, TREATY_ORDER } from "./lib/domain.js";
import { activeFilters, buildIndex, clearFilters, emptyState, facetOptions, filterRows, pruneState, removeFilter, stateFromParams } from "./lib/filters.js";
import { renderBarChart } from "./lib/chart.js";
import { debounce, safeUrl, toCsv } from "./lib/util.js";
import { $, app, checkboxFacet, completenessText, dateHtml, esc, exportExtras, fieldSourceHtml, flagLabel, lawDetailsHtml, NOT_SPECIFIED, pendingHtml, pickedRows, regionLabel, renderCompareButton, renderNews, renderScopeSelect, scrollBehavior, sourceDetail, sourceLabel, sourceLink, syncSelectAll, syncTranslations, valueHtml, writeUrl } from "./lib/view.js";

const treatyName = (treaty) => t(`treaty.${treaty}`);

// Comparison rows whose values are lists of treaties/frameworks, shown as badges with full names.
const LIST_CRITERIA = { treaties_signatory: treatyName, treaties_not_party: treatyName };

const termLabel = (bucket) =>
  bucket === "other" ? t("term.unspecified") : t("term.life", { years: bucket.replace("life-", "") });

const jurisdictionName = (tld) =>
  app.data.jurisdictions.find((j) => j.tld === tld)?.name ?? tld.toUpperCase();

/* ---------- Static parts (rebuilt when the language changes) ---------- */

function renderJurisdictionList() {
  $("countryList").innerHTML = app.data.jurisdictions
    .map((jurisdiction) => `<option value="${esc(jurisdiction.name)}"></option>`)
    .join("");
}

function renderFacets() {
  const parts = [
    checkboxFacet("region", "filters.region", app.options.region, regionLabel),
    checkboxFacet("treaty", "filters.treaty", app.options.treaty, (v) => v, {
      hintKey: "filters.treatyHint",
      titleFor: treatyName,
    }),
    checkboxFacet("term", "filters.term", app.options.term, termLabel),
    checkboxFacet("source", "filters.source", app.options.source, sourceLabel, { hintKey: "filters.sourceHint" }),
  ];
  if (app.options.flag.length) parts.push(checkboxFacet("flag", "filters.flag", app.options.flag, flagLabel, { hintKey: "filters.flagHint" }));
  // A filter with a single possible value cannot narrow anything, so it stays hidden
  // until the dataset has a second protection type (related rights, database rights…).
  if (app.options.ptype.length > 1) {
    parts.push(checkboxFacet("ptype", "filters.ptype", app.options.ptype, (v) => v));
  }
  $("facets").innerHTML = parts.join("");
  syncFacetControls();
}

function renderFooter() {
  $("footerData").textContent = t("footer.data", {
    date: formatDate(app.data.meta.generatedAt),
    version: app.data.meta.version,
    laws: app.data.rows.length,
    countries: plural("unit.jurisdiction", app.data.jurisdictions.length),
  });
}

function renderStatic() {
  renderScopeSelect();
  renderFacets();
  renderFooter();
}

/* ---------- Dynamic parts (re-rendered on every state change) ---------- */

function syncFacetControls() {
  for (const facet of FACETS) {
    const selected = app.state.facets[facet.id];
    for (const input of $("facets").querySelectorAll(`input[name="${facet.id}"]`)) {
      input.checked = selected.includes(input.value);
    }
  }
  $("scope").value = app.state.scope;
}

function chipLabel(chip) {
  switch (chip.facet) {
    case "country":
      return t("chip.country", { value: jurisdictionName(chip.value) });
    case "region":
      return regionLabel(chip.value);
    case "treaty":
      return t("chip.treaty", { value: chip.value });
    case "term":
      return t("chip.term", { value: termLabel(chip.value) });
    case "source":
      return t("chip.source", { value: sourceLabel(chip.value) });
    case "flag":
      return t("chip.flag", { value: flagLabel(chip.value) });
    default:
      return t("chip.ptype", { value: chip.value });
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
  const jurisdictions = new Set(app.results.map((row) => row.tld)).size;
  $("resultsSummary").textContent = t("results.summary", {
    shown: app.results.length,
    total: app.data.rows.length,
    countries: plural("unit.jurisdiction", jurisdictions),
  });
}

function renderChart() {
  const host = $("chart");
  if (!app.results.length) {
    host.replaceChildren();
    return;
  }
  const bars = buildChartBars(app.results, app.data.rows).map((bar) => ({
    ...bar,
    label: bar.bucket === "other" ? t("term.unspecified") : `+${bar.years}`,
    name: termLabel(bar.bucket),
  }));
  renderBarChart(host, {
    title: t("chart.title"),
    subtitle: t("chart.subtitle", { laws: plural("unit.law", app.results.length) }),
    bars,
    formatValue: (n) => plural("unit.law", n),
    labels: {
      showTable: t("chart.showTable"),
      showChart: t("chart.showChart"),
      category: t("chart.colTerm"),
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
  const url = safeUrl(row.linked_resources);
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
      <td><small>${esc(row.term_of_protection)}</small></td>
      <td class="col-optional"><small>${valueHtml(row.author_rights)}</small></td>
      <td class="col-optional"><small>${valueHtml(row.moral_rights)}</small></td>
      <td><small>${valueHtml(row.treaties.join(", "))}</small></td>
      <td class="col-source">${sourceLink(url, row.law_name)}</td>
    </tr>`;
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
  const jurisdiction = app.data.jurisdictions.find((j) => j.tld === tlds[0]);
  const treaties = [
    ...new Set(app.data.rows.filter((row) => row.tld === jurisdiction.tld).flatMap((row) => row.treaties)),
  ].sort((a, b) => TREATY_ORDER.indexOf(a) - TREATY_ORDER.indexOf(b));
  const laws = app.data.rows.filter((row) => row.tld === jurisdiction.tld);
  const notParty = TREATY_ORDER.filter((t) => !treaties.includes(t) && laws.some((row) => row.treatiesNot.includes(t)));
  const unconfirmed = TREATY_ORDER.filter((t) => !treaties.includes(t) && !notParty.includes(t));
  const badgeList = (list, extraClass = "") =>
    list.length
      ? list.map((treaty) => `<span class="badge${extraClass}" title="${esc(treatyName(treaty))}">${esc(treaty)}</span>`).join("")
      : "—";
  const badges = badgeList(treaties);
  const notes = [...new Set(laws.map((row) => row.notes).filter(Boolean))];

  host.hidden = false;
  host.innerHTML = `
    <h2>${esc(jurisdiction.name)} <span class="badge">${esc(jurisdiction.code)}</span></h2>
    <dl class="card-grid">
      <div><dt>${esc(t("card.region"))}</dt><dd>${esc(regionLabel(jurisdiction.region))}</dd></div>
      <div><dt>${esc(t("card.laws"))}</dt><dd>${jurisdiction.lawCount}</dd></div>
      <div><dt>${esc(t("card.treaties"))}</dt><dd class="badge-list">${badges}</dd></div>
      <div><dt>${esc(t("card.notParty"))}</dt><dd class="badge-list">${badgeList(notParty, " badge-outline")}</dd></div>
      <div><dt>${esc(t("card.unconfirmed"))}</dt><dd>${unconfirmed.length ? esc(unconfirmed.join(", ")) : "—"}</dd></div>
      <div><dt>${esc(t("card.folder"))}</dt><dd><code>jurisdictions/${esc(jurisdiction.tld)}/</code></dd></div>
      <div><dt>${esc(t("card.source"))}</dt><dd>${laws.map((row) => esc(sourceDetail(row))).join("<br>")}</dd></div>
      <div><dt>${esc(t("card.completeness"))}</dt><dd>${laws.map((row) => esc(completenessText(row))).join("<br>")}</dd></div>
      <div><dt>${esc(t("card.updated"))}</dt><dd>${esc(formatDate(app.data.meta.generatedAt))}</dd></div>
    </dl>
    ${notes.length ? `<p class="card-notes"><strong>${esc(t("card.notes"))}:</strong> ${esc(notes.join(" · "))}</p>` : ""}
    ${laws.map(pendingHtml).join("")}
    ${laws.map((row) => lawDetailsHtml(row, laws.length === 1)).join("")}
    <p class="card-links">
      <a href="${esc(jurisdictionFileUrl(jurisdiction.tld, "laws.json"))}" download>${esc(t("card.json"))}</a>
      <a href="${esc(jurisdictionFileUrl(jurisdiction.tld, "laws.csv"))}" download>${esc(t("card.csv"))}</a>
    </p>`;
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
  link.download = `copyright-laws-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
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
    const { name } = event.target;
    if (!app.state.facets[name]) return;
    app.state.facets[name] = [...$("facets").querySelectorAll(`input[name="${name}"]:checked`)].map(
      (input) => input.value
    );
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
  renderJurisdictionList();
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
