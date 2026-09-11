import { el } from "./nucleo.js";
import { t, etiquetaGenero } from "./idioma.js";

export function renderUserCard(user) {
  const card = document.getElementById("user-card");
  card.innerHTML = "";
  card.appendChild(el("h2", { text: user.name }));
  card.appendChild(el("p", { class: "user-meta", text: t("usercard_id_prefix", { id: user.id }) }));

  const dl = el("dl");
  const addRow = (label, value) => {
    dl.appendChild(el("dt", { text: label }));
    dl.appendChild(el("dd", { text: value }));
  };
  addRow(t("label_country"), user.country_name);
  addRow(t("label_age"), t("common_years", { n: user.age }));
  addRow(t("label_gender"), etiquetaGenero(user.gender));
  card.appendChild(dl);
}

export function renderFilterSummary(filters, itemCount) {
  const box = document.getElementById("filter-summary");
  const ageLabel = `${filters.age_min}-${filters.age_max}`;
  const genderLabel = filters.gender === "all" ? t("filter_all_genders") : etiquetaGenero(filters.gender);
  const typeSuffix = filters.type === "all" ? "" : t("filter_summary_type_suffix", { type: t(`action_${filters.type}`) });
  box.textContent = t("filter_summary", {
    scope: filters.scope_label,
    age: t("common_years", { n: ageLabel }),
    gender: genderLabel,
    typeSuffix,
    count: itemCount,
  });
}

export function renderStatusMessage(statsAvailable) {
  const box = document.getElementById("status-message");
  if (statsAvailable) {
    box.hidden = true;
    return;
  }
  box.hidden = false;
  box.className = "status-message status-message--warning";
  box.textContent = t("status_stats_unavailable");
}
