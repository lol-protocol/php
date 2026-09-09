import { postJson } from "./sesion.js";

const timers = new Map();

async function guardarNota(accionId, texto) {
  try {
    await postJson("/api/notes", { accion_id: accionId, texto });
  } catch (err) {
    console.error("Error guardando nota:", err);
  }
}

/** Debounce independiente por acción: escribir en una nota no cancela el guardado de otra. */
export function guardarNotaConDebounce(accionId, texto) {
  clearTimeout(timers.get(accionId));
  timers.set(accionId, setTimeout(() => guardarNota(accionId, texto), 600));
}
