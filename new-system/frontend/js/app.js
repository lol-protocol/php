(() => {
  "use strict";

  // Backend PHP (API). Cambiar si se corre en otro host/puerto.
  const API_BASE = "http://localhost:8000";

  const ACTION_ICONS = {
    login: "🔐",
    search: "🔎",
    view_product: "👀",
    add_to_cart: "🛒",
    checkout_start: "🧾",
    payment: "💳",
    support_ticket: "🆘",
    logout: "🚪",
  };

  const GENDER_LABELS = { M: "Masculino", F: "Femenino", O: "Otro" };

  const state = {
    users: [],
    groups: null,
    selectedUserId: null,
  };

  const el = (tag, props = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(props).forEach(([key, value]) => {
      if (key === "class") {
        node.className = value;
      } else if (key === "text") {
        node.textContent = value;
      } else {
        node.setAttribute(key, value);
      }
    });
    children.forEach((child) => node.appendChild(child));
    return node;
  };

  const debounce = (fn, wait) => {
    let timer = null;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), wait);
    };
  };

  async function fetchJson(path) {
    const response = await fetch(API_BASE + path);
    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.error || `Error ${response.status} al llamar ${path}`);
    }
    return response.json();
  }

  function formatDuration(ms) {
    if (ms < 1000) return `${Math.round(ms)} ms`;
    if (ms < 60000) return `${(ms / 1000).toFixed(1)} s`;
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.round((ms % 60000) / 1000);
    return `${minutes} m ${seconds} s`;
  }

  function formatMoney(amount) {
    return new Intl.NumberFormat("es", { style: "currency", currency: "USD" }).format(amount);
  }

  function formatDateTime(iso) {
    return new Intl.DateTimeFormat("es", { dateStyle: "medium", timeStyle: "short" }).format(new Date(iso));
  }

  function formatPct(pct) {
    const sign = pct > 0 ? "+" : "";
    return `${sign}${pct.toFixed(0)}%`;
  }

  // --- Poblado de selectores ---

  function populateScopeSelect() {
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

  function renderUserOptions(filterText = "") {
    const select = document.getElementById("user-select");
    const needle = filterText.trim().toLowerCase();
    const filtered = state.users.filter((u) => {
      if (!needle) return true;
      return u.name.toLowerCase().includes(needle) || u.country_name.toLowerCase().includes(needle);
    });

    select.innerHTML = "";
    filtered.forEach((u) => {
      select.appendChild(
        el("option", {
          value: u.id,
          text: `${u.name} · ${u.country_name}, ${u.age} años`,
        })
      );
    });

    if (filtered.some((u) => u.id === state.selectedUserId)) {
      select.value = state.selectedUserId;
    } else if (filtered.length > 0) {
      select.value = filtered[0].id;
      state.selectedUserId = filtered[0].id;
      loadTimeline();
    }
  }

  // --- Render de resultados ---

  function renderUserCard(user) {
    const card = document.getElementById("user-card");
    card.innerHTML = "";
    card.appendChild(el("h2", { text: user.name }));
    card.appendChild(el("p", { class: "user-meta", text: `Usuario ${user.id}` }));

    const dl = el("dl");
    const addRow = (label, value) => {
      dl.appendChild(el("dt", { text: label }));
      dl.appendChild(el("dd", { text: value }));
    };
    addRow("País", user.country_name);
    addRow("Edad", `${user.age} años`);
    addRow("Género", GENDER_LABELS[user.gender] || user.gender);
    card.appendChild(dl);
  }

  function renderFilterSummary(filters, itemCount) {
    const box = document.getElementById("filter-summary");
    const ageLabel = `${filters.age_min}-${filters.age_max} años`;
    const genderLabel = filters.gender === "all" ? "todos los géneros" : GENDER_LABELS[filters.gender];
    box.textContent =
      `Comparando contra: ${filters.scope_label} · ${ageLabel} · ${genderLabel} ` +
      `— ${itemCount} acciones en el flujo`;
  }

  function renderStatusMessage(statsAvailable) {
    const box = document.getElementById("status-message");
    if (statsAvailable) {
      box.hidden = true;
      return;
    }
    box.hidden = false;
    box.className = "status-message status-message--warning";
    box.textContent =
      "⚠ El servicio de estadísticas (Java) no respondió. Se muestran las acciones sin comparación contra el universo elegido.";
  }

  function buildDeltaBadge(deltaPct, { betterWhenLower = true, goodLabel, badLabel }) {
    if (deltaPct === null) {
      return el("span", { class: "badge badge--neutral", text: "Sin datos de comparación" });
    }
    const magnitude = Math.abs(deltaPct);
    if (magnitude <= 10) {
      return el("span", { class: "badge badge--neutral", text: `≈ promedio (${formatPct(deltaPct)})` });
    }
    const isGood = betterWhenLower ? deltaPct < 0 : deltaPct > 0;
    const label = isGood ? goodLabel : badLabel;
    return el("span", {
      class: `badge ${isGood ? "badge--good" : "badge--bad"}`,
      text: `${formatPct(deltaPct)} ${label}`,
    });
  }

  function renderTimeline(items) {
    const list = document.getElementById("timeline");
    list.innerHTML = "";

    if (items.length === 0) {
      list.appendChild(el("li", { class: "empty-state", text: "Este usuario no tiene acciones registradas." }));
      return;
    }

    items.forEach((item) => {
      const marker = el("div", { class: "marker" }, [
        el("span", { class: "marker-icon", text: ACTION_ICONS[item.type] || "•" }),
        el("span", { class: "marker-line" }),
      ]);

      const header = el("div", { class: "card-header" }, [
        el("span", { class: "card-title", text: item.label }),
        el("span", { class: "card-time", text: formatDateTime(item.timestamp) }),
      ]);

      const metrics = el("div", { class: "card-metrics" }, [
        el("span", { class: "metric", text: `⏱ ${formatDuration(item.duration_ms)}` }),
        ...(item.amount_usd !== null ? [el("span", { class: "metric", text: `💰 ${formatMoney(item.amount_usd)}` })] : []),
        ...(item.cohort ? [el("span", { class: "metric", text: `(vs. ${item.cohort.count} acciones del universo elegido)` })] : []),
      ]);

      const badges = el("div", { class: "badges" }, [
        buildDeltaBadge(item.duration_delta_pct, { betterWhenLower: true, goodLabel: "más rápido", badLabel: "más lento" }),
        ...(item.amount_usd !== null
          ? [buildDeltaBadge(item.amount_delta_pct, { betterWhenLower: true, goodLabel: "más barato", badLabel: "más caro" })]
          : []),
      ]);

      const card = el("div", { class: "card" }, [header, metrics, badges]);
      list.appendChild(el("li", { class: "timeline-item" }, [marker, card]));
    });
  }

  // --- Carga principal ---

  async function loadTimeline() {
    if (!state.selectedUserId) return;

    const scope = document.getElementById("scope-select").value;
    const ageMin = document.getElementById("age-min").value || 0;
    const ageMax = document.getElementById("age-max").value || 150;
    const gender = document.getElementById("gender-select").value;

    const query = new URLSearchParams({
      user_id: state.selectedUserId,
      scope,
      age_min: ageMin,
      age_max: ageMax,
      gender,
    });

    try {
      const data = await fetchJson(`/api/timeline?${query.toString()}`);
      renderUserCard(data.user);
      renderFilterSummary(data.filters, data.timeline.length);
      renderStatusMessage(data.stats_service_available);
      renderTimeline(data.timeline);
    } catch (err) {
      const box = document.getElementById("status-message");
      box.hidden = false;
      box.className = "status-message status-message--warning";
      box.textContent = `⚠ ${err.message}`;
    }
  }

  async function init() {
    try {
      const [groups, users] = await Promise.all([fetchJson("/api/groups"), fetchJson("/api/users")]);
      state.groups = groups;
      state.users = users;

      populateScopeSelect();
      renderUserOptions();

      if (state.users.length > 0) {
        state.selectedUserId = document.getElementById("user-select").value || state.users[0].id;
        await loadTimeline();
      }
    } catch (err) {
      const box = document.getElementById("status-message");
      box.hidden = false;
      box.className = "status-message status-message--warning";
      box.textContent = `⚠ No se pudo conectar con el backend PHP en ${API_BASE}. (${err.message})`;
    }
  }

  document.getElementById("user-search").addEventListener("input", (e) => renderUserOptions(e.target.value));
  document.getElementById("user-select").addEventListener("change", (e) => {
    state.selectedUserId = e.target.value;
    loadTimeline();
  });
  document.getElementById("scope-select").addEventListener("change", loadTimeline);
  document.getElementById("gender-select").addEventListener("change", loadTimeline);
  document.getElementById("age-min").addEventListener("input", debounce(loadTimeline, 400));
  document.getElementById("age-max").addEventListener("input", debounce(loadTimeline, 400));

  init();
})();
