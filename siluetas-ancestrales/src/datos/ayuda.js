// Ayudantes para escribir los datos de forma compacta.

export const L = (s) => s.split(/\s+/).filter(Boolean);

const COL = { principal: 'A', secundario: 'B', ribete: 'C', detalle: 'D' };
/** Prenda: id, nombre, ranura, plantilla, parámetros y papeles de color (A principal, B secundario, C ribete, D detalle). */
export const P = (id, nombre, ranura, plantilla, params = {}, col = {}) => ({ id, nombre, ranura, plantilla, params, col: { ...COL, ...col } });

/**
 * Atuendo: id, nombre, región, [año desde, hasta], sexo, clase, prendas, paleta de tintes por letra y extras
 * (peinados, barbas, tocados, pieles, nota y conf: 'alta' | 'media' | 'baja', cuán respaldada y específica es la representación).
 */
export const A = (id, nombre, region, epoca, sexo, clase, prendas, paleta, extra = {}) => ({
  id, nombre, region, epoca, sexo, clase,
  prendas: L(prendas),
  paleta: Object.fromEntries(Object.entries(paleta).map(([k, v]) => [k, L(v)])),
  peinados: L(extra.peinados ?? 'corto'),
  barbas: L(extra.barbas ?? 'ninguna'),
  tocados: L(extra.tocados ?? 'ninguno'),
  pieles: extra.pieles ? L(extra.pieles) : null,
  nota: extra.nota ?? '',
  confianza: extra.conf ?? 'media',
});

/** Etnia o cultura (ver etnias.js para el significado de cada campo). */
export const E = (id, nombre, d) => ({
  id, nombre, grupo: d.grupo, regiones: L(d.regiones), prefijos: d.prefijos ? L(d.prefijos) : null,
  pieles: L(d.pieles), cabellos: d.cabellos, peinados: L(d.peinados ?? ''), barba: d.barba ?? 0.3,
  altura: d.altura, corpulencia: d.corpulencia ?? [['delgada', 1], ['media', 4], ['robusta', 1.5]], nota: d.nota ?? '',
  confianza: d.conf ?? 'media',
});

// Distribuciones de color de cabello ([id, peso]) que comparten varias etnias.
export const OSC = [['negro', 7], ['negro-azulado', 2], ['castano-oscuro', 1]];
export const MORENO = [['negro', 5], ['negro-azulado', 1.5], ['castano-oscuro', 3], ['castano', 1.5]];
export const CLARO = [['castano', 3], ['castano-claro', 3], ['rubio-oscuro', 2], ['rubio', 2], ['castano-oscuro', 2], ['pelirrojo', 0.8], ['negro', 1]];
export const MED = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 0.5]];
export const MESTIZO = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 1]];
