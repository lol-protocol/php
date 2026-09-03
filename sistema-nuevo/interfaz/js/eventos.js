import { state, debounce } from "./nucleo.js";
import { postJson, showLogin, showApp, boot } from "./sesion.js";
import { renderUserOptions } from "./selectores.js";
import { loadAppData, loadTimelineFromStart, selectUser, goToPage } from "./aplicacion.js";
import { establecerIdioma, inicializarIdioma } from "./idioma.js";
import { refrescarIdioma } from "./idioma-refrescar.js";

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
    showApp(result.username);
    await loadAppData();
  } catch (err) {
    showLogin(err.message);
  }
});

document.getElementById("logout-button").addEventListener("click", async () => {
  try {
    await postJson("/api/logout", {});
  } finally {
    state.selectedUserId = null;
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

boot(loadAppData);
