import { el } from "./nucleo.js";
import { t } from "./idioma.js";

/** @param {(userId: string) => void} onSelectUser */
export function renderAlerts(data, onSelectUser) {
  const panel = document.getElementById("alerts-panel");
  let totalAlertas = 0;

  Object.values(data).forEach((alertType) => {
    if (alertType.total_mismatches) totalAlertas += alertType.total_mismatches;
    if (alertType.total_changes) totalAlertas += alertType.total_changes;
  });

  if (totalAlertas === 0) {
    panel.hidden = true;
    return;
  }
  panel.hidden = false;

  const list = document.getElementById("alerts-list");
  list.innerHTML = "";

  if (data.ip_pais_mismatch?.top) {
    renderAlertType(list, data.ip_pais_mismatch, "alerts_title_ip", onSelectUser);
  }

  if (data.cambios_pais_imposibles?.top) {
    renderAlertType(list, data.cambios_pais_imposibles, "alerts_title_cambios", onSelectUser);
  }
}

function renderAlertType(list, alertData, titleKey, onSelectUser) {
  const section = el("li", { class: "alerts-section" });
  section.appendChild(el("h4", { class: "alerts-type-title", "data-i18n": titleKey }));

  const subList = el("ul", { class: "alerts-sublist" });
  alertData.top.forEach((row) => {
    const button = el("button", {
      type: "button",
      class: "alerts-button",
      text: `${row.user_name} (${row.country || row.pais_anterior}) · ${row.mismatch_count || row.cambio_count}`,
      title: t("alerts_last_seen", { date: row.last_seen }),
    });
    button.addEventListener("click", () => onSelectUser(row.user_id));
    subList.appendChild(el("li", { class: "alerts-item" }, [button]));
  });
  section.appendChild(subList);

  list.appendChild(section);
}
