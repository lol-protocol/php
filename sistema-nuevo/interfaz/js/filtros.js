import { state, el, API_BASE } from "./nucleo.js";
import { postJson, deleteJson, fetchJson } from "./sesion.js";
import { t } from "./idioma.js";

export async function cargarFiltrosGuardados() {
  try {
    const filtros = await fetchJson("/api/filtros");
    renderFiltrosDropdown(filtros);
  } catch (err) {
    console.error("Error cargando filtros:", err);
  }
}

function renderFiltrosDropdown(filtros) {
  const select = document.getElementById("saved-filters-select");
  if (!select) return;

  select.innerHTML = '<option value="">-- Cargar filtro guardado --</option>';
  filtros.forEach((f) => {
    const opt = document.createElement("option");
    opt.value = f.id;
    opt.textContent = f.nombre;
    select.appendChild(opt);
  });
}

export async function aplicarFiltroGuardado(filtroId) {
  if (!filtroId) return;

  try {
    const filtros = await fetchJson("/api/filtros");
    const filtro = filtros.find((f) => f.id == filtroId);
    if (!filtro) return;

    // Parsear scope (e.g., "pais:US", "grupo:otan")
    if (filtro.scope === "all_countries") {
      document.getElementById("scope-select").value = "all";
    } else if (filtro.scope.startsWith("pais:")) {
      const pais = filtro.scope.split(":")[1];
      document.getElementById("scope-select").value = pais;
    } else if (filtro.scope.startsWith("grupo:")) {
      const grupo = filtro.scope.split(":")[1];
      document.getElementById("scope-select").value = grupo;
    }

    if (filtro.age_min !== null) document.getElementById("age-min").value = filtro.age_min;
    if (filtro.age_max !== null) document.getElementById("age-max").value = filtro.age_max;
    if (filtro.gender) document.getElementById("gender-select").value = filtro.gender;
    if (filtro.tipo_accion) document.getElementById("type-select").value = filtro.tipo_accion;

    // Disparar evento de cambio para cargar datos
    document.getElementById("scope-select").dispatchEvent(new Event("change"));
  } catch (err) {
    console.error("Error aplicando filtro:", err);
  }
}

export async function guardarFiltroActual() {
  const nombre = prompt(t("filtro_nombre_prompt"));
  if (!nombre) return;

  const scope = document.getElementById("scope-select").value;
  const ageMin = parseInt(document.getElementById("age-min").value) || null;
  const ageMax = parseInt(document.getElementById("age-max").value) || null;
  const gender = document.getElementById("gender-select").value || null;
  const tipoAccion = document.getElementById("type-select").value || null;

  try {
    await postJson("/api/filtros", {
      nombre,
      scope: scope === "all" ? "all_countries" : scope,
      age_min: ageMin,
      age_max: ageMax,
      gender: gender === "all" ? null : gender,
      tipo_accion: tipoAccion === "all" ? null : tipoAccion,
    });
    await cargarFiltrosGuardados();
  } catch (err) {
    console.error("Error guardando filtro:", err);
  }
}

export async function eliminarFiltroGuardado(filtroId) {
  if (!confirm(t("filtro_eliminar_confirmar"))) return;

  try {
    await deleteJson("/api/filtros/" + filtroId);
    await cargarFiltrosGuardados();
  } catch (err) {
    console.error("Error eliminando filtro:", err);
  }
}
