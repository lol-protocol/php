import { state } from "./nucleo.js";
import ES from "./i18n/es.js";
import EN from "./i18n/en.js";

const DICCIONARIOS = { es: ES, en: EN };
const CLAVE_GUARDADO = "backoffice_idioma";
const CLAVE_GENERO = { M: "gender_m", F: "gender_f", O: "gender_o" };

state.lang = localStorage.getItem(CLAVE_GUARDADO) || "es";

/** @param {string} key @param {Record<string,string|number>} [vars] */
export function t(key, vars = {}) {
  let str = DICCIONARIOS[state.lang][key] ?? key;
  for (const [k, v] of Object.entries(vars)) {
    str = str.replaceAll(`{${k}}`, String(v));
  }
  return str;
}

export function etiquetaGenero(codigo) {
  return t(CLAVE_GENERO[codigo] ?? codigo);
}

/** Traduce todo el texto/placeholder estático marcado con data-i18n en el DOM actual. */
export function aplicarEstatico() {
  document.querySelectorAll("[data-i18n]").forEach((nodo) => {
    nodo.textContent = t(nodo.dataset.i18n);
  });
  document.querySelectorAll("[data-i18n-placeholder]").forEach((nodo) => {
    nodo.placeholder = t(nodo.dataset.i18nPlaceholder);
  });
  if (state.username) {
    document.getElementById("session-username").textContent = t("session_connected_as", { name: state.username });
  }
}

function marcarBotonActivo(lang) {
  document.querySelectorAll(".lang-button").forEach((boton) => {
    boton.classList.toggle("active", boton.dataset.lang === lang);
  });
}

export function establecerIdioma(lang) {
  state.lang = lang;
  localStorage.setItem(CLAVE_GUARDADO, lang);
  aplicarEstatico();
  marcarBotonActivo(lang);
}

export function inicializarIdioma() {
  aplicarEstatico();
  marcarBotonActivo(state.lang);
}
