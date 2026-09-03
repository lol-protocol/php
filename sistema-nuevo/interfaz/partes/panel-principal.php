<main class="layout">
  <aside class="sidebar">
    <div id="user-card" class="user-card">
      <p class="placeholder">Elegí un usuario arriba para ver su actividad.</p>
    </div>

    <div class="legend">
      <h3>Cómo leer las guías</h3>
      <div class="legend-item"><span class="badge badge--good">▼ 20%</span> mejor que el promedio</div>
      <div class="legend-item"><span class="badge badge--bad">▲ 20%</span> peor que el promedio</div>
      <div class="legend-item"><span class="badge badge--neutral">≈ prom.</span> dentro del rango normal (±10%)</div>
      <p class="legend-note">Duración: más rápido = mejor. Monto: se compara en USD; más barato = mejor.</p>

      <h3>Colores por tipo de dato</h3>
      <div class="legend-swatches">
        <span class="legend-swatch" style="--dot-color: var(--dato-fecha)">fecha/hora</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-duracion)">duración</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-dinero)">monto</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-comentario)">comentario</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-ruta)">ruta/archivo, endpoint</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-archivo)">tamaño de archivo</span>
        <span class="legend-swatch" style="--dot-color: var(--dato-ip)">IP</span>
      </div>
      <p class="legend-note">Una IP en rojo no coincide con el país declarado del usuario (posible VPN/proxy).</p>
    </div>
  </aside>

  <section class="timeline-panel">
    <div id="filter-summary" class="filter-summary"></div>
    <div id="status-message" class="status-message" hidden></div>
    <ol id="timeline" class="timeline"></ol>
  </section>
</main>
