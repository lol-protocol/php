import { state } from "./nucleo.js";
import { aplicarEstatico } from "./idioma.js";
import { populateScopeSelect, populateTypeSelect, renderUserOptions } from "./selectores.js";
import { renderUserCard, renderFilterSummary, renderStatusMessage } from "./tarjeta-usuario.js";
import { renderTimeline } from "./linea-tiempo.js";
import { renderPagination } from "./paginacion.js";
import { renderChart } from "./grafico.js";
import { renderAlerts } from "./alertas.js";
import { renderKpis } from "./kpis.js";

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

  if (state.lastKpis) {
    renderKpis(state.lastKpis);
  }

  const data = state.lastTimeline;
  if (data) {
    renderUserCard(data.user);
    renderFilterSummary(data.filters, data.pagination.total);
    renderStatusMessage(data.stats_service_available);
    renderChart(data.chart);

    // Una nota recién tipeada puede no estar guardada todavía (debounce de
    // 600ms): reconstruir el timeline desde el caché de data.timeline la
    // pisaría con el texto viejo, aunque el guardado en curso sí vaya a la
    // base bien -- solo la pantalla quedaría mintiendo.
    const notasEnPantalla = new Map(
      Array.from(document.querySelectorAll(".note-textarea[data-accion-id]")).map((n) => [n.dataset.accionId, n.value])
    );
    const timeline = data.timeline.map((item) =>
      notasEnPantalla.has(String(item.id)) ? { ...item, note: notasEnPantalla.get(String(item.id)) } : item
    );
    renderTimeline(timeline);
    renderPagination(data.pagination, onPageChange);
  }
}
