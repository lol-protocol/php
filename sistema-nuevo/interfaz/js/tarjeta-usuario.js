import { el, GENDER_LABELS } from "./nucleo.js";

export function renderUserCard(user) {
  const card = document.getElementById("user-card");
  card.innerHTML = "";
  card.appendChild(el("h2", { text: user.name }));
  card.appendChild(el("p", { class: "user-meta", text: `Usuario ${user.id}` }));

  const dl = el("dl");
  const addRow = (label, value) => {
    dl.appendChild(el("dt", { text: label }));
    dl.appendChild(el("dd", { text: value }));
  };
  addRow("País", user.country_name);
  addRow("Edad", `${user.age} años`);
  addRow("Género", GENDER_LABELS[user.gender] || user.gender);
  card.appendChild(dl);
}

export function renderFilterSummary(filters, itemCount) {
  const box = document.getElementById("filter-summary");
  const ageLabel = `${filters.age_min}-${filters.age_max} años`;
  const genderLabel = filters.gender === "all" ? "todos los géneros" : GENDER_LABELS[filters.gender];
  box.textContent =
    `Comparando contra: ${filters.scope_label} · ${ageLabel} · ${genderLabel} ` +
    `— ${itemCount} acciones en el flujo`;
}

export function renderStatusMessage(statsAvailable) {
  const box = document.getElementById("status-message");
  if (statsAvailable) {
    box.hidden = true;
    return;
  }
  box.hidden = false;
  box.className = "status-message status-message--warning";
  box.textContent =
    "⚠ El servicio de estadísticas (Java) no respondió. Se muestran las acciones sin comparación contra el universo elegido.";
}
