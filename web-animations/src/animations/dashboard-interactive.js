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
    this.initializeEventListeners();
  }

  initializeEventListeners() {
    document.addEventListener('click', (e) => {
      const card = e.target.closest(InteractiveDashboard.SELECTORS.CARD);
      if (!card) return;

      const animId = card.dataset.animId;
      if (e.target.closest(InteractiveDashboard.SELECTORS.COPY_BTN)) {
        this.copyAnimationCode(animId);
      } else if (e.target.closest(InteractiveDashboard.SELECTORS.DETAILS_BTN)) {
        this.showDetailsModal(animId);
      } else if (e.target.closest(InteractiveDashboard.SELECTORS.MODAL_CLOSE)) {
        this.closeModal();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeModal();
    });
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
    const html = this.getAnimationHtmlSnippet(animId);
    const btn = document.querySelector(`[data-anim-id="${animId}"] ${InteractiveDashboard.SELECTORS.COPY_BTN}`);

    try {
      await navigator.clipboard.writeText(html);
      this.showButtonFeedback(btn, '✅ Copiado!');
    } catch (err) {
      console.error('Error al copiar:', err);
      this.showButtonFeedback(btn, '❌ Error');
    }
  }

  showButtonFeedback(btn, message) {
    const originalText = btn.textContent;
    btn.textContent = message;
    setTimeout(() => {
      btn.textContent = originalText;
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
      <div class="modal-content">
        <div class="modal-header">
          <h2>${data.title}</h2>
          <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
          ${this.createSection('📝 Descripción', `<p>${data.description}</p>`)}
          <div class="modal-grid">
            ${this.createSection('📊 Dificultad', `<div class="difficulty-badge" style="background:${diffColor}">${data.difficulty}</div>`)}
            ${this.createSection('🏷️ Categoría', `<div class="category-badge">${data.category}</div>`)}
          </div>
          ${this.createSection('⚙️ Técnicas Utilizadas', `<div class="techniques-list">${data.techniques.map(t => `<span class="technique-tag">${t}</span>`).join('')}</div>`)}
          ${this.createSection('📋 Archivo HTML', `<code class="code-block">${htmlPath}</code>`)}
          ${this.createSection('💻 Código de Inicio Rápido', `<textarea class="code-block code-textarea" readonly>${iframeCode}</textarea><button class="copy-btn" onclick="this.parentElement.querySelector('textarea').select();document.execCommand('copy');">Copiar iframe</button>`)}
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

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('active'), 10);
  }

  closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
      modal.classList.remove('active');
      setTimeout(() => modal.remove(), 300);
    }
  }
}

// Estilos del modal (.modal-*, .code-block, .link-btn, etc.) viven en el
// <style> de index.html, junto al resto del CSS de la galería.
window.dashboard = new InteractiveDashboard();
