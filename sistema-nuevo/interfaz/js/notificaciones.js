import { el } from "./nucleo.js";

let ocultarTimer = null;

/** Toast de error genérico, arriba a la derecha, se autooculta a los 4s. */
export function mostrarError(mensaje) {
  let toast = document.getElementById("toast-error");
  if (!toast) {
    toast = el("div", { id: "toast-error", class: "toast-error" });
    document.body.appendChild(toast);
  }

  toast.textContent = mensaje;
  toast.hidden = false;

  clearTimeout(ocultarTimer);
  ocultarTimer = setTimeout(() => { toast.hidden = true; }, 4000);
}
