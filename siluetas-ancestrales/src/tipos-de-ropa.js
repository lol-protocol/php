// Fuente única de los tipos de ropa que entienden las plantillas (src/prendas.js): qué parámetros acepta cada una y
// qué valores puede tomar cada parámetro enumerado. Sirve para validar las prendas (un `manga: 'larg0'` ya no se
// dibuja en silencio con la manga por defecto) y para que el generador de ropa nueva use todas las variantes.
// Si agregas una variante a una plantilla, agrégala aquí; una prueba comprueba que todo el catálogo la cumple.

import { PATRONES_ID } from './patrones.js';

export const LARGOS = ['pecho', 'cintura', 'cadera', 'muslo-alto', 'muslo', 'rodilla', 'bajo-rodilla', 'pantorrilla', 'tobillo', 'suelo'];

/** Valores permitidos de cada parámetro enumerado (los demás son números, booleanos o textos libres). */
export const TIPOS = {
  escote: ['redondo', 'v', 'cuadrado', 'barco', 'alto', 'ojal', 'tirantes', 'corazon', 'halter', 'asimetrico'],
  manga: ['sin', 'cap', 'corta', 'codo', 'tres-cuartos', 'larga', 'ajustada', 'larga-mano', 'ancha', 'campana', 'pagoda', 'globo', 'abullonada', 'farol', 'kimono'],
  bajo: ['recto', 'curvo', 'ondulado', 'dentado', 'flecos', 'pico', 'curvo-alto'],
  forma: ['campana', 'sirena', 'trapecio', 'globo', 'tubo'],
  cintura: ['natural', 'baja', 'alta'],
  anchoPantalon: ['ajustado', 'pitillo', 'tubo', 'recto', 'amplio', 'palazzo', 'bombacho', 'jodhpur', 'campana'],
  bolsillosPantalon: ['cargo', 'jean', 'pliegues'],
  bolsillosTorso: ['pecho', 'cadera', 'canguro', 'ambos'],
  pieles: ['cuello', 'puños', 'bajo', 'todo'],
  lado: ['izq', 'der'],
  abertura: ['frente', 'lado'],
  cierre: ['broche', 'fibula', 'abierta'],
  cuello: ['collar', 'gargantilla', 'cuentas-largas', 'pectoral', 'gorguera', 'alto', 'bufanda', 'lazo', 'corbata', 'piel', 'esclavina', 'chalina', 'triangulo', 'babero', 'trapelacucha'],
  calzado: ['descalzo', 'sandalias', 'sandalia-romana', 'chancletas', 'zapatos', 'oxford', 'tacones', 'mocasines', 'zuecos', 'zapatillas', 'botines', 'botas', 'botas-altas', 'polainas'],
  accesorio: ['brazaletes', 'bolso', 'tirantes', 'cinta-frente'],
  fajaY: ['cintura', 'cadera', 'pecho'],
};

const COMUNES = ['patron']; // todas las plantillas que rellenan con tela aceptan un patrón

/** Parámetros que lee cada plantilla. `mono` contiene dos juegos de parámetros anidados (torso y pantalon). */
export const PARAMS = {
  tunica: ['bajo', 'bandaPatron', 'bandas', 'bandasAncho', 'bolsillos', 'bordadoPecho', 'botones', 'capucha', 'cintura', 'cinturon', 'cordones', 'cruzada', 'cuelloCamisa', 'escote', 'flecos', 'forma', 'holgura', 'lado', 'largo', 'manga', 'pieles', 'pliegues', 'prof', 'puños', 'ribete', 'ribeteBajo', 'volantes', 'vuelo', ...COMUNES],
  pantalon: ['ancho', 'bolsillos', 'cintura', 'cordon', 'holgura', 'largo', 'puños', ...COMUNES],
  falda: ['abertura', 'bajo', 'bandaPatron', 'cintura', 'flecos', 'forma', 'holgura', 'largo', 'pliegues', 'ribeteBajo', 'volantes', 'vuelo', ...COMUNES],
  taparrabo: ['ancho', 'largo', 'punta', 'ribeteBajo', ...COMUNES],
  capa: ['ancho', 'bajo', 'cierre', 'largo', 'ribete', 'ribeteBajo', ...COMUNES],
  poncho: ['ancho', 'flecos', 'largo', 'listas', 'punta', ...COMUNES],
  drapeado: ['lado', 'largo', 'orla', 'pliegues', ...COMUNES],
  chaqueta: ['abertura', 'aberturaBajo', 'bajo', 'bolsillos', 'bordadoBorde', 'botones', 'capucha', 'cintura', 'cinturon', 'cordones', 'cuelloCamisa', 'holgura', 'largo', 'manga', 'pieles', 'pliegues', 'puños', 'ribete', 'solapa', 'solapaColor', 'vuelo', ...COMUNES],
  delantal: ['largo', 'peto', 'vuelo', ...COMUNES],
  faja: ['ancho', 'cuelga', 'doble', 'hebilla', 'y', ...COMUNES],
  bandolera: ['ancho', 'bolsa', 'lado'],
  chal: ['flecos', 'largo', ...COMUNES],
  cuello: ['colgante', 'cuentas', 'rayas', 'tipo'],
  calzado: ['hebilla', 'tipo'],
  accesorio: ['alto', 'lado', 'tipo'],
  mono: ['pantalon', 'torso'],
};

// Enumeraciones por plantilla y parámetro: [plantilla][parámetro] = lista de valores.
const ENUMS = {
  tunica: { escote: TIPOS.escote, manga: TIPOS.manga, bajo: TIPOS.bajo, forma: TIPOS.forma, bolsillos: TIPOS.bolsillosTorso, pieles: TIPOS.pieles, cruzada: TIPOS.lado, lado: TIPOS.lado },
  pantalon: { ancho: TIPOS.anchoPantalon, cintura: TIPOS.cintura, bolsillos: TIPOS.bolsillosPantalon },
  falda: { bajo: TIPOS.bajo, forma: TIPOS.forma, cintura: TIPOS.cintura, abertura: TIPOS.abertura },
  capa: { bajo: TIPOS.bajo, cierre: TIPOS.cierre },
  drapeado: { lado: TIPOS.lado },
  chaqueta: { manga: TIPOS.manga, bajo: TIPOS.bajo, bolsillos: TIPOS.bolsillosTorso, pieles: TIPOS.pieles },
  faja: { y: TIPOS.fajaY, cuelga: TIPOS.lado },
  bandolera: { lado: TIPOS.lado },
  cuello: { tipo: TIPOS.cuello },
  calzado: { tipo: TIPOS.calzado },
  accesorio: { tipo: TIPOS.accesorio, lado: TIPOS.lado },
};

const PARAMS_DE_PATRON = ['bandaPatron', 'bordadoPecho'];

/** Distancia de edición entre dos textos cortos, para sugerir «¿quisiste decir…?». */
function distancia(a, b) {
  const d = Array.from({ length: a.length + 1 }, (_, i) => [i, ...Array(b.length).fill(0)]);
  for (let j = 1; j <= b.length; j++) d[0][j] = j;
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++) d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
  return d[a.length][b.length];
}
const parecido = (x, lista) => { const m = lista.map((v) => [v, distancia(String(x), v)]).sort((p, q) => p[1] - q[1])[0]; return m && m[1] <= 2 ? m[0] : null; };

/**
 * Revisa los parámetros de una prenda y devuelve la lista de problemas (vacía si está bien):
 * parámetros que la plantilla no lee, valores fuera de las listas y patrones que no existen.
 */
export function problemasDeParams(plantilla, params = {}, ruta = plantilla) {
  const permitidos = PARAMS[plantilla];
  if (!permitidos) return [`Plantilla desconocida: ${plantilla}`];
  const out = [];
  for (const [k, v] of Object.entries(params)) {
    if (!permitidos.includes(k)) { const sug = parecido(k, permitidos); out.push(`${ruta}: la plantilla no lee el parámetro «${k}»${sug ? ` (¿quisiste decir «${sug}»?)` : ''}.`); continue; }
    if (plantilla === 'mono') { out.push(...problemasDeParams(k === 'torso' ? 'tunica' : 'pantalon', v, `${ruta}.${k}`)); continue; }
    const lista = ENUMS[plantilla]?.[k];
    if (lista && v !== undefined && v !== false && v !== null && !lista.includes(v)) { const sug = typeof v === 'string' ? parecido(v, lista) : null; out.push(`${ruta}: «${k}» = ${JSON.stringify(v)} no es un valor válido${sug ? ` (¿quisiste decir «${sug}»?)` : ''}. Valores posibles: ${lista.join(', ')}.`); }
    if (k === 'largo' && v !== undefined && typeof v !== 'number' && !LARGOS.includes(v)) { const sug = typeof v === 'string' ? parecido(v, LARGOS) : null; out.push(`${ruta}: «largo» = ${JSON.stringify(v)} no es un largo válido${sug ? ` (¿quisiste decir «${sug}»?)` : ''}. Valores posibles: ${LARGOS.join(', ')} o un número.`); }
    if (k === 'patron' && v) { const t = v.tipo ?? v; if (!PATRONES_ID.includes(t)) out.push(`${ruta}: el patrón «${t}» no existe. Patrones: ${PATRONES_ID.join(', ')}.`); }
    if (PARAMS_DE_PATRON.includes(k) && v && !PATRONES_ID.includes(v)) out.push(`${ruta}: el patrón «${v}» de «${k}» no existe.`);
  }
  return out;
}

/** Lanza un error con todos los problemas de una prenda `{ id, plantilla, params }`, si los hay. */
export function validarPrenda(prenda) {
  const problemas = problemasDeParams(prenda.plantilla, prenda.params ?? {}, `Prenda «${prenda.id ?? prenda.plantilla}»`);
  if (problemas.length) throw new Error(problemas.join(' '));
}
