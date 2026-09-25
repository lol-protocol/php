// Gráfico de evolución temporal, SVG armado a mano (sin librerías): barras de
// acciones/día + línea de gasto acumulado en USD, cada una con su propia escala.
import { t } from "./idioma.js";

const formatShortDate = (iso) => iso.slice(5); // "2026-08-15" -> "08-15"

export function renderChart(dias) {
  const container = document.getElementById("chart-container");

  if (!dias || dias.length === 0) {
    container.hidden = true;
    return;
  }
  container.hidden = false;

  const W = 640, H = 220;
  const left = 34, right = 54, top = 16, bottom = 28;
  const innerW = W - left - right;
  const innerH = H - top - bottom;
  const n = dias.length;
  const slot = innerW / n;

  const maxCount = Math.max(1, ...dias.map((d) => d.count));
  const maxSpend = Math.max(1, ...dias.map((d) => d.cumulative_spend_usd));
  const xAt = (i) => left + (i + 0.5) * slot;
  const barTopY = (count) => top + innerH - (count / maxCount) * innerH;
  const lineY = (spend) => top + innerH - (spend / maxSpend) * innerH;
  const everyNth = Math.max(1, Math.ceil(n / 8));

  const bars = dias.map((d, i) => {
    const barW = slot * 0.55;
    const y = barTopY(d.count);
    return `<rect x="${(xAt(i) - barW / 2).toFixed(1)}" y="${y.toFixed(1)}" width="${barW.toFixed(1)}"
      height="${(top + innerH - y).toFixed(1)}" fill="var(--accent)" opacity="0.55" rx="2"></rect>`;
  }).join("");

  const linePoints = dias.map((d, i) => `${xAt(i).toFixed(1)},${lineY(d.cumulative_spend_usd).toFixed(1)}`).join(" ");
  const dots = dias.map((d, i) =>
    `<circle cx="${xAt(i).toFixed(1)}" cy="${lineY(d.cumulative_spend_usd).toFixed(1)}" r="3" fill="var(--dato-dinero)"></circle>`
  ).join("");
  const labels = dias.map((d, i) => (i % everyNth !== 0 ? "" :
    `<text x="${xAt(i).toFixed(1)}" y="${H - 8}" font-size="9" fill="var(--text-muted)" text-anchor="middle">${formatShortDate(d.date)}</text>`
  )).join("");

  container.innerHTML = `
    <h3>${t("chart_title")}</h3>
    <svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${t("chart_legend_count")} / ${t("chart_legend_spend")}">
      <line x1="${left}" y1="${top}" x2="${left}" y2="${top + innerH}" stroke="var(--border)"></line>
      <line x1="${left}" y1="${top + innerH}" x2="${left + innerW}" y2="${top + innerH}" stroke="var(--border)"></line>
      ${bars}
      <polyline points="${linePoints}" fill="none" stroke="var(--dato-dinero)" stroke-width="2"></polyline>
      ${dots}
      ${labels}
      <text x="2" y="${top + 4}" font-size="9" fill="var(--accent)">${maxCount}</text>
      <text x="2" y="${top + innerH}" font-size="9" fill="var(--accent)">0</text>
      <text x="${W - right + 6}" y="${top + 4}" font-size="9" fill="var(--dato-dinero)">$${maxSpend.toFixed(0)}</text>
      <text x="${W - right + 6}" y="${top + innerH}" font-size="9" fill="var(--dato-dinero)">$0</text>
    </svg>
    <div class="legend-swatches">
      <span class="legend-swatch" style="--dot-color: var(--accent)">${t("chart_legend_count")}</span>
      <span class="legend-swatch" style="--dot-color: var(--dato-dinero)">${t("chart_legend_spend")}</span>
    </div>`;
}
