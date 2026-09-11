import { postJson } from "./sesion.js";

const timers = new Map();

async function guardarNota(accionId, texto, onEstado) {
  onEstado?.("guardando");
  try {
    await postJson("/api/notes", { accion_id: accionId, texto });
    onEstado?.("guardado");
  } catch (err) {
    console.error("Error guardando nota:", err);
    onEstado?.("error");
  }
}

/**
 * Debounce independiente por acción: escribir en una nota no cancela el
 * guardado de otra. onEstado(status) recibe "guardando" | "guardado" | "error".
 */
export function guardarNotaConDebounce(accionId, texto, onEstado) {
  clearTimeout(timers.get(accionId));
  timers.set(accionId, setTimeout(() => guardarNota(accionId, texto, onEstado), 600));
}
