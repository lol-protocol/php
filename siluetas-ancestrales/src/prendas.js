// Plantillas de prendas. Cada una recibe las medidas del cuerpo (M), sus parámetros (p), los colores ya
// resueltos (k: principal, secundario, ribete, detalle) y un contexto (ctx.patron) y devuelve:
//   { atras: [...], partes: [...], detalles: [...] }
// `atras` va detrás del cuerpo, `partes` son las piezas que se funden en una sola silueta con contorno y
// `detalles` (trazos, botones, ribetes) se dibujan encima. Las medidas son fracciones de la altura (H).

import { tunica } from './prendas/tunica.js';
import { pantalon } from './prendas/pantalon.js';
import { falda } from './prendas/falda.js';
import { taparrabo } from './prendas/taparrabo.js';
import { capa } from './prendas/capa.js';
import { poncho } from './prendas/poncho.js';
import { drapeado } from './prendas/drapeado.js';
import { chaqueta } from './prendas/chaqueta.js';
import { delantal } from './prendas/delantal.js';
import { faja } from './prendas/faja.js';
import { bandolera } from './prendas/bandolera.js';
import { chal } from './prendas/chal.js';
import { cuello } from './prendas/cuello.js';
import { calzado } from './prendas/calzado.js';
import { accesorio } from './prendas/accesorio.js';
import { mono } from './prendas/mono.js';
export { yLargo, anchoEn, finManga, ESCOTES_SIN_HOMBRO } from './prendas/comun.js';

export const PLANTILLAS = { tunica, pantalon, falda, taparrabo, capa, poncho, drapeado, chaqueta, delantal, faja, bandolera, chal, cuello, calzado, accesorio, mono };
export const PLANTILLAS_ID = Object.keys(PLANTILLAS);

/** Orden de dibujo por ranura (menor = más al fondo). */
export const ORDEN_RANURAS = {
  capa_atras: 10, calzado: 20, inferior: 30, torso: 40, sobre: 50, faja: 60, capa: 70, cuello: 80, accesorio: 90,
};
