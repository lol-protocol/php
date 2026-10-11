// El índice de categorías es un <details>: en pantallas anchas queda siempre abierto
// a un lado; en pantallas angostas empieza cerrado para no empujar los marcalibros.
(() => {
  'use strict';
  const box = document.querySelector('.indice__caja');
  if (!box || !window.matchMedia) return;

  const wide = window.matchMedia('(min-width: 60rem)');
  const apply = () => { box.open = wide.matches; };
  apply();
  wide.addEventListener('change', apply);
  box.addEventListener('toggle', () => {
    if (wide.matches && !box.open) box.open = true;
  });
})();
