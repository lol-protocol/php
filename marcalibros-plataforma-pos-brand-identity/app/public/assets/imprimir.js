// El botón "Imprimir" solo aparece con JavaScript: sin él, se imprime desde el menú del navegador.
(() => {
  'use strict';
  const button = document.getElementById('imprimir');
  if (!button || typeof window.print !== 'function') return;
  button.hidden = false;
  button.addEventListener('click', () => window.print());
})();
