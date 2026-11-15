// Dashboard Interactivo para Animaciones
class InteractiveDashboard {
  static SELECTORS = {
    CARD: '[data-anim-id]',
    COPY_BTN: '.copy-code-btn',
    DETAILS_BTN: '.view-details-btn',
    MODAL_CLOSE: '.modal-close',
    MODAL_OVERLAY: '.modal-overlay',
  };

  static CONFIG = {
    DIFFICULTY_COLORS: {
      'Fácil': '#4CAF50',
      'Medio': '#ff9800',
      'Alto': '#f44336',
    },
    FEEDBACK_DURATION: 2000,
  };

  constructor() {
    this.opener = null; // el botón que abrió el modal: el foco vuelve a él al cerrarlo
    this.initializeEventListeners();
  }

  initializeEventListeners() {
    document.addEventListener('click', (e) => {
      // El modal cuelga de <body>, fuera de las tarjetas: se cierra con la × o con un clic en el fondo
      // antes de buscar la tarjeta (con ella por delante, ni la × ni el fondo cerraban nada).
      if (e.target.closest(InteractiveDashboard.SELECTORS.MODAL_CLOSE) ||
          e.target.matches(InteractiveDashboard.SELECTORS.MODAL_OVERLAY)) {
        this.closeModal();
        return;
      }

      const card = e.target.closest(InteractiveDashboard.SELECTORS.CARD);
      if (!card) return;

      const animId = card.dataset.animId;
      if (e.target.closest(InteractiveDashboard.SELECTORS.COPY_BTN)) {
        this.copyAnimationCode(animId);
      } else if (e.target.closest(InteractiveDashboard.SELECTORS.DETAILS_BTN)) {
        this.showDetailsModal(animId);
      }
    });

    // El botón «Copiar iframe» está dentro del modal, fuera de las tarjetas.
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.copy-iframe-btn');
      if (btn) this.copyText(btn.parentElement.querySelector('textarea').value, btn);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeModal();
      else if (e.key === 'Tab') this.keepFocusInModal(e);
    });
  }

  // Con el modal abierto, Tab y Mayús+Tab dan la vuelta dentro de él en vez de salir a la página de atrás.
  keepFocusInModal(e) {
    const modal = document.querySelector('.modal-overlay');
    if (!modal) return;
    const focusables = [...modal.querySelectorAll('button, a[href], textarea, [tabindex]:not([tabindex="-1"])')];
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (!modal.contains(document.activeElement)) {
      e.preventDefault();
      first.focus();
    } else if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  // Copia al portapapeles con navigator.clipboard y, si la página no es segura o lo rechaza, con un campo
  // oculto y execCommand. Devuelve si se pudo copiar.
  async writeClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (err) {
      const field = document.createElement('textarea');
      field.value = text;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.appendChild(field);
      field.select();
      let copied = false;
      try { copied = document.execCommand('copy'); } catch (failure) { /* no se copió */ }
      field.remove();
      return copied;
    }
  }

  async copyText(text, btn) {
    this.showButtonFeedback(btn, await this.writeClipboard(text) ? '✅ Copiado!' : '❌ Error');
  }

  getAnimationHtmlSnippet(animId) {
    return `
<!-- Animación ${animId.toUpperCase()} -->
<link rel="stylesheet" href="src/animations/animacion-${animId}.css">
<div class="animation-container" id="container${animId}"></div>
<button onclick="toggle.toggle()">Pausar/Reanudar</button>
<script src="src/helpers/common.js"><\/script>
<script src="src/animations/animacion-${animId}.js"><\/script>
    `.trim();
  }

  async copyAnimationCode(animId) {
    const btn = document.querySelector(`[data-anim-id="${animId}"] ${InteractiveDashboard.SELECTORS.COPY_BTN}`);
    await this.copyText(this.getAnimationHtmlSnippet(animId), btn);
  }

  showButtonFeedback(btn, message) {
    if (btn.dataset.originalText === undefined) btn.dataset.originalText = btn.textContent;
    btn.textContent = message;
    clearTimeout(btn.feedbackTimer);
    btn.feedbackTimer = setTimeout(() => {
      btn.textContent = btn.dataset.originalText;
      delete btn.dataset.originalText;
    }, InteractiveDashboard.CONFIG.FEEDBACK_DURATION);
  }

  createSection(title, content) {
    return `<div class="modal-section"><h3>${title}</h3>${content}</div>`;
  }

  buildModalHtml(data, animId) {
    const htmlPath = `src/animations/animacion-${animId}.html`;
    const diffColor = InteractiveDashboard.CONFIG.DIFFICULTY_COLORS[data.difficulty];
    const iframeCode = `&lt;iframe src="${htmlPath}" width="100%" height="600px"&gt;&lt;/iframe&gt;`;

    return `
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
          <h2 id="modal-title">${data.title}</h2>
          <button type="button" class="modal-close" aria-label="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
          ${this.createSection('📝 Descripción', `<p>${data.description}</p>`)}
          <div class="modal-grid">
            ${this.createSection('📊 Dificultad', `<div class="difficulty-badge" style="background:${diffColor}">${data.difficulty}</div>`)}
            ${this.createSection('🏷️ Categoría', `<div class="category-badge">${data.category}</div>`)}
          </div>
          ${this.createSection('⚙️ Técnicas Utilizadas', `<div class="techniques-list">${data.techniques.map(t => `<span class="technique-tag">${t}</span>`).join('')}</div>`)}
          ${this.createSection('📋 Archivo HTML', `<code class="code-block">${htmlPath}</code>`)}
          ${this.createSection('💻 Código de Inicio Rápido', `<textarea class="code-block code-textarea" readonly>${iframeCode}</textarea><button type="button" class="copy-btn copy-iframe-btn">Copiar iframe</button>`)}
          ${this.createSection('🔗 Links', `<div class="links-grid"><a href="animacion-${animId}.html" target="_blank" class="link-btn">Ver Animación ↗</a><a href="animacion-${animId}.css" target="_blank" class="link-btn">Ver CSS ↗</a><a href="animacion-${animId}.js" target="_blank" class="link-btn">Ver JS ↗</a></div>`)}
        </div>
      </div>
    `;
  }

  showDetailsModal(animId) {
    const data = getAnimationById(animId);
    if (!data) return;

    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = this.buildModalHtml(data, animId);

    this.opener = document.activeElement;
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('active'), 10);
    modal.querySelector(InteractiveDashboard.SELECTORS.MODAL_CLOSE).focus();
  }

  closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
      modal.classList.remove('active');
      setTimeout(() => modal.remove(), 300);
      // El foco vuelve al botón que abrió el modal (si sigue en la página).
      if (this.opener?.isConnected) this.opener.focus();
      this.opener = null;
    }
  }
}

// Estilos del modal (.modal-*, .code-block, .link-btn, etc.) viven en el
// <style> de index.html, junto al resto del CSS de la galería.
window.dashboard = new InteractiveDashboard();
