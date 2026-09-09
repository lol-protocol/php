import { el, ACTION_ICONS } from "./nucleo.js";
import { t } from "./idioma.js";
import { formatPct, formatDuration, formatMoney } from "./formato.js";
import { buildMetricNodes } from "./metricas.js";
import { guardarNotaConDebounce } from "./notas.js";

function buildDeltaBadge(deltaPct, { betterWhenLower = true, goodLabel, badLabel, tooltip = "" }) {
  const props = tooltip ? { title: tooltip } : {};
  if (deltaPct === null) {
    return el("span", { class: "badge badge--neutral", text: t("badge_no_comparison"), ...props });
  }
  if (Math.abs(deltaPct) <= 10) {
    return el("span", { class: "badge badge--neutral", text: t("badge_avg", { pct: formatPct(deltaPct) }), ...props });
  }
  const isGood = betterWhenLower ? deltaPct < 0 : deltaPct > 0;
  return el("span", {
    class: `badge ${isGood ? "badge--good" : "badge--bad"}`,
    text: `${formatPct(deltaPct)} ${isGood ? goodLabel : badLabel}`,
    ...props,
  });
}

function buildStatsTooltip(median, p90, formatter) {
  if (median === null && p90 === null) return "";
  const parts = [];
  if (median !== null) parts.push(t("stats_median", { value: formatter(median) }));
  if (p90 !== null) parts.push(t("stats_p90", { value: formatter(p90) }));
  return parts.join(" · ");
}

function buildNoteBlock(item) {
  const textarea = el("textarea", {
    class: "note-textarea",
    rows: "2",
    placeholder: t("note_placeholder"),
  });
  textarea.value = item.note || "";
  textarea.addEventListener("input", () => guardarNotaConDebounce(item.id, textarea.value));
  return el("div", { class: "note-block" }, [
    el("span", { class: "note-icon", text: "📝" }),
    textarea,
  ]);
}

export function renderTimeline(items) {
  const list = document.getElementById("timeline");
  list.innerHTML = "";

  if (items.length === 0) {
    list.appendChild(el("li", { class: "empty-state", text: t("timeline_empty") }));
    return;
  }

  items.forEach((item) => {
    const marker = el("div", { class: "marker" }, [
      el("span", { class: "marker-icon", text: ACTION_ICONS[item.type] || "•" }),
      el("span", { class: "marker-line" }),
    ]);

    const header = el("div", { class: "card-header" }, [
      el("span", { class: "card-title", text: t(`action_${item.type}`) }),
      el("span", { class: "card-time", text: item.timestamp }),
    ]);

    const metrics = el("div", { class: "card-metrics" }, buildMetricNodes(item));

    const cohort = item.cohort || {};
    const badges = el("div", { class: "badges" }, [
      buildDeltaBadge(item.duration_delta_pct, {
        betterWhenLower: true, goodLabel: t("badge_faster"), badLabel: t("badge_slower"),
        tooltip: buildStatsTooltip(cohort.median_duration_ms, cohort.p90_duration_ms, formatDuration),
      }),
      ...(item.amount_usd !== null
        ? [buildDeltaBadge(item.amount_delta_pct, {
            betterWhenLower: true, goodLabel: t("badge_cheaper"), badLabel: t("badge_pricier"),
            tooltip: buildStatsTooltip(cohort.median_amount_usd, cohort.p90_amount_usd, (v) => formatMoney(v, "USD")),
          })]
        : []),
    ]);

    const cardChildren = [header, metrics, badges];
    if (item.comment) {
      cardChildren.push(el("div", { class: "comment-block", text: `💬 "${item.comment}"` }));
    }
    cardChildren.push(buildNoteBlock(item));

    const card = el("div", { class: "card" }, cardChildren);
    list.appendChild(el("li", { class: "timeline-item" }, [marker, card]));
  });
}
