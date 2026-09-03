import { el, ACTION_ICONS } from "./nucleo.js";
import { formatDuration, formatMoney, formatFileSize, formatPct } from "./formato.js";

function buildDeltaBadge(deltaPct, { betterWhenLower = true, goodLabel, badLabel }) {
  if (deltaPct === null) {
    return el("span", { class: "badge badge--neutral", text: "Sin datos de comparación" });
  }
  if (Math.abs(deltaPct) <= 10) {
    return el("span", { class: "badge badge--neutral", text: `≈ promedio (${formatPct(deltaPct)})` });
  }
  const isGood = betterWhenLower ? deltaPct < 0 : deltaPct > 0;
  return el("span", {
    class: `badge ${isGood ? "badge--good" : "badge--bad"}`,
    text: `${formatPct(deltaPct)} ${isGood ? goodLabel : badLabel}`,
  });
}

// Cada tipo de dato mostrado tiene su propia clase de color (ver css/tarjetas.css).
function buildMetricNodes(item) {
  const nodes = [el("span", { class: "metric metric--duracion", text: `⏱ ${formatDuration(item.duration_ms)}` })];

  if (item.amount_usd !== null) {
    const texto =
      item.currency && item.currency !== "USD"
        ? `💰 ${formatMoney(item.amount_local, item.currency)} (≈ ${formatMoney(item.amount_usd, "USD")})`
        : `💰 ${formatMoney(item.amount_usd, "USD")}`;
    nodes.push(el("span", { class: "metric metric--dinero", text: texto }));
  }

  if (item.endpoint) {
    nodes.push(el("span", { class: "metric metric--endpoint", text: `⚙ ${item.endpoint} → ${item.http_status ?? "?"}` }));
  }

  if (item.file_size_kb !== null) {
    nodes.push(el("span", { class: "metric metric--archivo", text: `📎 ${formatFileSize(item.file_size_kb)}` }));
  }

  if (item.cohort) {
    nodes.push(el("span", { class: "metric", text: `(vs. ${item.cohort.count} acciones del universo elegido)` }));
  }

  return nodes;
}

export function renderTimeline(items) {
  const list = document.getElementById("timeline");
  list.innerHTML = "";

  if (items.length === 0) {
    list.appendChild(el("li", { class: "empty-state", text: "Este usuario no tiene acciones registradas." }));
    return;
  }

  items.forEach((item) => {
    const marker = el("div", { class: "marker" }, [
      el("span", { class: "marker-icon", text: ACTION_ICONS[item.type] || "•" }),
      el("span", { class: "marker-line" }),
    ]);

    const header = el("div", { class: "card-header" }, [
      el("span", { class: "card-title", text: item.label }),
      el("span", { class: "card-time", text: item.timestamp }),
    ]);

    const metrics = el("div", { class: "card-metrics" }, buildMetricNodes(item));

    const badges = el("div", { class: "badges" }, [
      buildDeltaBadge(item.duration_delta_pct, { betterWhenLower: true, goodLabel: "más rápido", badLabel: "más lento" }),
      ...(item.amount_usd !== null
        ? [buildDeltaBadge(item.amount_delta_pct, { betterWhenLower: true, goodLabel: "más barato", badLabel: "más caro" })]
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
