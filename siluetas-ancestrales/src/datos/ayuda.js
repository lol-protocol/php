// Ayudantes para escribir los datos de forma compacta.

export const L = (s) => s.split(/\s+/).filter(Boolean);

const COL = { principal: 'A', secundario: 'B', ribete: 'C', detalle: 'D' };
/** Prenda: id, nombre, ranura, plantilla, parámetros y papeles de color (A principal, B secundario, C ribete, D detalle). */
export const P = (id, nombre, ranura, plantilla, params = {}, col = {}) => ({ id, nombre, ranura, plantilla, params, col: { ...COL, ...col } });

/**
 * Atuendo: id, nombre, región, [año desde, hasta], sexo, clase, prendas, paleta de tintes por letra y extras
 * (peinados, barbas, tocados, pieles, nota, fuente —la referencia que respalda el traje— y conf: 'alta' | 'media' | 'baja', cuán respaldada y específica es la representación;
 * 'alta' exige `fuente`).
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
  fuente: extra.fuente ?? '',
  confianza: extra.conf ?? 'media',
});

/** Etnia o cultura (ver etnias.js para el significado de cada campo). */
export const E = (id, nombre, d) => ({
  id, nombre, grupo: d.grupo, regiones: L(d.regiones), prefijos: d.prefijos ? L(d.prefijos) : null,
  pieles: L(d.pieles), cabellos: d.cabellos, peinados: L(d.peinados ?? ''), barba: d.barba ?? 0.3,
  altura: d.altura, corpulencia: d.corpulencia ?? [['delgada', 1], ['media', 4], ['robusta', 1.5]], nota: d.nota ?? '', fuente: d.fuente ?? '',
  confianza: d.conf ?? 'media',
});

// Distribuciones de color de cabello ([id, peso]) que comparten varias etnias.
export const OSC = [['negro', 7], ['negro-azulado', 2], ['castano-oscuro', 1]];
export const MORENO = [['negro', 5], ['negro-azulado', 1.5], ['castano-oscuro', 3], ['castano', 1.5]];
export const CLARO = [['castano', 3], ['castano-claro', 3], ['rubio-oscuro', 2], ['rubio', 2], ['castano-oscuro', 2], ['pelirrojo', 0.8], ['negro', 1]];
export const MED = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 0.5]];
export const MESTIZO = [['negro', 4], ['castano-oscuro', 4], ['castano', 2], ['castano-claro', 1]];

// Paletas de tintes y tonos de piel que comparten varios archivos de atuendos (antes copiadas en cada uno).
export const BL = 'blanco-lino crudo';
export const CLAROS = 'porcelana clara clara-calida';
export const MEST = 'clara-calida oliva-clara oliva canela';
export const OLIVA = 'clara-calida oliva-clara oliva';
export const OSCURA = 'canela bronce cobre';
export const EBANO = 'castana ebano-claro ebano ebano-profundo';
export const CIUDAD = { A: 'lana-negra gris-oscuro azul-marino', B: 'crema', C: 'lana-negra', D: 'plata' };
export const TRAB = { A: 'caqui-militar azul-marino kaki', B: 'crudo', C: 'lana-negra', D: 'cobre' };
export const VEST = { A: 'azul-marino burdeos verde-bosque rosa-viejo', B: 'crema', C: 'lana-negra', D: 'plata' };
export const GALA = { A: 'negro-carbon burdeos azul-marino verde-esmeralda', B: 'crema', C: 'plata', D: 'oro' };
export const TIERRA = { A: 'lana-parda umbra nogal', B: 'crudo', C: 'rojo-ocre', D: 'cobre' };

/** Atuendo de mujer o de hombre: igual que A con el sexo ya puesto. */
export const F = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'f', clase, prendas, paleta, extra);
export const M = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'm', clase, prendas, paleta, extra);
