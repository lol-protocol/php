import { el, ACTION_ICONS } from "./nucleo.js";
import { t } from "./idioma.js";
import { formatPct } from "./formato.js";
import { buildMetricNodes } from "./metricas.js";

function buildDeltaBadge(deltaPct, { betterWhenLower = true, goodLabel, badLabel }) {
  if (deltaPct === null) {
    return el("span", { class: "badge badge--neutral", text: t("badge_no_comparison") });
  }
  if (Math.abs(deltaPct) <= 10) {
    return el("span", { class: "badge badge--neutral", text: t("badge_avg", { pct: formatPct(deltaPct) }) });
  }
  const isGood = betterWhenLower ? deltaPct < 0 : deltaPct > 0;
  return el("span", {
    class: `badge ${isGood ? "badge--good" : "badge--bad"}`,
    text: `${formatPct(deltaPct)} ${isGood ? goodLabel : badLabel}`,
  });
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

    const badges = el("div", { class: "badges" }, [
      buildDeltaBadge(item.duration_delta_pct, { betterWhenLower: true, goodLabel: t("badge_faster"), badLabel: t("badge_slower") }),
      ...(item.amount_usd !== null
        ? [buildDeltaBadge(item.amount_delta_pct, { betterWhenLower: true, goodLabel: t("badge_cheaper"), badLabel: t("badge_pricier") })]
        : []),
    ]);

    const cardChildren = [header, metrics, badges];
    if (item.comment) {
      cardChildren.push(el("div", { class: "comment-block", text: `💬 "${item.comment}"` }));
    }

    const card = el("div", { class: "card" }, cardChildren);
    list.appendChild(el("li", { class: "timeline-item" }, [marker, card]));
  });
}
