import { debounce } from "./nucleo.js";

// Los cinco controles de filtro de la barra superior (partes/topbar.php). Sus ids se escriben solo acá; el resto de la
// interfaz los pide por nombre. Antes estaban repetidos en 27 lugares de 5 módulos, y renombrar uno obligaba a
// encontrarlos todos.
export const IDS_FILTRO = {
  scope: "scope-select",
  ageMin: "age-min",
  ageMax: "age-max",
  gender: "gender-select",
  type: "type-select",
};

/** El control de ese filtro: "scope", "ageMin", "ageMax", "gender" o "type". */
export function controlFiltro(nombre) {
  if (!(nombre in IDS_FILTRO)) {
    throw new Error(`filtro desconocido: ${nombre}`);
  }
  return document.getElementById(IDS_FILTRO[nombre]);
}

/** Lo que hay elegido ahora, tal cual está en los controles (texto): cada uso lo interpreta a su manera. */
export function leerFiltros() {
  return Object.fromEntries(Object.keys(IDS_FILTRO).map((nombre) => [nombre, controlFiltro(nombre).value]));
}

/** Pone el valor de los filtros que se nombran y deja los demás como están. No avisa del cambio: ver avisarCambioDeFiltros. */
export function escribirFiltros(valores) {
  for (const [nombre, valor] of Object.entries(valores)) {
    controlFiltro(nombre).value = valor;
  }
}

/** Para después de escribirFiltros() cuando hay que recargar: cambiar un valor por código no dispara ningún evento. */
export function avisarCambioDeFiltros() {
  controlFiltro("scope").dispatchEvent(new Event("change"));
}

/**
 * Llama a alCambiar cuando cambia cualquiera de los cinco filtros. Los desplegables avisan al instante; las edades
 * esperan esperaEdadMs desde la última tecla (cada una con su propio temporizador), para no pedir en cada dígito.
 * @param {() => void} alCambiar
 */
export function escucharCambios(alCambiar, esperaEdadMs = 400) {
  for (const nombre of ["scope", "gender", "type"]) {
    controlFiltro(nombre).addEventListener("change", alCambiar);
  }
  for (const nombre of ["ageMin", "ageMax"]) {
    controlFiltro(nombre).addEventListener("input", debounce(alCambiar, esperaEdadMs));
  }
}
