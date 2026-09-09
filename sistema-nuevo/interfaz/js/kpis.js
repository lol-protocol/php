import { el } from "./nucleo.js";
import { t } from "./idioma.js";
import { formatMoney } from "./formato.js";

function buildTile(icon, value, labelKey) {
  return el("div", { class: "kpi-tile" }, [
    el("span", { class: "kpi-icon", text: icon }),
    el("span", { class: "kpi-value", text: String(value) }),
    el("span", { class: "kpi-label", text: t(labelKey) }),
  ]);
}

export function renderKpis(data) {
  const container = document.getElementById("kpis-dashboard");
  container.innerHTML = "";

  container.appendChild(buildTile("👥", data.total_users, "kpi_total_users"));
  container.appendChild(buildTile("📊", data.total_actions, "kpi_total_actions"));
  container.appendChild(buildTile("💰", formatMoney(data.total_spend_usd, "USD"), "kpi_total_spend"));

  if (data.top_action_types[0]) {
    container.appendChild(buildTile("🏆", t(`action_${data.top_action_types[0].type}`), "kpi_top_action"));
  }
}
