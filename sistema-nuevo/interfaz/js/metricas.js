import { el } from "./nucleo.js";
import { t } from "./idioma.js";
import { formatDuration, formatMoney, formatFileSize } from "./formato.js";

// Cada tipo de dato mostrado tiene su propia clase de color (ver css/tarjetas.css).
// La ruta/archivo y la llamada a la API comparten color: ambas son "dónde pasó esto".
export function buildMetricNodes(item) {
  const nodes = [
    el("span", { class: "metric metric--duracion", text: `⏱ ${formatDuration(item.duration_ms)}` }),
    el("span", { class: "metric metric--ruta", text: `📁 ${item.path}` }),
  ];

  if (item.amount_usd !== null) {
    const texto =
      item.currency && item.currency !== "USD"
        ? `💰 ${formatMoney(item.amount_local, item.currency)} (≈ ${formatMoney(item.amount_usd, "USD")})`
        : `💰 ${formatMoney(item.amount_usd, "USD")}`;
    nodes.push(el("span", { class: "metric metric--dinero", text: texto }));
  }

  if (item.endpoint) {
    nodes.push(el("span", { class: "metric metric--ruta", text: `⚙ ${item.endpoint} → ${item.http_status ?? "?"}` }));
  }

  if (item.file_size_kb !== null) {
    nodes.push(el("span", { class: "metric metric--archivo", text: `📎 ${formatFileSize(item.file_size_kb)}` }));
  }

  if (item.ip) {
    const detalle = [item.ip_country, item.ip_local_time && `${item.ip_local_time} ${t("metric_local_time_suffix")}`, item.ip_isp]
      .filter(Boolean)
      .join(" · ");
    const alerta = item.ip_mismatch ? ` ${t("metric_ip_mismatch")}` : "";
    nodes.push(el("span", {
      class: `metric ${item.ip_mismatch ? "metric--ip-alerta" : "metric--ip"}`,
      text: `🌐 ${item.ip} · ${detalle}${alerta}`,
    }));
  }

  if (item.cohort) {
    nodes.push(el("span", { class: "metric", text: t("metric_cohort_count", { count: item.cohort.count }) }));
  }

  return nodes;
}
