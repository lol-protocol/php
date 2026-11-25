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

  /*
   * Huella de un término del glosario (FNV-1a de 32 bits, en hexadecimal): la misma que `huella` de comun.py. Cambia si
   * cambia el término, su idioma, su causa o su confianza, y hace caducar una revisión cuando el dato cambia.
   */
  function huella(termino, idioma, causaId, confianza) {
    var bytes = new TextEncoder().encode(termino + '|' + idioma + '|' + causaId + '|' + confianza);
    var h = 0x811c9dc5;
    for (var i = 0; i < bytes.length; i++) h = Math.imul(h ^ bytes[i], 0x01000193) >>> 0;
    return ('00000000' + h.toString(16)).slice(-8);
  }

  /*
   * El modelo log-cuadrático (Wilmoth et al., 2012): los mismos cálculos que modelo.py, paso por paso. Los coeficientes
   * salen de la tabla `modelo_logcuadratico` (columnas sexo, edad, ax, bx, cx, vx).
   */
  var EDADES_MODELO = [0, 1, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100, 105, 110];
  var EDAD_MAXIMA = 110;
  var AMPLITUD = [1, 4].concat(new Array(21).fill(5));

  function crearModelo(tabla) {
    var cache = {};
    function coeficientes(sexo) {
      if (SEXOS.indexOf(sexo) < 0) lanzar('sexo «' + sexo + '»: debe ser uno de ' + SEXOS.join(', '));
      if (cache[sexo]) return cache[sexo];
      var c = {};
      tabla.columnas.forEach(function (n, i) { c[n] = i; });
      var filas = tabla.filas.filter(function (f) { return f[c.sexo] === sexo; });
      var r = { ax: [], bx: [], cx: [], vx: [] };
      filas.forEach(function (f) { ['ax', 'bx', 'cx', 'vx'].forEach(function (k) { r[k].push(Number(f[c[k]])); }); });
      if (filas.length !== EDADES_MODELO.length) lanzar('modelo_logcuadratico no tiene los 24 grupos de edad para el sexo ' + sexo);
      return (cache[sexo] = r);
    }
    function tablaDeVida(q5, k, sexo) {
      if (!(q5 > 0 && q5 < 1)) lanzar('5q0 fuera de rango: ' + q5);
      var co = coeficientes(sexo), h = Math.log(q5), n = EDADES_MODELO.length;
      var m = co.ax.map(function (a, i) { return Math.exp(a + co.bx[i] * h + co.cx[i] * h * h + co.vx[i] * k); });
      var a0 = m[0] >= 0.107 ? 0.35 : 0.0425 + 2.875 * m[0];
      var q0 = m[0] / (1 + (1 - a0) * m[0]);
      var q = [q0, 1 - (1 - q5) / (1 - q0)], a = [a0, 1.5], i;
      for (i = 2; i < n - 1; i++) { q.push(Math.min(1.0, 5 * m[i] / (1 + 2.5 * m[i]))); a.push(2.5); }
      q.push(1.0);
      a.push(1 / m[n - 1]);
      var l = [1.0];
      for (i = 0; i < n - 1; i++) l.push(l[i] * (1 - q[i]));
      var personasAnio = [];
      for (i = 0; i < n - 1; i++) personasAnio.push(AMPLITUD[i] * l[i + 1] + a[i] * l[i] * q[i]);
      personasAnio.push(l[n - 1] / m[n - 1]);
      var e = new Array(n).fill(0), acumulado = 0;
      for (i = n - 1; i >= 0; i--) { acumulado += personasAnio[i]; e[i] = l[i] > 0 ? acumulado / l[i] : 0; }
      return [l, e];
    }
    function resolverK(q5, e0, sexo) {
      var f = function (k) { return tablaDeVida(q5, k, sexo)[1][0] - e0; };
      var bajo = -10, alto = 10;
      if (!(f(bajo) > 0 && 0 > f(alto))) return null;
      for (var i = 0; i < 50; i++) {
        var medio = (bajo + alto) / 2;
        if (f(medio) > 0) bajo = medio; else alto = medio;
      }
      return (bajo + alto) / 2;
    }
    function perfil(q5PorMil, e0, sexo) {
      var q5 = q5PorMil / 1000;
      if (!(q5 > 0 && q5 < 1)) return null;
      var k = resolverK(q5, e0, sexo);
      return k === null ? null : tablaDeVida(q5, k, sexo);
    }
    return { perfil: perfil, tabla: tablaDeVida, resolverK: resolverK };
  }

  // Proporción de los recién nacidos que llega a `edad` (0 a 110), por interpolación lineal entre los grupos de edad.
  function supervivenciaEn(l, edad) {
    if (!(edad >= 0 && edad <= EDAD_MAXIMA)) lanzar('edad fuera de 0 a ' + EDAD_MAXIMA + ': ' + edad);
    for (var i = 0; i < EDADES_MODELO.length - 1; i++) {
      if (edad <= EDADES_MODELO[i + 1]) {
        var fraccion = (edad - EDADES_MODELO[i]) / (EDADES_MODELO[i + 1] - EDADES_MODELO[i]);
        return l[i] + fraccion * (l[i + 1] - l[i]);
      }
    }
    return l[l.length - 1];
  }

  // La edad a la que la proporción de sobrevivientes baja a `objetivo` (0 < objetivo <= 1); 110 si el modelo no llega.
  function edadDeSupervivencia(l, objetivo) {
    if (!(objetivo > 0 && objetivo <= 1)) lanzar('proporción fuera de (0, 1]: ' + objetivo);
    for (var i = 0; i < EDADES_MODELO.length - 1; i++) {
      if (l[i + 1] <= objetivo && objetivo <= l[i]) {
        if (l[i] === l[i + 1]) return EDADES_MODELO[i];
        return EDADES_MODELO[i] + (l[i] - objetivo) / (l[i] - l[i + 1]) * (EDADES_MODELO[i + 1] - EDADES_MODELO[i]);
      }
    }
    return EDAD_MAXIMA;
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
    var inglaterra = null;

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
          var bajo = f[c.incertidumbre_baja];
          (periodos[clave] = periodos[clave] || []).push([
            Number(f[c.desde]), Number(f[c.hasta]), Number(f[c.esperanza_restante]),
            s === '' || s === null || s === undefined ? null : Number(s),
            bajo === '' || bajo === null || bajo === undefined ? null : [Number(bajo), Number(f[c.incertidumbre_alta])]]);
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

    // (bajo, alto) de esperanzaRestante, o null: desde 1950 es la discrepancia entre fuentes; antes, un supuesto.
    function bandaEsperanza(codigo, anio, edad, sexo) {
      var p = periodoQueCubre(codigo, anio, edad === undefined ? 0 : edad, sexo === undefined ? 'T' : sexo);
      return p === null ? null : p[4];
    }

    // --- Edades de muerte y supervivencia: estadística de población, por país y década (modelada). Solo reciben país, año,
    // sexo y edades: nada de una persona ni de una familia. No hay datos (null) sin la esperanza al nacer y la mortalidad
    // de 0 a 5 años de la década.
    var modeloLc = null;
    var perfiles = {};
    function perfilDe(codigo, anio, sexo) {
      if (SEXOS.indexOf(sexo) < 0) lanzar('sexo «' + sexo + '»: debe ser uno de ' + SEXOS.join(', '));
      periodoQueCubre(codigo, anio, 0, sexo);   // arma el índice de períodos
      var desde = null, e0 = null, lista = periodos[codigo + '|' + sexo + '|0'] || [];
      for (var i = 0; i < lista.length; i++) {
        if (lista[i][0] <= anio && anio <= lista[i][1]) { desde = lista[i][0]; e0 = lista[i][2]; break; }
      }
      if (desde === null) return null;
      var clave = codigo + '|' + sexo + '|' + desde;
      if (!(clave in perfiles)) {
        var l5 = null, cinco = periodos[codigo + '|T|5'] || [];
        for (var j = 0; j < cinco.length; j++) { if (cinco[j][0] === desde) { l5 = cinco[j][3]; break; } }
        if (modeloLc === null) modeloLc = crearModelo(tablas.modelo_logcuadratico);
        perfiles[clave] = l5 === null ? null : modeloLc.perfil((1 - l5) * 1000, e0, sexo);
      }
      return perfiles[clave];
    }

    function probabilidadDeLlegarA(codigo, anio, edad, sexo) {
      var perfil = perfilDe(codigo, anio, sexo === undefined ? 'T' : sexo);
      return perfil === null ? null : supervivenciaEn(perfil[0], edad);
    }

    function supervivientesEsperados(codigo, anio, nacidos, edad, sexo) {
      if (nacidos < 0) lanzar('nacidos no puede ser negativo: ' + nacidos);
      var p = probabilidadDeLlegarA(codigo, anio, edad, sexo);
      return p === null ? null : nacidos * p;
    }

    function fraccionDeMuertesAntesDe(codigo, anio, edad, desdeEdad, sexo) {
      if (desdeEdad === undefined) desdeEdad = 0;
      if (edad < desdeEdad) lanzar('edad (' + edad + ') menor que desdeEdad (' + desdeEdad + ')');
      var perfil = perfilDe(codigo, anio, sexo === undefined ? 'T' : sexo);
      if (perfil === null) return null;
      var base = supervivenciaEn(perfil[0], desdeEdad);
      return base <= 0 ? null : 1 - supervivenciaEn(perfil[0], edad) / base;
    }

    function edadDeMuertePercentil(codigo, anio, p, desdeEdad, sexo) {
      if (desdeEdad === undefined) desdeEdad = 0;
      if (!(p > 0 && p < 1)) lanzar('p fuera de (0, 1): ' + p);
      var perfil = perfilDe(codigo, anio, sexo === undefined ? 'T' : sexo);
      if (perfil === null) return null;
      var base = supervivenciaEn(perfil[0], desdeEdad);
      return base <= 0 ? null : edadDeSupervivencia(perfil[0], base * (1 - p));
    }

    function edadesDeMuerteComunes(codigo, anio, desdeEdad, sexo) {
      var resultado = {};
      var porcentajes = [10, 25, 50, 75, 90];
      for (var i = 0; i < porcentajes.length; i++) {
        var edad = edadDeMuertePercentil(codigo, anio, porcentajes[i] / 100, desdeEdad, sexo);
        if (edad === null) return null;
        resultado['p' + porcentajes[i]] = edad;
      }
      return resultado;
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

    function serieDeInglaterra() {
      if (inglaterra === null) {
        var t = tablas.inglaterra_tasas;
        var c = {};
        t.columnas.forEach(function (n, i) { c[n] = i; });
        inglaterra = t.filas.map(function (f) {
          return [Number(f[c.anio]), Number(f[c.natalidad]), Number(f[c.mortalidad])];
        });
      }
      return inglaterra;
    }

    function tasasInglaterra(anio) {
      var serie = serieDeInglaterra();
      for (var i = 0; i < serie.length; i++) {
        if (serie[i][0] === anio) return { natalidad: serie[i][1], mortalidad: serie[i][2] };
      }
      return null;
    }

    function excesoDeMortalidadInglaterra(anio, vecinos) {
      if (vecinos === undefined) vecinos = 2;
      var serie = serieDeInglaterra();
      var indice = -1;
      for (var i = 0; i < serie.length; i++) {
        if (serie[i][0] === anio) { indice = i; break; }
      }
      if (indice < 0) return null;
      var valores = [];
      for (var j = indice - vecinos; j <= indice + vecinos; j++) {
        if (j !== indice && j >= 0 && j < serie.length) valores.push(serie[j][2]);
      }
      if (valores.length < 4) return null;
      valores.sort(function (x, y) { return x - y; });
      var mitad = Math.floor(valores.length / 2);
      var mediana = valores.length % 2 === 1 ? valores[mitad] : (valores[mitad - 1] + valores[mitad]) / 2;
      return serie[indice][2] / mediana;
    }

    function aniosDeMortalidadAnormalInglaterra(umbral) {
      if (umbral === undefined) umbral = 1.15;
      var resultado = [];
      serieDeInglaterra().forEach(function (f) {
        var exceso = excesoDeMortalidadInglaterra(f[0]);
        if (exceso !== null && exceso >= umbral) resultado.push({ anio: f[0], exceso: exceso });
      });
      return resultado;
    }

    return {
      causaModerna: causaModerna,
      regionDe: regionDe,
      epidemiasEn: epidemiasEn,
      hitosDe: hitosDe,
      esperanzaRestante: esperanzaRestante,
      bandaEsperanza: bandaEsperanza,
      supervivencia: supervivencia,
      probabilidadDeLlegarA: probabilidadDeLlegarA,
      supervivientesEsperados: supervivientesEsperados,
      fraccionDeMuertesAntesDe: fraccionDeMuertesAntesDe,
      edadDeMuertePercentil: edadDeMuertePercentil,
      edadesDeMuerteComunes: edadesDeMuerteComunes,
      muertesLondres: muertesLondres,
      excesoDeMuertesLondres: excesoDeMuertesLondres,
      tasasInglaterra: tasasInglaterra,
      excesoDeMortalidadInglaterra: excesoDeMortalidadInglaterra,
      aniosDeMortalidadAnormalInglaterra: aniosDeMortalidadAnormalInglaterra,
    };
  }

  return { IDIOMAS: IDIOMAS, REGIONES: REGIONES, SEXOS: SEXOS, normalizar: normalizar, huella: huella, crear: crear };
});
