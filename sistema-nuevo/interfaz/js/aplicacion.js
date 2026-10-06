import { state, PER_PAGE } from "./nucleo.js";
import { fetchJson } from "./sesion.js";
import { populateScopeSelect, populateTypeSelect, renderUserOptions } from "./selectores.js";
import { mostrarAviso } from "./tarjeta-usuario.js";
import { leerFiltros } from "./controles-filtro.js";
import { renderRespuestaTimeline } from "./respuesta-timeline.js";
import { renderAlerts } from "./alertas.js";
import { renderKpis } from "./kpis.js";

let ultimaPeticionTimeline = 0;

async function loadTimeline() {
  if (!state.selectedUserId) return;

  const { scope, ageMin, ageMax, gender, type } = leerFiltros();

  const query = new URLSearchParams({
    user_id: state.selectedUserId, scope, age_min: ageMin || 0, age_max: ageMax || 150, gender, type,
    page: state.page, per_page: PER_PAGE,
  });
  const peticionId = ++ultimaPeticionTimeline;

  try {
    const data = await fetchJson(`/api/timeline?${query.toString()}`);
    if (peticionId !== ultimaPeticionTimeline) return; // una respuesta más nueva ya ganó
    state.lastTimeline = data;
    renderRespuestaTimeline(data, goToPage);
  } catch (err) {
    if (peticionId !== ultimaPeticionTimeline) return;
    mostrarAviso(`⚠ ${err.message}`);
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
      fetchJson("/api/groups"), fetchJson("/api/action-types"), fetchJson("/api/users?per_page=100"),
      fetchJson("/api/alerts"), fetchJson("/api/kpis"),
    ]);
    state.groups = groups;
    state.actionTypes = actionTypes;
    state.users = users.items;
    state.lastAlerts = alerts;
    state.lastKpis = kpis;

    populateScopeSelect();
    populateTypeSelect();
    renderUserOptions("", () => {}); // el bloque de abajo ya dispara la carga inicial; duplicaba el pedido
    renderAlerts(alerts, selectUser);
    renderKpis(kpis);

    if (state.users.length > 0) {
      state.selectedUserId = document.getElementById("user-select").value || state.users[0].id;
      await loadTimelineFromStart();
    }
  } catch (err) {
    mostrarAviso(`⚠ ${err.message}`);
  }
}
