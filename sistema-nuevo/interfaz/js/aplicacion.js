import { state, PER_PAGE } from "./nucleo.js";
import { fetchJson } from "./sesion.js";
import { populateScopeSelect, populateTypeSelect, renderUserOptions } from "./selectores.js";
import { renderUserCard, renderFilterSummary, renderStatusMessage } from "./tarjeta-usuario.js";
import { renderTimeline } from "./linea-tiempo.js";
import { renderPagination } from "./paginacion.js";
import { renderChart } from "./grafico.js";
import { renderAlerts } from "./alertas.js";
import { renderKpis } from "./kpis.js";

async function loadTimeline() {
  if (!state.selectedUserId) return;

  const scope = document.getElementById("scope-select").value;
  const ageMin = document.getElementById("age-min").value || 0;
  const ageMax = document.getElementById("age-max").value || 150;
  const gender = document.getElementById("gender-select").value;
  const type = document.getElementById("type-select").value;

  const query = new URLSearchParams({
    user_id: state.selectedUserId, scope, age_min: ageMin, age_max: ageMax, gender, type,
    page: state.page, per_page: PER_PAGE,
  });

  try {
    const data = await fetchJson(`/api/timeline?${query.toString()}`);
    state.lastTimeline = data;
    renderUserCard(data.user);
    renderFilterSummary(data.filters, data.pagination.total);
    renderStatusMessage(data.stats_service_available);
    renderChart(data.chart);
    renderTimeline(data.timeline);
    renderPagination(data.pagination, goToPage);
  } catch (err) {
    const box = document.getElementById("status-message");
    box.hidden = false;
    box.className = "status-message status-message--warning";
    box.textContent = `⚠ ${err.message}`;
  }
}

export function goToPage(page) {
  state.page = page;
  return loadTimeline();
}

// Cualquier cambio de filtro (usuario, universo, edad, género, tipo) vuelve a la página 1.
export function loadTimelineFromStart() {
  state.page = 1;
  return loadTimeline();
}

/** Selecciona un usuario desde afuera del selector (p. ej. un clic en el panel de alertas). */
export function selectUser(userId) {
  state.selectedUserId = userId;
  document.getElementById("user-select").value = userId;
  return loadTimelineFromStart();
}

export async function reloadAlerts() {
  const alerts = await fetchJson("/api/alerts");
  state.lastAlerts = alerts;
  renderAlerts(alerts, selectUser);
}

export async function loadAppData() {
  try {
    const [groups, actionTypes, users, alerts, kpis] = await Promise.all([
      fetchJson("/api/groups"),
      fetchJson("/api/action-types"),
      fetchJson("/api/users?per_page=100"),
      fetchJson("/api/alerts"),
      fetchJson("/api/kpis"),
    ]);
    state.groups = groups;
    state.actionTypes = actionTypes;
    state.users = users.items;
    state.lastAlerts = alerts;
    state.lastKpis = kpis;

    populateScopeSelect();
    populateTypeSelect();
    renderUserOptions("", loadTimelineFromStart);
    renderAlerts(alerts, selectUser);
    renderKpis(kpis);

    if (state.users.length > 0) {
      state.selectedUserId = document.getElementById("user-select").value || state.users[0].id;
      await loadTimelineFromStart();
    }
  } catch (err) {
    const box = document.getElementById("status-message");
    box.hidden = false;
    box.className = "status-message status-message--warning";
    box.textContent = `⚠ ${err.message}`;
  }
}
