import { renderUserCard, renderFilterSummary, renderStatusMessage } from "./tarjeta-usuario.js";
import { renderChart } from "./grafico.js";
import { renderTimeline } from "./linea-tiempo.js";
import { renderPagination } from "./paginacion.js";

/**
 * Pinta todo lo que trae una respuesta de /api/timeline: la tarjeta del usuario, el resumen de filtros, el aviso del
 * servicio de estadísticas, el gráfico, el timeline y la paginación. Lo usan el pedido de datos (aplicacion.js) y el
 * cambio de idioma (idioma-refrescar.js), que vuelve a pintar lo último recibido sin pedir nada: antes cada uno
 * tenía su copia de esta secuencia.
 * @param {object} data respuesta de /api/timeline
 * @param {(page: number) => void} onPageChange
 * @param {object[]} [items] acciones a dibujar en el timeline si no son las de data.timeline (el cambio de idioma
 *        pasa las suyas, con las notas que están en pantalla y todavía no se guardaron)
 */
export function renderRespuestaTimeline(data, onPageChange, items = data.timeline) {
  renderUserCard(data.user);
  renderFilterSummary(data.filters, data.pagination.total);
  renderStatusMessage(data.stats_service_available);
  renderChart(data.chart);
  renderTimeline(items);
  renderPagination(data.pagination, onPageChange);
}
