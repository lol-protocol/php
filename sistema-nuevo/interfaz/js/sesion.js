import { API_BASE } from "./nucleo.js";

export async function fetchJson(path) {
  const response = await fetch(API_BASE + path, { credentials: "include" });
  if (response.status === 401) {
    showLogin();
    throw new Error("sesión expirada");
  }
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new Error(body.error || `Error ${response.status} al llamar ${path}`);
  }
  return response.json();
}

export async function postJson(path, body) {
  const response = await fetch(API_BASE + path, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || `Error ${response.status} al llamar ${path}`);
  }
  return data;
}

export function showLogin(errorMessage = "") {
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

export function showApp(username) {
  document.getElementById("login-screen").hidden = true;
  document.getElementById("app").hidden = false;
  document.getElementById("session-username").textContent = `Conectado como ${username}`;
}

/** @param {() => Promise<void>} onAuthenticated */
export async function boot(onAuthenticated) {
  try {
    const session = await fetchJson("/api/session");
    if (session.authenticated) {
      showApp(session.username);
      await onAuthenticated();
    } else {
      showLogin();
    }
  } catch (err) {
    showLogin(`No se pudo conectar con el backend PHP en ${API_BASE}. (${err.message})`);
  }
}
