/*
 * citas-pos: «Adivina la categoría». Fuente a mano de salida/citas-pos-juego.js. Sin dependencias.
 * Se carga con `defer`, junto con citas-pos.css y citas-pos-juego.css.
 *
 * Convierte cada palabra etiquetada (.jw) en un botón que se pulsa para descubrir su categoría y su color, y va
 * llenando la leyenda con las categorías descubiertas.
 * Sin este script, la frase queda completa y explicada: no hace falta nada para leerla.
 */
(function () {
  'use strict';

  function iniciar(juego) {
    if (juego.getAttribute('data-iniciado')) {
      return;
    }
    var palabras = Array.prototype.slice.call(juego.querySelectorAll('.jw'));
    var leyenda = Array.prototype.slice.call(juego.querySelectorAll('.jleyenda li'));
    var lista = juego.querySelector('.jleyenda');
    var frase = juego.querySelector('blockquote');
    var ayuda = juego.querySelector('.jayuda');
    var barra = juego.querySelector('.jbar');
    var estado = juego.querySelector('.jestado');
    var total = palabras.length;
    if (total === 0 || !barra || !estado) {
      return;
    }
    juego.setAttribute('data-iniciado', 'true');

    function revelada(palabra) {
      return palabra.getAttribute('aria-pressed') === 'true';
    }

    function avisar(mensaje) {
      var n = palabras.filter(revelada).length;
      var resumen = n === total ? '¡Descubiertas todas las palabras!' : 'Descubiertas ' + n + ' de ' + total + '.';
      estado.textContent = mensaje ? mensaje + ' ' + resumen : resumen;
    }

    // Una lista vacía se anuncia como «lista» sin nada dentro: se oculta entera hasta que haya algo que decir.
    function actualizarLeyenda() {
      var alguna = false;
      leyenda.forEach(function (item) {
        var categoria = item.getAttribute('data-c');
        item.hidden = !palabras.some(function (p) { return revelada(p) && p.getAttribute('data-c') === categoria; });
        alguna = alguna || !item.hidden;
      });
      if (lista) {
        lista.hidden = !alguna;
      }
    }

    // La categoría entra en el nombre accesible de la palabra solo cuando se ha descubierto.
    function poner(palabra, valor) {
      palabra.setAttribute('aria-pressed', valor ? 'true' : 'false');
      if (valor) {
        palabra.setAttribute('aria-label', palabra.textContent + ', ' + palabra.getAttribute('data-nombre'));
      } else {
        palabra.removeAttribute('aria-label');
      }
    }

    function alternar(palabra) {
      var ahora = !revelada(palabra);
      poner(palabra, ahora);
      actualizarLeyenda();
      avisar(ahora ? palabra.textContent + ': ' + palabra.getAttribute('data-nombre') + '.' : '');
    }

    palabras.forEach(function (palabra) {
      palabra.setAttribute('role', 'button');
      palabra.setAttribute('tabindex', '0');
      poner(palabra, false);
      palabra.addEventListener('click', function () { alternar(palabra); });
      palabra.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter' || evento.key === ' ') {
          evento.preventDefault();
          alternar(palabra);
        }
      });
    });

    barra.addEventListener('click', function (evento) {
      var accion = evento.target && evento.target.getAttribute ? evento.target.getAttribute('data-accion') : null;
      if (accion === 'todas') {
        palabras.forEach(function (p) { poner(p, true); });
        actualizarLeyenda();
        avisar('');
      } else if (accion === 'ocultar') {
        palabras.forEach(function (p) { poner(p, false); });
        actualizarLeyenda();
        avisar('Todas ocultas.');
      }
    });

    // Con JavaScript cada palabra es un botón: la frase entera, para quien la oye con un lector de pantalla, va aparte.
    if (frase) {
      var lectura = document.createElement('p');
      lectura.className = 'jsr';
      lectura.textContent = frase.textContent;
      frase.parentNode.insertBefore(lectura, frase);
    }

    juego.classList.add('js');
    if (ayuda) {
      ayuda.hidden = false;
    }
    barra.hidden = false;
    actualizarLeyenda();
    avisar('');
  }

  function iniciarTodos() {
    Array.prototype.forEach.call(document.querySelectorAll('.citas-pos-juego'), iniciar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
  } else {
    iniciarTodos();
  }
}());
