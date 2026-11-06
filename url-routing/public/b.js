/*
 * Búsqueda y listados (/b/…): al llegar al final de la lista carga la página siguiente y la agrega, sin salir de
 * esta. Pide la MISMA URL del enlace «Siguiente →» con la cabecera X-Fragmento: 1, y el servidor contesta solo la
 * lista y la navegación (ver docs/URL_STRUCTURES.md). Es una mejora: sin JavaScript, o en un navegador sin
 * IntersectionObserver, queda el enlace «Siguiente →» y las páginas /b/…/N/ de siempre, que es también lo que
 * ve un buscador.
 */
(function () {
    'use strict';

    var nav = document.querySelector('nav.acciones[aria-label="Páginas"]');
    var lista = document.querySelector('main ul.lista');
    if (!nav || !lista || !('IntersectionObserver' in window) || !window.fetch) {
        return;
    }

    var cargando = false;
    // Lo que el lector ve al llegar al final de la lista: se observa esto, no el enlace.
    var centinela = document.createElement('div');
    centinela.setAttribute('aria-hidden', 'true');
    nav.parentNode.insertBefore(centinela, nav);

    function siguiente() {
        return nav.querySelector('a[rel="next"]');
    }

    function terminar(enlace) {
        // Sin más páginas, o con un fallo: el enlace vuelve a verse y el lector decide.
        observador.disconnect();
        if (enlace) {
            enlace.hidden = false;
        }
    }

    function cargarMas() {
        var enlace = siguiente();
        if (cargando || !enlace) {
            return;
        }
        cargando = true;
        lista.setAttribute('aria-busy', 'true');
        // Mientras el script se encarga, el enlace sobra; si falla, se muestra de nuevo.
        enlace.hidden = true;

        fetch(enlace.getAttribute('href'), {
            headers: { 'X-Fragmento': '1' },
            credentials: 'same-origin'
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }
            return respuesta.text();
        }).then(function (html) {
            var plantilla = document.createElement('template');
            plantilla.innerHTML = html;
            var nuevos = plantilla.content.querySelectorAll('ul.lista > li');
            for (var i = 0; i < nuevos.length; i++) {
                lista.appendChild(document.importNode(nuevos[i], true));
            }

            // El «Siguiente →» de la página recién traída reemplaza al que usamos; «← Anterior» se queda como estaba.
            var proximo = plantilla.content.querySelector('nav.acciones a[rel="next"]');
            enlace.remove();
            if (proximo && nuevos.length > 0) {
                var nuevoEnlace = document.importNode(proximo, true);
                nuevoEnlace.hidden = true;
                nav.appendChild(nuevoEnlace);
                cargando = false;
                lista.removeAttribute('aria-busy');
                // Si el final de la lista sigue a la vista, el observador no vuelve a avisar solo.
                observador.unobserve(centinela);
                observador.observe(centinela);
            } else {
                lista.removeAttribute('aria-busy');
                observador.disconnect();
            }
        }).catch(function () {
            cargando = false;
            lista.removeAttribute('aria-busy');
            terminar(enlace);
        });
    }

    var observador = new IntersectionObserver(function (entradas) {
        for (var i = 0; i < entradas.length; i++) {
            if (entradas[i].isIntersecting) {
                cargarMas();
            }
        }
    }, { rootMargin: '400px' });
    observador.observe(centinela);
}());
