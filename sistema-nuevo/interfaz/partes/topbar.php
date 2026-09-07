<header class="topbar">
  <div class="topbar-title">
    <h1>Backoffice</h1>
    <span class="subtitle" data-i18n="topbar_subtitle">Actividad de usuarios</span>
  </div>

  <div class="filters">
    <div class="filter-group filter-group--narrow">
      <label for="saved-filters-select" data-i18n="label_saved_filters">Filtro guardado</label>
      <select id="saved-filters-select"></select>
      <div class="saved-filters-actions">
        <button type="button" id="btn-guardar-filtro" class="mini-button" data-i18n="btn_guardar_filtro">Guardar</button>
        <button type="button" id="btn-eliminar-filtro" class="mini-button mini-button--danger" data-i18n="btn_eliminar_filtro">Eliminar</button>
      </div>
    </div>

    <div class="filter-group">
      <label for="user-search" data-i18n="label_user">Usuario</label>
      <input type="text" id="user-search" data-i18n-placeholder="topbar_user_placeholder" placeholder="Buscar por nombre o país...">
      <select id="user-select" size="1"></select>
    </div>

    <div class="filter-group">
      <label for="scope-select" data-i18n="label_scope">Comparar contra</label>
      <select id="scope-select"></select>
    </div>

    <div class="filter-group">
      <label for="type-select" data-i18n="label_type">Tipo de acción</label>
      <select id="type-select"></select>
    </div>

    <div class="filter-group filter-group--narrow">
      <label data-i18n="label_age">Edad</label>
      <div class="range-inputs">
        <input type="number" id="age-min" min="0" max="120" value="18">
        <span>–</span>
        <input type="number" id="age-max" min="0" max="120" value="65">
      </div>
    </div>

    <div class="filter-group filter-group--narrow">
      <label for="gender-select" data-i18n="label_gender">Género</label>
      <select id="gender-select">
        <option value="all" data-i18n="gender_all">Todos</option>
        <option value="M" data-i18n="gender_m">Masculino</option>
        <option value="F" data-i18n="gender_f">Femenino</option>
        <option value="O" data-i18n="gender_o">Otro</option>
      </select>
    </div>
  </div>

  <div class="session-box">
    <div class="lang-switch">
      <button type="button" class="lang-button" data-lang="es">ES</button>
      <button type="button" class="lang-button" data-lang="en">EN</button>
    </div>
    <span id="session-username" class="session-username"></span>
    <button type="button" id="logout-button" class="logout-button" data-i18n="topbar_logout">Cerrar sesión</button>
  </div>
</header>
