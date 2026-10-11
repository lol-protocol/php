// Muestra el enlace completo del marcalibro y, si el navegador lo permite, un botón para copiarlo.
(() => {
  'use strict';
  const input = document.getElementById('enlace');
  const button = document.getElementById('copiar');
  const status = document.getElementById('copiado');
  if (!input || !button || !status) return;

  input.value = new URL(input.dataset.path || '', window.location.origin).href;
  input.addEventListener('focus', () => input.select());

  if (navigator.clipboard && window.isSecureContext) {
    button.hidden = false;
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = 'Enlace copiado.';
      } catch (e) {
        input.select();
        status.textContent = 'No se pudo copiar solo. El enlace quedó seleccionado: cópialo con el teclado.';
      }
    });
  }
})();
