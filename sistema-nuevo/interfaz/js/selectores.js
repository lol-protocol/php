import { state, el } from "./nucleo.js";

export function populateScopeSelect() {
  const select = document.getElementById("scope-select");
  select.innerHTML = "";
  select.appendChild(el("option", { value: "all", text: "Todos los países" }));

  const presetGroup = el("optgroup", { label: "Grupos de países" });
  state.groups.presets.forEach((preset) => {
    presetGroup.appendChild(el("option", { value: `preset:${preset.key}`, text: preset.label }));
  });
  select.appendChild(presetGroup);

  const countryGroup = el("optgroup", { label: "País específico" });
  Object.entries(state.groups.countries)
    .sort((a, b) => a[1].localeCompare(b[1], "es"))
    .forEach(([code, name]) => {
      countryGroup.appendChild(el("option", { value: `country:${code}`, text: name }));
    });
  select.appendChild(countryGroup);
}

/** @param {() => void} onFallbackSelect Se llama si hay que auto-elegir el primer usuario filtrado. */
export function renderUserOptions(filterText, onFallbackSelect) {
  const select = document.getElementById("user-select");
  const needle = filterText.trim().toLowerCase();
  const filtered = state.users.filter((u) => {
    if (!needle) return true;
    return u.name.toLowerCase().includes(needle) || u.country_name.toLowerCase().includes(needle);
  });

  select.innerHTML = "";
  filtered.forEach((u) => {
    select.appendChild(el("option", { value: u.id, text: `${u.name} · ${u.country_name}, ${u.age} años` }));
  });

  if (filtered.some((u) => u.id === state.selectedUserId)) {
    select.value = state.selectedUserId;
  } else if (filtered.length > 0) {
    select.value = filtered[0].id;
    state.selectedUserId = filtered[0].id;
    onFallbackSelect();
  }
}
