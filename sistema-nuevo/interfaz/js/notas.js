import { postJson } from "./sesion.js";
import { intentar } from "./nucleo.js";

const timers = new Map();
const colas = new Map();

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
 * Encadena el guardado detrás de cualquier guardado anterior de LA MISMA
 * acción, para que nunca haya dos en vuelo a la vez: si dos respuestas de red
 * llegaran desordenadas, la más vieja podía pisar a la más nueva sin que nada
 * lo note (quedaba "✓ guardado" en pantalla con el texto de antes).
 */
function encolar(accionId, texto, onEstado) {
  const anterior = colas.get(accionId) ?? Promise.resolve();
  const actual = anterior.then(() => guardarNota(accionId, texto, onEstado));
  colas.set(accionId, actual);
  actual.then(() => {
    if (colas.get(accionId) === actual) colas.delete(accionId);
  });
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
      encolar(accionId, texto, onEstado);
    }, 600)
  );
}
