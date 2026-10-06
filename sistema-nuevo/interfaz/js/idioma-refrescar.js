import { state } from "./nucleo.js";
import { aplicarEstatico } from "./idioma.js";
import { populateScopeSelect, populateTypeSelect, renderUserOptions } from "./selectores.js";
import { leerFiltros, escribirFiltros } from "./controles-filtro.js";
import { renderRespuestaTimeline } from "./respuesta-timeline.js";
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

  const { scope, type } = leerFiltros();
  if (state.groups) {
    populateScopeSelect();
    escribirFiltros({ scope });
  }
  if (state.actionTypes.length > 0) {
    populateTypeSelect();
    escribirFiltros({ type });
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
    renderRespuestaTimeline(data, onPageChange, timeline);
  }
}
