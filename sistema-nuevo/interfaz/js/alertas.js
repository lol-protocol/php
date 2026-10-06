import { el } from "./nucleo.js";
import { t } from "./idioma.js";

// Los tipos de alerta del panel, en el orden en que se dibujan. Sus claves son las de /api/alerts y las de
// /api/alerts-config; cada uno con su título y con el país que se muestra junto al nombre (el que declaró el usuario, o
// el país de donde venía antes del cambio). Todo lo demás de una alerta es igual para los dos tipos.
const TIPOS_DE_ALERTA = [
  { clave: "ip_pais", titulo: "alerts_title_ip", pais: (fila) => fila.country },
  { clave: "cambio_pais", titulo: "alerts_title_cambios", pais: (fila) => fila.previous_country },
];

/** @param {(userId: string) => void} onSelectUser */
export function renderAlerts(data, onSelectUser) {
  const panel = document.getElementById("alerts-panel");
  const totalEventos = TIPOS_DE_ALERTA.reduce((suma, tipo) => suma + (data[tipo.clave]?.total_events ?? 0), 0);

  if (totalEventos === 0) {
    panel.hidden = true;
    return;
  }
  panel.hidden = false;

  const list = document.getElementById("alerts-list");
  list.innerHTML = "";

  for (const tipo of TIPOS_DE_ALERTA) {
    if (data[tipo.clave]?.top.length > 0) {
      renderAlertType(list, data[tipo.clave], tipo, onSelectUser);
    }
  }
}

function renderAlertType(list, alertData, tipo, onSelectUser) {
  const section = el("li", { class: "alerts-section" });
  section.appendChild(el("h4", { class: "alerts-type-title", text: t(tipo.titulo) }));

  const subList = el("ul", { class: "alerts-sublist" });
  alertData.top.forEach((row) => {
    const button = el("button", {
      type: "button",
      class: "alerts-button",
      text: `${row.user_name} (${tipo.pais(row)}) · ${row.event_count}`,
      title: t("alerts_last_seen", { date: row.last_seen }),
    });
    button.addEventListener("click", () => onSelectUser(row.user_id));
    subList.appendChild(el("li", { class: "alerts-item" }, [button]));
  });
  section.appendChild(subList);

  list.appendChild(section);
}
