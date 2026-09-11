<section id="kpis-dashboard" class="kpis-dashboard"></section>

<main class="layout">
  <aside class="sidebar">
    <div id="alerts-panel" class="alerts-panel" hidden>
      <div class="alerts-header">
        <h3 data-i18n="alerts_title">⚠ Alertas</h3>
        <button type="button" id="btn-config-alertas" class="alerts-config-toggle" title="Configurar alertas">⚙</button>
      </div>
      <p id="alerts-summary" class="alerts-summary"></p>
      <ul id="alerts-list" class="alerts-list"></ul>

      <div id="alerts-config-panel" class="alerts-config-panel" hidden>
        <label class="alerts-config-check">
          <input type="checkbox" id="config-alerta-ip_pais">
          <span data-i18n="config_alerta_ip_pais">IP fuera del país declarado</span>
        </label>
        <label class="alerts-config-check">
          <input type="checkbox" id="config-alerta-cambio_pais">
          <span data-i18n="config_alerta_cambio_pais">Cambios de país imposibles</span>
        </label>
        <label class="alerts-config-slider">
          <span><span data-i18n="config_umbral_label">Sensibilidad</span>: <strong id="config-umbral-valor">50</strong>%</span>
          <input type="range" id="config-umbral" min="0" max="100" step="10" value="50">
        </label>
        <button type="button" id="btn-guardar-config-alertas" class="mini-button" data-i18n="config_guardar">Guardar configuración</button>
      </div>
    </div>

    <div id="user-card" class="user-card">
      <p class="placeholder" data-i18n="usercard_placeholder">Elegí un usuario arriba para ver su actividad.</p>
    </div>

    <div class="legend">
      <h3 data-i18n="legend_title">Cómo leer las guías</h3>
      <div class="legend-item"><span class="badge badge--good">▼ 20%</span> <span data-i18n="legend_good">mejor que el promedio</span></div>
      <div class="legend-item"><span class="badge badge--bad">▲ 20%</span> <span data-i18n="legend_bad">peor que el promedio</span></div>
      <div class="legend-item"><span class="badge badge--neutral" data-i18n="legend_badge_avg_abbrev">≈ prom.</span> <span data-i18n="legend_neutral">dentro del rango normal (±10%)</span></div>
      <p class="legend-note" data-i18n="legend_note_duration">Duración: más rápido = mejor. Monto: se compara en USD; más barato = mejor.</p>

      <h3 data-i18n="legend_colors_title">Colores por tipo de dato</h3>
      <div class="legend-swatches">
        <span class="legend-swatch" style="--dot-color: var(--dato-fecha)" data-i18n="legend_sw_datetime">fecha/hora</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-duracion)" data-i18n="legend_sw_duration">duración</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-dinero)" data-i18n="legend_sw_amount">monto</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-comentario)" data-i18n="legend_sw_comment">comentario</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-ruta)" data-i18n="legend_sw_path">ruta/archivo, endpoint</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-archivo)" data-i18n="legend_sw_filesize">tamaño de archivo</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-ip)" data-i18n="legend_sw_ip">IP</span>
      </div>
      <p class="legend-note" data-i18n="legend_note_ip">Una IP en rojo no coincide con el país declarado del usuario (posible VPN/proxy).</p>
    </div>
  </aside>

  <section class="timeline-panel">
    <div id="filter-summary" class="filter-summary"></div>
    <div id="status-message" class="status-message" hidden></div>
    <div id="chart-container" class="chart-panel" hidden></div>
    <ol id="timeline" class="timeline"></ol>
    <div id="pagination" class="pagination" hidden></div>
  </section>
</main>
