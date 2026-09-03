import { el } from "./nucleo.js";
import { t } from "./idioma.js";

/**
 * Panel proactivo: se llena solo, sin que haya que elegir un usuario primero.
 * @param {(userId: string) => void} onSelectUser
 */
export function renderAlerts(data, onSelectUser) {
  const panel = document.getElementById("alerts-panel");

  if (data.total_mismatches === 0) {
    panel.hidden = true;
    return;
  }
  panel.hidden = false;

  document.getElementById("alerts-summary").textContent =
    t("alerts_summary", { total: data.total_mismatches, users: data.total_users_affected });

  const list = document.getElementById("alerts-list");
  list.innerHTML = "";
  data.top.forEach((row) => {
    const button = el("button", {
      type: "button",
      class: "alerts-button",
      text: `${row.user_name} (${row.country}) · ${row.mismatch_count}`,
      title: t("alerts_last_seen", { date: row.last_seen }),
    });
    button.addEventListener("click", () => onSelectUser(row.user_id));
    list.appendChild(el("li", { class: "alerts-item" }, [button]));
  });
}
