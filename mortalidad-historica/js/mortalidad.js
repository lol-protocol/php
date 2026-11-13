/*
 * Port a JavaScript de consultas.py: las mismas consultas sobre los datos de mortalidad-historica, para la
 * vista web (vista.html) y para quien las quiera en el navegador o en Node. Sin dependencias.
 *
 * Devuelve las mismas respuestas que Python: lo comprueba pruebas/mortalidad.test.js con datos/vectores.json,
 * que genera generar_tabla.py con consultas.py. Ninguna función recibe una persona, una ficha ni un origen.
 *
 * Los datos entran como tablas en columnas: { nombre: { columnas: [...], filas: [[...], ...] } }, que es como
 * generar_tabla.py las incrusta en la página. `crear(tablas)` devuelve un objeto con las consultas.
 */
(function (raiz, fabrica) {
  if (typeof module === 'object' && module.exports) {
    module.exports = fabrica();
  } else {
    raiz.Mortalidad = fabrica();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var IDIOMAS = ['spa', 'eng', 'por', 'fra', 'ita', 'deu', 'lat', 'nld', 'swe', 'dan',
    'pol', 'rus', 'ukr', 'ces', 'ell', 'tur', 'ara', 'heb', 'zho', 'jpn'];
  var REGIONES = ['África', 'América', 'Asia', 'Europa', 'Oceanía'];
  var SEXOS = ['M', 'F', 'T'];
  var ORDEN_CONFIANZA = { alta: 0, media: 1, baja: 2 };

  /*
   * Forma para comparar términos: sin tildes ni diacríticos, en minúsculas y con espacios simples; la misma que
   * `normalizar` de comun.py. JavaScript no tiene el plegado completo de Unicode: aquí se cubren las diferencias
   * que importan a los datos (ß → ss, la sigma final ς → σ).
   */
  function normalizar(texto) {
    var s = texto.toLowerCase().replace(/ß/g, 'ss').replace(/ς/g, 'σ');
    s = s.normalize('NFD').replace(/\p{Mn}/gu, '').normalize('NFC');
    return s.split(/\s+/u).filter(Boolean).join(' ');
  }

  function objetos(tabla) {
    return tabla.filas.map(function (fila) {
      var o = {};
      tabla.columnas.forEach(function (c, i) { o[c] = fila[i]; });
      return o;
    });
  }

  function lanzar(mensaje) {
    throw new RangeError(mensaje);
  }

  function crear(tablas) {
    var modernas = null;
    var historicas = null;
    var paises = null;
    var epidemias = null;
    var hitos = null;
    var periodos = null;
    var londres = null;

    function perezoso(nombre, cache) { return cache || objetos(tablas[nombre]); }

    function causaModerna(termino, idioma) {
      if (IDIOMAS.indexOf(idioma) < 0) lanzar('idioma «' + idioma + '»: debe ser un código ISO 639-3');
      modernas = perezoso('causas_modernas', modernas);
      historicas = perezoso('causas_historicas', historicas);
      var porId = {};
      modernas.forEach(function (c) { porId[c.id] = c; });
      var clave = normalizar(termino);
      var encontradas = historicas
        .filter(function (f) { return f.idioma === idioma && normalizar(f.termino) === clave; })
        .map(function (f) {
          var m = porId[f.causa_id];
          return { causa_id: f.causa_id, nombre: m.nombre_spa, cie10: m.cie10.split(';'), confianza: f.confianza, nota: f.nota };
        });
      return encontradas.sort(function (a, b) {
        return ORDEN_CONFIANZA[a.confianza] - ORDEN_CONFIANZA[b.confianza] ||
          (a.nombre < b.nombre ? -1 : a.nombre > b.nombre ? 1 : 0);
      });
    }

    function regionDe(pais) {
      paises = perezoso('paises', paises);
      for (var i = 0; i < paises.length; i++) {
        if (paises[i].codigo === pais) return paises[i].region;
      }
      return null;
    }

    function epidemiasEn(anio, region, pais) {
      if (region === undefined) region = null;
      if (pais === undefined) pais = null;
      if (region !== null && REGIONES.indexOf(region) < 0) lanzar('región «' + region + '»: no es una de ' + REGIONES.join(', '));
      var regionDelPais = null;
      if (pais !== null) {
        regionDelPais = regionDe(pais);
        if (regionDelPais === null) lanzar('país «' + pais + '»: no está en paises.csv');
      }
      epidemias = perezoso('epidemias', epidemias);
      var resultado = [];
      epidemias.forEach(function (f) {
        var desde = parseInt(f.desde, 10);
        var hasta = f.hasta === '' ? null : parseInt(f.hasta, 10);
        var regiones = f.regiones.split(';');
        var lista = f.paises.split(';').filter(Boolean);
        if (!(desde <= anio && (hasta === null || anio <= hasta))) return;
        if (region !== null && regiones.indexOf(region) < 0) return;
        if (pais !== null && !(lista.indexOf(pais) >= 0 || (lista.length === 0 && regiones.indexOf(regionDelPais) >= 0))) return;
        resultado.push(Object.assign({}, f, { regiones: regiones, paises: lista }));
      });
      return resultado.sort(function (a, b) {
        return (parseInt(a.desde, 10) - parseInt(b.desde, 10)) || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);
      });
    }

    function hitosDe(codigo) {
      hitos = perezoso('hitos_registro', hitos);
      return hitos.filter(function (f) { return f.codigo === codigo; })
        .sort(function (a, b) { return parseInt(a.desde, 10) - parseInt(b.desde, 10); });
    }

    function periodoQueCubre(codigo, anio, edad, sexo) {
      if (SEXOS.indexOf(sexo) < 0) lanzar('sexo «' + sexo + '»: debe ser uno de ' + SEXOS.join(', '));
      if (periodos === null) {
        periodos = {};
        var t = tablas.esperanza_vida;
        var c = {};
        t.columnas.forEach(function (n, i) { c[n] = i; });
        t.filas.forEach(function (f) {
          var clave = f[c.codigo] + '|' + f[c.sexo] + '|' + Number(f[c.edad]);
          var s = f[c.supervivencia_hasta_edad];
          (periodos[clave] = periodos[clave] || []).push([
            Number(f[c.desde]), Number(f[c.hasta]), Number(f[c.esperanza_restante]),
            s === '' || s === null ? null : Number(s)]);
        });
      }
      var lista = periodos[codigo + '|' + sexo + '|' + edad] || [];
      for (var i = 0; i < lista.length; i++) {
        if (lista[i][0] <= anio && anio <= lista[i][1]) return lista[i];
      }
      return null;
    }

    function esperanzaRestante(codigo, anio, edad, sexo) {
      var p = periodoQueCubre(codigo, anio, edad === undefined ? 0 : edad, sexo === undefined ? 'T' : sexo);
      return p === null ? null : p[2];
    }

    function supervivencia(codigo, anio, edad, sexo) {
      var p = periodoQueCubre(codigo, anio, edad === undefined ? 5 : edad, sexo === undefined ? 'T' : sexo);
      return p === null ? null : p[3];
    }

    function serieDeLondres() {
      if (londres === null) {
        londres = {};
        var t = tablas.londres_muertes;
        var c = {};
        t.columnas.forEach(function (n, i) { c[n] = i; });
        t.filas.forEach(function (f) {
          londres[Number(f[c.anio])] = [Number(f[c.muertes_totales]), Number(f[c.muertes_viruela])];
        });
      }
      return londres;
    }

    function muertesLondres(anio) {
      var m = serieDeLondres()[anio];
      return m === undefined ? null : { totales: m[0], viruela: m[1] };
    }

    function excesoDeMuertesLondres(anio, ventana) {
      if (ventana === undefined) ventana = 5;
      var serie = serieDeLondres();
      if (serie[anio] === undefined) return null;
      var vecinos = [];
      for (var a = anio - ventana; a <= anio + ventana; a++) {
        if (a !== anio && serie[a] !== undefined) vecinos.push(serie[a][0]);
      }
      if (vecinos.length < 4) return null;
      vecinos.sort(function (x, y) { return x - y; });
      var mitad = Math.floor(vecinos.length / 2);
      var mediana = vecinos.length % 2 === 1 ? vecinos[mitad] : (vecinos[mitad - 1] + vecinos[mitad]) / 2;
      return serie[anio][0] / mediana;
    }

    return {
      causaModerna: causaModerna,
      regionDe: regionDe,
      epidemiasEn: epidemiasEn,
      hitosDe: hitosDe,
      esperanzaRestante: esperanzaRestante,
      supervivencia: supervivencia,
      muertesLondres: muertesLondres,
      excesoDeMuertesLondres: excesoDeMuertesLondres,
    };
  }

  return { IDIOMAS: IDIOMAS, REGIONES: REGIONES, SEXOS: SEXOS, normalizar: normalizar, crear: crear };
});
