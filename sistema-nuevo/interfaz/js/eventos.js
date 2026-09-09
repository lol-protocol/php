import { state, debounce } from "./nucleo.js";
import { postJson, showLogin, showApp, boot } from "./sesion.js";
import { renderUserOptions } from "./selectores.js";
import { loadAppData, loadTimelineFromStart, selectUser, goToPage, reloadAlerts } from "./aplicacion.js";
import { establecerIdioma, inicializarIdioma } from "./idioma.js";
import { refrescarIdioma } from "./idioma-refrescar.js";
import { cargarFiltrosGuardados, aplicarFiltroGuardado, guardarFiltroActual, eliminarFiltroGuardado } from "./filtros.js";
import { cargarConfigAlertas, toggleConfigPanel, guardarConfigAlertas } from "./configuracion-alertas.js";

inicializarIdioma();
document.querySelectorAll(".lang-button").forEach((boton) => {
  boton.addEventListener("click", () => {
    establecerIdioma(boton.dataset.lang);
    refrescarIdioma(selectUser, goToPage);
  });
});

document.getElementById("login-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  const username = document.getElementById("login-username").value.trim();
  const password = document.getElementById("login-password").value;
  try {
    const result = await postJson("/api/login", { username, password });
    document.getElementById("login-password").value = "";
    showApp(result.username, result.csrf_token);
    await loadAppData();
  } catch (err) {
    showLogin(err.message);
  }
});

document.getElementById("logout-button").addEventListener("click", async () => {
  try {
    await postJson("/api/logout", { csrf_token: state.csrf_token || "" });
  } finally {
    state.selectedUserId = null;
    state.csrf_token = null;
    showLogin();
  }
});

document.getElementById("user-search").addEventListener("input", (e) => renderUserOptions(e.target.value, loadTimelineFromStart));
document.getElementById("user-select").addEventListener("change", (e) => selectUser(e.target.value));
document.getElementById("scope-select").addEventListener("change", loadTimelineFromStart);
document.getElementById("gender-select").addEventListener("change", loadTimelineFromStart);
document.getElementById("type-select").addEventListener("change", loadTimelineFromStart);
document.getElementById("age-min").addEventListener("input", debounce(loadTimelineFromStart, 400));
document.getElementById("age-max").addEventListener("input", debounce(loadTimelineFromStart, 400));

document.getElementById("saved-filters-select").addEventListener("change", (e) => {
  if (e.target.value) {
    aplicarFiltroGuardado(e.target.value);
  }
});

document.getElementById("btn-guardar-filtro").addEventListener("click", guardarFiltroActual);

document.getElementById("btn-eliminar-filtro").addEventListener("click", async () => {
  const select = document.getElementById("saved-filters-select");
  if (!select.value) return;
  await eliminarFiltroGuardado(select.value);
});

document.getElementById("btn-config-alertas").addEventListener("click", async () => {
  const panel = document.getElementById("alerts-config-panel");
  if (panel.hidden) await cargarConfigAlertas();
  toggleConfigPanel();
});

document.getElementById("config-umbral").addEventListener("input", (e) => {
  document.getElementById("config-umbral-valor").textContent = e.target.value;
});

document.getElementById("btn-guardar-config-alertas").addEventListener("click", () => guardarConfigAlertas(reloadAlerts));

boot(async () => {
  await cargarFiltrosGuardados();
  await loadAppData();
});
