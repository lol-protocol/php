import { API_BASE, state } from "./nucleo.js";
import { t } from "./idioma.js";
import { iniciarMonitorInactividad, detenerMonitorInactividad } from "./inactividad.js";

/**
 * Único punto donde se hace fetch() contra la API: así una sesión que ya no
 * vale (401) se maneja igual sin importar el verbo -- antes solo fetchJson()
 * mandaba al login; un POST/DELETE con sesión muerta dejaba al usuario en un
 * panel muerto con un error genérico ("probá de nuevo") que nunca funciona.
 *
 * /api/login queda afuera: ahí un 401 es "contraseña incorrecta" (un intento
 * normal, ni siquiera hay sesión todavía), no una sesión que expiró -- mandar
 * a showLogin() y pisar el mensaje del servidor rompería el login mismo.
 */
async function pedir(path, options = {}) {
  const response = await fetch(API_BASE + path, { credentials: "include", ...options });
  if (response.status === 401 && path !== "/api/login") {
    showLogin();
    throw new Error(t("error_session_expired"));
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || t("error_http", { status: response.status, path }));
  }
  return data;
}

export function fetchJson(path) {
  return pedir(path);
}

export function postJson(path, body = {}) {
  return pedir(path, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-CSRF-Token": state.csrfToken || "" },
    body: JSON.stringify(body),
  });
}

export function deleteJson(path) {
  return pedir(path, {
    method: "DELETE",
    headers: { "X-CSRF-Token": state.csrfToken || "" },
  });
}

export function showLogin(errorMessage = "") {
  detenerMonitorInactividad();
  document.getElementById("app").hidden = true;
  document.getElementById("login-screen").hidden = false;
  const errorBox = document.getElementById("login-error");
  if (errorMessage) {
    errorBox.hidden = false;
    errorBox.textContent = errorMessage;
  } else {
    errorBox.hidden = true;
  }
}

export function showApp(username, csrfToken = null) {
  state.username = username;
  if (csrfToken) state.csrfToken = csrfToken;
  document.getElementById("login-screen").hidden = true;
  document.getElementById("app").hidden = false;
  document.getElementById("session-username").textContent = t("session_connected_as", { name: username });
  iniciarMonitorInactividad();
}

/** @param {() => Promise<void>} onAuthenticated */
export async function boot(onAuthenticated) {
  try {
    const session = await fetchJson("/api/session");
    if (session.authenticated) {
      state.csrfToken = session.csrf_token;
      showApp(session.username);
      await onAuthenticated();
    } else {
      showLogin();
    }
  } catch (err) {
    showLogin(t("error_connection", { base: API_BASE, message: err.message }));
  }
}
