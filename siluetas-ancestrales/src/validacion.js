// Validación de las opciones que recibe el generador. Un valor mal escrito (`sexo: 'z'`, `peinado: 'corot'`,
// `edad: 150`) lanza un Error que dice qué valores hay, en vez de caer en silencio en uno por defecto o
// fallar más adelante con un mensaje que no ayuda. Es la misma forma que ya usan `etnia` y `atuendo`.

import { PIELES, CABELLOS } from './datos/colores.js';
import { PRENDAS } from './datos/prendas.js';
import { REGIONES } from './datos/atuendos.js';
import { PEINADOS_ID, BARBAS_ID, TOCADOS_ID } from './cabeza.js';
import { CORPULENCIAS, POSTURAS, EDAD_MIN, EDAD_MAX, ALTURA_MIN, ALTURA_MAX, CORPULENCIA_MIN, CORPULENCIA_MAX } from './cuerpo.js';
import { esHex } from './util.js';

const lista = (v) => v.join(', ');
const falla = (campo, valor, validos) => { throw new Error(`${campo} no válido: ${JSON.stringify(valor)}. Valores posibles: ${lista(validos)}.`); };
const enLista = (campo, valor, validos) => { if (!validos.includes(valor)) falla(campo, valor, validos); };
const numero = (campo, valor, min, max, unidad = '') => {
  if (typeof valor !== 'number' || !Number.isFinite(valor) || valor < min || valor > max) {
    throw new Error(`${campo} no válido: ${JSON.stringify(valor)}. Debe ser un número entre ${min} y ${max}${unidad}.`);
  }
};
const hexOId = (campo, valor, ids) => { if (!esHex(valor) && !ids.includes(valor)) falla(campo, valor, [...ids, '#rrggbb']); };

export const ESTILOS_ROPA_NUEVA = ['sobrio', 'trabajo', 'festivo', 'ceremonial'];
export const ESQUEMAS = ['analogo', 'complementario', 'triada', 'monocromo', 'tierra', 'festivo'];
export const SALUDOS = ['derecha', 'izquierda', 'ninguno', 'ninguna'];
const BRAZOS_LIBRES = ['abajo', 'cintura', 'bolsillo'];

/** Comprueba las opciones de `generarPersona` (y de `generarRopaNueva`); no cambia nada, solo lanza si algo no vale. */
export function validarOpciones(opc = {}) {
  if (opc === null || typeof opc !== 'object') throw new Error('Las opciones deben ser un objeto.');
  const dado = (k) => opc[k] !== undefined && opc[k] !== null;
  if (opc.sexo !== undefined) enLista('Sexo', opc.sexo, ['f', 'm']);
  if (opc.edad !== undefined) numero('Edad', opc.edad, EDAD_MIN, EDAD_MAX, ' años');
  if (opc.altura !== undefined) numero('Altura', opc.altura, ALTURA_MIN, ALTURA_MAX, ' cm');
  if (opc.corpulencia !== undefined) {
    if (typeof opc.corpulencia === 'number') numero('Corpulencia', opc.corpulencia, CORPULENCIA_MIN, CORPULENCIA_MAX);
    else enLista('Corpulencia', opc.corpulencia, Object.keys(CORPULENCIAS));
  }
  if (opc.postura !== undefined && typeof opc.postura !== 'number') enLista('Postura', opc.postura, Object.keys(POSTURAS));
  if (opc.saludo !== undefined && opc.saludo !== false && opc.saludo !== null) enLista('Saludo', opc.saludo, SALUDOS);
  if (opc.pose !== undefined && opc.pose !== null && typeof opc.pose !== 'object') enLista('Pose', opc.pose, ['casual', 'firme']);
  if (opc.pose && typeof opc.pose === 'object') {
    if (opc.pose.brazoLibre !== undefined) enLista('pose.brazoLibre', opc.pose.brazoLibre, BRAZOS_LIBRES);
    if (opc.pose.carga !== undefined) enLista('pose.carga', opc.pose.carga, [-1, 0, 1]);
  }
  if (opc.detalle !== undefined) enLista('Detalle', opc.detalle, ['minimo', 'medio', 'alto']);
  if (opc.contorno !== undefined) enLista('Contorno', opc.contorno, ['ninguno', 'suave']);
  if (opc.clase !== undefined) enLista('Clase', opc.clase, ['baja', 'media', 'alta']);
  if (opc.anio !== undefined) numero('Año', opc.anio, -100000, 3000);
  if (opc.region !== undefined) enLista('Región', opc.region, Object.keys(REGIONES));
  if (dado('piel')) hexOId('Piel', opc.piel, PIELES.map((p) => p.id));
  if (dado('cabello')) hexOId('Cabello', opc.cabello, CABELLOS.map((c) => c.id));
  if (dado('peinado')) enLista('Peinado', opc.peinado, PEINADOS_ID);
  if (opc.barba !== undefined && typeof opc.barba !== 'boolean') enLista('Barba', opc.barba, [...BARBAS_ID, true, false]);
  if (dado('tocado')) enLista('Tocado', opc.tocado, TOCADOS_ID);
  if (dado('calzado')) enLista('Calzado', opc.calzado, PRENDAS.filter((p) => p.ranura === 'calzado').map((p) => p.id));
  if (opc.colores !== undefined) enLista('Colores', opc.colores, ['atuendo', 'prenda']);
  if (opc.variacion !== undefined) numero('Variación', opc.variacion, 0, 1);
  if (opc.estilo !== undefined) enLista('Estilo', opc.estilo, ESTILOS_ROPA_NUEVA);
  if (opc.esquema !== undefined) enLista('Esquema', opc.esquema, ESQUEMAS);
  if (opc.paleta !== undefined) {
    if (opc.paleta === null || typeof opc.paleta !== 'object') throw new Error('La paleta debe ser un objeto {A, B, C, D} con colores #rrggbb.');
    for (const [k, v] of Object.entries(opc.paleta)) if (!esHex(v)) throw new Error(`Color de paleta no válido en ${k}: ${JSON.stringify(v)}. Debe ser #rrggbb.`);
  }
  if (opc.vestir !== undefined && opc.vestir !== null) {
    const v = Array.isArray(opc.vestir) ? opc.vestir : (typeof opc.vestir === 'object' ? Object.values(opc.vestir) : null);
    if (!v) throw new Error('`vestir` debe ser una lista de ids de prenda o un objeto { ranura: id }.');
  }
}
