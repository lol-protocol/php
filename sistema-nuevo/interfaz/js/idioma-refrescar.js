import { state } from "./nucleo.js";
import { aplicarEstatico } from "./idioma.js";
import { populateScopeSelect, populateTypeSelect, renderUserOptions } from "./selectores.js";
import { renderUserCard, renderFilterSummary, renderStatusMessage } from "./tarjeta-usuario.js";
import { renderTimeline } from "./linea-tiempo.js";
import { renderPagination } from "./paginacion.js";
import { renderChart } from "./grafico.js";
import { renderAlerts } from "./alertas.js";

/**
 * Vuelve a pintar todo lo que ya está en pantalla en el nuevo idioma, usando lo
 * último cacheado en state (sin pedirle datos de nuevo al backend).
 * @param {(userId: string) => void} onSelectUser
 * @param {(page: number) => void} onPageChange
 */
export function refrescarIdioma(onSelectUser, onPageChange) {
  aplicarEstatico();

  const scopeValue = document.getElementById("scope-select").value;
  const typeValue = document.getElementById("type-select").value;
  if (state.groups) {
    populateScopeSelect();
    document.getElementById("scope-select").value = scopeValue;
  }
  if (state.actionTypes.length > 0) {
    populateTypeSelect();
    document.getElementById("type-select").value = typeValue;
  }
  renderUserOptions(document.getElementById("user-search").value, () => {});

  if (state.lastAlerts) {
    renderAlerts(state.lastAlerts, onSelectUser);
  }

  const data = state.lastTimeline;
  if (data) {
    renderUserCard(data.user);
    renderFilterSummary(data.filters, data.pagination.total);
    renderStatusMessage(data.stats_service_available);
    renderChart(data.chart);
    renderTimeline(data.timeline);
    renderPagination(data.pagination, onPageChange);
  }
}
