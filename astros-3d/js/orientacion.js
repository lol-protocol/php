/*
 * Orientación de cada astro en el visor: con qué signo gira alrededor de su eje.
 * Funciones puras (sin DOM): corren en el navegador y en Node, y se prueban en pruebas/orientacion.test.js.
 *
 * El catálogo trae dos datos que, en Venus, Urano y Plutón, dicen lo mismo: la inclinación del eje
 * pasa de 90° y el periodo de rotación es negativo (giro retrógrado). El visor inclina el astro con
 * esa inclinación y luego lo gira sobre su eje; si además invirtiera el sentido por el periodo
 * negativo, contaría el giro retrógrado dos veces y esos tres astros girarían al revés.
 */
(function (raiz) {
  'use strict';

  const GRADOS = Math.PI / 180;

  // Signo del giro sobre el eje del propio astro: -1 solo si el periodo es negativo y la
  // inclinación no pasa de 90° (los astros sin inclinación, como Tritón y Caronte, quedan aquí).
  function sentidoDeGiro(periodoRotacionH, inclinacionGrados) {
    const retrogrado = periodoRotacionH < 0;
    const ejeDadoVuelta = (inclinacionGrados || 0) > 90;
    return retrogrado && !ejeDadoVuelta ? -1 : 1;
  }

  // Componente vertical de la velocidad angular tal como se ve en la escena: positiva = giro
  // directo (antihorario visto desde el norte), negativa = retrógrado.
  function giroVertical(periodoRotacionH, inclinacionGrados) {
    return sentidoDeGiro(periodoRotacionH, inclinacionGrados) * Math.cos((inclinacionGrados || 0) * GRADOS);
  }

  const API = {
    sentidoDeGiro: sentidoDeGiro,
    giroVertical: giroVertical
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.AstrosOrientacion = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
