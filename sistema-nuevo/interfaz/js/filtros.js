import { postJson, deleteJson, fetchJson } from "./sesion.js";
import { intentar, el } from "./nucleo.js";
import { t } from "./idioma.js";
import { mostrarError } from "./notificaciones.js";

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
  select.appendChild(el("option", { value: "", text: t("filtro_cargar_placeholder") }));
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

    // scope se guarda tal cual viene de #scope-select (ya trae "country:XX"/"preset:XX").
    document.getElementById("scope-select").value = filtro.scope === "all_countries" ? "all" : filtro.scope;
    document.getElementById("age-min").value = filtro.age_min ?? 18;
    document.getElementById("age-max").value = filtro.age_max ?? 65;
    document.getElementById("gender-select").value = filtro.gender || "all";
    document.getElementById("type-select").value = filtro.tipo_accion || "all";

    // Disparar evento de cambio para cargar datos
    document.getElementById("scope-select").dispatchEvent(new Event("change"));
  }, "Error aplicando filtro:", () => mostrarError(t("toast_error_cargar")));
}

function parseIntOrNull(value) {
  const n = parseInt(value, 10);
  return Number.isNaN(n) ? null : n;
}

export async function guardarFiltroActual() {
  const nombre = prompt(t("filtro_nombre_prompt"));
  if (!nombre) return;

  const scope = document.getElementById("scope-select").value;
  const ageMin = parseIntOrNull(document.getElementById("age-min").value);
  const ageMax = parseIntOrNull(document.getElementById("age-max").value);
  const gender = document.getElementById("gender-select").value || null;
  const tipoAccion = document.getElementById("type-select").value || null;

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
  if (!confirm(t("filtro_eliminar_confirmar"))) return;

  await intentar(async () => {
    await deleteJson("/api/filtros/" + filtroId);
    await cargarFiltrosGuardados();
  }, "Error eliminando filtro:", () => mostrarError(t("toast_error_eliminar")));
}
