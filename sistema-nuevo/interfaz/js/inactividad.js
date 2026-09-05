import { state, API_BASE } from "./nucleo.js";
import { postJson, showLogin } from "./sesion.js";
import { t } from "./idioma.js";

const TIMEOUT_MINUTOS = 30;
const ADVERTENCIA_MINUTOS = 1;
const TIMEOUT_MS = TIMEOUT_MINUTOS * 60 * 1000;
const ADVERTENCIA_MS = (TIMEOUT_MINUTOS - ADVERTENCIA_MINUTOS) * 60 * 1000;

let ultimaActividad = Date.now();
let timerInactividad = null;
let modalAdvertencia = null;
let timerFinal = null;

export function iniciarMonitorInactividad() {
  if (!state.username) return;

  registrarActividad();

  ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach((evento) => {
    document.addEventListener(evento, registrarActividad, true);
  });

  timerInactividad = setInterval(verificarInactividad, 10000);
}

export function detenerMonitorInactividad() {
  if (timerInactividad) clearInterval(timerInactividad);
  if (timerFinal) clearTimeout(timerFinal);
  ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach((evento) => {
    document.removeEventListener(evento, registrarActividad, true);
  });
}

function registrarActividad() {
  ultimaActividad = Date.now();
  if (modalAdvertencia && !modalAdvertencia.hidden) {
    cerrarAdvertencia();
  }
}

function verificarInactividad() {
  const msInactivos = Date.now() - ultimaActividad;

  if (msInactivos >= TIMEOUT_MS) {
    logoutAutomatico();
  } else if (msInactivos >= ADVERTENCIA_MS && (!modalAdvertencia || modalAdvertencia.hidden)) {
    mostrarAdvertencia();
  }
}

function mostrarAdvertencia() {
  if (modalAdvertencia) {
    modalAdvertencia.hidden = false;
  } else {
    modalAdvertencia = document.createElement("div");
    modalAdvertencia.id = "inactividad-advertencia";
    modalAdvertencia.innerHTML = `
      <div class="inactividad-modal">
        <h3 data-i18n="inactividad_titulo">Sesión por expirar</h3>
        <p data-i18n="inactividad_mensaje">Por inactividad, tu sesión se cerrará en ${ADVERTENCIA_MINUTOS} minuto(s).</p>
        <div class="inactividad-botones">
          <button id="btn-continuar" data-i18n="inactividad_continuar">Continuar activo</button>
          <button id="btn-logout" data-i18n="inactividad_logout">Cerrar sesión ahora</button>
        </div>
      </div>
    `;
    document.body.appendChild(modalAdvertencia);

    document.getElementById("btn-continuar").addEventListener("click", cerrarAdvertencia);
    document.getElementById("btn-logout").addEventListener("click", logoutAutomatico);
  }

  if (timerFinal) clearTimeout(timerFinal);
  const tiempoHastaLogout = TIMEOUT_MS - ADVERTENCIA_MS;
  timerFinal = setTimeout(logoutAutomatico, tiempoHastaLogout);
}

function cerrarAdvertencia() {
  if (modalAdvertencia) modalAdvertencia.hidden = true;
  registrarActividad();
}

async function logoutAutomatico() {
  detenerMonitorInactividad();
  try {
    await postJson("/api/logout", { csrf_token: state.csrf_token || "" });
  } finally {
    state.selectedUserId = null;
    state.csrf_token = null;
    showLogin(t("inactividad_sesion_cerrada"));
  }
}
