#!/usr/bin/env node
'use strict';

// Genera lo que sale de datos/astros.json, sin dependencias (solo Node 22):
//   js/datos.js          el catálogo con los valores derivados, para el visor (abre sin servidor)
//   caracteristicas.md   la tabla de características, por categoría
//
// Uso:
//   node generar.js               escribe los archivos
//   node generar.js --comprobar   no escribe; falla si lo committeado no está al día

const fs = require('fs');
const path = require('path');

const RAIZ = __dirname;
const RUTA_DATOS = path.join(RAIZ, 'datos', 'astros.json');
const RUTA_JS = path.join(RAIZ, 'js', 'datos.js');
const RUTA_MD = path.join(RAIZ, 'caracteristicas.md');

const G = 6.674e-11;          // constante de gravitación, m³ kg⁻¹ s⁻²
const RADIO_SOL_KM = 695700;
const MASA_SOL_KG = 1.989e30;

// Número con coma decimal y punto de miles, como en el resto del repo.
function numero(valor, decimales) {
  const partes = valor.toFixed(decimales).split('.');
  const enteros = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return decimales > 0 ? enteros + ',' + partes[1] : enteros;
}

// Notación científica con superíndices: 5,97 × 10²⁴.
const SUPERINDICE = { '-': '⁻', '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹' };
function cientifico(valor) {
  const exp = Math.floor(Math.log10(Math.abs(valor)));
  const mantisa = valor / Math.pow(10, exp);
  const exponente = String(exp).split('').map(c => SUPERINDICE[c]).join('');
  return numero(mantisa, 2) + ' × 10' + exponente;
}

function ajustarDerivados(astro) {
  const derivado = Object.assign({}, astro);
  if (astro.categoria === 'sol') {
    derivado.radio_km = Math.round(astro.radio_solar * RADIO_SOL_KM);
    derivado.masa_kg = astro.masa_solar * MASA_SOL_KG;
    derivado.gravedad_ms2 = null;
    derivado.densidad_kg_m3 = null;
    return derivado;
  }
  const radioM = astro.radio_km * 1000;
  derivado.gravedad_ms2 = (G * astro.masa_kg) / (radioM * radioM);
  derivado.densidad_kg_m3 = astro.masa_kg / ((4 / 3) * Math.PI * radioM * radioM * radioM);
  return derivado;
}

function horas(valor) {
  // Periodo de rotación: negativo = retrógrado. Se muestra en horas y, si pasa de dos días, en días.
  const abs = Math.abs(valor);
  const texto = abs >= 48 ? numero(abs / 24, 2) + ' d' : numero(abs, abs < 10 ? 2 : 1) + ' h';
  return (valor < 0 ? '−' : '') + texto;
}

function filaPlaneta(a) {
  return `| ${a.nombre} | ${a.tipo} | ${numero(a.radio_km, 1)} | ${cientifico(a.masa_kg)} | ${numero(a.densidad_kg_m3, 0)} | ${numero(a.gravedad_ms2, 2)} | ${horas(a.periodo_rotacion_h)} | ${a.inclinacion_eje_grados == null ? '—' : numero(a.inclinacion_eje_grados, 2)} | ${numero(a.periodo_orbital_dias, 2)} | ${numero(a.temperatura_media_k, 0)} | ${a.lunas} | ${a.anillos ? 'Sí' : 'No'} | ${a.atmosfera} |`;
}

function filaLuna(a, padres) {
  return `| ${a.nombre} | ${padres[a.padre]} | ${numero(a.radio_km, 1)} | ${cientifico(a.masa_kg)} | ${numero(a.gravedad_ms2, 3)} | ${horas(a.periodo_rotacion_h)} | ${numero(a.periodo_orbital_dias, a.periodo_orbital_dias < 1 ? 3 : 2)} | ${numero(a.distancia_media.valor, 0)} | ${numero(a.temperatura_media_k, 0)} | ${a.atmosfera} |`;
}

function filaSol(a) {
  const distancia = a.distancia.unidad === 'UA'
    ? `${numero(a.distancia.valor, 0)} UA`
    : `${numero(a.distancia.valor, Number.isInteger(a.distancia.valor) ? 0 : 1)} años luz`;
  const edad = a.edad_aprox_mil_millones_anios == null ? '—' : numero(a.edad_aprox_mil_millones_anios, 3);
  return `| ${a.nombre} | ${a.constelacion || '—'} | ${a.signo || '—'} | ${a.tipo_espectral} | ${numero(a.radio_solar, a.radio_solar < 10 ? 2 : a.radio_solar < 100 ? 1 : 0)} | ${numero(a.radio_km, 0)} | ${numero(a.masa_solar, a.masa_solar < 10 ? 2 : 1)} | ${numero(a.temperatura_superficial_k, 0)} | ${distancia} | ${edad} | ${a.incertidumbre_radio} |`;
}

function generarMarkdown(datos) {
  const astros = datos.astros.map(ajustarDerivados);
  const padres = {};
  for (const a of astros) padres[a.id] = a.nombre;
  const por = categoria => astros.filter(a => a.categoria === categoria);

  const lineas = [];
  lineas.push('<!-- Generado por generar.js desde datos/astros.json: no lo edites a mano. -->');
  lineas.push('# Características de los astros');
  lineas.push('');
  lineas.push(`Versión de los datos: ${datos.version}. ${datos.nota}`);
  lineas.push('');
  lineas.push('## Planetas');
  lineas.push('');
  lineas.push('| Planeta | Tipo | Radio medio (km) | Masa (kg) | Densidad (kg/m³) | Gravedad en la superficie (m/s²) | Rotación | Inclinación del eje (°) | Año (días terrestres) | Temperatura media (K) | Lunas conocidas | Anillos | Atmósfera |');
  lineas.push('|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|---|');
  for (const a of por('planeta')) lineas.push(filaPlaneta(a));
  lineas.push('');
  lineas.push('Rotación negativa = sentido retrógrado. Las densidades y gravedades salen de la masa y el radio con G = 6,674 × 10⁻¹¹. Plutón, Ceres, Eris y Makemake son planetas enanos. La inclinación del eje de Eris y Makemake no se conoce bien («—»), y la rotación de Eris es poco precisa. Venus, Urano y Plutón traen la inclinación del eje mayor de 90° y, además, la rotación negativa: son dos maneras de decir lo mismo (giro retrógrado). «Lunas conocidas» son las cifras de NASA de abril de 2025 (Júpiter 95, Saturno 274, Urano 28 y Neptuno 16); el recuento sigue creciendo y la prensa de 2026 habla de más de 100 lunas en Júpiter y cerca de 290 en Saturno (sin confirmar).');
  lineas.push('');
  lineas.push('## Lunas');
  lineas.push('');
  lineas.push('| Luna | Cuerpo padre | Radio medio (km) | Masa (kg) | Gravedad (m/s²) | Rotación | Órbita (días) | Distancia al padre (km) | Temperatura media (K) | Atmósfera |');
  lineas.push('|---|---|---:|---:|---:|---:|---:|---:|---:|---|');
  for (const a of por('luna')) lineas.push(filaLuna(a, padres));
  lineas.push('');
  lineas.push('Las lunas de Marte son pequeñas e irregulares. Todas las lunas de la tabla giran sincronizadas con su órbita: su rotación es igual a su periodo orbital (la tabla muestra las dos columnas, y una prueba comprueba que coincidan). Tritón orbita en sentido retrógrado, y Caronte gira a la par de Plutón, que gira de forma retrógrada: por eso su rotación sale negativa. Las temperaturas de las lunas pequeñas son aproximadas.');
  lineas.push('');
  lineas.push('## Soles');
  lineas.push('');
  lineas.push('| Estrella | Constelación | Signo zodiacal | Tipo espectral | Radio (R☉) | Radio (km) | Masa (M☉) | Temperatura superficial (K) | Distancia | Edad aprox. (mil millones de años) | Incertidumbre del radio |');
  lineas.push('|---|---|---|---|---:|---:|---:|---:|---:|---:|---|');
  for (const a of por('sol')) lineas.push(filaSol(a));
  lineas.push('');
  lineas.push('R☉ y M☉ son el radio y la masa del Sol. Signo zodiacal: el de la constelación zodiacal a la que pertenece la estrella. La edad queda en «—» cuando no hay un dato fiable. El radio de Betelgeuse, Antares, Polaris y Deneb es poco preciso: cambia según el método de medida. El color de cada estrella en el visor es ilustrativo: la temperatura fija su familia (azul, blanca, amarilla, naranja o roja) y se exagera la saturación para distinguirlas.');
  lineas.push('');
  return lineas.join('\n');
}

function generarJs(datos) {
  const astros = datos.astros.map(ajustarDerivados);
  const cuerpo = JSON.stringify({ version: datos.version, nota: datos.nota, categorias: datos.categorias, astros: astros }, null, 2);
  return '// Generado por generar.js desde datos/astros.json: no lo edites a mano.\n' +
    'window.ASTROS_DATOS = ' + cuerpo + ';\n';
}

function leer(ruta) {
  try {
    return fs.readFileSync(ruta, 'utf8');
  } catch (e) {
    return null;
  }
}

function main() {
  const comprobar = process.argv.includes('--comprobar');
  const datos = JSON.parse(fs.readFileSync(RUTA_DATOS, 'utf8'));
  const salidas = [
    [RUTA_JS, generarJs(datos)],
    [RUTA_MD, generarMarkdown(datos)]
  ];

  if (comprobar) {
    const desactualizados = salidas.filter(([ruta, texto]) => leer(ruta) !== texto).map(([ruta]) => path.relative(RAIZ, ruta));
    if (desactualizados.length > 0) {
      console.error('Desactualizado: ' + desactualizados.join(', ') + '. Corre: node generar.js');
      process.exit(1);
    }
    console.log('Al día: js/datos.js y caracteristicas.md.');
    return;
  }

  for (const [ruta, texto] of salidas) {
    fs.mkdirSync(path.dirname(ruta), { recursive: true });
    fs.writeFileSync(ruta, texto);
    console.log('Escrito: ' + path.relative(RAIZ, ruta));
  }
}

if (require.main === module) {
  main();
} else {
  module.exports = { ajustarDerivados, numero, cientifico, horas, generarMarkdown, generarJs };
}
