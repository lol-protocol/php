import { el } from "./nucleo.js";
import { t } from "./idioma.js";

/**
 * Dibuja los controles Anterior/Siguiente + "Página X de Y (N acciones)".
 * @param {{total:number,page:number,per_page:number,total_pages:number}} pagination
 * @param {(page: number) => void} onPageChange
 */
export function renderPagination(pagination, onPageChange) {
  const box = document.getElementById("pagination");
  box.innerHTML = "";

  if (pagination.total === 0) {
    box.hidden = true;
    return;
  }
  box.hidden = false;

  const prev = el("button", { type: "button", class: "page-button", text: t("pagination_prev") });
  prev.disabled = pagination.page <= 1;
  prev.addEventListener("click", () => onPageChange(pagination.page - 1));

  const next = el("button", { type: "button", class: "page-button", text: t("pagination_next") });
  next.disabled = pagination.page >= pagination.total_pages;
  next.addEventListener("click", () => onPageChange(pagination.page + 1));

  const info = el("span", {
    class: "page-info",
    text: t("pagination_info", { page: pagination.page, total: pagination.total_pages, count: pagination.total }),
  });

  box.append(prev, info, next);
}
