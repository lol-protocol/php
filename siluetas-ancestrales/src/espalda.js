// Vista de espalda. La silueta de un cuerpo visto por detrás es la del frente reflejada, así que la figura se dibuja igual y se
// refleja (la mano derecha que saluda queda a la derecha de quien mira). Lo que cambia es lo que se ve: el rostro desaparece
// bajo el pelo y las prendas pierden lo que solo existe por delante (botones, bolsillos del pecho, escotes en V, collares).

/** Parámetros que solo se ven por delante. */
const SOLO_FRENTE = ['botones', 'bolsillos', 'cordones', 'bordadoPecho', 'cruzada', 'solapa', 'solapaColor', 'hebilla', 'colgante', 'peto'];
/** Escotes que por detrás se vuelven redondos. */
const ESCOTE_TRASERO = { v: 'redondo', cuadrado: 'redondo', ojal: 'redondo' };
/** Adornos de cuello que por detrás no se ven (el resto, como bufandas y gorgueras, sí). */
const CUELLOS_DE_FRENTE = ['collar', 'gargantilla', 'cuentas-largas', 'pectoral', 'corbata', 'lazo', 'babero', 'triangulo', 'trapelacucha'];
/** Plantillas que por detrás no se ven. */
const PLANTILLAS_DE_FRENTE = ['delantal'];

function paramsDeEspalda(plantilla, params = {}) {
  if (plantilla === 'mono') return { ...params, torso: params.torso && paramsDeEspalda('tunica', params.torso), pantalon: params.pantalon && paramsDeEspalda('pantalon', params.pantalon) };
  const p = { ...params };
  for (const k of SOLO_FRENTE) delete p[k];
  if (p.escote in ESCOTE_TRASERO) p.escote = ESCOTE_TRASERO[p.escote];
  return p;
}

/** La prenda como se ve por detrás, o null si por detrás no se ve. */
export function prendaDeEspalda(prenda) {
  if (PLANTILLAS_DE_FRENTE.includes(prenda.plantilla)) return null;
  if (prenda.plantilla === 'cuello' && CUELLOS_DE_FRENTE.includes(prenda.params?.tipo)) return null;
  if (prenda.plantilla === 'accesorio' && prenda.params?.tipo === 'cinta-frente') return null;
  return { ...prenda, params: paramsDeEspalda(prenda.plantilla, prenda.params) };
}

export const prendasDeEspalda = (prendas) => prendas.map(prendaDeEspalda).filter(Boolean);
