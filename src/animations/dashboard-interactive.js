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
    PATHS: {
      CSS: 'src/animations/animacion-{id}.css',
      JS: 'src/animations/animacion-{id}.js',
      HTML: 'src/animations/animacion-{id}.html',
      COMMON_JS: 'src/helpers/common.js',
    },
    DIFFICULTY_COLORS: {
      'Fácil': '#4CAF50',
      'Medio': '#ff9800',
      'Alto': '#f44336',
    },
    FEEDBACK_DURATION: 2000,
  };

  constructor() {
    this.currentAnimation = null;
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

  getAnimId(element) {
    return element.closest(InteractiveDashboard.SELECTORS.CARD)?.dataset.animId;
  }

  formatPath(template, id) {
    return template.replace('{id}', id);
  }

  getAnimationHtmlSnippet(animId) {
    const paths = InteractiveDashboard.CONFIG.PATHS;
    return `
<!-- Animación ${animId.toUpperCase()} -->
<link rel="stylesheet" href="${this.formatPath(paths.CSS, animId)}">
<div class="animation-container" id="container${animId}"></div>
<button onclick="toggle.toggle()">Pausar/Reanudar</button>
<script src="${paths.COMMON_JS}"><\/script>
<script src="${this.formatPath(paths.JS, animId)}"><\/script>
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
    const paths = InteractiveDashboard.CONFIG.PATHS;
    const diffColor = InteractiveDashboard.CONFIG.DIFFICULTY_COLORS[data.difficulty];
    const iframeCode = `&lt;iframe src="${this.formatPath(paths.HTML, animId)}" width="100%" height="600px"&gt;&lt;/iframe&gt;`;

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
          ${this.createSection('📋 Archivo HTML', `<code class="code-block">${this.formatPath(paths.HTML, animId)}</code>`)}
          ${this.createSection('💻 Código de Inicio Rápido', `<textarea class="code-block code-textarea" readonly>${iframeCode}</textarea><button class="copy-btn" onclick="this.parentElement.querySelector('textarea').select();document.execCommand('copy');">Copiar iframe</button>`)}
          ${this.createSection('🔗 Links', `<div class="links-grid"><a href="animacion-${animId}.html" target="_blank" class="link-btn">Ver Animación ↗</a><a href="animacion-${animId}.css" target="_blank" class="link-btn">Ver CSS ↗</a><a href="animacion-${animId}.js" target="_blank" class="link-btn">Ver JS ↗</a></div>`)}
        </div>
      </div>
    `;
  }

  showDetailsModal(animId) {
    const data = this.findAnimationData(animId);
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

  findAnimationData(animId) {
    for (const section of Object.values(animationsData)) {
      const found = section.animations.find(a => a.id === animId);
      if (found) return found;
    }
    return null;
  }

  // Inyectar estilos del dashboard
  static injectStyles() {
    const styles = `
      /* Modal */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .modal-overlay.active {
        opacity: 1;
      }

      .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.95);
        transition: transform 0.3s ease;
      }

      .modal-overlay.active .modal-content {
        transform: scale(1);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 30px;
        border-bottom: 2px solid #f0f0f0;
      }

      .modal-header h2 {
        margin: 0;
        color: #333;
      }

      .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        transition: color 0.2s;
      }

      .modal-close:hover {
        color: #333;
      }

      .modal-body {
        padding: 30px;
      }

      .modal-section {
        margin-bottom: 25px;
      }

      .modal-section h3 {
        margin: 0 0 12px 0;
        color: #667eea;
        font-size: 1em;
      }

      .modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
      }

      .difficulty-badge,
      .category-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        color: white;
        font-weight: 600;
        font-size: 0.9em;
      }

      .category-badge {
        background: #667eea;
      }

      .techniques-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }

      .technique-tag {
        background: #f0f0f0;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85em;
        color: #555;
        border-left: 3px solid #667eea;
      }

      .code-block {
        background: #f5f5f5;
        padding: 12px 16px;
        border-radius: 8px;
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 0.85em;
        color: #333;
        border-left: 3px solid #667eea;
      }

      .code-textarea {
        width: 100%;
        min-height: 60px;
        resize: none;
        border: 1px solid #ddd;
      }

      .copy-btn {
        margin-top: 10px;
        padding: 8px 16px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9em;
        transition: background 0.2s;
      }

      .copy-btn:hover {
        background: #764ba2;
      }

      .links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
      }

      .link-btn {
        display: inline-block;
        padding: 10px 16px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        text-align: center;
        font-size: 0.9em;
        transition: background 0.2s;
      }

      .link-btn:hover {
        background: #764ba2;
      }

      /* Card Actions */
      .card-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
      }

      .action-btn {
        flex: 1;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.2s;
        font-weight: 500;
      }

      .copy-code-btn {
        background: #667eea;
        color: white;
      }

      .copy-code-btn:hover {
        background: #764ba2;
      }

      .view-details-btn {
        background: #f0f0f0;
        color: #333;
      }

      .view-details-btn:hover {
        background: #e0e0e0;
      }

      /* Tech Info */
      .tech-info {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
      }

      .tech-label {
        font-size: 0.75em;
        padding: 4px 8px;
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
        border-radius: 4px;
        font-weight: 600;
      }

      @media (max-width: 768px) {
        .modal-grid {
          grid-template-columns: 1fr;
        }

        .links-grid {
          grid-template-columns: 1fr;
        }

        .modal-content {
          max-height: 90vh;
        }
      }
    `;

    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);
  }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    InteractiveDashboard.injectStyles();
    window.dashboard = new InteractiveDashboard();
  });
} else {
  InteractiveDashboard.injectStyles();
  window.dashboard = new InteractiveDashboard();
}
