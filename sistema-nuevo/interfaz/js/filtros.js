import { postJson, deleteJson, fetchJson } from "./sesion.js";
import { intentar, el } from "./nucleo.js";
import { t } from "./idioma.js";
import { mostrarError } from "./notificaciones.js";
import { leerFiltros, escribirFiltros, avisarCambioDeFiltros } from "./controles-filtro.js";
import { modalPrompt, modalConfirmar } from "./modal.js";

let ultimaPeticionFiltro = 0;

export async function cargarFiltrosGuardados() {
  const filtros = await intentar(
    () => fetchJson("/api/filtros"),
    "Error cargando filtros:",
    () => mostrarError(t("toast_error_cargar"))
  );
  if (filtros) renderFiltrosDropdown(filtros);
}

function renderFiltrosDropdown(filtros) {
  const select = document.getElementById("saved-filters-select");
  if (!select) return;

  select.innerHTML = "";
  // data-i18n: este desplegable no se re-renderiza al cambiar de idioma
  // (refrescarIdioma no lo toca), pero aplicarEstatico() sí retraduce esto.
  select.appendChild(el("option", { value: "", text: t("filtro_cargar_placeholder"), "data-i18n": "filtro_cargar_placeholder" }));
  filtros.forEach((f) => {
    const opt = document.createElement("option");
    opt.value = f.id;
    opt.textContent = f.nombre;
    select.appendChild(opt);
  });
}

export async function aplicarFiltroGuardado(filtroId) {
  if (!filtroId) return;
  const peticionId = ++ultimaPeticionFiltro;

  await intentar(async () => {
    const filtros = await fetchJson("/api/filtros");
    if (peticionId !== ultimaPeticionFiltro) return; // un cambio de filtro más nuevo ya ganó
    const filtro = filtros.find((f) => String(f.id) === String(filtroId));
    if (!filtro) return;

    // scope se guarda tal cual viene del desplegable de país (ya trae "country:XX"/"preset:XX").
    escribirFiltros({
      scope: filtro.scope === "all_countries" ? "all" : filtro.scope,
      ageMin: filtro.age_min ?? 18,
      ageMax: filtro.age_max ?? 65,
      gender: filtro.gender || "all",
      type: filtro.tipo_accion || "all",
    });

    // Disparar evento de cambio para cargar datos
    avisarCambioDeFiltros();
  }, "Error aplicando filtro:", () => mostrarError(t("toast_error_cargar")));
}

function parseIntOrNull(value) {
  const n = parseInt(value, 10);
  return Number.isNaN(n) ? null : n;
}

export async function guardarFiltroActual() {
  const nombre = await modalPrompt(t("filtro_nombre_prompt"), t("btn_guardar_filtro"));
  if (!nombre) return;

  const filtros = leerFiltros();
  const scope = filtros.scope;
  const ageMin = parseIntOrNull(filtros.ageMin);
  const ageMax = parseIntOrNull(filtros.ageMax);
  const gender = filtros.gender || null;
  const tipoAccion = filtros.type || null;

  await intentar(async () => {
    await postJson("/api/filtros", {
      nombre,
      scope: scope === "all" ? "all_countries" : scope,
      age_min: ageMin,
      age_max: ageMax,
      gender: gender === "all" ? null : gender,
      tipo_accion: tipoAccion === "all" ? null : tipoAccion,
    });
    await cargarFiltrosGuardados();
  }, "Error guardando filtro:", () => mostrarError(t("toast_error_guardar")));
}

export async function eliminarFiltroGuardado(filtroId) {
  const confirmado = await modalConfirmar(t("filtro_eliminar_confirmar"), t("btn_eliminar_filtro"), { peligroso: true });
  if (!confirmado) return;

  await intentar(async () => {
    await deleteJson("/api/filtros/" + filtroId);
    await cargarFiltrosGuardados();
  }, "Error eliminando filtro:", () => mostrarError(t("toast_error_eliminar")));
}
