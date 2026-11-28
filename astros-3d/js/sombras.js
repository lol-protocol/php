/*
 * Geometría de las sombras de los anillos. Funciones puras (sin DOM): corren en el navegador y en Node,
 * y se prueban en pruebas/sombras.test.js. El shader del visor (js/visor.js) implementa la misma
 * matemática en GLSL; aquí está la versión de referencia, con casos que se pueden comprobar a mano.
 *
 * Escena: el planeta es una esfera de radio 1 en el origen; los anillos están en un plano que pasa por
 * el origen, con normal `eje`. Un punto P (en la superficie del planeta o en un anillo) está a la sombra
 * de algo si el rayo que sale de P hacia la luz (dirección unitaria L) choca con ese algo.
 */
(function (raiz) {
  'use strict';

  const GRADOS = Math.PI / 180;

  function punto(a, b) {
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
  }

  function longitud(a) {
    return Math.sqrt(punto(a, a));
  }

  // Normal del plano de los anillos tras inclinar el eje del planeta (rotación sobre X): (0, cos, sin).
  function ejeDeAnillos(inclinacionGrados) {
    const t = (inclinacionGrados || 0) * GRADOS;
    return [0, Math.cos(t), Math.sin(t)];
  }

  // Dónde cruza el rayo P + t·L el plano de los anillos, con t > 0 (hacia la luz).
  // Devuelve { t, r } con r la distancia al centro del planeta (en radios del planeta), o null si el
  // rayo es paralelo al plano o el cruce queda detrás de P.
  function cruceConElPlanoDeAnillos(P, L, eje) {
    const lateral = punto(eje, L);
    if (Math.abs(lateral) < 1e-6) return null;
    const t = -punto(eje, P) / lateral;
    if (t <= 0) return null;
    const Q = [P[0] + t * L[0], P[1] + t * L[1], P[2] + t * L[2]];
    return { t: t, r: longitud(Q) };
  }

  // ¿El punto P está a la sombra de los anillos? Solo si el rayo hacia la luz los cruza entre sus radios.
  function enSombraDeAnillos(P, L, eje, interior, exterior) {
    const cruce = cruceConElPlanoDeAnillos(P, L, eje);
    return cruce !== null && cruce.r >= interior && cruce.r <= exterior;
  }

  // Distancia mínima del rayo P + t·L (t > 0) al centro del planeta. Si el rayo se aleja del planeta
  // desde el principio, es la distancia de P al centro. Menor que 1 = el rayo choca con el planeta.
  function distanciaDelRayoAlCentro(P, L) {
    const tc = -punto(P, L);
    if (tc <= 0) return longitud(P);
    return longitud([P[0] + tc * L[0], P[1] + tc * L[1], P[2] + tc * L[2]]);
  }

  // ¿El punto P (de un anillo) está a la sombra del planeta?
  function enSombraDelPlaneta(P, L) {
    return distanciaDelRayoAlCentro(P, L) < 1;
  }

  const API = {
    ejeDeAnillos: ejeDeAnillos,
    cruceConElPlanoDeAnillos: cruceConElPlanoDeAnillos,
    enSombraDeAnillos: enSombraDeAnillos,
    distanciaDelRayoAlCentro: distanciaDelRayoAlCentro,
    enSombraDelPlaneta: enSombraDelPlaneta
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
  } else {
    raiz.AstrosSombras = API;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this);
