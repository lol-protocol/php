<header class="topbar">
  <div class="topbar-title">
    <h1>Backoffice</h1>
    <span class="subtitle">Actividad de usuarios</span>
  </div>

  <div class="filters">
    <div class="filter-group">
      <label for="user-search">Usuario</label>
      <input type="text" id="user-search" placeholder="Buscar por nombre o país...">
      <select id="user-select" size="1"></select>
    </div>

    <div class="filter-group">
      <label for="scope-select">Comparar contra</label>
      <select id="scope-select"></select>
    </div>

    <div class="filter-group filter-group--narrow">
      <label>Edad</label>
      <div class="range-inputs">
        <input type="number" id="age-min" min="0" max="120" value="18">
        <span>–</span>
        <input type="number" id="age-max" min="0" max="120" value="65">
      </div>
    </div>

    <div class="filter-group filter-group--narrow">
      <label for="gender-select">Género</label>
      <select id="gender-select">
        <option value="all">Todos</option>
        <option value="M">Masculino</option>
        <option value="F">Femenino</option>
        <option value="O">Otro</option>
      </select>
    </div>
  </div>

  <div class="session-box">
    <span id="session-username" class="session-username"></span>
    <button type="button" id="logout-button" class="logout-button">Cerrar sesión</button>
  </div>
</header>
