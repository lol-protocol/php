/*
 * Ajustes de comodidad del visor: si el astro gira, a qué velocidad, y cómo se guardan.
 * Funciones puras (sin DOM): corren en el navegador y en Node, y se prueban en pruebas/ajustes.test.js.
 *
 * Por defecto el astro está quieto: el movimiento continuo cansa a quien lo mira. Quien quiera,
 * lo activa con «Girar» y elige la velocidad; el visor recuerda la elección en ese navegador.
 */
(function (raiz) {
  'use strict';

  const VELOCIDAD_MINIMA = 0.05;   // radianes por segundo: una vuelta cada 2 min
  const VELOCIDAD_MAXIMA = 0.6;    // una vuelta cada 10 s
  const VELOCIDAD_POR_DEFECTO = 0.12;  // una vuelta cada 52 s

  // Sujeta la velocidad al rango permitido; un valor que no es número da el predeterminado.
  function limitarVelocidad(valor) {
    const n = Number(valor);
    if (valor === null || valor === undefined || valor === '' || !Number.isFinite(n)) return VELOCIDAD_POR_DEFECTO;
    return Math.min(VELOCIDAD_MAXIMA, Math.max(VELOCIDAD_MINIMA, n));
  }

  // Estado inicial a partir de lo guardado y de la preferencia del sistema de reducir movimiento.
  // Con «reducir movimiento» el astro empieza quieto aunque antes se hubiera dejado girando.
  function ajustesIniciales(guardado, reducirMovimiento) {
    const g = guardado && typeof guardado === 'object' ? guardado : {};
    return {
      girando: !reducirMovimiento && g.girando === true,
      velocidad: limitarVelocidad(g.velocidad)
    };
  }

  // Segundos que tarda una vuelta completa a esa velocidad, redondeado.
  function segundosPorVuelta(velocidad) {
    return Math.round((2 * Math.PI) / limitarVelocidad(velocidad));
  }

  const API = {
    VELOCIDAD_MINIMA: VELOCIDAD_MINIMA,
    VELOCIDAD_MAXIMA: VELOCIDAD_MAXIMA,
    VELOCIDAD_POR_DEFECTO: VELOCIDAD_POR_DEFECTO,
    limitarVelocidad: limitarVelocidad,
    ajustesIniciales: ajustesIniciales,
    segundosPorVuelta: segundosPorVuelta
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.AstrosAjustes = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
