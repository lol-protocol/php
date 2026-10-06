import { state } from "./nucleo.js";
import { cerrarSesion } from "./sesion.js";
import { t } from "./idioma.js";
import { abrirModal } from "./modal.js";

const TIMEOUT_MINUTOS = 30;
const ADVERTENCIA_MINUTOS = 1;
const TIMEOUT_MS = TIMEOUT_MINUTOS * 60 * 1000;
const ADVERTENCIA_MS = (TIMEOUT_MINUTOS - ADVERTENCIA_MINUTOS) * 60 * 1000;
const EVENTOS_ACTIVIDAD = ["click", "mousemove", "keypress", "scroll", "touchstart"];
const SEGUIR = "seguir";
const SALIR = "salir";

let ultimaActividad = Date.now();
let timerInactividad = null;
let advertencia = null; // el aviso abierto ahora, si hay uno: el { eleccion, descartar } del modal

export function iniciarMonitorInactividad() {
  if (!state.username) return;

  registrarActividad();
  EVENTOS_ACTIVIDAD.forEach((evento) => document.addEventListener(evento, registrarActividad, true));
  timerInactividad = setInterval(verificarInactividad, 10000);
}

export function detenerMonitorInactividad() {
  if (timerInactividad) clearInterval(timerInactividad);
  timerInactividad = null;
  cerrarAdvertencia();
  EVENTOS_ACTIVIDAD.forEach((evento) => document.removeEventListener(evento, registrarActividad, true));
}

// Con el aviso abierto no se cuenta nada: mover el mouse o tocar la pantalla es como se llega a un botón, y el aviso
// se contesta solo (sus botones, Escape o un clic afuera). Si no se contesta, verificarInactividad cierra la sesión.
function registrarActividad() {
  if (advertencia) return;
  ultimaActividad = Date.now();
}

function verificarInactividad() {
  const msInactivos = Date.now() - ultimaActividad;

  if (msInactivos >= TIMEOUT_MS) {
    cerrarPorInactividad();
  } else if (msInactivos >= ADVERTENCIA_MS && !advertencia) {
    mostrarAdvertencia();
  }
}

async function mostrarAdvertencia() {
  const esta = abrirModal({
    titulo: t("inactividad_titulo"),
    mensaje: t("inactividad_mensaje"),
    botones: [
      { texto: t("inactividad_continuar"), clase: "modal-btn-confirmar", valor: SEGUIR },
      { texto: t("inactividad_logout"), clase: "modal-btn-peligro", valor: SALIR },
    ],
    alDescartar: SEGUIR,
  });
  advertencia = esta;

  const eleccion = await esta.eleccion;
  if (advertencia !== esta) return; // se cerró desde acá (se detuvo el monitor): no hay nada que decidir
  advertencia = null;
  if (eleccion === SALIR) {
    detenerMonitorInactividad();
    cerrarSesion(); // la persona la cerró a propósito: igual que con el botón "Cerrar sesión", sin explicar nada
  } else {
    ultimaActividad = Date.now();
  }
}

function cerrarAdvertencia() {
  const abierta = advertencia;
  advertencia = null;
  abierta?.descartar();
}

function cerrarPorInactividad() {
  detenerMonitorInactividad(); // antes del pedido: si tarda, el próximo chequeo no tiene que volver a cerrar
  return cerrarSesion(t("inactividad_sesion_cerrada"));
}
