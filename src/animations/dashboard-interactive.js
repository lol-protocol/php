// Dashboard Interactivo para Animaciones
class InteractiveDashboard {
  constructor() {
    this.currentAnimation = null;
    this.initializeEventListeners();
  }

  initializeEventListeners() {
    document.addEventListener('click', (e) => {
      if (e.target.closest('.copy-code-btn')) {
        this.copyAnimationCode(e.target.closest('[data-anim-id]').dataset.animId);
      }
      if (e.target.closest('.view-details-btn')) {
        this.showDetailsModal(e.target.closest('[data-anim-id]').dataset.animId);
      }
      if (e.target.closest('.modal-close')) {
        this.closeModal();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeModal();
    });
  }

  // Copiar código de animación
  copyAnimationCode(animId) {
    const html = `
<!-- Animación ${animId.toUpperCase()} -->
<link rel="stylesheet" href="src/animations/animacion-${animId}.css">
<div class="animation-container" id="container${animId}"></div>
<button onclick="toggle.toggle()">Pausar/Reanudar</button>
<script src="src/helpers/common.js"><\/script>
<script src="src/animations/animacion-${animId}.js"><\/script>
    `.trim();

    navigator.clipboard.writeText(html).then(() => {
      const btn = document.querySelector(`[data-anim-id="${animId}"] .copy-code-btn`);
      const originalText = btn.textContent;
      btn.textContent = '✅ Copiado!';
      setTimeout(() => {
        btn.textContent = originalText;
      }, 2000);
    });
  }

  // Mostrar modal con detalles
  showDetailsModal(animId) {
    const data = this.findAnimationData(animId);
    if (!data) return;

    const difficultyColor = {
      'Fácil': '#4CAF50',
      'Medio': '#ff9800',
      'Alto': '#f44336'
    };

    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h2>${data.title}</h2>
          <button class="modal-close">&times;</button>
        </div>

        <div class="modal-body">
          <div class="modal-section">
            <h3>📝 Descripción</h3>
            <p>${data.description}</p>
          </div>

          <div class="modal-grid">
            <div class="modal-section">
              <h3>📊 Dificultad</h3>
              <div class="difficulty-badge" style="background: ${difficultyColor[data.difficulty]}">
                ${data.difficulty}
              </div>
            </div>

            <div class="modal-section">
              <h3>🏷️ Categoría</h3>
              <div class="category-badge">${data.category}</div>
            </div>
          </div>

          <div class="modal-section">
            <h3>⚙️ Técnicas Utilizadas</h3>
            <div class="techniques-list">
              ${data.techniques.map(t => `<span class="technique-tag">${t}</span>`).join('')}
            </div>
          </div>

          <div class="modal-section">
            <h3>📋 Archivo HTML</h3>
            <code class="code-block">src/animations/animacion-${animId}.html</code>
          </div>

          <div class="modal-section">
            <h3>💻 Código de Inicio Rápido</h3>
            <textarea class="code-block code-textarea" readonly>&lt;iframe src="src/animations/animacion-${animId}.html" width="100%" height="600px"&gt;&lt;/iframe&gt;</textarea>
            <button class="copy-btn" onclick="this.parentElement.querySelector('textarea').select(); document.execCommand('copy');">Copiar iframe</button>
          </div>

          <div class="modal-section">
            <h3>🔗 Links</h3>
            <div class="links-grid">
              <a href="animacion-${animId}.html" target="_blank" class="link-btn">Ver Animación ↗</a>
              <a href="animacion-${animId}.css" target="_blank" class="link-btn">Ver CSS ↗</a>
              <a href="animacion-${animId}.js" target="_blank" class="link-btn">Ver JS ↗</a>
            </div>
          </div>
        </div>
      </div>
    `;

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
