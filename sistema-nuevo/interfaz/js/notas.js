import { postJson } from "./sesion.js";
import { intentar } from "./nucleo.js";

const timers = new Map();

async function guardarNota(accionId, texto, onEstado) {
  onEstado?.("guardando");
  const resultado = await intentar(
    () => postJson("/api/notes", { accion_id: accionId, texto }),
    "Error guardando nota:",
    () => onEstado?.("error")
  );
  if (resultado !== null) onEstado?.("guardado");
}

/**
 * Debounce independiente por acción: escribir en una nota no cancela el
 * guardado de otra. onEstado(status) recibe "guardando" | "guardado" | "error".
 */
export function guardarNotaConDebounce(accionId, texto, onEstado) {
  clearTimeout(timers.get(accionId));
  timers.set(
    accionId,
    setTimeout(() => {
      timers.delete(accionId);
      guardarNota(accionId, texto, onEstado);
    }, 600)
  );
}
