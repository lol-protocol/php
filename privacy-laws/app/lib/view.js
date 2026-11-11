// Piezas de la vista que privacy-laws/ y copyright-laws/ comparten, tal cual (leyes-comunes/comprobar-copias.js
// vigila que las dos copias sigan iguales). Lo que difiere entre los dos módulos (columnas, filtros, tarjetas,
// textos de las cifras) se queda en su archivo principal y en lib/domain.js.
import { formatDate, getLanguage, plural, t } from "../translations/i18n.js";
import { loadTranslations } from "./data.js";
import { CONFIG, DETAIL_FIELDS, FIELD_LABEL_KEYS, isReviewDue, SEARCH_SCOPES } from "./domain.js";
import { emptyState, stateToParams } from "./filters.js";
import { escapeHtml, safeUrl } from "./util.js";

export const $ = (id) => document.getElementById(id);

export const esc = escapeHtml;

export const app = {
  onlyDiff: false,
  data: null,
  translations: {},
  state: emptyState(CONFIG),
  results: [],
  chips: [],
  options: {},
  selected: new Set(),
  chartAsTable: false,
  urlHasLang: new URLSearchParams(location.search).has("lang"),
};

export const regionLabel = (region) => t(`region.${region}`);

// "Not specified" (or an empty cell) is shown muted and in the interface language, so real data stands out.
export const NOT_SPECIFIED = /^(not specified|—|-)?$/i;

export const valueHtml = (value) => {
  const text = String(value ?? "").trim();
  if (NOT_SPECIFIED.test(text)) return `<span class="muted">${esc(t("value.notSpecified"))}</span>`;
  // Descriptive values have a machine translation in the interface language: show it, marked, with the original on hover.
  const translated = app.translations[text];
  if (!translated) return esc(value);
  const note = t("value.machineTitle", { original: text });
  return `${esc(translated)} <span class="mt" role="img" aria-label="${esc(note)}" title="${esc(note)}">${esc(t("value.machineLabel"))}</span>`;
};
/** Loads the translations of the current language (none for English). */

export const syncTranslations = async () => {
  app.translations = await loadTranslations(getLanguage());
};

export const dateHtml = (iso) => `<time datetime="${esc(iso)}">${esc(formatDate(iso))}</time>`;
/** "www.example.org" → "example.org": shows where a link leads (official site, WIPO Lex, a law firm…). */

export const hostOf = (url) => {
  try {
    return new URL(url).hostname.replace(/^www\./, "");
  } catch {
    return "";
  }
};

// The accessible name starts with the visible text (the host), as WCAG 2.5.3 "Label in Name" asks:
// axe-core's label-content-name-mismatch rule fails a link whose name only ends with it.
export function sourceLink(url, lawName) {
  return url
    ? `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" class="source-link" title="${esc(t("table.open"))}" aria-label="${esc(
        `${hostOf(url)}: ${t("table.open")}: ${lawName}`
      )}">${hostOf(url).split(".").map(esc).join(".<wbr>")}<span aria-hidden="true"> ↗</span></a>`
    : valueHtml("");
}

export const sourceLabel = (status) => t(`source.${status}`);

export const flagLabel = (flag) => t(`flag.${flag}`);
/** "Web research (secondary sources) · medium confidence · checked 8 Oct 2026" */

export function sourceDetail(row) {
  const parts = [sourceLabel(row.data_status)];
  if (row.confidence) parts.push(t(`confidence.${row.confidence}`));
  if (row.last_checked) parts.push(t("card.checked", { date: formatDate(row.last_checked) }));
  if (isReviewDue(row.last_checked, TODAY)) parts.push(t("card.reviewDue"));
  return parts.join(" · ");
}

export function completenessText(row) {
  return t("card.completenessValue", {
    filled: row.fieldsFilled,
    total: row.fieldsTotal,
    percent: Math.round((100 * row.fieldsFilled) / row.fieldsTotal),
    sourced: row.fieldsSourced,
  });
}

export function fieldLabel(field) {
  const key = FIELD_LABEL_KEYS[field] ?? `compare.${field}`;
  const text = t(key);
  return text === key ? field.replace(/_/g, " ") : text;
}

export const shorten = (value, max = 140) => {
  const text = String(value ?? "").trim();
  return text.length > max ? `${text.slice(0, max - 1)}…` : text;
};

/** A small link after a value: where that one value comes from. */

export function fieldSourceHtml(source) {
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

export function pendingHtml(row) {
  if (!row.pending_change) return "";
  const date = row.pending_date ? formatDate(row.pending_date) : "";
  const when = !date ? "" : row.pending_date <= TODAY ? t("card.pendingOverdue", { date }) : t("card.pendingFrom", { date });
  return `<p class="card-pending${row.pending_date && row.pending_date <= TODAY ? " is-overdue" : ""}"><strong>${esc(t("card.pending"))}</strong> · ${esc(
    row.law_name
  )}: ${esc(row.pending_change)}${when ? ` <span class="nowrap">(${esc(when)})</span>` : ""}</p>`;
}

/** One recorded change as a list item; `who` prefixes it with the law it belongs to (the recent-changes list). */

export function changeItem(change, who = "") {
  if (change.field === "row") return `<li>${dateHtml(change.date)} · ${who}${esc(t("card.historyAdded"))}</li>`;
  const isDate = /_date$/.test(change.field);
  const was = change.from && !NOT_SPECIFIED.test(change.from.trim())
    ? ` <span class="muted">(${esc(t("card.historyWas", { value: isDate ? formatDate(change.from) : shorten(change.from, 100) }))})</span>`
    : "";
  const now = isDate && change.to ? dateHtml(change.to) : valueHtml(shorten(change.to));
  return `<li>${dateHtml(change.date)} · ${who}<strong>${esc(fieldLabel(change.field))}</strong>: ${now}${was}</li>`;
}

export const HISTORY_SHOWN = 8;

export function historyHtml(row) {
  if (!row.changes.length) return "";
  const more = row.changes.length - HISTORY_SHOWN;
  return `
    <h3 class="history-title">${esc(t("card.history"))}</h3>
    <ul class="history">${row.changes.slice(0, HISTORY_SHOWN).map((change) => changeItem(change)).join("")}${
      more > 0 ? `<li class="muted">${esc(plural("card.historyMore", more))}</li>` : ""
    }</ul>`;
}

export const NEWS_SHOWN = 15;
/** The latest recorded changes across the laws currently listed, newest first. */

export function renderNews() {
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

export function lawDetailsHtml(row, open) {
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

export function renderScopeSelect() {
  $("scope").innerHTML = SEARCH_SCOPES.map(
    (scope) => `<option value="${esc(scope.id)}">${esc(t(`scope.${scope.id}`))}</option>`
  ).join("");
  $("scope").value = app.state.scope;
}

export function checkboxFacet(id, legendKey, options, labelFor, { hintKey, titleFor } = {}) {
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

export function syncSelectAll() {
  const picked = app.results.filter((row) => app.selected.has(row.id)).length;
  const box = $("selectAll");
  box.checked = app.results.length > 0 && picked === app.results.length;
  box.indeterminate = picked > 0 && picked < app.results.length;
}

export function renderCompareButton() {
  const count = app.selected.size;
  $("compareBtn").textContent = count ? t("btn.compareCount", { count }) : t("btn.compare");
}

export function pickedRows() {
  return app.results.filter((row) => app.selected.has(row.id));
}

export function writeUrl() {
  const params = stateToParams(app.state, CONFIG);
  if (app.urlHasLang) params.set("lang", getLanguage());
  const query = params.toString();
  history.replaceState(null, "", query ? `?${query}` : location.pathname);
}

/** One cell per law: where each value comes from, and what changed when. */

export function exportExtras(row) {
  const sources = Object.entries(row.sources)
    .map(([field, source]) => `${field}: ${source.url}${source.checked ? ` (${source.checked})` : ""}`)
    .join(" | ");
  const history = row.changes
    .map((change) => (change.field === "row" ? `${change.date} ${change.to}` : `${change.date} ${change.field}: ${change.from || "(empty)"} -> ${change.to}`))
    .join(" | ");
  return { field_sources: sources, change_history: history };
}

export const scrollBehavior = () =>
  matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth";

// Laws can be enacted with an effective date still ahead (ISO dates compare as strings).
export const TODAY = new Date().toISOString().slice(0, 10);
