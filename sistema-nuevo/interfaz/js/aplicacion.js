import { state, debounce } from "./nucleo.js";
import { fetchJson, postJson, showLogin, showApp, boot } from "./sesion.js";
import { populateScopeSelect, renderUserOptions } from "./selectores.js";
import { renderUserCard, renderFilterSummary, renderStatusMessage } from "./tarjeta-usuario.js";
import { renderTimeline } from "./linea-tiempo.js";

async function loadTimeline() {
  if (!state.selectedUserId) return;

  const scope = document.getElementById("scope-select").value;
  const ageMin = document.getElementById("age-min").value || 0;
  const ageMax = document.getElementById("age-max").value || 150;
  const gender = document.getElementById("gender-select").value;

  const query = new URLSearchParams({ user_id: state.selectedUserId, scope, age_min: ageMin, age_max: ageMax, gender });

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

async function loadAppData() {
  try {
    const [groups, users] = await Promise.all([fetchJson("/api/groups"), fetchJson("/api/users")]);
    state.groups = groups;
    state.users = users;

    populateScopeSelect();
    renderUserOptions("", loadTimeline);

    if (state.users.length > 0) {
      state.selectedUserId = document.getElementById("user-select").value || state.users[0].id;
      await loadTimeline();
    }
  } catch (err) {
    const box = document.getElementById("status-message");
    box.hidden = false;
    box.className = "status-message status-message--warning";
    box.textContent = `⚠ ${err.message}`;
  }
}

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

document.getElementById("user-search").addEventListener("input", (e) => renderUserOptions(e.target.value, loadTimeline));
document.getElementById("user-select").addEventListener("change", (e) => {
  state.selectedUserId = e.target.value;
  loadTimeline();
});
document.getElementById("scope-select").addEventListener("change", loadTimeline);
document.getElementById("gender-select").addEventListener("change", loadTimeline);
document.getElementById("age-min").addEventListener("input", debounce(loadTimeline, 400));
document.getElementById("age-max").addEventListener("input", debounce(loadTimeline, 400));

boot(loadAppData);
