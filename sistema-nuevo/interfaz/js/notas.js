import { postJson } from "./sesion.js";
import { intentar } from "./nucleo.js";

const timers = new Map();
const colas = new Map();

/**
 * La API pasa la nota por el filtro: si la rechaza (insultos, amenazas) responde
 * con error y el motivo llega en el mensaje; si la guarda pero hay que revisarla
 * (una acusación, un dato sensible o personal) responde revisar: true y los motivos.
 */
async function guardarNota(accionId, texto, onEstado) {
  onEstado?.("guardando");
  const resultado = await intentar(
    () => postJson("/api/notes", { accion_id: accionId, texto }),
    "Error guardando nota:",
    (err) => onEstado?.("error", err?.message)
  );
  if (resultado === null) return;
  if (resultado.revisar) onEstado?.("revisar", (resultado.motivos ?? []).join(", "));
  else onEstado?.("guardado");
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
 * guardado de otra. onEstado(status, detalle) recibe "guardando" | "guardado" |
 * "revisar" | "error", y el detalle (los motivos o el mensaje de error) si lo hay.
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
